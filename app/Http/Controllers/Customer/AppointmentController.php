<?php

namespace App\Http\Controllers\Customer;

use App\Enums\ConsultationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointments\CancelAppointmentRequest;
use App\Http\Requests\Appointments\RescheduleAppointmentRequest;
use App\Http\Requests\Customer\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\LawyerProfile;
use App\Services\AppointmentAvailabilityService;
use App\Services\AppointmentStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Customer-facing appointment management (Phase 6 Part 1 booking
 * foundation, requirements #12-#28; extended in Phase 6 Part 2 with
 * cancellation and rescheduling -- requirements #11-#17).
 *
 * Every lawyer id this controller ever resolves goes through
 * `LawyerProfile::searchable()`, the same eligibility scope used by
 * the Phase 5 directory/profile pages and the Phase 5 Half 2 matching
 * engine (requirement #5: reuse, never re-derive eligibility). A
 * pending/rejected/inactive/suspended lawyer id produces the exact
 * same 404 as a nonexistent one. Status changes (cancel, reschedule)
 * never touch `status` directly -- they are delegated entirely to
 * AppointmentStatusService, the single place allowed to change it.
 */
class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentAvailabilityService $availability,
        private readonly AppointmentStatusService $status,
    ) {
    }

    /** The authenticated customer's own appointments -- never another customer's. */
    public function index(Request $request): View
    {
        $filter = $request->query('filter');

        $appointments = $request->user()->appointmentsAsCustomer()
            ->filter(in_array($filter, Appointment::filterValues(), true) ? $filter : null)
            ->with(['lawyerProfile:id,display_name,title,city', 'service:id,name'])
            ->orderByDesc('appointment_date')
            ->orderByDesc('start_time')
            ->paginate(10)
            ->withQueryString();

        return view('customer.appointments.index', ['appointments' => $appointments, 'filter' => $filter]);
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $this->authorize('view', $appointment);

        $appointment->load(['lawyerProfile:id,display_name,title,city', 'service:id,name', 'rescheduledFrom', 'rescheduledTo', 'review']);

        return view('customer.appointments.show', [
            'appointment' => $appointment,
            'actions' => $this->status->availableActions($appointment, $request->user()),
        ]);
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->status->cancel($appointment, $request->user(), $request->validated('reason'));

        return redirect()->route('customer.appointments.show', $appointment)
            ->with('status', "Appointment {$appointment->reference} cancelled.");
    }

    public function rescheduleForm(Request $request, Appointment $appointment): View
    {
        $this->authorize('reschedule', $appointment);

        $appointment->load('lawyerProfile');

        return view('customer.appointments.reschedule', [
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

        return redirect()->route('customer.appointments.show', $new)
            ->with('status', "Appointment {$appointment->reference} rescheduled. It is pending the lawyer's confirmation as {$new->reference}.");
    }

    public function create(Request $request, int $lawyer): View
    {
        $lawyerProfile = LawyerProfile::query()
            ->searchable()
            ->with(['services' => fn ($query) => $query->active()->select('services.id', 'services.name', 'services.slug')])
            ->findOrFail($lawyer);

        return view('customer.appointments.create', [
            'lawyer' => $lawyerProfile,
            'consultationTypes' => ConsultationType::cases(),
            'minDate' => now()->toDateString(),
            'maxDate' => now()->addDays($this->availability->bookingWindowDays())->toDateString(),
        ]);
    }

    /**
     * JSON: free slots for this lawyer on a given date. Read-only,
     * derived entirely server-side from LawyerAvailability +
     * Appointment rows -- the client never tells the server which
     * slots are free, only which date it wants to see (requirement
     * #26: frontend slot visibility is not a security mechanism, this
     * endpoint is the actual source of truth the create/store actions
     * also re-check independently).
     */
    public function slots(Request $request, int $lawyer): JsonResponse
    {
        $lawyerProfile = LawyerProfile::query()->searchable()->findOrFail($lawyer);

        $validated = $request->validate([
            'date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.now()->addDays($this->availability->bookingWindowDays())->toDateString(),
            ],
        ]);

        $slots = $this->availability->availableSlots($lawyerProfile, Carbon::parse($validated['date']));

        return response()->json(['slots' => $slots]);
    }

    public function store(StoreAppointmentRequest $request, int $lawyer): RedirectResponse
    {
        $lawyerProfile = LawyerProfile::query()->searchable()->findOrFail($lawyer);

        // Re-derived from the lawyer's own services() relation (not a
        // bare Service::find()) so an id that exists but isn't offered
        // by this lawyer 404s here even if it somehow slipped past the
        // Form Request's own relationship check.
        $service = $lawyerProfile->services()->active()->findOrFail($request->validated('service_id'));

        $appointment = $this->availability->book(
            customer: $request->user(),
            lawyer: $lawyerProfile,
            service: $service,
            date: Carbon::parse($request->validated('appointment_date')),
            startTime: $request->validated('start_time'),
            consultationType: $request->validated('consultation_type'),
            reason: $request->validated('reason'),
        );

        $this->status->notifyBooked($appointment);

        return redirect()->route('customer.appointments.show', $appointment)
            ->with('status', "Appointment request {$appointment->reference} submitted. It is pending the lawyer's confirmation.");
    }
}
