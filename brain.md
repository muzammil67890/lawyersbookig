# brain.md — Context for the next AI/engineer on Legal Connect

This file is a working-memory dump for whoever (human or AI) picks up
Phase 4+. Read this before touching anything. It explains *why* things
are the way they are, not just what exists — `README.md` is the
user-facing doc; this is the internal one.

## What this project actually is

A Laravel 12 / PHP 8.2+ / MySQL backend being built underneath an
**already-approved, static-feeling frontend prototype** ("Legal
Connect" — an online lawyer-marketplace concept: customers find and
book consultations with lawyers, lawyers manage a practice, admins run
the platform). The prototype (`docs/design-source.html`, copied into
`public/css/legal-connect.css` + `public/js/legal-connect.js` +
`resources/views/pages/legal-connect.blade.php`) is a single-page
demo that fakes its entire backend with `localStorage`. **The whole
multi-phase project is the process of replacing that fake backend with
a real one, one layer at a time, without ever touching the approved
UI.** If you are tempted to "improve" the frontend — don't. That is
explicitly out of scope in every phase seen so far.

## Phase history

- **Phase 1**: Took the raw HTML/CSS/JS prototype and wrapped it in a
  real Laravel 12 skeleton (routes, one controller, one Blade view
  that includes the untouched CSS/JS, MySQL config, a minimal `users`
  migration with a `role` column). No auth, no other tables. The
  Phase 1 author's own audit notes (bottom of `README.md`, above the
  Phase 2 section) are worth reading — they found and fixed a
  double-loaded-script bug and documented that PHP/Composer/MySQL
  weren't available in *that* sandbox either.
- **Phase 2** (this pass): Built the full MySQL domain schema — every
  table a marketplace like this needs (lawyer profiles, verification,
  services/specializations, availability, appointments, cases +
  documents, conversations + messages, notifications, reviews, AI
  matching-assistant conversations) — plus matching Eloquent models,
  enums, factories, seeders and a relationship/constraint test suite.
  **No authentication, no booking workflow, no chat delivery, no AI
  engine, no file-upload handling, no dashboards wired to real data.**
  Just the schema + models those things will be built on. See the
  "Phase 2 — Database / domain foundation" section of `README.md` for
  the full table list and the reasoning behind specific design
  choices (denormalized `verification_status`, no JSON for
  relational data, `LegalCase` instead of `Case`, etc.) — don't
  duplicate that here, go read it.
- **Phase 3** (this pass): Built the real authentication/authorization
  layer on top of Phase 2's schema — Laravel session auth (register,
  login, logout, email verification, password reset), `role` +
  `active` middleware, rate limiting, and three minimal
  role-protected placeholder dashboards. The existing prototype's
  login/register UI was **connected**, not replaced: the exact same
  `.auth-page` markup/CSS now lives in real Blade views
  (`resources/views/auth/*`) that POST to real routes, and the header
  nav + logout button in the big SPA page were repointed at those
  routes with a handful of surgical JS edits — `getUser()` now reads
  real server auth instead of localStorage, `performLogout()` submits
  a real form, three dead demo functions were removed, and 6
  `navigate('login')` calls now redirect to the real `/login`. That is
  the *entire* JS diff — do not assume anything else in
  `legal-connect.js` changed, it didn't (verified by full diff +
  `node -c`). **No lawyer onboarding/CNIC upload, no admin approval
  UI, no booking, no chat, no AI, no payments.** See the "Phase 3 —
  Authentication & authorization" section of `README.md` for the full
  route table, rate-limit table, and security-audit writeup.

## The one thing every phase must keep in mind

**Do not redesign the UI.** Every phase prompt so far has said this in
different words. Preserve `docs/design-source.html`'s visual language,
layout, JS interaction model, and class names exactly. Backend phases
should feel invisible to the frontend until the phase that explicitly
says "now wire the frontend to the real backend."

## Sandbox constraints you will probably also hit

Whatever sandbox is building the next phase likely also has:

- **No Composer/Packagist access.** `composer install` cannot
  actually run. You *can* usually `apt-get install php8.x-cli` and
  similar extensions (archive.ubuntu.com is typically allowlisted) to
  at least get a PHP interpreter for `php -l` syntax-checking, but you
  will not have `vendor/` or be able to run `php artisan` commands for
  real, or reach a live MySQL server.
- Because of this, **treat "did I actually run migrate/test" as a
  hard honesty check.** Don't claim commands ran if they didn't. Lint
  every file you touch with `php -l`, review relationships/migrations
  by hand very carefully (they will not be caught by a real test run),
  and say explicitly in your final report what could and couldn't be
  verified in-sandbox. The end user runs the real first migration on
  their own XAMPP/MySQL setup.
- If a future sandbox *does* have Packagist/MySQL access, actually run
  `composer install && php artisan migrate:fresh --seed && php artisan
  test` for real and say so plainly — don't keep the cautious
  "couldn't verify" language if you genuinely could that time.

## Domain vocabulary (pulled from the frontend prototype — keep consistent)

The prototype's demo data (`public/js/legal-connect.js`, roughly lines
1-40) is the source of truth for naming conventions used throughout
the schema:

- Roles: `customer`, `lawyer`, `admin` (lowercase, exactly these
  strings — matches `UserRole` enum values).
- Ten legal services: Criminal Law, Family Law, Divorce, Civil Law,
  Property Law, Corporate Law, Immigration, Employment Law, Tax Law,
  Affidavit (seeded verbatim by `ServiceSeeder`).
- Appointment statuses in the demo: Pending, Confirmed, Completed,
  Cancelled, Rejected (the schema adds `Rescheduled` per the Phase 2
  spec; the enum values are lowercased versions of these).
- Consultation types: Online, Office, Phone.
- Lawyer verification/status badges: Verified, Pending, Rejected,
  Suspended.
- Case statuses seen in the demo: Active, Pending, Completed, Closed
  (schema also has `in_progress` per spec).
- Currency: Pakistani Rupees, no currency column exists yet (all fee
  fields are plain `decimal(10,2)`) — add one only if a future phase
  needs multi-currency, don't invent it speculatively.

## Where things live

- `app/Enums/` — all backed string enums (role/status vocab).
- `app/Models/` — 14 domain models + `User`. Read the PHPDoc-ish inline
  comments in `LawyerProfile`, `User`, and `LegalCase` — they explain
  non-obvious decisions (denormalized status, hasManyThrough usage,
  reserved-word class naming).
- `database/migrations/` — timestamped `2026_09_22_1000XX_*`, in FK
  dependency order. If you add a table, keep dependency order in mind
  and give it a timestamp after `100017`.
- `database/factories/`, `database/seeders/` — one factory per model;
  `DatabaseSeeder` calls `ServiceSeeder → SpecializationSeeder →
  DemoAccountSeeder → DemoActivitySeeder` in that order. All seeders
  are idempotent (`updateOrCreate`/existence checks) — safe to re-run.
- `tests/Feature/DomainSchemaTest.php` — the Phase 2 relationship/
  constraint suite. Add to this rather than replacing it as new phases
  add behavior; the 14 numbered tests map directly to the Phase 2 spec
  checklist.
- `app/Http/Middleware/EnsureUserHasRole.php`,
  `EnsureAccountIsActive.php` — the `role:*` / `active` authorization
  gates (Phase 3), aliased in `bootstrap/app.php`.
- `app/Http/Requests/Auth/`, `app/Http/Controllers/Auth/` — real
  registration/login/logout/verification/password-reset (Phase 3).
  `LoginRequest::authenticate()` is the one place credential-checking +
  rate limiting + account-status enforcement happens; don't duplicate
  that logic elsewhere.
- `routes/auth.php` — every auth route, `require`'d from `routes/web.php`.
- `resources/views/auth/*`, `resources/views/dashboard/*` — real pages
  on `layouts/minimal.blade.php` (deliberately *not*
  `layouts/app.blade.php`, which loads the SPA's demo JS bundle that
  assumes `legal-connect.blade.php`'s DOM exists).
- `tests/Feature/Auth/` — the Phase 3 test suite (Registration, Login,
  Logout, Authorization, EmailVerification, PasswordReset, Security).

## Suggested order for Phase 4

Per README's "Phase 4 readiness notes": lawyer onboarding first
(`RegisteredUserController` already creates a bare `role = lawyer`
user with no `LawyerProfile`/`LawyerVerification` row — Phase 4 should
create those, plus the CNIC/face-image upload, the first time a lawyer
completes their profile), then admin lawyer-approval UI (update
`LawyerVerification.status` + `reviewed_by`/`reviewed_at`, sync
`LawyerProfile.verification_status` — the `role:admin` route
group/middleware already exists at `/admin/*`), then public lawyer
search (read path only, `LawyerProfile::searchable()` scope already
exists), then booking, then messaging, then documents, then
notifications, then reviews, then the AI matching assistant last (it
depends on services/specializations/lawyers all being real and
searchable first). The three placeholder dashboards
(`resources/views/dashboard/*`) are natural homes for the real
per-role application UI as each of these lands — swap their content,
keep the routes/controllers/middleware as they are.

## Phase 4 (done): lawyer onboarding, verification, admin approval

- `app/Http/Controllers/Lawyer/OnboardingController.php` — `create()`/
  `store()`, one-time-only (redirects to dashboard if `lawyerProfile`
  already exists). Uploads happen *before* `DB::transaction()`; on any
  failure inside the transaction, the already-written files are deleted
  in a `catch` block via `LawyerFileUploadService::delete()` so nothing
  orphaned is left behind.
- `app/Services/LawyerFileUploadService.php` — the only place that turns
  an `UploadedFile` into a stored path. UUID filename, MIME-derived
  extension, client filename never touched. `store($file, $directory,
  $disk)`; `$directory`/`$disk` are always hardcoded call-site literals,
  never built from request data.
- `app/Policies/LawyerVerificationPolicy.php` — `viewAny`/`view`/`review`.
  This is the *only* place "who can see this lawyer's CNIC/face image" or
  "who can approve/reject" is decided. Don't re-implement the check
  inline in a controller; call `$this->authorize()`.
- `app/Http/Controllers/VerificationDocumentController.php` — serves
  CNIC/face images from the `local` (private) disk via
  `Storage::disk('local')->response($path)`. Two entry points
  (`own()` for a lawyer, `forVerification()` for admin) both funnel
  through `respond()` which calls the policy. Never add a public route
  or symlink for `lawyers/verification/*`.
- `app/Http/Controllers/Admin/LawyerVerificationController.php` —
  `index`/`show`/`approve`/`reject`. Approve/reject both: run inside
  `DB::transaction()`, set `reviewed_by`/`reviewed_at`, and update
  `LawyerProfile.verification_status` to match
  `LawyerVerification.status` (the profile column is a denormalized
  copy — see the model comment — keep both in sync, don't touch other
  profile columns from here).
- `App\Models\LawyerProfile::isEligible()` — the instance-level twin of
  `scopeSearchable()`. Any future phase (search, booking) checking "is
  this lawyer bookable" should call one of these two, not re-derive
  `is_active && is_public && verification_status === approved`
  somewhere new.
- Notifications are **database-only** (`App\Notifications\
  LawyerApplication{Submitted,Approved,Rejected}`) — no mail channel/
  template exists yet for these; add one deliberately later if a real
  "email the lawyer" requirement shows up, don't assume `via()` should
  include `mail` just because `MAIL_MAILER=log` is already configured.
- The one migration this phase needed:
  `2026_09_22_100018_add_title_to_lawyer_profiles_table.php` (nullable
  `title` string). Everything else onboarding collects already existed
  on `lawyer_profiles`/`lawyer_verifications` from Phase 2 — check the
  existing schema before adding a column for anything that looks like
  it might need one.
- `vendor/` still doesn't exist in this sandbox and `repo.packagist.org`
  is not on the network allowlist — `composer install` fails with a
  clean `HTTP 403`. Every Phase 4 test file is syntax-checked
  (`php -l`) only; nobody has run them with PHPUnit yet. Do this for
  real the first time this project runs somewhere with Packagist
  access, before trusting any of the new test files.

## Suggested order for Phase 5

Public lawyer search (read path only, `LawyerProfile::searchable()`/
`isEligible()` already exist and are now actually reachable end-to-end
since Phase 4 lets a lawyer become `approved`), then booking, then
messaging, then documents, then reviews, then the AI matching assistant
last (depends on services/specializations/lawyers all being real and
searchable first).

## Phase 5 Half 1 (done): lawyer directory, search, filters, public profile

- `app/Http/Controllers/LawyerDirectoryController.php` — `index()`
  (directory) and `show()` (public profile). Both are built
  exclusively on `LawyerProfile::query()->searchable()` — there is no
  second eligibility check anywhere in this class, and it's the only
  controller that should ever serve public lawyer listings/profiles.
  `show()` uses `searchable()->findOrFail($id)` specifically so a
  pending/rejected/suspended/not-public lawyer 404s exactly the same
  as a nonexistent ID — don't add a `abort(403)` or any other branch
  that would let a client tell the difference.
- Sort is a hardcoded allow-list (`SORTS` const in the controller) —
  `name`/`fee`/`experience`/`recent` map to real columns + direction.
  Never add a new sort option by just reading whatever column name the
  client sends; add a new key to `SORTS` instead.
- New `LawyerProfile` scopes added this phase: `minFee`,
  `minExperience`, `keyword` (search), `offeringService`,
  `offeringSpecialization` — alongside the existing `scopeSearchable`,
  `scopeInCity`, `scopeMaxFee`, `isEligible()` from Phase 4. Any future
  phase adding another lawyer-directory filter should add a scope here
  rather than inlining a `where()` in a controller.
- `resources/views/layouts/site.blade.php` is a **second, real** site
  chrome (header/footer) alongside the Phase 1 SPA's own header/footer
  markup in `pages/legal-connect.blade.php`. It intentionally does
  *not* include `legal-connect.js` — that script's `navigate()`/
  `routeFromHash()` assume the SPA's `#view-*` sections exist in the
  DOM and will throw on any page that doesn't have them. If a future
  phase wants another "real" (non-hash-routed) public page with the
  same header/footer, extend `layouts.site`, not `layouts.app`.
- The Phase 1 demo directory/profile (`public/js/legal-connect.js`'s
  `filterLawyers()`/`renderProfile()` against `localStorage`) was
  deliberately left completely untouched. `/lawyers` and
  `/lawyers/{id}` are new, additional real routes, not a replacement —
  wiring the SPA's own "Find Lawyers" nav link/buttons to the real
  routes (instead of `navigate('lawyers')`) is a reasonable follow-up
  but was not done here to avoid touching the demo's booking flow,
  which reads the same in-memory lawyer array by numeric ID and is out
  of scope until the booking phase exists for real.
- Still no `vendor/` in this sandbox, still `HTTP 403` from
  `repo.packagist.org` — every Phase 5 test file is `php -l`
  syntax-checked only, never run with PHPUnit. Do this for real before
  trusting `tests/Feature/Lawyers/*`.

## Suggested order for Phase 5 Half 2 / Phase 6

Wire the Phase 1 SPA's "Find Lawyers"/profile links to the new real
`/lawyers` routes (optional polish), then real appointment booking
(`Appointment` model already exists from Phase 2 — a customer booking
a slot with an eligible `LawyerProfile`, a lawyer accepting/rejecting/
rescheduling it, replacing the demo's `localStorage` booking flow),
then messaging, then documents, then reviews (there's already a
`Review` model/relation — real reviews should feed into a real rating
shown on the directory/profile once they exist, rather than adding a
fake rating column now), then the AI matching assistant last.

## Phase 5 Half 2 (done): deterministic lawyer matching foundation

**Not AI.** Deterministic weighted scoring; Phase 11 (AI Assistant) is
meant to call it. Full description: README "Phase 5 Half 2".

- `app/Services/LawyerMatchingService.php` — the only matching logic.
  Pipeline: `LawyerProfile::searchable()` → strict filters (reusing
  `minFee`/`maxFee`/`minExperience`) → topical gate (service / spec /
  problem must match at least one) or non-topical gate → PHP scoring on
  the small eager-loaded candidate set → total-order sort.
- `app/Services/Matching/` — `MatchCriteria` (self-validating input; null
  = not requested), `LawyerMatch`, `MatchResultSet`,
  `ProblemInterpretation`, `ProblemKeywordResolver`.
- `config/lawyer_matching.php` — the ONLY place weights/tiers/tolerance/
  limits/stopwords/synonym table live. Don't put numbers in the service.
  Score normalises over *supplied* criteria only.
- `GET /lawyers/match` (`LawyerMatchController`, `LawyerMatchRequest`,
  `LawyerMatchResource`, `resources/views/lawyers/match.blade.php`).
  JSON output goes ONLY through `LawyerMatchResource` (allow-list). The
  service also selects public columns only.
- **Eligibility change:** `scopeSearchable()`/`isEligible()` now also
  require `users.status = active`. This hides suspended-account lawyers
  from the directory/profile as well. Keep the single definition.
- Reasons must only describe conditions that held; unmet criteria go in
  `gaps`; `matches.*` is true/false/null(not requested). Budget "near"
  is `matches.budget = false` with `fit = near`.
- Problem reading is name-match + editable synonym table + weak profile
  text. Specialization names match as whole phrases only (words like
  "disputes" are shared across unrelated specs). Descriptions unused.
- No geography: city is exact (case-insensitive). `location`/`address`
  columns are unused. Availability = recurring available slot on a
  weekday (one-off slots ignored).
- Directory controller was intentionally NOT refactored onto the matcher.

### Test-running lesson (important)

This phase finally ran PHPUnit for real (Packagist still 403, so vendor
was built from GitHub tags with `composer.local.json` path repos — a
local-only file, never shipped). Doing so exposed 4 latent bugs from
earlier phases (max_fee-only validation, missing `autoload-dev`, wrong
login-redirect test expectations, a fragile DB-facade mock test) — fixed
and listed in the README. Lesson: earlier "php -l only" phases hid real
failures; run the suite whenever any vendor route exists.
`storage/framework/*` and `bootstrap/cache` must exist for tests.

### Suggested next steps

Real appointment booking (uses `LawyerAvailability`), messaging,
documents, reviews (feed a real rating, no fake column), then Phase 11
AI Assistant calling `LawyerMatchingService::match()`.

## Phase 6 Part 1 (done): lawyer availability + appointment booking foundation

**Not Part 2.** No accept/reject, reschedule, cancellation, notifications,
calendar UI, chat, payments, AI, or case management here — foundation only.

- `app/Services/AppointmentAvailabilityService.php` — the only place that
  knows about availability windows, conflict detection, slot generation,
  and the transactional `book()`. Part 2 should extend this, not duplicate
  it. `windowsForDate()` treats any `specific_date` row(s) for a date as an
  exclusive override of the recurring `day_of_week` rows for that date.
- `config/appointments.php` — `default_duration_minutes` (30, fixed — no
  per-service duration yet) and `booking_window_days` (60). The only place
  these numbers live.
- No migrations needed — `appointments`/`lawyer_availabilities` schema from
  Phase 2 already had everything (day_of_week, specific_date, start/end
  time, is_recurring, is_available, reference, status, etc.).
- Eligibility: reused `LawyerProfile::scopeSearchable()`/`isEligible()`
  as-is. Every lawyer id anywhere in this phase (booking create/slots/store,
  and nowhere for availability since that's scoped to the authed lawyer
  regardless of eligibility — a lawyer can configure availability before
  being approved) goes through `searchable()`.
- Availability: exactly one of `day_of_week`/`specific_date` per row
  (enforced in the Form Request, not just UI); overlap on the same
  day/date rejected via a half-open interval check; back-to-back allowed.
  Ownership: `LawyerAvailabilityPolicy` + always scoped off
  `$user->lawyerProfile`, never a request id.
- Booking: `customer_id` always `auth()->user()`, never a field.
  `{lawyer}` route int → re-resolved via `searchable()->findOrFail()` in
  the controller AND independently in `StoreAppointmentRequest::withValidator()`
  (so an ineligible lawyer id fails as a validation error — redirect with
  `errors->lawyer` — on POST, not a 404; the GET create/slots endpoints
  have no Form Request so those genuinely 404 via `findOrFail()`). Service
  must belong to `$lawyer->services()->active()`, checked twice (Form
  Request + controller re-fetch via the relation, not `Service::find()`).
  Status always `PENDING` on create, hardcoded, never client-set.
- Conflict: `hasConflict()` — pure SQL half-open overlap
  (`start_time < new.end AND end_time > new.start`), scoped to
  lawyer+date, excludes cancelled/rejected. Exact/partial/containing
  overlaps rejected; back-to-back and different-lawyer allowed.
- Race protection: `DB::transaction()` + `lockForUpdate()` on the lawyer's
  appointments for that date, re-checking availability+conflict inside the
  lock, right before insert. **Caveat documented in README**: SQLite (the
  test DB) doesn't really do row locking, so this only meaningfully
  protects concurrent requests on the MySQL the app actually runs on —
  tests check the logical rules, not real concurrency.
- Routes: `/lawyer/availability*` (role:lawyer) and
  `/customer/appointments*` + `/customer/lawyers/{lawyer}/{book,slots}`
  (role:customer). Both purely additive in `routes/web.php` — nothing
  existing was touched or renamed.
- Views: `lawyer/availability/{index,create,edit}` and
  `customer/appointments/{create,index,show}`, all built from the
  existing `.panel`/`.field`/`.data-table`/`.btn`/`layouts.minimal`
  system — no new CSS file, no React/Vue. Booking form's slot dropdown is
  populated by a small inline vanilla-JS `fetch()` against the slots JSON
  endpoint (the actual source of truth `store()` re-derives independently
  — the client can't fake a slot into being valid). Added one CTA button
  to `lawyers/show.blade.php`'s previously-unused `.profile-actions` slot,
  and one new panel + one copy tweak to `dashboard/lawyer.blade.php`.

### Test-running lesson (worse than the Packagist-403 phases)

This sandbox has **no PHP binary at all** — not a Packagist/network
restriction, `php`/`composer` themselves are simply not installed. So
unlike Phase 5 Half 2 (which got a real PHPUnit run once vendor/ was
hand-built from GitHub tags), this phase could not run PHPUnit, and
could not even run `php -l`. Verification instead: a Python
comment-aware bracket/paren/quote balance checker over every new PHP
file (all balanced), manual namespace/class-vs-path checks, Blade
directive pairing checks, and cross-reading every model
relation/scope/enum/factory-state/middleware-behavior/route-name this
phase's code and tests depend on against the actual source rather than
assuming it from memory or from the README. Do NOT treat the new test
files as proven passing — run `composer install && php artisan test`
for real before relying on them. If a future session has a working PHP
environment, that is the very first thing to do before adding Part 2.

## Phase 6 Part 2 — Complete Appointment Management

### Lifecycle = one enum, one service, nothing else touches status

`App\Enums\AppointmentAction` (new) is the single transition table —
each case (`confirm`/`reject`/`cancel`/`complete`/`reschedule`) knows
its target status, which roles may take it, and which statuses it may
start from **per role** (a lawyer's `cancel` only starts from
`confirmed`; a customer's starts from `pending` or `confirmed` — the
lawyer only *answers* a pending request via confirm/reject). Read this
enum's docblock table before touching lifecycle logic again; don't
re-derive the rules from scratch or from README prose.

`App\Services\AppointmentStatusService` (new) is the **only** place
`Appointment::$status` is ever assigned. Pattern for every action:
`Gate::forUser($actor)->authorize($action->value, $appointment)` →
`DB::transaction` → (if the action claims calendar time)
`AppointmentAvailabilityService::lockLawyerCalendar($lawyerProfileId)`
→ `Appointment::lockForUpdate()->firstOrFail()` (re-read the
committed row, never trust the one passed in) → check
`AppointmentAction::allowedFrom($actor->role)` +
future/started-time rules → apply → notify *after* commit. If you add
a sixth action, add it to this enum and this service — do not let a
controller set `->status =` directly, ever, for any reason.

### Reschedule = new row, not an in-place edit

`reschedule()` sets the OLD row to `rescheduled` (frees its slot
immediately — see `AppointmentStatus::slotBlocking()`, which excludes
`rescheduled`/`rejected`/`cancelled` and includes
`pending`/`confirmed`/`completed`) and creates a NEW `Appointment` with
`rescheduled_from_id` pointing back. Customer reschedule → new row is
`pending` (needs re-confirmation); lawyer reschedule → new row is
`confirmed` directly. `rescheduledFrom()`/`rescheduledTo()` relations
on the model let both directions be walked from either row. This was
a deliberate design choice over editing in place — it keeps full
history and lets the exact same conflict/availability check
(`assertSlotAvailable()`, extracted from `book()`) be reused
unmodified for booking, confirming, and rescheduling alike, always
with `$excludeAppointmentId` set to the row being moved so a slot
never "conflicts" with the appointment currently occupying it.

### Locking, extended

`AppointmentAvailabilityService::lockLawyerCalendar()` (renamed/
extracted from what was inline in Part 1's `book()`) now takes an
`int $lawyerProfileId`, not a model, and is called by `book()`,
`confirm()`, and `reschedule()` — anything that claims a slot.
`AppointmentStatusService::transition()` additionally always
`lockForUpdate()`s the appointment row itself before checking its
status, for every action (not just calendar-claiming ones), so two
simultaneous actions on the *same appointment* (e.g. customer cancels
while lawyer confirms) can't both win. Same SQLite-vs-MySQL caveat as
Part 1: meaningful on MySQL, a no-op on the SQLite test DB.

### Files (Part 2)

New: `app/Enums/{AppointmentAction,AppointmentEvent}.php`,
`app/Services/AppointmentStatusService.php`,
`app/Notifications/AppointmentNotification.php` (database channel
only, minimal payload — event/message/id/reference/date/time, never
reason/contact/documents — see README "Notifications"),
`app/Http/Controllers/Lawyer/AppointmentController.php`,
`app/Http/Requests/Appointments/{Cancel,Reschedule}AppointmentRequest.php`,
`resources/views/lawyer/appointments/{index,show,reschedule}.blade.php`,
`resources/views/customer/appointments/reschedule.blade.php`,
`resources/views/appointments/_reschedule-fields.blade.php` (shared
slot-picker partial — reuses Part 1's `create.blade.php` fetch()
pattern verbatim, don't build a third copy of this JS),
`tests/Feature/Appointments/LifecycleTest.php`.

Extended (not replaced): `app/Models/Appointment.php` (added
`FILTER_UPCOMING`/`filterValues()`/`scopeFilter()`,
`startsAt()`/`endsAt()`/`hasStarted()`, `rescheduledTo()` relation —
Part 1's fields/relations/casts untouched),
`app/Services/AppointmentAvailabilityService.php` (`book()` now calls
extracted `lockLawyerCalendar()` + `assertSlotAvailable()` instead of
inlining them; `hasConflict()` reads its status list from
`AppointmentStatus::slotBlockingValues()` instead of a hardcoded
`whereNotIn` — same three statuses; `availableSlots()` gained one
trailing optional `$excludeAppointmentId = null` param — no existing
call site broke), `app/Policies/AppointmentPolicy.php` (added
`confirm`/`reject`/`cancel`/`complete`/`reschedule` abilities, all
routed through one private `mayPerform()` that checks
`AppointmentAction::allowedRoles()` + the existing `view()`
ownership check — `view`/`create` untouched),
`Customer/AppointmentController.php` (`store()` gained one line:
`$this->status->notifyBooked($appointment)`; everything else new),
`routes/web.php` (additive only), both dashboard views (one new panel
each, one copy fix each).

Admin appointment oversight was deliberately NOT added — not part of
the existing product design; don't add it without an explicit future
requirement (see `AppointmentPolicy` docblock).

### Test-running: this sandbox has `php`, still no `composer`/PHPUnit

Improvement over Phase 6 Part 1's sandbox (which had no `php` binary
at all): `php -l` actually ran this time, against every PHP file,
clean. But `repo.packagist.org` AND `getcomposer.org` are both
`host_not_allowed` here, so no `vendor/`, no PHPUnit, still. Same
verification discipline as documented in README: read the actual
source of everything new code calls rather than trust memory/prior
docs, cross-check every route name against the real `routes/web.php`,
check every Blade directive pair. **Run
`composer install && php artisan test` before trusting the new tests
as green** — if a future session finally has full Composer access,
do that FIRST, fix whatever it finds, then continue from Phase 7.

### Suggested next steps (Phase 7+)

Appointment lifecycle is now complete end-to-end (book → confirm/
reject → cancel/reschedule → complete). Natural next slice per the
earlier "suggested order" note: case management tied to a completed/
confirmed appointment, then client messaging, then documents/reviews,
then eventually the Phase 11 AI assistant (which should call the
existing `LawyerMatchingService`/`AppointmentAvailabilityService`
rather than duplicate their logic, same principle this phase followed
throughout).

## Phase 7 (done): Case Management + Secure Document Management

### Lifecycle = one enum, one service, nothing else touches status — same shape as Phase 6 Part 2

`App\Enums\CaseAction` (new) is the single transition table for cases —
`activate`/`start_progress`/`complete`/`close`/`cancel`, each knowing
its target status, which roles may take it, and which statuses it may
start from **per role** (cancellation reach differs sharply by role —
see the table in its docblock/README before touching it). Reassigning
to a different lawyer is deliberately **not** in this enum — it never
touches `status` — see `LegalCasePolicy::assignLawyer()` +
`LegalCaseStatusService::reassignLawyer()` instead.

`App\Services\LegalCaseStatusService` (new) is the **only** place
`LegalCase::$status` or `$lawyer_profile_id` is ever assigned. Same
pattern as `AppointmentStatusService`:
`Gate::forUser($actor)->authorize($action->value, $case)` →
`DB::transaction` → `LegalCase::lockForUpdate()->firstOrFail()`
(re-read the committed row) → check `CaseAction::allowedFrom($actor->role)`
→ apply → notify *after* commit. If you add a sixth action or change
who can cancel from where, this enum + this service are the only two
places to touch — do not let a controller set `->status =` directly.

### Only one schema change, and it was additive

`CaseStatus` gained `CANCELLED` (+ an `isTerminal()` helper). Nothing
else in the Phase 2 schema needed to change — `lawyer_profile_id` on
`cases` already existed and *is* the assignment mechanism (no new
column/table for it), and `case_documents.category` was already a
free string that happened to already only ever contain values now
formalized as `App\Enums\CaseDocumentCategory`. Don't add a second
status/category enum or a separate "case_assignments" table later
without checking this first — the hooks are already there.

### Document security = reuse Phase 4's upload service, add the missing pieces

`LawyerFileUploadService::store()/delete()` (Phase 4, built for CNIC/
face verification uploads) is reused completely unchanged for case
documents — it was already generic (UUID filename, private disk,
directory param), not lawyer-specific. What Phase 7 added on top,
all in `CaseDocumentController`:
- `/cases/{case}/documents/{document}` route shape, with an explicit
  `$document->case_id === $case->id` check *before* the policy check
  (a document id from a different case 404s the same as a nonexistent
  one — never a distinguishable "wrong case" response).
- `Storage::download()` (forced attachment) for every download, never
  an inline response.
- Orphan-safe upload (delete the just-written file if the DB insert
  in the same transaction fails) and orphan-safe delete (DB row goes
  first, physical-file removal failure is `report()`-ed, never
  surfaced as a failed delete to the user).
- `CaseDocument::$hidden = ['stored_path']` so the private path can
  never leak through an accidental serialization.

`CaseDocumentPolicy` deliberately delegates view/download to
`Gate::forUser($user)->allows('viewDocuments', $document->case)`
instead of re-deriving case ownership — if `LegalCasePolicy::view()`
ever changes, document access follows automatically. `delete()` is the
one place document access is *narrower* than viewing: uploader-only or
admin, never "the other party on the same case even though they can
see it."

### Admin oversight is deliberately narrow — read `LegalCasePolicy`'s docblock before adding an admin ability

An admin can: view any case, reassign it, close a *completed* case,
cancel a case at any non-terminal status, and delete an already-
cancelled + document-free case. An admin **cannot**: activate,
progress, or complete a case (that's the assigned lawyer's operational
work — `CaseAction::allowedRoles()` simply never includes admin for
those three), or upload a document (`uploadDocuments()` explicitly
excludes admin even though `view()` would otherwise allow it). If a
future phase needs to widen admin's reach, do it explicitly in
`LegalCasePolicy`/`CaseAction`, not by loosening `view()`.

### Files (Phase 7)

New: `app/Enums/{CaseAction,CaseEvent,CaseDocumentCategory}.php`,
`app/Services/LegalCaseStatusService.php`,
`app/Notifications/CaseNotification.php` (database channel only,
minimal payload — same shape as `AppointmentNotification`),
`app/Policies/{LegalCasePolicy,CaseDocumentPolicy}.php`,
`config/cases.php` (upload MIME allow-list + size cap),
`app/Http/Requests/Customer/StoreCaseRequest.php`,
`app/Http/Requests/Cases/{UpdateCaseRequest,CancelCaseRequest,StoreCaseDocumentRequest}.php`,
`app/Http/Requests/Admin/AssignCaseLawyerRequest.php`,
`app/Http/Controllers/Customer/CaseController.php`,
`app/Http/Controllers/Lawyer/CaseController.php`,
`app/Http/Controllers/Admin/CaseController.php`,
`app/Http/Controllers/CaseDocumentController.php` (shared across all
three route prefixes — same pattern as Phase 4's
`VerificationDocumentController`),
`resources/views/{customer,lawyer,admin}/cases/*.blade.php`,
`tests/Feature/Cases/{LifecycleTest,DocumentTest}.php`.

Extended (not replaced): `app/Enums/CaseStatus.php` (added
`CANCELLED`/`isTerminal()` only), `app/Models/LegalCase.php` (added
`scopeForCustomer()`/`scopeForLawyerUser()` only),
`app/Models/CaseDocument.php` (added `$hidden` + a `category` enum
cast only), `routes/web.php` (additive registrations inside the
existing three role groups), all three dashboard views (one new
"Cases" panel each, placeholder copy touched up).

### Test-running: this sandbox regressed to *no* `php` binary at all

Worse than Phase 6 Part 2's sandbox (which at least had `php` for
`php -l`, even with no Composer). Here: no `vendor/`, no `composer`,
and `php -v`/`apt-get install php-cli` both failed outright — the
network egress proxy blocks package hosts entirely. Verification fell
back to a scripted brace/paren-balance sweep plus manually
cross-reading the real source of every method/route/relation this
phase's code calls (see README's "Testing" subsection under Phase 7
for the full list of what was and wasn't possible). **If a future
session gets real `php`/Composer access, running
`composer install && php artisan test` should be the very first
thing done, before adding anything past Phase 7** — this phase's test
suite is written to the same standard as Phase 6 Part 2's but has
literally never executed.

### Suggested next steps (Phase 8+)

Case management + secure documents are now complete end-to-end
(open → activate → progress → complete → close, with cancellation and
admin reassignment/oversight throughout). The natural next slice per
the original product shape: client↔lawyer messaging tied to a case
(the `conversations`/`messages` tables already exist from Phase 2 and
are still untouched), then reviews, then eventually the Phase 11 AI
assistant — which per the standing principle every phase has followed,
should call the existing `LawyerMatchingService`/
`AppointmentAvailabilityService`/`LegalCaseStatusService` rather than
duplicate any of their logic.

## Phase 8 (done): Private Client ↔ Lawyer Messaging

### Ownership defined once, on the model, not three times

`Conversation::isParticipant(User $user)` and
`Conversation::otherPartyFor(User $user)` (new, on the model) are the
single definition of "is this user a party to this conversation."
`ConversationPolicy::view()`, `MessagingService` (creation reuse,
`sendMessage()`, `markConversationRead()`), and `MessagePolicy`
(delegating through `Gate::forUser()`) all call the model method
rather than each re-deriving `customer_id === $user->id` /
`lawyerProfile->id === ...` separately. If you ever need to change who
counts as a participant (e.g. a future "case-scoped" conversation
model), this one method is the only place to touch.

### Conversation creation is `SELECT ... FOR UPDATE` first, `INSERT` second, catch-and-reselect third

`MessagingService::startOrResumeConversation()` doesn't rely on the
unique DB constraint alone to prevent duplicates — it locks for the
existing row first (handles the common case cheaply), and only falls
back to catching the constraint violation `QueryException` for the
genuine race (two requests both missing the lock-read because neither
row existed yet). Same three-layer belt-and-braces shape as Phase 6's
appointment-slot double-booking protection — if you're adding another
"only one of X per (A, B)" flow later, this is the pattern to copy.

### Eligibility is re-resolved from `LawyerProfile::searchable()`, not cached on the conversation

A conversation, once created, stays viewable/sendable by both parties
forever (there's no re-check of lawyer eligibility on `view()` or
`sendMessage()` — only on *creating* a new one). This is deliberate:
if a lawyer's profile later gets suspended, existing customers who
already have a conversation with them should still be able to read
their own message history; they just can't start a *new* one with
that lawyer, and neither can anyone else. Don't "fix" this by adding
an eligibility re-check to `ConversationPolicy::view()` — that would
retroactively lock customers out of their own conversation history,
which nothing in the spec asked for.

### Notification payload is intentionally thin

`NewMessageNotification`'s `toArray()` carries the sender's name and
the conversation id — **never the message body**. This was a
deliberate choice, not an oversight: notification data sits on a
lower-trust surface (broader storage/serialization/future-channel
surface area) than the conversation view itself, which is
authorization-protected end to end. If a future phase adds a
notification-center UI that shows message previews, pull the preview
from the conversation (authorization-checked) at render time — don't
widen this payload to include the body.

### Read/unread reuses Phase 2's `read_at` column exactly as designed, no schema change needed

Phase 2 had already put a nullable `read_at` on `messages` for exactly
this purpose (and an unused `attachment_path` for a future phase, left
untouched — no file-attachment UI was in scope here). This phase only
added two indexes (`conversations.lawyer_profile_id`,
`conversations.last_message_at`, `messages(conversation_id, read_at)`)
for query patterns the schema didn't have until now.

### `.chat-view` CSS was already built for this exact screen, by Phase 1

The Phase 1 prototype's demo chat page (`.chat-view`/
`.conversation-list`/`.conversation`/`.chat-pane`/`.messages`/
`.message`/`.message.mine`/`.chat-compose`/`.unread`) was sitting
unused in `legal-connect.css` since Phase 1, described in earlier
phases' dashboard placeholder copy as "still the existing frontend
demo." This phase is what finally wires it to real, authorized,
paginated data — no new CSS was needed for the chat screen itself. The
only near-miss: the existing `.hidden` utility class is `display:none`
globally (not a proper visually-hidden-but-screen-reader-visible
pattern), so an accessible label on the composer input used
`aria-label` instead of `<label class="hidden">` — don't reach for
`.hidden` for that purpose elsewhere either.

### Files (Phase 8)

New: `app/Services/MessagingService.php`,
`app/Policies/{ConversationPolicy,MessagePolicy}.php`,
`app/Notifications/NewMessageNotification.php`,
`app/Http/Requests/Messaging/StoreMessageRequest.php`,
`app/Http/Controllers/{Customer,Lawyer}/MessagingController.php`,
`database/migrations/2026_09_29_100000_add_messaging_performance_indexes.php`,
`resources/views/{customer,lawyer}/messages/{index,show}.blade.php`,
`tests/Feature/Messaging/*.php` (6 files). Extended: `Conversation`
model (3 new methods, nothing removed), `routes/web.php` (additive
only), both `DashboardController`s (+unread badge), both dashboard
views (+Messages panel), `lawyers/show.blade.php` (+Message button).
See README's "Phase 8" section for the full security/performance/
accessibility audit writeup — don't duplicate it here.

### Test-running: PHP now installs, Packagist still doesn't — read this before trusting green checkmarks

Better than Phase 7's sandbox but still not a real run: `apt-get
install php8.3-cli` (+ sqlite3/mbstring/xml/curl/dom/bcmath/zip)
worked this time (`archive.ubuntu.com`/`security.ubuntu.com` are
reachable), and `composer.phar` downloaded fine from GitHub release
assets — but `repo.packagist.org` returns `HTTP 403` (confirmed via
`composer diagnose`), so `composer install` cannot resolve a single
package and Laravel never boots. What this bought: **every PHP file
this phase touched was actually run through `php -l`** (not the
brace-balance approximation Phase 7 had to use), plus a Blade
directive-balance sweep and a full `diff -rq` against a pristine
Phase 7 re-extraction to confirm the change set was exactly what was
intended. **Still: run `composer install && php artisan test` in a
real environment before trusting this phase's ~50 test methods as
green.** If a future session finally gets Packagist access, that
should be the very first thing done, before Phase 9.

### Suggested next steps (Phase 9+)

Messaging is now complete end-to-end (start/reuse → send → read →
notify), independent of any specific case or appointment (a customer
can message an eligible lawyer directly from their profile, not only
from within a case). Natural next slices per the original product
shape: reviews (the `reviews` table has sat unused since Phase 2,
same as `conversations`/`messages` did until this phase), then
eventually the Phase 11 AI assistant — which, per the standing
principle every phase has followed, should call the existing
`LawyerMatchingService`/`AppointmentAvailabilityService`/
`LegalCaseStatusService`/`MessagingService` rather than duplicate any
of their logic.

## Phase 9 — Reviews, Ratings & Lawyer Reputation

The `reviews` table/model/enum/factory really had sat completely
unused since Phase 2, exactly as the note above predicted — this phase
finally wires it up. The whole business rule lives in one place,
`ReviewPolicy::create(User, Appointment)`: active customer, owns the
appointment, appointment is COMPLETED (no other status ever
qualifies), lawyer isn't the acting user, no existing review — and
that one method is reused unchanged by the controller, the service
(re-checked after `lockForUpdate()`-ing the appointment row inside a
transaction, closing the double-submit race), and the Blade "Leave a
review" button. `customer_id`/`lawyer_profile_id`/`appointment_id` are
never fields on the Store/Update requests at all, so nothing in the
request body can ever redirect a review to someone else's account or
appointment — the database's own unique constraint is the last-resort
backstop, not the main defense.

Decided reviews publish immediately rather than sitting in a
moderation queue — the eligibility rule already fully vets
authenticity by the time a review can be created at all, so there's
nothing left to pre-approve, and a queue would have made this the
"unrelated admin system" the brief said not to build. The
pending/published/rejected status column instead powers *after-the-
fact* admin abuse handling: `Admin\ReviewController` can reject/
restore a review (hides/shows it) but can never edit its content or
hard-delete it — same narrow-admin-power shape as Phase 7's case
oversight.

`ReviewService::reputation()` (two aggregate queries: avg+count, then
a GROUP BY rating for the 1–5 breakdown) is the single source every
rating figure reads from — public profile, directory card
(`withCount()`/`withAvg()` as correlated subqueries, not a query per
card), lawyer's own dashboard panel. Public review listings only ever
eager-load `customer:id,name` — no reviewer contact info can leave
that query regardless of what a view does with it, verified directly
in a test. Roughly 45 new test methods across 5 files; the
model-relationship and DB-unique-constraint checks were deliberately
*not* duplicated since `DomainSchemaTest` already had them from Phase
2. Full writeup, including the full requirement-by-requirement
security audit, in README's "Phase 9" section.

Files: new `config/reviews.php`, `ReviewPolicy`, `ReviewService`,
`Store/UpdateReviewRequest`, `ReviewNotification`,
`Customer/Lawyer/Admin ReviewController`, the review Blade views,
`tests/Feature/Reviews/*.php` (5 files). Extended: `routes/web.php`
(additive only), `Customer\AppointmentController::show()` (+`review`
eager-load), `LawyerDirectoryController` (+reputation/rating
aggregates), `Lawyer\DashboardController` (+reputation), `lawyers/
{index,show}.blade.php` and both dashboard views (+rating UI, nothing
removed), `legal-connect.css` (additive rules appended only).

### Test-running: no PHP, no network at all this session — read this before trusting anything

A real step backward from Phase 8's sandbox, not forward: `bash_tool`
had network access disabled this time — `apt-get update` itself
returned `403 Forbidden` on every configured repository
(`archive.ubuntu.com`, `security.ubuntu.com`, even `deb.nodesource.com`),
not just Packagist the way Phase 8 saw — so no `php` binary could be
obtained by any means, so nothing could be run: no `php -l`, no
PHPUnit, no route-list dump. This
phase's correctness rests entirely on manual reading plus text-level
scripted checks (brace/paren balance counts, a Blade
`@directive`/`@enddirective` balance sweep, and cross-checking every
model method/scope/enum case/route name/CSS class this phase uses
against its real source before using it) — **materially weaker than
Phase 8's actual green `php -l` run.** Do not treat this phase's ~45
test methods as verified-green without actually running
`composer install && php artisan test` first. If a future session
finally gets real PHP/Composer access, running the *entire* suite
(this phase's plus everything from Phases 1–8) should be the very
first thing done, before starting Phase 10.

### Suggested next steps (Phase 10+)

Reviews now close out the last major unused table from the Phase 2
schema. What's left of the original product shape is essentially the
Phase 11 AI assistant (smart-match / chatbot-style guidance) — which,
per the standing principle every phase has followed, should call the
existing `LawyerMatchingService`/`AppointmentAvailabilityService`/
`LegalCaseStatusService`/`MessagingService`/`ReviewService` rather than
duplicate any of their logic — and whatever payments/billing phase the
product eventually needs, which no phase so far has touched.


## Phase 10 — Notifications & Communication Center

One page, `/notifications`, shows a user everything that happened across
appointments, messages, cases, documents, reviews and account/system events.
It is a *read model over what already existed*: no second notification
system, no new notification class, no new table.

### Architecture
- Reuses Laravel's `notifications` table and the `Notifiable` trait already on
  `User`. Every existing notification class (`AppointmentNotification`,
  `NewMessageNotification`, `CaseNotification`, `ReviewNotification`,
  `LawyerApplication{Submitted,Approved,Rejected}`) is surfaced unchanged.
- `NotificationCategory` (enum) derives the category from the payload `type`
  key: `appointment_*` -> Appointment, `new_message` -> Message,
  `case_document_uploaded` -> Document (checked *before* the generic
  `case_*` -> Case rule), `review_submitted` -> Review, everything else
  (lawyer application events, unknown types) -> System.
- `NotificationCenterService` is the only reader/writer: paginate with an
  all/unread/read filter, recent-N for dashboards, unread count, owned-lookup,
  mark read / unread / all read. Every method goes through
  `$user->notifications()`, and none accepts a user id.
- `NotificationPresenter` is a *whitelist*: only category, a stripped/limited
  message, read flag, timestamp and a destination URL reach a view. Raw
  payload never does.
- `NotificationController` (not role-prefixed; `auth` + `active` like
  `/dashboard`), `NotificationPolicy` (registered explicitly with
  `Gate::policy` because `DatabaseNotification` is a framework class and
  cannot be auto-discovered).

### Security model
- `{notification}` is `whereUuid()` constrained and resolved *only* through the
  authenticated user's relation: someone else's id is a **404** (no existence
  leak), and the policy is a second layer (morph type + id + active status).
  Admins get no special access.
- Mutations are POST only (CSRF via `web` group); no GET mutates state.
- Nothing in the request body can choose ownership; mark-all is scoped to
  `$user->unreadNotifications()`.
- Output is Blade-escaped; presenter also `strip_tags` + length-limits.
- Destination links are built from integer ids to existing routes for the
  viewer's own role prefix; a bad id yields no link. Links never grant
  access - the target page runs its own policy. Document notifications link
  to the *case page*, never a file/storage path; message notifications never
  carry the body (unchanged from Phase 8).

### Destination map
appointment -> `{role}.appointments.show`; message -> `{role}.messages.show`;
case/document -> `{role}.cases.show`; review -> `lawyer.reviews.index`;
application submitted (admin) -> `admin.lawyer-verifications.index`;
application approved/rejected (lawyer) -> `lawyer.dashboard`.
(The submitted payload holds the lawyer's *user* id, not the verification id,
so it links to the queue rather than guessing a record.)

### UI
Reuses `.panel`, `.data-table`, `.status`, `.btn`, `.empty`, `.unread`,
`.row-actions`, standard pagination. Two additive CSS rules only
(`.notif-strip`, unread row tint). Note the real app has **no shared navbar**:
protected pages extend `layouts.minimal` with per-page headers. So the bell +
unread badge is a slim `@auth` strip in `layouts.minimal` (skipped when
`site-content` exists) and a bell in `layouts.site`'s header; a view composer
supplies the count, memoized per request (one COUNT query). Each dashboard
gets a "Notifications" panel (latest 5) via `notifications/_recent`.

### Schema
Only additive: composite index
`(notifiable_type, notifiable_id, read_at, created_at)` on `notifications`
(`2026_09_29_200000_add_notification_center_indexes.php`).

### Files (Phase 10)
New: `NotificationCategory`, `NotificationPresenter`,
`NotificationCenterService`, `NotificationPolicy`, `NotificationController`,
the index migration, `notifications/{index,_recent}.blade.php`,
`tests/Feature/Notifications/*` (6 files). Edited: `routes/web.php`,
`AppServiceProvider`, both layouts, 3 dashboard views + controllers,
`legal-connect.css` (appended).

### Test-running: read this before trusting green checkmarks
This session had a real `php` (8.3) but **`repo.packagist.org` /
`getcomposer.org` return 403 `host_not_allowed`**, so `composer install`
failed and there is no `vendor/`. **PHPUnit was NOT run.** What *was* run:
`php -l` on every PHP file (275, all clean) and scripted Blade
directive-balance checks; routes/names/enum cases used by Phase 10 were
cross-checked against source by hand. The new tests (~35 methods) are
unexecuted. The very first thing to do with real Composer access:
`composer install && php artisan test` (whole suite, Phases 1-10). Known
unverified assumptions: `@sectionMissing` compiles as expected; the query-count
test's ceiling of 8 is an estimate.

### Suggested next steps (Phase 11+)
Category filter (would need a JSON query or a stored category column), and
an optional "open and mark read" flow. Deliberately not done: realtime,
mail, notification preferences.

---

## Phase 11 — Final QA, Security Audit & Stabilization

**Scope:** QA of everything in Phases 1–10. No product features were added. AI, camera/biometric verification, payments and all other out-of-scope items were not touched.

### What could and could not be executed (honest status)

The QA environment had **no PHP runtime, no Composer, and no network access** (Packagist/apt returned HTTP 403). Therefore:

| Check | Status |
|---|---|
| `php -l` lint sweep | **NOT RUN** (no `php` binary). Substitute: a Python static check over all 227 non-Blade `.php` files (226 from Phase 10 + the new regression test) — brace/paren/bracket balance, `<?php` open tag, namespace-vs-path match, `use App\…`/`use Database\…` imports resolving to real classes, duplicate imports. Result: 0 problems. This is *not* a substitute for `php -l`. |
| PHPUnit (existing suite + new tests) | **NOT RUN.** No pass/fail counts exist. |
| `php artisan migrate` on a fresh DB | **NOT RUN.** Migrations were read, not executed. |
| Route-name cross-check (Python) | Run. All 101 named routes referenced by `route()`/`routeIs()` in views and app code exist. |
| Blade escaping / CSRF grep | Run. 0 uses of `{!! !!}`; every non-GET `<form>` in `resources/views` contains `@csrf`. |
| Debug-statement grep | Run. No `dd()`, `dump()`, `var_dump`, `print_r`, `console.log`, `debugger`. |

**Before deploying, run locally:** `composer install && php artisan key:generate && php artisan migrate --seed && php artisan test`. Treat the results of that run, not this document, as the real verdict.

### Defects found and fixed

| # | Severity | Finding | Fix |
|---|---|---|---|
| 1 | High (boot) | ZIP lacked `bootstrap/cache/`, `storage/framework/{cache/data,sessions,views}`, `storage/logs`. Laravel aborts if `bootstrap/cache` is missing. | Added the directories with `.gitignore` placeholders. |
| 2 | Medium (boot) | `composer.json` had no `scripts`, so `package:discover` never ran and `.env` was never created on install. | Added standard Laravel scripts. |
| 3 | Medium | No root `.gitignore` (risk of committing `.env`, `vendor/`). | Added. |
| 4 | High (security) | `db:seed` unconditionally created an admin and other accounts with the well-known password `password`, including in production. | `DatabaseSeeder` seeds only reference data when `app()->isProduction()`. |
| 5 | Medium (integrity) | Admin approve/reject read status without a lock; two concurrent actions could both succeed and double-notify. Re-rejecting overwrote reviewer/reason and re-notified. | Status re-read with `lockForUpdate()` inside the transaction; no-op + message when already in the target state; notification only on a real transition. |
| 6 | Low | Directory sort on non-unique columns (fee, experience, name) had no tiebreaker, so pages could repeat/skip rows. | Added `orderBy('id')`. |
| 7 | Low | Directory keyword search treated `%`/`_` as wildcards. | Escaped with a portable `ESCAPE '!'`. |

Regression tests for 4–7 are in `tests/Feature/Qa/Phase11RegressionTest.php` (**written, not executed**).

### Audit results (static code review — nothing was exercised at runtime)

- **Authentication/roles:** `auth` + `active` on all protected routes; `role:` middleware returns 403 (guests → login); suspended users are logged out mid-session; login is rate-limited; session regenerated on login; registration allow-lists `customer|lawyer`.
- **IDOR matrix (by code path):** appointments, cases, documents, conversations/messages, reviews and notifications are each gated by a policy and/or an owner-scoped query. Notifications resolve only through `$user->notifications()` (foreign id → 404). Documents cross-check `document.case_id === case.id`, then policy. Conversations exclude admins. Not exercised with live requests.
- **XSS:** all Blade output uses `{{ }}`; notification text is `strip_tags`'d and limited to 200 chars.
- **Files:** verification images and case documents live on the private `local` disk, UUID filenames, extension from detected MIME, `mimes:` + size limits, downloads forced as attachments; only lawyer profile photos are on the public disk.
- **Sensitive data:** notification payloads carry no message bodies, review comments or document paths; the presenter whitelists output. Public directory selects only public columns.
- **Performance:** directory/matching/messaging/notification pages use eager loading, `withCount`/`withAvg`, and pagination; unread badge count is memoized per request. No N+1 found by inspection; not profiled.
- **Secrets:** no `.env`, keys or credentials in the tree other than the documented demo-seeder password (now blocked in production).

### Known limitations / non-blocking observations (not fixed; not in scope)

1. Tests, lint and migrations must still be run in a real environment (see above).
2. No DB-level unique constraint prevents overlapping appointments; protection is a row lock on the lawyer profile inside a transaction (effective on MySQL/InnoDB, a no-op on SQLite).
3. `User::$fillable` includes `role` and `status`. Current controllers only pass validated data, but any future `User::create($request->all())` would be unsafe.
4. Rejecting an already-approved lawyer removes them from search but does not touch their existing appointments/cases.
5. `.env.example` ships `APP_DEBUG=true`; set it to `false` outside local development.
6. The home-page prototype (`public/js/legal-connect.js`) is a localStorage demo using `innerHTML` with `esc()`-escaped data and inline `onclick` handlers; it holds only client-side demo data. Vite (`resources/js/app.js`) is configured but not used by any view.
7. Public profile photos require `php artisan storage:link`.
8. Message conversations stay open after a lawyer becomes ineligible (only *starting* one is eligibility-gated).
9. Face verification remains prototype-level (image upload only), as specified.

This is not a "production ready" statement: the checks that would justify one have not been run.

### Phase 11 — Second-pass audit and confirmed fixes (this update)

A second, deeper pass read the remaining controllers, all Blade views, factories, CSS, and every feature test's method names (full bodies for the security-focused suites). Three additional defects were confirmed and fixed:

1. **Login page exposed working demo credentials, including the admin account, outside local development.** `resources/views/auth/login.blade.php` now wraps the "Development accounts" block in `@if(app()->environment('local'))`, so it renders in local only and never in staging/production. No credential is hardcoded anywhere else; the seeder-level fix from the first pass (demo accounts skipped in production) still stands independently.
2. **Admin lawyer-verification queue ran 5 separate `COUNT` queries for its status tabs.** `Admin/LawyerVerificationController::index()` now runs one `groupBy('status')` query and maps the result into the same `counts` array, including a correct `0` for any status with no rows. UI, tab set, authorization and filtering are unchanged.
3. **`cases.notes` was shared and readable/writable by both the customer and the lawyer (the defect flagged as KNOWN in the first pass) — now fixed.**
   - New migration `2026_09_29_300000_add_customer_notes_and_cancellation_reason_to_cases_table.php` adds `customer_notes` (nullable text) and `cancellation_reason` (nullable text) to `cases`. `notes` is unchanged and is now treated exclusively as the **lawyer's** private notes; `customer_notes` is the **customer's** private notes. No data migration was possible or attempted for old `notes` values, since there is no reliable way to attribute a pre-existing shared value to one party after the fact.
   - `LegalCaseStatusService::cancel()` no longer appends the cancellation reason into `notes`. It now writes it to the new `cancellation_reason` column (mirroring the pattern Phase 6 already established for `appointments.cancellation_reason`), so a cancellation reason is visible to both parties without going through either one's private notes.
   - `Customer\CaseController::update()` writes only `title`, `description`, `customer_notes` from the request; `Lawyer\CaseController::update()` writes only `notes`. Each `->only()` call is the actual privacy boundary: a `notes` value smuggled into a customer's request (or a `customer_notes` value in a lawyer's) passes shape validation in the shared `UpdateCaseRequest` but is silently dropped before it reaches the model.
   - `LegalCase::$fillable` now includes `customer_notes` and `cancellation_reason`.
   - Customer case view shows only "Your Notes" (`customer_notes`); lawyer case view shows only "Your Private Notes" (`notes`); admin case view shows both, separately labeled "Customer Notes" and "Lawyer Notes". All three show a "Cancellation reason" line when the case is cancelled and a reason was given.
   - `tests/Feature/Cases/LifecycleTest.php` was updated (not deleted) to assert the corrected model: the customer-update test now submits/asserts `customer_notes`, and the cancellation test now asserts `cancellation_reason` instead of a string embedded in `notes`. A new `tests/Feature/Cases/NotesPrivacyTest.php` adds ten regression tests: each party can read/write only their own notes field, each party's attempt to write the other's field is silently ignored (not an error), the admin view shows both fields separately labeled, non-party role-gated routes still return 403, and cancellation does not touch either party's private notes.

None of these three fixes touch AI, payments, face verification, video calls, WebSockets, or any future-phase functionality.

### What was and wasn't executed for these three fixes

The environment still has no `php`, `composer`, or network access (`curl` to Packagist returns `403 host_not_allowed`), so none of `php artisan test`, `php -l`, or a real `migrate` could be run for this update either. What was actually done instead:

- The same Python brace/paren/import static check as the first pass, re-run over all 229 non-Blade PHP files (up from 227: +1 migration, +1 test file) — 0 problems.
- A prefix-aware static re-check that every `route('...')` call added or changed in this update resolves against the real route list (101 names, unchanged) — all resolve.
- Blade `@if`/`@endif`/`@unless`/`@endunless` directive-count balance check on the three edited case-show views — balanced.
- The migration's `ALTER TABLE ADD COLUMN` statements were replayed against an in-memory SQLite database shaped like the existing `cases` table (SQLite is what `phpunit.xml` configures for this project's test run) to confirm they are schema-valid and additive; a full `php artisan migrate` was not run.
- A full diff against the previously shipped Phase 11 ZIP was inspected file-by-file to confirm only the intended files changed.

**Still not executable here, and not fabricated:** `php artisan test` (no pass/fail counts exist for `NotesPrivacyTest.php`, the updated `LifecycleTest.php` lines, or the rest of the suite), a real `migrate`, and `php -l`. Run `composer install && php artisan migrate --seed && php artisan test` locally before treating this as verified.
