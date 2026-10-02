<?php

namespace App\Http\Resources;

use App\Services\Matching\LawyerMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * The ONLY serialisation of a match that leaves the application.
 *
 * It is an explicit allow-list of the same fields the public lawyer
 * directory already shows. It never reads or emits user_id, email,
 * phone, address, license/bar-council numbers, CNIC/face verification
 * data, rejection reasons, admin notes or reviewer information -- and
 * the service does not even select those columns.
 *
 * @property LawyerMatch $resource
 */
class LawyerMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $match = $this->resource;
        $lawyer = $match->lawyer;

        return [
            'lawyer' => [
                'id' => $lawyer->id,
                'name' => $lawyer->display_name,
                'title' => $lawyer->title,
                'city' => $lawyer->city,
                'years_of_experience' => (int) $lawyer->years_of_experience,
                'consultation_fee' => $lawyer->consultation_fee === null ? null : (float) $lawyer->consultation_fee,
                'bio_excerpt' => $lawyer->bio === null ? null : Str::limit($lawyer->bio, 200),
                'profile_image_url' => $lawyer->profile_image ? asset('storage/'.$lawyer->profile_image) : null,
                'profile_url' => route('lawyers.show', $lawyer->id),
                'services' => $lawyer->services->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug])->values(),
                'specializations' => $lawyer->specializations->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug])->values(),
            ],
            'score' => $match->score,
            'reasons' => $match->reasons,
            'gaps' => $match->gaps,
            'matches' => $match->matches,
            'breakdown' => $match->breakdown,
        ];
    }
}
