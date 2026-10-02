<?php

namespace App\Http\Controllers\Lawyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\CancelAppointmentRequest;
use App\Http\Requests\Appointments\RescheduleAppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentAvailabilityService;
use App\Services\AppointmentStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Phase 6 Part 2: complete appointment management, lawyer side.
 *
 * Every record this controller touches is scoped to the authenticated
 * lawyer's own `lawyerProfile` (never a bare `Appointment::find()`),
 * and every mutating action is additionally re-checked by
 * AppointmentPolicy inside AppointmentStatusService -- two independent
 * layers, matching the pattern already established by
 * Lawyer\AvailabilityController in Phase 6 Part 1. No method here ever
 * assigns `status` directly; every state change goes through
 * AppointmentStatusService, which is the only place that may.
 */
class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentStatusService $status,
        private readonly AppointmentAvailabilityService $availability,
    ) {
    }

    /** The authenticated lawyer's own appointments -- never another lawyer's. */
    public function index(Request $request): View|RedirectResponse
    {
        $profile = $request->user()->lawyerProfile;

        if (! $profile) {
            return redirect()->route('lawyer.onboarding.create')
                ->with('status', 'Complete your lawyer application before managing appointments.');
        }

        $filter = $request->query('filter');

        $appointments = $profile->appointments()
            ->filter(in_array($filter, Appointment::filterValues(), true) ? $filter : null)
            ->with(['customer:id,name,email', 'service:id,name'])
            ->orderByDesc('appointment_date')
            ->orderByDesc('start_time')
            ->paginate(10)
            ->withQueryString();

        return view('lawyer.appointments.index', [
            'appointments' => $appointments,
            'filter' => $filter,
        ]);
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $this->authorize('view', $appointment);

        $appointment->load(['customer:id,name,email,phone', 'service:id,name', 'rescheduledFrom', 'rescheduledTo']);

        return view('lawyer.appointments.show', [
            'appointment' => $appointment,
            'actions' => $this->status->availableActions($appointment, $request->user()),
        ]);
    }

    public function confirm(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->status->confirm($appointment, $request->user());

        return redirect()->route('lawyer.appointments.show', $appointment)
            ->with('status', "Appointment {$appointment->reference} confirmed.");
    }

    public function reject(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->status->reject($appointment, $request->user());

        return redirect()->route('lawyer.appointments.show', $appointment)
            ->with('status', "Appointment {$appointment->reference} rejected.");
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->status->cancel($appointment, $request->user(), $request->validated('reason'));

        return redirect()->route('lawyer.appointments.show', $appointment)
            ->with('status', "Appointment {$appointment->reference} cancelled.");
    }

    public function complete(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->status->complete($appointment, $request->user());

        return redirect()->route('lawyer.appointments.show', $appointment)
            ->with('status', "Appointment {$appointment->reference} marked as completed.");
    }

    public function rescheduleForm(Request $request, Appointment $appointment): View
    {
        $this->authorize('reschedule', $appointment);

        $appointment->load('lawyerProfile');

        return view('lawyer.appointments.reschedule', [
            'appointment' => $appointment,
            'minDate' => now()->toDateString(),
            'maxDate' => now()->addDays($this->availability->bookingWindowDays())->toDateString(),
        ]);
    }

    /** JSON free slots for the reschedule picker, excluding this appointment's own current slot. */
    public function rescheduleSlots(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('reschedule', $appointment);

        $validated = $request->validate([
            'date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.now()->addDays($this->availability->bookingWindowDays())->toDateString(),
            ],
        ]);

        $slots = $this->availability->availableSlots(
            $appointment->lawyerProfile,
            Carbon::parse($validated['date']),
            null,
            $appointment->id,
        );

        return response()->json(['slots' => $slots]);
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $new = $this->status->reschedule(
            $appointment,
            $request->user(),
            Carbon::parse($request->validated('appointment_date')),
            $request->validated('start_time'),
        );

        return redirect()->route('lawyer.appointments.show', $new)
            ->with('status', "Appointment {$appointment->reference} rescheduled to {$new->reference}.");
    }
}
