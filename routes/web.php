<?php

use App\Http\Controllers\Admin\CaseController as AdminCaseController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LawyerVerificationController as AdminLawyerVerificationController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\CaseDocumentController;
use App\Http\Controllers\Customer\AppointmentController as CustomerAppointmentController;
use App\Http\Controllers\Customer\CaseController as CustomerCaseController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\MessagingController as CustomerMessagingController;
use App\Http\Controllers\Customer\ReviewController as CustomerReviewController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\LawyerDirectoryController;
use App\Http\Controllers\LawyerMatchController;
use App\Http\Controllers\LegalConnectController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Lawyer\AppointmentController as LawyerAppointmentController;
use App\Http\Controllers\Lawyer\AvailabilityController as LawyerAvailabilityController;
use App\Http\Controllers\Lawyer\CaseController as LawyerCaseController;
use App\Http\Controllers\Lawyer\DashboardController as LawyerDashboardController;
use App\Http\Controllers\Lawyer\MessagingController as LawyerMessagingController;
use App\Http\Controllers\Lawyer\OnboardingController as LawyerOnboardingController;
use App\Http\Controllers\Lawyer\ReviewController as LawyerReviewController;
use App\Http\Controllers\VerificationDocumentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
| The Legal Connect prototype (home, lawyer browsing, services, about,
| contact, resources, smart-match, etc.) is a single hash-routed page
| and stays reachable to everyone at "/" exactly as Phase 1/2 left it.
*/
Route::get('/', [LegalConnectController::class, 'index'])->name('home');

/*
|--------------------------------------------------------------------------
| Phase 5: real, database-backed lawyer directory + public profile
|--------------------------------------------------------------------------
| Additive, alongside (not replacing) the "/" demo above. Both routes
| are public -- no 'auth' middleware -- and both go through
| LawyerProfile::scopeSearchable() so only approved + active lawyers
| are ever returned or found.
*/
Route::get('/lawyers', [LawyerDirectoryController::class, 'index'])->name('lawyers.index');

/*
| Phase 5 Half 2: deterministic lawyer-matching foundation (NOT the AI
| Assistant -- that is Phase 11 and will call the same service).
| Registered before /lawyers/{lawyer}; that route is also whereNumber()
| constrained, so "match" can never be treated as a lawyer ID. Public,
| read-only, public data only, throttled.
*/
Route::get('/lawyers/match', [LawyerMatchController::class, 'index'])
    ->middleware('throttle:30,1')
    ->name('lawyers.match');

Route::get('/lawyers/{lawyer}', [LawyerDirectoryController::class, 'show'])
    ->whereNumber('lawyer')
    ->name('lawyers.show');

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Protected routes
|--------------------------------------------------------------------------
| 'auth' -> must be logged in. 'active' -> account must not be
| suspended/inactive (checked again here, not just at login, in case an
| admin suspends an account mid-session). Role + email-verification are
| layered on the three role-specific groups below.
*/
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard.redirect');

    /*
    | Phase 10: central Notification Center, available to any active,
    | authenticated account (all three roles) and deliberately not
    | role-prefixed. {notification} is a UUID resolved only through the
    | authenticated user's own notifications inside the controller, so a
    | guessed id that belongs to someone else is a 404. All mutations
    | are POST (CSRF-protected).
    */
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->whereUuid('notification')
        ->name('notifications.read');
    Route::post('/notifications/{notification}/unread', [NotificationController::class, 'markUnread'])
        ->whereUuid('notification')
        ->name('notifications.unread');

    Route::middleware(['role:customer', 'verified'])
        ->prefix('customer')
        ->name('customer.')
        ->group(function () {
            Route::get('/dashboard', [CustomerDashboardController::class, 'index'])->name('dashboard');

            /*
            | Phase 6 Part 1: appointment booking foundation. Lawyer
            | eligibility is re-resolved from LawyerProfile::searchable()
            | inside the controller/Form Request on every action below --
            | {lawyer} is never trusted as-is. AppointmentPolicy gates
            | show() so a customer can only ever view their own
            | appointment.
            */
            Route::get('/appointments', [CustomerAppointmentController::class, 'index'])->name('appointments.index');
            Route::get('/appointments/{appointment}', [CustomerAppointmentController::class, 'show'])->name('appointments.show');

            /*
            | Phase 6 Part 2: cancellation + rescheduling. Every action
            | is re-authorized and re-validated by AppointmentPolicy +
            | AppointmentStatusService from the {appointment} route
            | binding -- ownership is never assumed from the fact that
            | this route is under /customer.
            */
            Route::post('/appointments/{appointment}/cancel', [CustomerAppointmentController::class, 'cancel'])->name('appointments.cancel');
            Route::get('/appointments/{appointment}/reschedule', [CustomerAppointmentController::class, 'rescheduleForm'])->name('appointments.reschedule.form');
            Route::get('/appointments/{appointment}/reschedule/slots', [CustomerAppointmentController::class, 'rescheduleSlots'])->name('appointments.reschedule.slots');
            Route::post('/appointments/{appointment}/reschedule', [CustomerAppointmentController::class, 'reschedule'])->name('appointments.reschedule');

            Route::get('/lawyers/{lawyer}/book', [CustomerAppointmentController::class, 'create'])
                ->whereNumber('lawyer')
                ->name('appointments.create');
            Route::get('/lawyers/{lawyer}/slots', [CustomerAppointmentController::class, 'slots'])
                ->whereNumber('lawyer')
                ->name('appointments.slots');
            Route::post('/lawyers/{lawyer}/book', [CustomerAppointmentController::class, 'store'])
                ->whereNumber('lawyer')
                ->name('appointments.store');

            /*
            | Phase 9: reviews. {appointment} is resolved by implicit
            | route-model binding and re-validated in full by
            | ReviewPolicy::create() inside the controller/service --
            | ownership, COMPLETED status, and "not already reviewed"
            | are never assumed from the fact that this route sits
            | under /customer. {review} on the edit/update/destroy
            | routes is likewise gated by ReviewPolicy::update()/
            | delete() so a customer can only ever reach their own
            | review, never another customer's.
            */
            Route::get('/appointments/{appointment}/review/create', [CustomerReviewController::class, 'create'])->name('appointments.review.create');
            Route::post('/appointments/{appointment}/review', [CustomerReviewController::class, 'store'])->name('appointments.review.store');
            Route::get('/reviews', [CustomerReviewController::class, 'index'])->name('reviews.index');
            Route::get('/reviews/{review}/edit', [CustomerReviewController::class, 'edit'])->name('reviews.edit');
            Route::put('/reviews/{review}', [CustomerReviewController::class, 'update'])->name('reviews.update');
            Route::delete('/reviews/{review}', [CustomerReviewController::class, 'destroy'])->name('reviews.destroy');

            /*
            | Phase 7: case management (requirement #7). {lawyer} in the
            | create/store routes is re-resolved through
            | LawyerProfile::searchable() exactly like the booking
            | routes above -- a customer can only open a case with a
            | lawyer who is actually eligible right now. {case} in
            | every other route is gated by LegalCasePolicy::view() so
            | a customer can only ever reach their own case.
            */
            Route::get('/cases', [CustomerCaseController::class, 'index'])->name('cases.index');
            Route::get('/cases/{case}', [CustomerCaseController::class, 'show'])->name('cases.show');
            Route::put('/cases/{case}', [CustomerCaseController::class, 'update'])->name('cases.update');
            Route::post('/cases/{case}/cancel', [CustomerCaseController::class, 'cancel'])->name('cases.cancel');

            Route::get('/lawyers/{lawyer}/cases/create', [CustomerCaseController::class, 'create'])
                ->whereNumber('lawyer')
                ->name('cases.create');
            Route::post('/lawyers/{lawyer}/cases', [CustomerCaseController::class, 'store'])
                ->whereNumber('lawyer')
                ->name('cases.store');

            // Documents: {case} + {document} are cross-checked against
            // each other and re-authorized independently of this URL
            // prefix -- see CaseDocumentController.
            Route::post('/cases/{case}/documents', [CaseDocumentController::class, 'store'])->name('cases.documents.store');
            Route::get('/cases/{case}/documents/{document}', [CaseDocumentController::class, 'download'])->name('cases.documents.download');
            Route::delete('/cases/{case}/documents/{document}', [CaseDocumentController::class, 'destroy'])->name('cases.documents.destroy');

            /*
            | Phase 8: private customer <-> lawyer messaging
            | (requirements #3, #10). {conversation} is additionally
            | gated by ConversationPolicy inside the controller -- this
            | middleware group only proves "some active, verified
            | customer", not "this customer's own conversation".
            | {lawyer} in the start route is re-resolved through
            | LawyerProfile::searchable() inside MessagingService,
            | exactly like the booking and case-creation flows, so a
            | pending/rejected/inactive/suspended lawyer can never be
            | messaged.
            */
            Route::get('/messages', [CustomerMessagingController::class, 'index'])->name('messages.index');
            Route::get('/messages/{conversation}', [CustomerMessagingController::class, 'show'])->name('messages.show');
            Route::post('/messages/{conversation}', [CustomerMessagingController::class, 'store'])->name('messages.store');
            Route::post('/lawyers/{lawyer}/message', [CustomerMessagingController::class, 'start'])
                ->whereNumber('lawyer')
                ->name('messages.start');
        });

    Route::middleware(['role:lawyer', 'verified'])
        ->prefix('lawyer')
        ->name('lawyer.')
        ->group(function () {
            Route::get('/dashboard', [LawyerDashboardController::class, 'index'])->name('dashboard');

            /*
            | Phase 6 Part 2: complete appointment management. Every
            | record is scoped to $request->user()->lawyerProfile
            | inside the controller and additionally gated by
            | AppointmentPolicy -- the same two-layer pattern as
            | availability management below.
            */
            Route::get('/appointments', [LawyerAppointmentController::class, 'index'])->name('appointments.index');
            Route::get('/appointments/{appointment}', [LawyerAppointmentController::class, 'show'])->name('appointments.show');
            Route::post('/appointments/{appointment}/confirm', [LawyerAppointmentController::class, 'confirm'])->name('appointments.confirm');
            Route::post('/appointments/{appointment}/reject', [LawyerAppointmentController::class, 'reject'])->name('appointments.reject');
            Route::post('/appointments/{appointment}/cancel', [LawyerAppointmentController::class, 'cancel'])->name('appointments.cancel');
            Route::post('/appointments/{appointment}/complete', [LawyerAppointmentController::class, 'complete'])->name('appointments.complete');
            Route::get('/appointments/{appointment}/reschedule', [LawyerAppointmentController::class, 'rescheduleForm'])->name('appointments.reschedule.form');
            Route::get('/appointments/{appointment}/reschedule/slots', [LawyerAppointmentController::class, 'rescheduleSlots'])->name('appointments.reschedule.slots');
            Route::post('/appointments/{appointment}/reschedule', [LawyerAppointmentController::class, 'reschedule'])->name('appointments.reschedule');

            // Phase 4: one-time onboarding (profile + CNIC/face upload).
            // OnboardingController redirects away from both of these the
            // moment a LawyerProfile already exists for the user.
            Route::get('/onboarding', [LawyerOnboardingController::class, 'create'])->name('onboarding.create');
            Route::post('/onboarding', [LawyerOnboardingController::class, 'store'])->name('onboarding.store');

            // A lawyer viewing their own CNIC/face verification image.
            // {type} is constrained below; VerificationDocumentController
            // additionally verifies the record belongs to this user.
            Route::get('/verification-document/{type}', [VerificationDocumentController::class, 'own'])
                ->whereIn('type', ['cnic', 'face'])
                ->name('verification.document');

            /*
            | Phase 6 Part 1: recurring/one-off availability management.
            | Every action is additionally gated by
            | LawyerAvailabilityPolicy (see AvailabilityController) --
            | this middleware group only proves "some active, verified
            | lawyer", not "this lawyer's own record".
            */
            Route::get('/availability', [LawyerAvailabilityController::class, 'index'])->name('availability.index');
            Route::get('/availability/create', [LawyerAvailabilityController::class, 'create'])->name('availability.create');
            Route::post('/availability', [LawyerAvailabilityController::class, 'store'])->name('availability.store');
            Route::get('/availability/{availability}/edit', [LawyerAvailabilityController::class, 'edit'])->name('availability.edit');
            Route::put('/availability/{availability}', [LawyerAvailabilityController::class, 'update'])->name('availability.update');
            Route::patch('/availability/{availability}/toggle', [LawyerAvailabilityController::class, 'toggle'])->name('availability.toggle');
            Route::delete('/availability/{availability}', [LawyerAvailabilityController::class, 'destroy'])->name('availability.destroy');

            /*
            | Phase 7: case management (requirement #8). Every {case}
            | is additionally gated by LegalCasePolicy -- this
            | middleware group only proves "some active, verified
            | lawyer", not "this lawyer's own assigned case".
            */
            Route::get('/cases', [LawyerCaseController::class, 'index'])->name('cases.index');
            Route::get('/cases/{case}', [LawyerCaseController::class, 'show'])->name('cases.show');
            Route::put('/cases/{case}', [LawyerCaseController::class, 'update'])->name('cases.update');
            Route::post('/cases/{case}/activate', [LawyerCaseController::class, 'activate'])->name('cases.activate');
            Route::post('/cases/{case}/start-progress', [LawyerCaseController::class, 'startProgress'])->name('cases.start-progress');
            Route::post('/cases/{case}/complete', [LawyerCaseController::class, 'complete'])->name('cases.complete');
            Route::post('/cases/{case}/close', [LawyerCaseController::class, 'close'])->name('cases.close');
            Route::post('/cases/{case}/cancel', [LawyerCaseController::class, 'cancel'])->name('cases.cancel');

            Route::post('/cases/{case}/documents', [CaseDocumentController::class, 'store'])->name('cases.documents.store');
            Route::get('/cases/{case}/documents/{document}', [CaseDocumentController::class, 'download'])->name('cases.documents.download');
            Route::delete('/cases/{case}/documents/{document}', [CaseDocumentController::class, 'destroy'])->name('cases.documents.destroy');

            /*
            | Phase 8: private customer <-> lawyer messaging, lawyer
            | side. No "start" route here -- a lawyer never initiates a
            | conversation (ConversationPolicy::create() only allows
            | customers); a lawyer can only view/reply to conversations
            | a customer already started. {conversation} is gated by
            | ConversationPolicy inside the controller, same pattern as
            | every other record in this group.
            */
            Route::get('/messages', [LawyerMessagingController::class, 'index'])->name('messages.index');
            Route::get('/messages/{conversation}', [LawyerMessagingController::class, 'show'])->name('messages.show');
            Route::post('/messages/{conversation}', [LawyerMessagingController::class, 'store'])->name('messages.store');

            /*
            | Phase 9: read-only reputation view. A lawyer has no write
            | access to reviews at all (cannot create, edit, or
            | moderate one -- see ReviewPolicy) and only ever sees
            | reviews scoped to their own profile.
            */
            Route::get('/reviews', [LawyerReviewController::class, 'index'])->name('reviews.index');
        });

    Route::middleware(['role:admin', 'verified'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

            // Phase 4: lawyer verification review queue.
            Route::get('/lawyer-verifications', [AdminLawyerVerificationController::class, 'index'])
                ->name('lawyer-verifications.index');
            Route::get('/lawyer-verifications/{lawyerVerification}', [AdminLawyerVerificationController::class, 'show'])
                ->name('lawyer-verifications.show');
            Route::post('/lawyer-verifications/{lawyerVerification}/approve', [AdminLawyerVerificationController::class, 'approve'])
                ->name('lawyer-verifications.approve');
            Route::post('/lawyer-verifications/{lawyerVerification}/reject', [AdminLawyerVerificationController::class, 'reject'])
                ->name('lawyer-verifications.reject');
            Route::get('/lawyer-verifications/{lawyerVerification}/document/{type}', [VerificationDocumentController::class, 'forVerification'])
                ->whereIn('type', ['cnic', 'face'])
                ->name('lawyer-verifications.document');

            /*
            | Phase 7: deliberately narrow admin case oversight
            | (requirement #10) -- view, reassign, close, cancel,
            | delete (already-cancelled + document-free cases only).
            | Never activate/start-progress/complete: that operational
            | work belongs to the assigned lawyer, enforced by
            | LegalCasePolicy, not merely omitted from this route list.
            */
            Route::get('/cases', [AdminCaseController::class, 'index'])->name('cases.index');
            Route::get('/cases/{case}', [AdminCaseController::class, 'show'])->name('cases.show');
            Route::post('/cases/{case}/assign-lawyer', [AdminCaseController::class, 'assignLawyer'])->name('cases.assign-lawyer');
            Route::post('/cases/{case}/close', [AdminCaseController::class, 'close'])->name('cases.close');
            Route::post('/cases/{case}/cancel', [AdminCaseController::class, 'cancel'])->name('cases.cancel');
            Route::delete('/cases/{case}', [AdminCaseController::class, 'destroy'])->name('cases.destroy');

            Route::get('/cases/{case}/documents/{document}', [CaseDocumentController::class, 'download'])->name('cases.documents.download');
            Route::delete('/cases/{case}/documents/{document}', [CaseDocumentController::class, 'destroy'])->name('cases.documents.destroy');

            /*
            | Phase 9, requirement #19: deliberately narrow admin
            | oversight -- inspect and reject/restore only. No edit,
            | no hard delete (see Admin\ReviewController and
            | ReviewPolicy::moderate()).
            */
            Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
            Route::get('/reviews/{review}', [AdminReviewController::class, 'show'])->name('reviews.show');
            Route::post('/reviews/{review}/reject', [AdminReviewController::class, 'reject'])->name('reviews.reject');
            Route::post('/reviews/{review}/restore', [AdminReviewController::class, 'restore'])->name('reviews.restore');
        });
});
