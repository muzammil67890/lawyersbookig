<?php

namespace App\Services\Matching;

use App\Models\Service;
use App\Models\Specialization;
use Illuminate\Support\Collection;

/**
 * Conservative, deterministic "legal problem" reader.
 *
 * This is word/phrase matching -- NOT natural-language understanding
 * and NOT AI. It turns free text such as "property dispute" into
 * references to REAL rows of the services / specializations tables:
 *
 *   1. Name match   -- the whole service/specialization name appears in
 *                      the text ("tenancy disputes"), or one distinctive
 *                      word of a SERVICE name does ("criminal" ->
 *                      Criminal Law). Specialization names are matched
 *                      as whole phrases only, because single words such
 *                      as "disputes" or "applications" are shared by
 *                      unrelated specializations and would mis-match.
 *   2. Synonym table -- config('lawyer_matching.problem.keywords'), an
 *                      editable phrase => slugs map ("inheritance" ->
 *                      Family Law). Unknown slugs are ignored.
 *   3. Text terms   -- remaining meaningful words, later looked up in a
 *                      lawyer's own title/bio/summary as a weak signal.
 *
 * Service/specialization descriptions are deliberately NOT used: they
 * are marketing prose, and matching on them produces false positives.
 *
 * Plural forms are folded ("taxes" ~ "tax") on both sides so the
 * comparison is symmetrical. Only two tiny catalogue queries are run
 * per call; no lawyer rows are touched here.
 */
final class ProblemKeywordResolver
{
    /** @var array<string, mixed> */
    private readonly array $config;

    /** @var array<string, true> */
    private readonly array $stopwords;

    /** @var array<string, true> */
    private readonly array $genericServiceWords;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('lawyer_matching.problem', []);

        $this->stopwords = $this->foldedSet((array) ($this->config['stopwords'] ?? []));
        $this->genericServiceWords = $this->foldedSet((array) ($this->config['generic_service_words'] ?? []));
    }

    public function resolve(string $problem): ProblemInterpretation
    {
        // Phrase/name matching runs over ALL words (a specialization
        // called "Domestic Matters" contains a stopword); only the weak
        // text-term signal below uses the stopword-filtered list.
        $allTokens = $this->tokens($problem, filterStopwords: false);
        $tokens = $this->tokens($problem);

        if ($tokens === []) {
            return ProblemInterpretation::empty();
        }

        $tokenSet = array_flip($allTokens);
        $haystack = ' '.implode(' ', $allTokens).' ';

        /** @var Collection<int, Service> $services */
        $services = Service::query()->active()->get(['id', 'name', 'slug'])->keyBy('id');
        /** @var Collection<int, Specialization> $specializations */
        $specializations = $services->isEmpty()
            ? new Collection()
            : Specialization::query()->whereIn('service_id', $services->keys())->get(['id', 'service_id', 'name', 'slug']);

        $serviceHits = [];
        $specializationHits = [];

        // 1. Name matches against the real catalogue.
        foreach ($services as $service) {
            $nameTokens = $this->tokens($service->name, filterStopwords: false);
            $distinctive = array_values(array_filter($nameTokens, fn (string $t) => ! isset($this->genericServiceWords[$t])));

            if ($this->containsPhrase($haystack, $nameTokens)) {
                $this->addHit($serviceHits, $service->id, $service->name, $service->name, direct: true);

                continue;
            }
            foreach ($distinctive as $word) {
                if (isset($tokenSet[$word])) {
                    $this->addHit($serviceHits, $service->id, $service->name, $word, direct: true);
                }
            }
        }

        foreach ($specializations as $specialization) {
            if ($this->containsPhrase($haystack, $this->tokens($specialization->name, filterStopwords: false))) {
                $this->addHit($specializationHits, $specialization->id, $specialization->name, $specialization->name, direct: true);
            }
        }

        // 2. Editable synonym table (related, not direct).
        foreach ((array) ($this->config['keywords'] ?? []) as $phrase => $targets) {
            if (! $this->containsPhrase($haystack, $this->tokens((string) $phrase, filterStopwords: false))) {
                continue;
            }

            foreach ((array) ($targets['services'] ?? []) as $slug) {
                foreach ($services->where('slug', $slug) as $service) {
                    $this->addHit($serviceHits, $service->id, $service->name, (string) $phrase, direct: false);
                }
            }
            foreach ((array) ($targets['specializations'] ?? []) as $slug) {
                foreach ($specializations->where('slug', $slug) as $specialization) {
                    $this->addHit($specializationHits, $specialization->id, $specialization->name, (string) $phrase, direct: false);
                }
            }
        }

        // 3. Weak signal: meaningful words to look for in lawyer text.
        $minLength = (int) ($this->config['min_token_length'] ?? 4);
        $textTerms = array_values(array_unique(array_filter(
            $tokens,
            fn (string $t) => mb_strlen($t) >= $minLength
        )));
        $textTerms = array_slice($textTerms, 0, (int) ($this->config['max_text_terms'] ?? 6));

        return new ProblemInterpretation($serviceHits, $specializationHits, $textTerms);
    }

    /**
     * Lower-case, letters/digits only, plural-folded, stopwords removed.
     *
     * @return list<string>
     */
    private function tokens(string $text, bool $filterStopwords = true): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $folded = $this->fold($part);
            if ($filterStopwords && isset($this->stopwords[$folded])) {
                continue;
            }
            $tokens[] = $folded;
        }

        return $tokens;
    }

    /** Minimal, symmetric plural folding: "taxes"->"tax", "disputes"->"dispute". */
    private function fold(string $word): string
    {
        $length = mb_strlen($word);

        if ($length > 4 && str_ends_with($word, 'ies')) {
            return mb_substr($word, 0, -3).'y';
        }
        if ($length > 4 && preg_match('/(s|x|z|ch|sh)es$/u', $word) === 1) {
            return mb_substr($word, 0, -2);
        }
        if ($length > 3 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            return mb_substr($word, 0, -1);
        }

        return $word;
    }

    /**
     * @param  list<string>  $words
     * @return array<string, true>
     */
    private function foldedSet(array $words): array
    {
        $set = [];
        foreach ($words as $word) {
            $set[$this->fold(mb_strtolower((string) $word))] = true;
        }

        return $set;
    }

    /** @param list<string> $phraseTokens */
    private function containsPhrase(string $haystack, array $phraseTokens): bool
    {
        return $phraseTokens !== [] && str_contains($haystack, ' '.implode(' ', $phraseTokens).' ');
    }

    /** @param array<int, array{name: string, terms: list<string>, direct: bool}> $hits */
    private function addHit(array &$hits, int $id, string $name, string $term, bool $direct): void
    {
        $hits[$id] ??= ['name' => $name, 'terms' => [], 'direct' => false];

        if (! in_array($term, $hits[$id]['terms'], true)) {
            $hits[$id]['terms'][] = $term;
        }
        // A direct (name) hit is never downgraded by a later synonym hit.
        $hits[$id]['direct'] = $hits[$id]['direct'] || $direct;
    }
}
