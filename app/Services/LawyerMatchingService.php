<?php

namespace App\Services;

use App\Models\LawyerProfile;
use App\Services\Matching\LawyerMatch;
use App\Services\Matching\MatchCriteria;
use App\Services\Matching\MatchResultSet;
use App\Services\Matching\ProblemInterpretation;
use App\Services\Matching\ProblemKeywordResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Deterministic lawyer matching (Phase 5 Half 2).
 *
 * THIS IS NOT AI. There is no model, randomness or external call: the
 * same criteria over the same data always produce the same scores and
 * the same order. It is the data/scoring foundation the Phase 11 AI
 * Assistant is meant to call.
 *
 * Pipeline
 * --------
 *  1. Eligibility (non-negotiable): every query starts from
 *     LawyerProfile::searchable() -- approved + active + public + owning
 *     account active. There is no other entry point to lawyer rows in
 *     this class, so an ineligible lawyer cannot be returned.
 *  2. Candidate narrowing in SQL (never "load everyone, filter in PHP"):
 *       - strict criteria (`strict` option) become hard WHERE clauses;
 *       - if a topical criterion (service / specialization / problem)
 *         was supplied, a lawyer must satisfy at least one of them --
 *         the right city or fee never rescues the wrong area of law;
 *       - otherwise a lawyer must satisfy at least one supplied
 *         location/budget/experience/availability criterion;
 *       - rows are capped (`limits.candidate_cap`) as a safety net.
 *  3. Scoring in PHP over that small, eager-loaded set (see config
 *     lawyer_matching.php for weights and normalisation). Only criteria
 *     the customer supplied contribute, so 100 always means "satisfies
 *     everything that was asked".
 *  4. Ranking: score desc, then years of experience desc, then name,
 *     then id -- a total order, so identical input => identical output.
 *
 * Queries per call are constant (catalogue lookups for the problem
 * text + one lawyer query + two eager loads), independent of result
 * count -- no N+1.
 */
class LawyerMatchingService
{
    private const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /** @var array<string, mixed> */
    private readonly array $config;

    public function __construct(
        private readonly ProblemKeywordResolver $resolver,
        ?array $config = null,
    ) {
        $this->config = $config ?? (array) config('lawyer_matching', []);
    }

    public function match(MatchCriteria $criteria): MatchResultSet
    {
        $interpretation = $criteria->problem !== null
            ? $this->resolver->resolve($criteria->problem)
            : null;
        $problemActive = $interpretation?->hasSignals() ?? false;

        $components = $this->requestedComponents($criteria, $problemActive);

        if ($components === []) {
            return new MatchResultSet(new Collection(), $this->meta($criteria, $interpretation, $components, 0, false, 0));
        }

        $cap = max(1, (int) ($this->config['limits']['candidate_cap'] ?? 300));

        $candidates = $this->candidateQuery($criteria, $interpretation, $problemActive)
            ->orderBy('lawyer_profiles.id')
            ->limit($cap)
            ->get();

        $possible = array_sum($components);

        $ranked = $candidates
            ->map(fn (LawyerProfile $lawyer) => $this->score($lawyer, $criteria, $interpretation, $components, $possible))
            ->filter(fn (array $row) => $row['points'] > 0)
            ->sort(fn (array $a, array $b) => $this->compare($a, $b))
            ->take($criteria->limit)
            ->values()
            ->map(fn (array $row) => $row['match']);

        return new MatchResultSet(
            $ranked,
            $this->meta($criteria, $interpretation, $components, $candidates->count(), $candidates->count() >= $cap, $ranked->count()),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Which criteria are being scored
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, float> component => weight, ONLY for supplied criteria
     */
    private function requestedComponents(MatchCriteria $criteria, bool $problemActive): array
    {
        $weights = (array) ($this->config['weights'] ?? []);

        $requested = array_filter([
            'service' => $criteria->serviceId !== null,
            'specialization' => $criteria->specializationId !== null,
            'problem' => $problemActive,
            'location' => $criteria->city !== null,
            'budget' => $criteria->hasBudget(),
            'experience' => $criteria->minExperience !== null,
            'availability' => $criteria->availableDay !== null,
        ]);

        $components = [];
        foreach (array_keys($requested) as $name) {
            $weight = max(0.0, (float) ($weights[$name] ?? 0));
            if ($weight > 0) {
                $components[$name] = $weight;
            }
        }

        return $components;
    }

    /*
    |--------------------------------------------------------------------------
    | Candidate query (all eligibility + narrowing happens in SQL)
    |--------------------------------------------------------------------------
    */

    /** @return Builder<LawyerProfile> */
    private function candidateQuery(MatchCriteria $criteria, ?ProblemInterpretation $interpretation, bool $problemActive): Builder
    {
        $query = LawyerProfile::query()
            ->searchable() // approved + active + public + owning account active
            ->select([
                'lawyer_profiles.id', 'lawyer_profiles.display_name', 'lawyer_profiles.title',
                'lawyer_profiles.bio', 'lawyer_profiles.experience_summary', 'lawyer_profiles.city',
                'lawyer_profiles.years_of_experience', 'lawyer_profiles.consultation_fee',
                'lawyer_profiles.profile_image', 'lawyer_profiles.updated_at',
            ])
            ->with([
                'services:id,name,slug',
                'specializations:id,name,slug,service_id',
            ]);

        if ($criteria->availableDay !== null) {
            $query->withExists(['availabilities as available_on_day' => fn ($q) => $this->availabilityConstraint($q, $criteria->availableDay)]);
        }

        // Strict criteria: hard filters, reusing the existing scopes.
        if ($criteria->isStrict(MatchCriteria::STRICT_BUDGET)) {
            $criteria->minBudget !== null && $query->minFee($criteria->minBudget);
            $criteria->maxBudget !== null && $query->maxFee($criteria->maxBudget);
        }
        if ($criteria->isStrict(MatchCriteria::STRICT_LOCATION) && $criteria->city !== null) {
            $this->whereCity($query, $criteria->city);
        }
        if ($criteria->isStrict(MatchCriteria::STRICT_EXPERIENCE) && $criteria->minExperience !== null) {
            $query->minExperience($criteria->minExperience);
        }

        $topical = $criteria->serviceId !== null || $criteria->specializationId !== null || $problemActive;

        $query->where(function (Builder $gate) use ($criteria, $interpretation, $problemActive, $topical) {
            if ($topical) {
                $this->applyTopicalGate($gate, $criteria, $interpretation, $problemActive);
            } else {
                $this->applyNonTopicalGate($gate, $criteria);
            }
        });

        return $query;
    }

    private function applyTopicalGate(Builder $gate, MatchCriteria $criteria, ?ProblemInterpretation $interpretation, bool $problemActive): void
    {
        if ($criteria->serviceId !== null) {
            $gate->orWhereHas('services', fn ($q) => $q->where('services.id', $criteria->serviceId));
        }
        if ($criteria->specializationId !== null) {
            $gate->orWhereHas('specializations', fn ($q) => $q->where('specializations.id', $criteria->specializationId));
        }

        if ($problemActive && $interpretation !== null) {
            if ($interpretation->serviceIds() !== []) {
                $gate->orWhereHas('services', fn ($q) => $q->whereIn('services.id', $interpretation->serviceIds()));
            }
            if ($interpretation->specializationIds() !== []) {
                $gate->orWhereHas('specializations', fn ($q) => $q->whereIn('specializations.id', $interpretation->specializationIds()));
            }
            if ($interpretation->textTerms !== []) {
                $gate->orWhere(function (Builder $text) use ($interpretation) {
                    foreach ($interpretation->textTerms as $term) {
                        // Terms are letters/digits only (see resolver) and always bound.
                        $like = '%'.$term.'%';
                        $text->orWhere('lawyer_profiles.title', 'like', $like)
                            ->orWhere('lawyer_profiles.bio', 'like', $like)
                            ->orWhere('lawyer_profiles.experience_summary', 'like', $like);
                    }
                });
            }
        }
    }

    private function applyNonTopicalGate(Builder $gate, MatchCriteria $criteria): void
    {
        if ($criteria->city !== null) {
            $gate->orWhere(fn (Builder $q) => $this->whereCity($q, $criteria->city));
        }

        if ($criteria->hasBudget()) {
            $tolerance = $this->tolerance();
            $gate->orWhere(function (Builder $q) use ($criteria, $tolerance) {
                $criteria->minBudget !== null && $q->where('lawyer_profiles.consultation_fee', '>=', $criteria->minBudget * (1 - $tolerance));
                $criteria->maxBudget !== null && $q->where('lawyer_profiles.consultation_fee', '<=', $criteria->maxBudget * (1 + $tolerance));
            });
        }

        if ($criteria->minExperience !== null) {
            $gate->orWhere(fn (Builder $q) => $q->minExperience($criteria->minExperience));
        }

        if ($criteria->availableDay !== null) {
            $gate->orWhereHas('availabilities', fn ($q) => $this->availabilityConstraint($q, $criteria->availableDay));
        }
    }

    /** Exact city match, case-insensitive, identical on every database. */
    private function whereCity(Builder $query, string $city): void
    {
        $query->whereRaw('LOWER(lawyer_profiles.city) = ?', [mb_strtolower($city)]);
    }

    /** A recurring, available slot on the requested weekday (0 = Sunday). */
    private function availabilityConstraint($query, int $day): void
    {
        $query->where('is_available', true)
            ->where('is_recurring', true)
            ->where('day_of_week', $day);
    }

    /*
    |--------------------------------------------------------------------------
    | Scoring
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, float>  $components
     * @return array{match: LawyerMatch, points: float, exact: float}
     */
    private function score(LawyerProfile $lawyer, MatchCriteria $criteria, ?ProblemInterpretation $interpretation, array $components, float $possible): array
    {
        $reasons = [];
        $gaps = [];
        $matches = array_fill_keys(array_keys((array) ($this->config['weights'] ?? [])), null);
        $breakdown = [];
        $earned = 0.0;

        foreach ($components as $name => $weight) {
            $result = match ($name) {
                'service' => $this->evaluateService($lawyer, $criteria),
                'specialization' => $this->evaluateSpecialization($lawyer, $criteria),
                'problem' => $this->evaluateProblem($lawyer, $interpretation),
                'location' => $this->evaluateLocation($lawyer, $criteria),
                'budget' => $this->evaluateBudget($lawyer, $criteria),
                'experience' => $this->evaluateExperience($lawyer, $criteria),
                'availability' => $this->evaluateAvailability($lawyer, $criteria),
            };

            $fraction = max(0.0, min(1.0, $result['fraction']));
            $points = $weight * $fraction;
            $earned += $points;

            $matches[$name] = $result['matched'];
            $result['reason'] !== null && $reasons[] = $result['reason'];
            $result['gap'] !== null && $gaps[] = $result['gap'];
            $breakdown[$name] = ['weight' => $weight, 'fraction' => round($fraction, 4), 'points' => round($points, 4)] + $result['extra'];
        }

        $exact = $possible > 0 ? min(100.0, max(0.0, $earned / $possible * 100)) : 0.0;

        return [
            'match' => new LawyerMatch($lawyer, (int) round($exact), $reasons, $gaps, $matches, $breakdown),
            'points' => $earned,
            'exact' => round($exact, 6),
        ];
    }

    /** Total order: score, experience, name, id -- never ambiguous. */
    private function compare(array $a, array $b): int
    {
        return [$b['exact'], $b['match']->lawyer->years_of_experience, $a['match']->lawyer->display_name, $a['match']->lawyer->id]
            <=> [$a['exact'], $a['match']->lawyer->years_of_experience, $b['match']->lawyer->display_name, $b['match']->lawyer->id];
    }

    /** @return array{fraction: float, matched: bool, reason: ?string, gap: ?string, extra: array} */
    private function evaluateService(LawyerProfile $lawyer, MatchCriteria $criteria): array
    {
        $service = $lawyer->services->firstWhere('id', $criteria->serviceId);

        return $service !== null
            ? $this->result(1.0, true, 'Offers the requested service: '.$service->name)
            : $this->result(0.0, false, null, 'Does not offer the requested service');
    }

    private function evaluateSpecialization(LawyerProfile $lawyer, MatchCriteria $criteria): array
    {
        $specialization = $lawyer->specializations->firstWhere('id', $criteria->specializationId);

        return $specialization !== null
            ? $this->result(1.0, true, 'Specializes in the requested area: '.$specialization->name)
            : $this->result(0.0, false, null, 'Does not list the requested specialization');
    }

    private function evaluateProblem(LawyerProfile $lawyer, ?ProblemInterpretation $interpretation): array
    {
        $tiers = (array) ($this->config['problem_tiers'] ?? []);
        $best = ['fraction' => 0.0, 'reason' => null, 'tier' => null];

        if ($interpretation !== null) {
            foreach ($lawyer->specializations as $specialization) {
                $hit = $interpretation->specializations[$specialization->id] ?? null;
                if ($hit === null) {
                    continue;
                }
                $tier = $hit['direct'] ? 'specialization_direct' : 'specialization_related';
                $fraction = (float) ($tiers[$tier] ?? 0);
                if ($fraction > $best['fraction']) {
                    $best = ['fraction' => $fraction, 'tier' => $tier, 'reason' => sprintf(
                        'Specialization "%s" %s your legal problem ("%s")',
                        $hit['name'], $hit['direct'] ? 'matches' : 'relates to', $hit['terms'][0]
                    )];
                }
            }

            foreach ($lawyer->services as $service) {
                $hit = $interpretation->services[$service->id] ?? null;
                if ($hit === null) {
                    continue;
                }
                $tier = $hit['direct'] ? 'service_direct' : 'service_related';
                $fraction = (float) ($tiers[$tier] ?? 0);
                if ($fraction > $best['fraction']) {
                    $best = ['fraction' => $fraction, 'tier' => $tier, 'reason' => sprintf(
                        'Offers %s, which %s your legal problem ("%s")',
                        $hit['name'], $hit['direct'] ? 'matches' : 'relates to', $hit['terms'][0]
                    )];
                }
            }

            if ($best['fraction'] < (float) ($tiers['text'] ?? 0)) {
                $profileText = mb_strtolower(implode(' ', array_filter([$lawyer->title, $lawyer->bio, $lawyer->experience_summary])));
                foreach ($interpretation->textTerms as $term) {
                    if ($profileText !== '' && str_contains($profileText, $term)) {
                        $best = ['fraction' => (float) $tiers['text'], 'tier' => 'text', 'reason' => sprintf('Profile mentions "%s" from your legal problem', $term)];
                        break;
                    }
                }
            }
        }

        return $best['fraction'] > 0
            ? $this->result($best['fraction'], true, $best['reason'], null, ['tier' => $best['tier']])
            : $this->result(0.0, false, null, 'No clear link between this lawyer\'s profile and your legal problem');
    }

    private function evaluateLocation(LawyerProfile $lawyer, MatchCriteria $criteria): array
    {
        $city = trim((string) $lawyer->city);

        if ($city === '') {
            return $this->result(0.0, false, null, 'City not listed');
        }

        return mb_strtolower($city) === mb_strtolower((string) $criteria->city)
            ? $this->result(1.0, true, 'Based in '.$city)
            : $this->result(0.0, false, null, 'Based in '.$city.', not '.$criteria->city);
    }

    private function evaluateBudget(LawyerProfile $lawyer, MatchCriteria $criteria): array
    {
        if ($lawyer->consultation_fee === null) {
            return $this->result(0.0, false, null, 'Consultation fee not listed', ['fit' => 'unknown']);
        }

        $fee = (float) $lawyer->consultation_fee;
        $label = 'Rs. '.number_format($fee, 0);
        $min = $criteria->minBudget;
        $max = $criteria->maxBudget;

        if (($min === null || $fee >= $min) && ($max === null || $fee <= $max)) {
            return $this->result(1.0, true, "Consultation fee ({$label}) is within your budget", null, ['fit' => 'within']);
        }

        $tolerance = $this->tolerance();
        if (($min === null || $fee >= $min * (1 - $tolerance)) && ($max === null || $fee <= $max * (1 + $tolerance))) {
            return $this->result(
                (float) ($this->config['budget']['near_score'] ?? 0.5),
                false,
                "Consultation fee ({$label}) is close to your budget (within ".(int) ($tolerance * 100).'%)',
                null,
                ['fit' => 'near'],
            );
        }

        return $this->result(0.0, false, null, "Consultation fee ({$label}) is outside your budget", ['fit' => 'outside']);
    }

    private function evaluateExperience(LawyerProfile $lawyer, MatchCriteria $criteria): array
    {
        $years = (int) $lawyer->years_of_experience;
        $min = (int) $criteria->minExperience;

        if ($years < $min) {
            return $this->result(0.0, false, null, "{$years} years of experience is below your minimum of {$min}");
        }

        $met = (float) ($this->config['experience']['met_score'] ?? 0.7);
        $span = max(1, (int) ($this->config['experience']['bonus_span_years'] ?? 10));
        $fraction = $met + (1 - $met) * min(1.0, ($years - $min) / $span);

        return $this->result(
            $fraction,
            true,
            $years === $min
                ? "{$years} years of experience (meets your minimum of {$min})"
                : "{$years} years of experience (exceeds your minimum of {$min})"
        );
    }

    private function evaluateAvailability(LawyerProfile $lawyer, MatchCriteria $criteria): array
    {
        $day = self::DAY_NAMES[$criteria->availableDay];

        return (bool) ($lawyer->available_on_day ?? false)
            ? $this->result(1.0, true, "Has recurring availability on {$day}")
            : $this->result(0.0, false, null, "No recurring availability listed for {$day}");
    }

    /** @return array{fraction: float, matched: bool, reason: ?string, gap: ?string, extra: array} */
    private function result(float $fraction, bool $matched, ?string $reason = null, ?string $gap = null, array $extra = []): array
    {
        return ['fraction' => $fraction, 'matched' => $matched, 'reason' => $reason, 'gap' => $gap, 'extra' => $extra];
    }

    private function tolerance(): float
    {
        return max(0.0, (float) ($this->config['budget']['tolerance_percent'] ?? 20)) / 100;
    }

    /*
    |--------------------------------------------------------------------------
    | Metadata
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, float>  $components
     * @return array<string, mixed>
     */
    private function meta(MatchCriteria $criteria, ?ProblemInterpretation $interpretation, array $components, int $candidates, bool $capReached, int $returned): array
    {
        return [
            'max_score' => 100,
            'method' => 'deterministic-weighted-scoring',
            'eligibility' => 'approved + active + public lawyers only',
            'criteria' => $criteria->toArray(),
            'applied_components' => $components,
            'problem' => $interpretation === null ? null : [
                // `used` = the text produced at least one usable signal;
                // `catalogue_matched` = it hit real services/specializations
                // (otherwise it is only searched for in lawyer profile text).
                'used' => $interpretation->hasSignals(),
                'catalogue_matched' => $interpretation->services !== [] || $interpretation->specializations !== [],
                'services' => array_values(array_map(fn ($h) => $h['name'], $interpretation->services)),
                'specializations' => array_values(array_map(fn ($h) => $h['name'], $interpretation->specializations)),
                'keywords' => $interpretation->matchedTerms(),
                'text_terms' => $interpretation->textTerms,
            ],
            'candidates_considered' => $candidates,
            'candidate_cap_reached' => $capReached,
            'returned' => $returned,
        ];
    }
}
