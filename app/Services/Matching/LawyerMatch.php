<?php

namespace App\Services\Matching;

use App\Models\LawyerProfile;

/**
 * One ranked lawyer plus the full explanation of the score.
 *
 * - `score`     integer 0..100 (see config/lawyer_matching.php).
 * - `reasons`   human-readable statements, each generated from a
 *               condition that ACTUALLY held for this lawyer.
 * - `gaps`      statements for requested criteria this lawyer did NOT
 *               satisfy (so nothing is silently hidden).
 * - `matches`   per-criterion result: true = satisfied, false = requested
 *               but not satisfied, null = not requested.
 * - `breakdown` per-criterion weight / fraction / points, so the score
 *               can be re-derived by hand.
 *
 * `lawyer` is the Eloquent model, loaded with ONLY public columns by
 * the service; anything that leaves the application (JSON) must go
 * through App\Http\Resources\LawyerMatchResource, which allow-lists
 * fields.
 */
final class LawyerMatch
{
    /**
     * @param  list<string>  $reasons
     * @param  list<string>  $gaps
     * @param  array<string, bool|null>  $matches
     * @param  array<string, array<string, mixed>>  $breakdown
     */
    public function __construct(
        public readonly LawyerProfile $lawyer,
        public readonly int $score,
        public readonly array $reasons,
        public readonly array $gaps,
        public readonly array $matches,
        public readonly array $breakdown,
    ) {}
}
