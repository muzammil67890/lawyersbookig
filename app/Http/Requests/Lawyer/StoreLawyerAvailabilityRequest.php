<?php

namespace App\Http\Requests\Lawyer;

use App\Models\LawyerAvailability;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a lawyer adding a new availability window (Phase 6 Part 1,
 * requirements #6/#9).
 *
 * A window is either a weekly recurring slot (`day_of_week` set) or a
 * one-off date override (`specific_date` set) -- never both, never
 * neither, matching the schema comment on the lawyer_availabilities
 * migration. `is_recurring` is never accepted from the client: it is
 * derived server-side from which of the two was supplied, so it can
 * never disagree with the data.
 *
 * Overlap: this project's availability model does not support
 * overlapping windows for the same lawyer on the same day/date --
 * `Monday 09:00-12:00` and `Monday 11:00-14:00` cannot both exist,
 * since a single, unambiguous view of "when is this lawyer free" is
 * what the booking foundation (and Phase 6 Part 2) is built on top
 * of. This is enforced here, not just as a UI nicety.
 */
class StoreLawyerAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isLawyer() && $this->user()->lawyerProfile !== null;
    }

    public function rules(): array
    {
        return [
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'specific_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasDayOfWeek = $this->filled('day_of_week');
            $hasSpecificDate = $this->filled('specific_date');

            if ($hasDayOfWeek === $hasSpecificDate) {
                $validator->errors()->add(
                    'day_of_week',
                    'Choose either a recurring weekday or a specific one-off date, not both or neither.'
                );

                return;
            }

            if ($this->overlapsExistingWindow()) {
                $validator->errors()->add(
                    'start_time',
                    'This overlaps an existing availability window on the same day. Adjust the times or edit the existing window instead.'
                );
            }
        });
    }

    /**
     * True if the submitted window overlaps any of this lawyer's
     * existing windows that share the same day_of_week (for a
     * recurring row) or the same specific_date (for a one-off row).
     * Called only after the mutual-exclusivity check above has
     * already passed, so exactly one of the two is set here.
     */
    protected function overlapsExistingWindow(): bool
    {
        $profile = $this->user()->lawyerProfile;
        $start = $this->input('start_time');
        $end = $this->input('end_time');

        $query = $profile->availabilities()->newQuery();

        if ($this->filled('day_of_week')) {
            $query->where('day_of_week', (int) $this->input('day_of_week'));
        } else {
            $query->whereDate('specific_date', $this->input('specific_date'));
        }

        return $this->excludingAvailability($query)
            ->where('start_time', '<', $end.':00')
            ->where('end_time', '>', $start.':00')
            ->exists();
    }

    /**
     * Hook for UpdateLawyerAvailabilityRequest to exclude the record
     * being edited from its own overlap check. The store request has
     * no existing record to exclude.
     */
    protected function excludingAvailability($query)
    {
        return $query;
    }

    public function validatedForCreate(): array
    {
        $data = $this->validated();

        return [
            'day_of_week' => $this->filled('day_of_week') ? (int) $data['day_of_week'] : null,
            'specific_date' => $this->filled('specific_date') ? $data['specific_date'] : null,
            'start_time' => $data['start_time'].':00',
            'end_time' => $data['end_time'].':00',
            'is_recurring' => $this->filled('day_of_week'),
            'is_available' => $this->boolean('is_available', true),
        ];
    }
}
