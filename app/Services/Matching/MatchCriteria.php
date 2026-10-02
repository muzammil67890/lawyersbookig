<?php

namespace App\Services\Matching;

use InvalidArgumentException;

/**
 * Structured, self-validating input for LawyerMatchingService.
 *
 * The HTTP layer validates with LawyerMatchRequest first, but this
 * value object re-checks its own invariants so a future in-process
 * caller (e.g. the Phase 11 assistant) can never hand the matching
 * engine an out-of-range or inconsistent value.
 *
 * "Not requested" is always `null` (empty strings, and a minimum of 0,
 * are normalized to null) -- the engine only scores criteria that were
 * genuinely supplied.
 */
final class MatchCriteria
{
    public const STRICT_BUDGET = 'budget';

    public const STRICT_LOCATION = 'location';

    public const STRICT_EXPERIENCE = 'experience';

    public const STRICT_OPTIONS = [self::STRICT_BUDGET, self::STRICT_LOCATION, self::STRICT_EXPERIENCE];

    /** Request keys that count as a matching criterion (strict/limit do not). */
    public const CRITERIA_KEYS = [
        'problem', 'service', 'specialization', 'city',
        'min_budget', 'max_budget', 'min_experience', 'available_day',
    ];

    public readonly ?string $problem;

    public readonly ?string $city;

    public readonly ?float $minBudget;

    public readonly ?int $minExperience;

    /** @var list<string> */
    public readonly array $strict;

    public readonly int $limit;

    /**
     * @param  list<string>  $strict  Criteria to enforce as HARD filters instead of just scoring.
     */
    public function __construct(
        ?string $problem = null,
        public readonly ?int $serviceId = null,
        public readonly ?int $specializationId = null,
        ?string $city = null,
        ?float $minBudget = null,
        public readonly ?float $maxBudget = null,
        ?int $minExperience = null,
        public readonly ?int $availableDay = null,
        array $strict = [],
        ?int $limit = null,
    ) {
        $this->problem = self::cleanText($problem, (int) config('lawyer_matching.problem.max_length', 500));
        $this->city = self::cleanText($city, 100);

        $this->minBudget = ($minBudget !== null && $minBudget > 0) ? $minBudget : null;
        $this->minExperience = ($minExperience !== null && $minExperience > 0) ? $minExperience : null;

        if (($this->minBudget !== null && $this->minBudget > 9_999_999)
            || ($this->maxBudget !== null && ($this->maxBudget < 0 || $this->maxBudget > 9_999_999))) {
            throw new InvalidArgumentException('Budget is out of range.');
        }
        if ($this->minBudget !== null && $this->maxBudget !== null && $this->maxBudget < $this->minBudget) {
            throw new InvalidArgumentException('Maximum budget cannot be lower than minimum budget.');
        }
        if ($this->minExperience !== null && $this->minExperience > 70) {
            throw new InvalidArgumentException('Minimum experience is out of range.');
        }
        if ($this->availableDay !== null && ($this->availableDay < 0 || $this->availableDay > 6)) {
            throw new InvalidArgumentException('Available day must be 0 (Sunday) to 6 (Saturday).');
        }
        if (($this->serviceId !== null && $this->serviceId < 1) || ($this->specializationId !== null && $this->specializationId < 1)) {
            throw new InvalidArgumentException('Service and specialization must be positive identifiers.');
        }

        $unknown = array_diff($strict, self::STRICT_OPTIONS);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown strict option: '.implode(', ', $unknown));
        }
        $this->strict = array_values(array_unique($strict));

        $max = (int) config('lawyer_matching.limits.max', 50);
        $default = (int) config('lawyer_matching.limits.default', 10);
        $this->limit = max(1, min($limit ?? $default, $max));
    }

    /**
     * Build from an already-validated request array (see LawyerMatchRequest).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $int = fn (string $key): ?int => isset($data[$key]) && $data[$key] !== '' ? (int) $data[$key] : null;
        $float = fn (string $key): ?float => isset($data[$key]) && $data[$key] !== '' ? (float) $data[$key] : null;

        return new self(
            problem: isset($data['problem']) ? (string) $data['problem'] : null,
            serviceId: $int('service'),
            specializationId: $int('specialization'),
            city: isset($data['city']) ? (string) $data['city'] : null,
            minBudget: $float('min_budget'),
            maxBudget: $float('max_budget'),
            minExperience: $int('min_experience'),
            availableDay: $int('available_day'),
            strict: array_map('strval', (array) ($data['strict'] ?? [])),
            limit: $int('limit'),
        );
    }

    /** True when at least one real matching criterion was supplied. */
    public function hasCriteria(): bool
    {
        return $this->problem !== null
            || $this->serviceId !== null
            || $this->specializationId !== null
            || $this->city !== null
            || $this->minBudget !== null
            || $this->maxBudget !== null
            || $this->minExperience !== null
            || $this->availableDay !== null;
    }

    public function hasBudget(): bool
    {
        return $this->minBudget !== null || $this->maxBudget !== null;
    }

    public function isStrict(string $criterion): bool
    {
        return in_array($criterion, $this->strict, true);
    }

    /** @return array<string, mixed> Echo of what was actually applied (no user identity involved). */
    public function toArray(): array
    {
        return [
            'problem' => $this->problem,
            'service' => $this->serviceId,
            'specialization' => $this->specializationId,
            'city' => $this->city,
            'min_budget' => $this->minBudget,
            'max_budget' => $this->maxBudget,
            'min_experience' => $this->minExperience,
            'available_day' => $this->availableDay,
            'strict' => $this->strict,
            'limit' => $this->limit,
        ];
    }

    private static function cleanText(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        // Drop control characters, collapse whitespace, cap the length.
        $value = preg_replace('/[\p{C}]+/u', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }
}
