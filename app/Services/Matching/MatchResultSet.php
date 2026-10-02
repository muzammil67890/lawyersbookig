<?php

namespace App\Services\Matching;

use Illuminate\Support\Collection;

/**
 * Ranked matches (best first) plus metadata describing how the result
 * was produced. `meta` never contains lawyer-private or user-private
 * data -- only the applied criteria, weights and counters.
 */
final class MatchResultSet
{
    /**
     * @param  Collection<int, LawyerMatch>  $matches
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        private readonly Collection $matches,
        private readonly array $meta,
    ) {}

    /** @return Collection<int, LawyerMatch> */
    public function matches(): Collection
    {
        return $this->matches;
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->meta;
    }
}
