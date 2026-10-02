<?php

namespace App\Services\Matching;

/**
 * What ProblemKeywordResolver understood from a free-text legal
 * problem, expressed ONLY in terms of real catalogue rows (services /
 * specializations) plus literal words to look for in a lawyer's own
 * profile text. Nothing here is inferred beyond word/phrase matching.
 *
 * Each catalogue hit is:
 *   [id => ['name' => string, 'terms' => list<string>, 'direct' => bool]]
 * `direct` = the catalogue NAME appeared in the customer's words;
 * otherwise the hit came from the editable synonym table (related).
 */
final class ProblemInterpretation
{
    /**
     * @param  array<int, array{name: string, terms: list<string>, direct: bool}>  $services
     * @param  array<int, array{name: string, terms: list<string>, direct: bool}>  $specializations
     * @param  list<string>  $textTerms  words to look for in a lawyer's title/bio/summary
     */
    public function __construct(
        public readonly array $services = [],
        public readonly array $specializations = [],
        public readonly array $textTerms = [],
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    public function hasSignals(): bool
    {
        return $this->services !== [] || $this->specializations !== [] || $this->textTerms !== [];
    }

    /** @return list<int> */
    public function serviceIds(): array
    {
        return array_keys($this->services);
    }

    /** @return list<int> */
    public function specializationIds(): array
    {
        return array_keys($this->specializations);
    }

    /** @return list<string> Every distinct customer word/phrase that produced a catalogue hit. */
    public function matchedTerms(): array
    {
        $terms = [];
        foreach ([...$this->services, ...$this->specializations] as $hit) {
            array_push($terms, ...$hit['terms']);
        }

        return array_values(array_unique($terms));
    }
}
