<?php

namespace App\Http\Requests\Lawyer;

/**
 * Same validation as StoreLawyerAvailabilityRequest, but the overlap
 * check excludes the record currently being edited (so saving a
 * window with its own unchanged times doesn't reject itself as
 * overlapping "another" window).
 *
 * Authorization for *which* availability record may be edited is
 * handled by LawyerAvailabilityPolicy via $this->authorize() in the
 * controller, not here -- this class only validates the submitted
 * field shape/business rules.
 */
class UpdateLawyerAvailabilityRequest extends StoreLawyerAvailabilityRequest
{
    protected function excludingAvailability($query)
    {
        $availability = $this->route('availability');
        $id = is_object($availability) ? $availability->id : $availability;

        return $query->where('id', '!=', $id);
    }

    public function validatedForUpdate(): array
    {
        return $this->validatedForCreate();
    }
}
