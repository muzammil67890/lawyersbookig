# Legal Connect — Laravel Phase 1

This package converts the supplied Legal Connect HTML/CSS/JS prototype into a Laravel 12-compatible application foundation.

## Preserved
- Existing Legal Connect visual layout
- Existing HTML structure
- Existing CSS
- Existing JavaScript interactions
- Fonts/icons/chart CDN dependencies
- Original source at `docs/design-source.html`

## Laravel foundation
- Laravel routes
- Controller
- Blade layout/page
- MySQL configuration
- Initial users migration with customer/lawyer/admin-ready `role`
- Basic feature test
- XAMPP-oriented `.env.example`

## Local setup

Requirements: PHP 8.2+, Composer, MySQL/XAMPP.

1. Create MySQL database: `legal_connect`
2. Run `composer install`
3. Copy `.env.example` to `.env`
4. Run `php artisan key:generate`
5. Run `php artisan migrate`
6. Run `php artisan serve`
7. Open `http://127.0.0.1:8000`

The later phases should build on this ZIP. Do not redesign the approved UI.

## Phase 1 audit notes (this pass)

**What was inspected:** the prior Laravel skeleton (`bootstrap/app.php`, `routes/web.php`, the controller, `User` model, the users migration, the Blade layout/page) and the original `docs/design-source.html` prototype it was converted from.

**What was fixed:**
- The Blade page was loading the entire 155-line prototype script twice — once inlined at the bottom of `legal-connect.blade.php`, and again via `public/js/legal-connect.js` referenced in the layout. Byte-for-byte identical, so every `DOMContentLoaded`/`hashchange` listener and function definition ran twice, which would double-fire notifications, toasts, and route rendering in the browser. The inline copy was removed; the page now loads the script once from `public/js/legal-connect.js`.
- Verified the CSS and JS extractions were otherwise byte-for-byte faithful to `docs/design-source.html` (only whitespace differed) — no visual fidelity was lost in the original conversion, contrary to what the line-count difference initially suggested.
- The skeleton was missing the files a Laravel 12 app actually needs to boot: `artisan`, `public/index.php` + `.htaccess`, the full `config/` directory, `bootstrap/providers.php`, `storage/` and `bootstrap/cache/` directories, `app/Providers/AppServiceProvider.php`, `tests/TestCase.php`, `phpunit.xml`, and a `DatabaseSeeder`/`UserFactory` pair. These are now present.
- `.env.example` sets `SESSION_DRIVER`, `CACHE_STORE`, and `QUEUE_CONNECTION` to `database`, which requires `sessions`, `cache`/`cache_locks`, `jobs`/`job_batches`/`failed_jobs`, and `password_reset_tokens` tables. Migrations for all of these were added alongside the existing `users` migration.
- Added a `sqlite` database connection (used only by the test environment via `phpunit.xml`) so feature tests don't require a live MySQL server.
- `User` model now uses the `HasFactory` trait, required by the new `UserFactory`.

**What could not be verified in this pass:** this environment has no PHP, Composer, or MySQL installed, and its network allowlist does not include Packagist, so `composer install`, `php artisan serve`, `php artisan migrate`, and `phpunit` could not actually be run here. Everything above was written to match Laravel 12's real skeleton conventions and reviewed by hand, but you should run the standard setup (see above) on your XAMPP environment and treat `composer install` → `php artisan migrate` → `php artisan serve` as the real first test, not an assumption.

**Not yet done (correctly out of Phase 1 scope):** authentication is not wired up — login/register/dashboards are still the prototype's own JS + localStorage, as they should be at this stage. Phase 2 can layer real auth, appointments, cases, messaging, and the CNIC/lawyer-verification flow onto this foundation without restructuring it.

---

## Phase 2 — Database / domain foundation

Phase 2 is a **backend/database-only** phase. The approved Legal Connect
frontend (`docs/design-source.html`, `resources/views`, `public/css`,
`public/js`) is untouched — no redesign, no new framework, no CSS/JS
rewrite. See `brain.md` for a fuller architectural explainer aimed at
whichever AI/engineer picks up Phase 3.

### Database architecture

Building on the Phase 1 `users` table (now extended with `phone`,
`profile_image`, `status`), Phase 2 adds 16 domain tables:

| Domain | Table(s) |
|---|---|
| Legal services & specializations | `services`, `specializations` |
| Lawyer profile | `lawyer_profiles` |
| Lawyer identity verification | `lawyer_verifications` |
| Lawyer ↔ service/specialization | `lawyer_service`, `lawyer_specialization` (pivots) |
| Availability | `lawyer_availabilities` |
| Appointments | `appointments` |
| Cases | `cases`, `case_documents` |
| Messaging | `conversations`, `messages` |
| Notifications | `notifications` (Laravel's standard database-notifications schema) |
| Reviews | `reviews` |
| AI matching assistant | `ai_conversations`, `ai_messages` |

Design choices worth knowing about:

- **`lawyer_profiles.verification_status` and `is_active`** are a
  denormalized cache of the real approval state in
  `lawyer_verifications.status` / admin actions. This is deliberate:
  the public lawyer-search query (filter by service, specialization,
  city, fee, approved+active status) never needs to join the
  verification-review table to render results.
- **No JSON columns for relational/searchable data.** Services,
  specializations, and lawyer↔service/specialization links are
  normalized tables + pivots, not JSON arrays, specifically so the
  future AI-matching system can query/filter them with SQL.
- **Status fields use PHP 8.1+ backed enums** (`app/Enums/*.php`) cast
  on the Eloquent models, rather than uncontrolled arbitrary strings:
  `UserRole`, `UserStatus`, `VerificationStatus`, `AppointmentStatus`,
  `ConsultationType`, `CaseStatus`, `ConversationStatus`,
  `ReviewStatus`, `AiConversationStatus`, `AiMessageSender`.
- **`App\Models\LegalCase`** maps to the `cases` table. The model
  isn't called `Case` because `case` is a reserved word in PHP and
  cannot be used as a class name.
- **File uploads (CNIC/face images, case documents, message
  attachments) store only a path string**, never binary data. The
  storage/filesystem layer itself is a later phase.
- **`lawyer_availabilities`** supports both a weekly recurring slot
  (`day_of_week` set) and a one-off date override (`specific_date`
  set) from day one, so Phase 6's booking engine doesn't need a schema
  change.
- Every foreign key is a real constraint; deletes are `cascade` where
  the child row is meaningless without the parent (e.g. a lawyer
  profile's availabilities) and `nullOnDelete`/`restrict`-equivalent
  where the record should survive (e.g. a case document if the
  uploader account is later removed).

### Models & relationships

14 Eloquent models were added under `app/Models/`, plus 10 enums under
`app/Enums/`. `App\Models\User` (Phase 1) was extended with the
lawyer/customer/admin relationships described in the Phase 2 spec
(`lawyerProfile`, `lawyerVerification`, `appointmentsAsCustomer`,
`appointmentsAsLawyer` (via `hasManyThrough` on `lawyer_profiles`),
`casesAsCustomer`, `casesAsLawyer`, `conversations`, `sentMessages`,
`reviews`, `uploadedDocuments`, `aiConversations`) without touching its
Phase 1 columns.

### Local setup (unchanged from Phase 1, plus seeding)

1. Create MySQL database: `legal_connect`
2. `composer install`
3. Copy `.env.example` to `.env`, then `php artisan key:generate`
4. `php artisan migrate`
5. `php artisan db:seed` (optional — loads reference data + demo accounts)
6. `php artisan serve`

### Testing

```
php artisan migrate:fresh
php artisan db:seed
php artisan test
```

`tests/Feature/DomainSchemaTest.php` covers all 14 relationship/
constraint checks from the Phase 2 spec (roles, lawyer profile↔user,
verification↔user, services↔lawyers, appointment relationships,
cases↔customer/lawyer, documents↔case, conversations↔participants,
messages↔conversation/sender, reviews↔customer/lawyer, AI
conversations/messages, FK enforcement, unique-constraint enforcement)
plus a check that every expected table exists after migration. It runs
against SQLite in-memory (`phpunit.xml`), with SQLite foreign-key
enforcement asserted directly in the FK test, while the schema itself
(column types, engine-level constraints) is written to be MySQL
production-compatible.

### Development credentials (throwaway, local only)

Seeded by `DemoAccountSeeder` — **not real people, not real
credentials**, password `password` for all:

| Role | Email | Notes |
|---|---|---|
| Customer | `customer@legalconnect.test` | |
| Admin | `admin@legalconnect.test` | |
| Lawyer | `lawyer.approved@legalconnect.test` | verification: approved |
| Lawyer | `lawyer.pending@legalconnect.test` | verification: pending |
| Lawyer | `lawyer.rejected@legalconnect.test` | verification: rejected |

### What was actually executed in this pass

This sandbox has no Composer/Packagist access and originally had no
PHP interpreter either. PHP 8.3 CLI was installed via `apt` (allowed
by the network policy) specifically so every new/modified `.php` file
could be **syntax-checked with `php -l`** (all pass). Without
`vendor/` (Composer can't reach `repo.packagist.org` from this
sandbox) and without a MySQL server, `composer install`,
`php artisan migrate`, `php artisan db:seed`, and `php artisan test`
could **not** actually be executed here, and nothing in this
repository claims otherwise. Every migration, model, factory and
seeder was instead reviewed by hand against Laravel 12 / PHP 8.2+
conventions, cross-checked for FK-dependency ordering, and the test
suite above was written to the same standard the linked commands
would run. **Run the commands above on your XAMPP/MySQL environment
as the real first verification pass.**

### What Phase 2 intentionally does NOT implement

Per the Phase 2 scope: no real login/register/password-reset
implementation, no lawyer-onboarding UI, no CNIC-upload workflow, no
admin-approval UI, no appointment-booking engine, no
lawyer/customer/admin dashboard *backend* logic, no case or document
*management* UI, no real-time chat, no AI assistant/matching engine,
no notification delivery, no payments. Only the database/model
foundation those features will sit on.

### Phase 3 readiness notes

- Auth: `users.role` + `users.status` are ready for Laravel's
  auth scaffolding / Sanctum / Breeze; `email_verified_at` and
  `remember_token` already exist from Phase 1.
- Lawyer onboarding: create a `User` (`role = lawyer`), a
  `LawyerProfile`, and a `LawyerVerification` row with uploaded file
  paths; admin approval = update `LawyerVerification.status` +
  `reviewed_by`/`reviewed_at`, then sync
  `LawyerProfile.verification_status`.
- Public search: query `LawyerProfile::searchable()` (scope already
  defined) filtered by `services()`/`specializations()`,
  `city`, `consultation_fee`, using the composite index on
  `(verification_status, is_active, city)`.
- Notifications: write real `Illuminate\Notifications\Notification`
  classes and call `$user->notify(...)` — the `notifications` table is
  already Laravel's standard schema.
- Booking engine: read `LawyerAvailability` rows for a lawyer/date,
  write `Appointment` rows with `status = pending`, transition status
  via the existing `AppointmentStatus` enum.

---

## Phase 3 — Authentication & authorization

Phase 3 turns the prototype's demo login/register/logout into a real
Laravel session-based authentication system, and adds server-side role
+ account-status authorization. **The visual design is untouched** —
CSS is byte-for-byte identical to Phase 2, and the JS changes are 8
surgical edits (see below) out of ~150 functions in
`public/js/legal-connect.js`.

### Authentication architecture

- **Laravel's native session guard** (`config/auth.php`, unchanged from
  Phase 1/2) — no custom auth system, no JWT.
- **Credential checking + rate limiting + account-status enforcement**
  all live in one place, `App\Http\Requests\Auth\LoginRequest`
  (Laravel's own recommended/Breeze pattern), so it's defined once and
  reused rather than duplicated per controller.
- **Session regeneration on login** (`$request->session()->regenerate()`
  in `AuthenticatedSessionController::store()`) prevents session
  fixation. **Session invalidation + token regeneration on logout**
  (`AuthenticatedSessionController::destroy()`).
- **CSRF**: unchanged framework default — every route in
  `routes/auth.php` and `routes/web.php` runs inside Laravel's `web`
  middleware group, which includes CSRF validation automatically.
  Nothing disables it; see `tests/Feature/Auth/SecurityTest.php` for
  the route-level proof.
- **Password hashing**: Laravel's `Hash` facade (bcrypt) everywhere;
  `User::casts()` already has `'password' => 'hashed'` (Phase 2), and
  `'password'`/`'remember_token'` are in `$hidden`.
- **Email verification**: `User implements MustVerifyEmail`. Real
  Laravel verification flow — `verification.notice`,
  `verification.verify` (signed URL), `verification.send` (resend,
  rate-limited).
- **Password reset**: Laravel's password-broker (`Password` facade),
  using the `password_reset_tokens` table that already existed from
  Phase 1's base migrations. 60-minute token expiry
  (`config('auth.passwords.users.expire')`), generic responses that
  never confirm/deny an email is registered.
- **Open redirect protection**: `redirect()->intended(...)` is
  Laravel's own session-backed mechanism — it is **not** driven by any
  request query parameter, so there is nothing for an attacker-supplied
  `?redirect=`/`redirect_to` value to influence. See
  `SecurityTest::test_login_redirect_ignores_attacker_supplied_redirect_parameters`.

### Authorization architecture

- **`role:customer|lawyer|admin` middleware**
  (`App\Http\Middleware\EnsureUserHasRole`) — guest → redirected to
  login; wrong role → `403`, never a silent redirect that hints at
  what exists behind the wall.
- **`active` middleware** (`App\Http\Middleware\EnsureAccountIsActive`)
  — blocks suspended/inactive accounts from any request after login,
  not just at login time (covers an account suspended mid-session).
- Both registered as aliases in `bootstrap/app.php`; Laravel's own
  `auth`, `guest`, `verified`, `signed` aliases need no extra setup.
- Route groups in `routes/web.php`:
  `['auth','active']` → `/dashboard` (role-based redirect) →
  `['role:customer','verified']` `/customer/dashboard`,
  `['role:lawyer','verified']` `/lawyer/dashboard`,
  `['role:admin','verified']` `/admin/dashboard`.

### Routes added

| Method | URI | Name | Notes |
|---|---|---|---|
| GET/POST | `/register` | `register` | guest only |
| GET/POST | `/login` | `login` | guest only |
| GET | `/forgot-password` | `password.request` | guest only |
| POST | `/forgot-password` | `password.email` | throttled |
| GET | `/reset-password/{token}` | `password.reset` | guest only |
| POST | `/reset-password` | `password.store` | |
| GET | `/verify-email` | `verification.notice` | auth |
| GET | `/verify-email/{id}/{hash}` | `verification.verify` | auth + signed |
| POST | `/email/verification-notification` | `verification.send` | auth + throttled |
| POST | `/logout` | `logout` | auth |
| GET | `/dashboard` | `dashboard.redirect` | auth + active |
| GET | `/customer/dashboard` | `customer.dashboard` | auth + active + role:customer + verified |
| GET | `/lawyer/dashboard` | `lawyer.dashboard` | auth + active + role:lawyer + verified |
| GET | `/admin/dashboard` | `admin.dashboard` | auth + active + role:admin + verified |

### Controllers / Form Requests added

`app/Http/Controllers/Auth/`: `RegisteredUserController`,
`AuthenticatedSessionController`, `EmailVerificationPromptController`,
`VerifyEmailController`, `EmailVerificationNotificationController`,
`PasswordResetLinkController`, `NewPasswordController`.
`app/Http/Controllers/`: `DashboardRedirectController` (single
centralized role→destination redirect, reused after both login and
registration), `Customer\DashboardController`,
`Lawyer\DashboardController`, `Admin\DashboardController` (minimal
placeholders — see "What Phase 3 intentionally does NOT implement").
`app/Http/Requests/Auth/`: `RegisterRequest` (role locked to a
`customer`/`lawyer` allow-list — `admin` fails validation and never
reaches the controller), `LoginRequest`.

### Rate limiting

| Limiter | Limit | Where |
|---|---|---|
| Login | 5 attempts per email+IP per minute | manual, inside `LoginRequest` (Breeze pattern) |
| Registration | 5 requests/min per IP | `throttle:register` |
| Password-reset request | 5 requests/min per IP+email | `throttle:password-reset` |
| Verification resend | 6 requests/min per user | `throttle:verification-resend` |

Defined in `App\Providers\AppServiceProvider::boot()`. These are
generous enough not to interfere with normal local development or
testing.

### Frontend integration (surgical, not a rewrite)

- `resources/views/pages/legal-connect.blade.php`: header's
  Login/Register/Dashboard links now point at real routes; a
  `logoutNav` button and a hidden, CSRF-protected `#realLogoutForm`
  were added; the `view-login`/`view-register` demo sections were
  removed (superseded by real pages at `/login` and `/register` — no
  duplicate auth UI); `window.LC_AUTH` is injected server-side so the
  page's own JS can read real auth state.
- `public/js/legal-connect.js` — **8 total edits**: `getUser()` now
  reads `window.LC_AUTH` instead of localStorage `currentUser` (the
  server is the source of truth for "am I logged in", everywhere this
  function is used — favorites, booking gates, demo dashboards, etc.);
  `performLogout()` submits the real hidden form instead of clearing
  localStorage; `updateHeader()` also toggles the new logout button;
  the demo `loginUser()`, `registerUser()`, and now-unused
  `openDashboard()` functions were removed; the 6 remaining
  `navigate('login')` calls (favorites/booking/demo-dashboard guards)
  now send the visitor to the real `/login` page. Every other function
  — appointments, cases, documents, messages, notifications, the demo
  dashboards' own data — is byte-for-byte unchanged and remains
  frontend-only demo data, per Phase 3 scope.
- New pages (`auth/*`, `dashboard/*`) render on a new, lightweight
  `layouts/minimal.blade.php` that pulls in the same fonts, icons and
  `legal-connect.css` for visual consistency, but not the SPA's demo
  JS bundle (which assumes the big single-page prototype's DOM exists
  and would error harmlessly-but-noisily on these standalone pages).

### Local setup

Same as Phase 2, plus:

```
php artisan migrate
php artisan db:seed
php artisan serve
```

Visit `/login` or `/register`, or click Login/Register in the site
header.

### Testing

```
php artisan test
```

Six new test files under `tests/Feature/Auth/`:
`RegistrationTest`, `LoginTest`, `LogoutTest`, `AuthorizationTest`,
`EmailVerificationTest`, `PasswordResetTest`, `SecurityTest` — covering
every item in the Phase 3 spec's testing checklist (registration,
login, logout, role authorization for all three roles, suspended
accounts, email verification including signed-URL tamper/expiry
cases, password reset including the enumeration-safe generic
response, CSRF wiring, mass-assignment safety, and open-redirect
protection). `tests/Feature/DomainSchemaTest.php` (Phase 2) is
untouched and still exercises the full schema.

### Development accounts

Unchanged from Phase 2, plus one new suspended account for manually
testing account-status blocking. Password `password` for all —
**throwaway local seed accounts, not real people**:

| Role | Email | Notes |
|---|---|---|
| Customer | `customer@legalconnect.test` | |
| Admin | `admin@legalconnect.test` | |
| Lawyer | `lawyer.approved@legalconnect.test` | verification: approved |
| Lawyer | `lawyer.pending@legalconnect.test` | verification: pending |
| Lawyer | `lawyer.rejected@legalconnect.test` | verification: rejected |
| Customer | `suspended.customer@legalconnect.test` | status: suspended — cannot log in |

### Required `.env` settings

See `.env.example` — `SESSION_SECURE_COOKIE` (set `true` once served
over HTTPS), `SESSION_SAME_SITE` (defaults `lax`), `MAIL_MAILER`
(defaults `log` — no email is actually delivered locally unless this
is pointed at real SMTP/Mailpit).

### Mailpit / local mail

Not configured or tested in this sandbox (no such service was
available). `MAIL_MAILER=log` means verification/reset emails are
written to `storage/logs/laravel.log`; point `MAIL_MAILER` at `smtp`
with Mailpit's host/port to see them in a real inbox locally.

### Security audit performed

- **Session fixation**: session ID regenerated on every successful
  login.
- **CSRF**: framework default `web` group middleware, unmodified;
  verified at the route level in `SecurityTest`.
- **Password hashing**: `Hash::make()`/`'password' => 'hashed'` cast
  everywhere; no MD5/SHA1/plaintext anywhere in the codebase.
- **Mass assignment**: `RegisterRequest::validated()` is the *only*
  data that reaches `User::create()`; `role` is allow-listed to
  `customer`/`lawyer`; `status`/`id`/`email_verified_at` are not
  validated fields at all, so submitting them has zero effect (see
  `SecurityTest::test_registration_cannot_mass_assign_account_status_or_id`).
- **Role escalation**: structurally impossible via the public form —
  `admin` fails validation before the controller ever runs.
- **Authorization bypass**: proven negative via
  `AuthorizationTest` — customer→lawyer/admin, lawyer→admin/customer,
  admin→customer/lawyer are all `403`; guests are all redirected to
  login.
- **Account-status bypass**: checked both at login (`LoginRequest`)
  and per-request for live sessions (`EnsureAccountIsActive`
  middleware) — an account suspended mid-session is cut off on its
  very next request, not just at next login.
- **Email enumeration**: login and forgot-password both return
  identical generic responses regardless of whether the email is
  registered; tested explicitly.
- **Brute force**: rate limiting on login, registration, password
  reset requests, and verification resend (see table above).
- **Password reset security**: token expiry + single-use via Laravel's
  password broker; invalid/expired tokens rejected without revealing
  account existence.
- **Open redirects**: `intended()` is session-backed, not
  query-string-driven; tested with attacker-supplied `redirect`/
  `redirect_to`/`next` parameters.
- **Insecure cookies**: `http_only` defaults `true`, `same_site`
  defaults `lax` (both in `config/session.php`, present since Phase
  1/2); `SESSION_SECURE_COOKIE` documented for HTTPS deployment.
- **Sensitive data exposure**: `password`/`remember_token` hidden on
  the `User` model; tested that a rendered dashboard page never
  contains a raw password or its hash.
- **Unsafe logging**: no password or token is ever passed to `Log::`
  anywhere in the new code.
- **Unsafe Blade output**: all new Blade views use `{{ }}` (escaped)
  output exclusively — no `{!! !!}` anywhere in the new auth/dashboard
  views.
- **Insecure localStorage auth**: `getUser()` no longer reads
  localStorage for identity — it reads real server-rendered
  `window.LC_AUTH`, so a guest cannot spoof being logged in via
  localStorage, and a real login can't be hidden by stale localStorage
  data either. Real protected routes were never guarded by
  localStorage in the first place (server-side middleware only).

This is not a claim of 100% security — it is what was specifically
reviewed and tested for Phase 3's scope.

### What Phase 3 intentionally does NOT implement

Per the Phase 3 scope: no CNIC/face-image upload workflow, no admin
lawyer-approval UI, no complete lawyer registration/onboarding beyond
a bare `User` row, no lawyer search backend, no appointment booking,
no case/document management UI, no real-time chat, no AI assistant/
matching, no payments. The three role dashboards created in this
phase (`dashboard/customer.blade.php`, `dashboard/lawyer.blade.php`,
`dashboard/admin.blade.php`) are deliberately minimal placeholders
that prove the authentication/authorization boundary works — not the
full future application.

### What was actually executed in this pass

Same sandbox constraints as Phase 2: no Composer/Packagist access, no
MySQL server, so `composer install`, `php artisan migrate`,
`php artisan db:seed`, and `php artisan test` could **not** be run
here. What was done instead: **every** new/modified `.php` file (26
files: 4 enums/model change, 2 middleware, 2 Form Requests, 11
controllers, 2 route files, 7 test files, 1 seeder) was syntax-checked
individually with `php -l` — all pass. Blade files can't be
meaningfully checked with `php -l` (a template with no raw `<?php`
lints trivially regardless of broken `@directive` syntax), so all 8
new/modified Blade files were reviewed by hand instead. The
`public/js/legal-connect.js` changes were verified two ways: `node -c`
(syntax-valid) and a full line-by-line `diff` against the pre-edit
file, confirming exactly the 8 intended edits and nothing else
changed. Every Phase 2 file was diffed against the delivered Phase 2
ZIP to confirm zero unintended changes (only `app/Models/User.php`
differs, by the one intended `MustVerifyEmail` addition).
**Run the commands above on your XAMPP/MySQL environment as the real
first verification pass** — nothing here should be read as a claim
that `php artisan test` was actually executed.

### Phase 4 readiness notes

- Lawyer onboarding: `RegisteredUserController` already creates a bare
  `role = lawyer` user with no `LawyerProfile`/`LawyerVerification`
  row — Phase 4's onboarding flow should create those (and the CNIC/
  face-image upload) the first time a lawyer completes their profile.
- Admin lawyer approval: update `LawyerVerification.status` +
  `reviewed_by`/`reviewed_at`, then sync
  `LawyerProfile.verification_status` — both models and the admin
  route group/middleware (`role:admin`) already exist.
- The three placeholder dashboards are natural homes for the real
  customer/lawyer/admin application UI once it's built — swap their
  Blade content, keep the controllers/routes/middleware as-is.

## Phase 4 — Lawyer Onboarding, Verification & Admin Approval

### What was implemented

- **Lawyer onboarding** (`GET/POST /lawyer/onboarding`, `Lawyer\OnboardingController`):
  a lawyer who registered (Phase 3, bare `role = lawyer`, no profile) submits
  professional info (title, bio, years of experience, city, consultation fee,
  optional phone/bar council/license/education), picks services/specializations,
  and uploads a profile photo (optional), a CNIC image and a face photo (both
  required). One `LawyerProfile` + one `LawyerVerification` row are created in
  a single DB transaction; the route redirects to the dashboard once a profile
  already exists, so this is a one-time submission, not an edit form.
- **Verification lifecycle**: every new application starts at
  `VerificationStatus::PENDING` on both `LawyerVerification.status` and the
  denormalized `LawyerProfile.verification_status`. An admin can move it to
  `APPROVED` or `REJECTED` (`Admin\LawyerVerificationController`, routes under
  `/admin/lawyer-verifications/*`, `role:admin` + `verified` middleware).
  Both actions run in a DB transaction, record `reviewed_by`/`reviewed_at`,
  and keep the two status columns in sync. Rejection requires a
  `rejection_reason` (min 10 chars), validated by
  `RejectLawyerVerificationRequest` and shown back to the lawyer.
- **Eligibility**: `LawyerProfile::scopeSearchable()` (already existed) and
  the new `LawyerProfile::isEligible()` helper are the single definition of
  "approved + active + public" used anywhere eligibility needs to be checked.
  Approval alone does not make a lawyer eligible if `is_active`/`is_public`
  are false; nothing else re-derives this logic.
- **File security**: `LawyerFileUploadService` stores every upload under a
  fresh UUID filename with an extension guessed from the file's actual MIME
  type — the client's filename/extension is never used. Profile photos go to
  the `public` disk (`storage/app/public/lawyers/profile/...`, linked via
  `storage:link`); CNIC and face images go to the `local` (private) disk
  under `lawyers/verification/{cnic,face}/...` and are **not** reachable by
  any public URL. If the database transaction fails after files were
  written, the controller deletes the orphaned files.
- **Authorized document access**: `VerificationDocumentController` serves
  CNIC/face images through two routes —
  `GET /lawyer/verification-document/{type}` (a lawyer's own documents) and
  `GET /admin/lawyer-verifications/{lawyerVerification}/document/{type}`
  (admin review) — both gated by `LawyerVerificationPolicy::view()`: the
  owning lawyer or an admin, nobody else. A missing file/record returns 404,
  not a leaked path.
- **Authorization**: `LawyerVerificationPolicy` (`viewAny`, `view`, `review`)
  is checked explicitly with `$this->authorize()` in every relevant
  controller action, on top of (not instead of) the existing `role:lawyer` /
  `role:admin` route middleware — two independent enforcement points.
- **Notifications** (Laravel database notifications, via the existing
  `Notifiable` trait on `User`): `LawyerApplicationSubmitted` (sent to every
  admin when a lawyer submits), `LawyerApplicationApproved` and
  `LawyerApplicationRejected` (sent to the lawyer). No mail channel/template
  was added — database-only, surfaced via `$user->notifications`.
- **UI**: `resources/views/lawyer/onboarding.blade.php` (the application
  form), the lawyer dashboard now shows real verification status/rejection
  reason instead of a placeholder, the admin dashboard links to a new
  verification queue (`resources/views/admin/lawyer-verifications/index.blade.php`
  with pending/approved/rejected/suspended/all tabs) and detail/review page
  (`show.blade.php`, with approve/reject forms). All of this reuses the
  existing `layouts.minimal` layout and `legal-connect.css` classes
  (`panel`, `field`, `two-field`, `checks`/`check`, `data-table`,
  `table-wrap`, `toolbar`, `row-actions`, `tag`/`tag-row`, `btn` variants) —
  **no CSS file was modified**.
- **One schema addition**: a nullable `lawyer_profiles.title` column
  (migration `2026_09_22_100018_add_title_to_lawyer_profiles_table.php`).
  Every other onboarding field reuses a column Phase 2 already created.

### Routes added

```
GET  /lawyer/onboarding                                             lawyer.onboarding.create
POST /lawyer/onboarding                                             lawyer.onboarding.store
GET  /lawyer/verification-document/{type}                           lawyer.verification.document
GET  /admin/lawyer-verifications                                    admin.lawyer-verifications.index
GET  /admin/lawyer-verifications/{lawyerVerification}                admin.lawyer-verifications.show
POST /admin/lawyer-verifications/{lawyerVerification}/approve        admin.lawyer-verifications.approve
POST /admin/lawyer-verifications/{lawyerVerification}/reject         admin.lawyer-verifications.reject
GET  /admin/lawyer-verifications/{lawyerVerification}/document/{type} admin.lawyer-verifications.document
```

All eight sit inside the existing `['auth', 'active']` group plus their
respective `role:*` + `verified` sub-group — nothing bypasses Phase 3's
authentication/authorization boundary.

### Testing

- `tests/Feature/Lawyer/OnboardingTest.php` — form access by role, profile/
  verification creation, service/specialization sync, file storage (with
  `Storage::fake`), invalid MIME/oversized-file rejection, missing-field
  validation, one-time-submission enforcement, client cannot force
  `verification_status = approved`, transaction rollback cleans up files.
- `tests/Feature/Lawyer/VerificationDocumentAccessTest.php` — owner can view
  own CNIC/face, 404 with no verification record, customer forbidden, a
  lawyer forbidden from another lawyer's documents via the admin route,
  admin can view any, guest redirected to login.
- `tests/Feature/Admin/LawyerVerificationTest.php` — queue/detail access
  requires admin, approve/reject happen server-side and transactionally,
  `reviewed_by`/`reviewed_at` recorded, rejection reason required and
  stored, approval/rejection correctly flip `isEligible()`/
  `scopeSearchable()`, a lawyer (including the applicant themself) and a
  customer are both forbidden from approving/rejecting.
- All three new test files plus every existing Phase 1–3 test file were
  checked with `php -l` (syntax-only) — see "What was actually executed"
  below for why they were not run with PHPUnit.

### What was actually executed in this pass

- `php -l` against every changed/added file under `app/`, `database/`,
  `routes/`, `bootstrap/`, `config/`, and every file under `tests/` —
  **all passed, zero syntax errors**.
- `composer install` was attempted to obtain `vendor/` and run
  `php artisan test`. It fails: this sandbox's network egress allowlist
  does not include `repo.packagist.org`/`getcomposer.org` (confirmed via a
  direct `HTTP 403 host_not_allowed`), so Composer cannot resolve the
  Laravel framework dependency and `vendor/` cannot be built here.
  **PHPUnit was not executed. No test result described above was actually
  run in this sandbox** — run `composer install && php artisan test` (or
  `vendor/bin/phpunit`) in a normal environment with Packagist access to
  get real pass/fail results.
- Every new Blade view's CSS class usage was checked by hand against
  `public/css/legal-connect.css` (`panel`, `checks`, `check`, `data-table`,
  `table-wrap`, `table-person`, `row-actions`, `toolbar`, `tag`, `tag-row`,
  `btn.outline`, `btn.gold`, `btn.small`, `.full`) — no class was invented;
  **no CSS file was edited**.

### Security review performed on Phase 4

- Mass assignment: every `::create()`/`->update()` in the new controllers
  passes an explicit array built from `$request->validated()` plus
  server-computed constants (`VerificationStatus::PENDING->value`,
  `auth()->id()`, `now()`) — never `$request->all()`. `role`, `status`, and
  `verification_status`/`status` values sent by a client are never read by
  any Phase 4 controller.
- Uploads: MIME/type validated by `image`+`mimes:jpeg,jpg,png,webp`, size
  capped (2 MB profile photo, 4 MB CNIC/face), stored filename is always a
  server-generated UUID + MIME-derived extension (client filename/extension
  never used), private documents live on the `local` disk with no public
  URL, nothing is executed, no binary blobs in the database — only paths.
- Access control: CNIC/face documents are only reachable through
  `LawyerVerificationPolicy`-gated routes (owner or admin); approve/reject
  require `role:admin` middleware **and** `LawyerVerificationPolicy::review`
  independently; a lawyer cannot reach their own or anyone else's
  approve/reject endpoint (403, verified in tests).
- CSRF: every new POST form includes `@csrf`, consistent with the rest of
  the app.
- No debug statements (`dd`/`dump`/`var_dump`/`print_r`), no secrets, and no
  `.env` changes were introduced — verified by grep across every new file.

## Phase 5 Half 1 — Lawyer Directory, Search, Filters & Public Profiles

### What was implemented

- **Real, database-backed public lawyer directory** at `GET /lawyers`
  (`LawyerDirectoryController@index`) and **public lawyer profile** at
  `GET /lawyers/{lawyer}` (`@show`). Both are new, additive routes —
  they sit alongside the still-unmodified Phase 1 frontend demo
  (`resources/views/pages/legal-connect.blade.php` /
  `public/js/legal-connect.js`), which keeps its own separate,
  illustrative "Find Lawyers"/"Profile" hash-routed views against
  localStorage demo data. That demo's booking/messaging/dashboards are
  out of this phase's scope (see "Strict phase boundary" below) and
  were not touched.
- **Eligibility, enforced once, reused everywhere**: every query in
  `LawyerDirectoryController` goes through
  `LawyerProfile::scopeSearchable()` (approved + active + public,
  already existed from Phase 4) — the directory list, every filter,
  every sort, pagination, and the profile lookup all use the same
  scope. No second/parallel "is this lawyer eligible" check exists
  anywhere in Phase 5. The profile route (`show()`) calls
  `LawyerProfile::query()->searchable()->findOrFail($id)`: a pending,
  rejected, suspended/inactive, opted-out (`is_public = false`), or
  simply nonexistent lawyer ID all produce the **identical** 404 —
  nothing in the response reveals which case it was.
- **Search**: `LawyerProfile::scopeKeyword()` matches lawyer name,
  title, bio, city, and the names of services/specializations the
  lawyer offers — all via parameterized Eloquent `where`/`whereHas`,
  no raw SQL.
- **Filters** (all real columns/relationships, GET params, shareable
  URLs): `service` / `specialization` (matched by slug via
  `whereHas`), `city`, `min_fee` / `max_fee` (`consultation_fee`),
  `min_experience` (`years_of_experience`). Every filter is validated
  by `LawyerDirectoryRequest` before it reaches the query builder.
- **Sorting**: a fixed allow-list (`name`, `fee`, `experience`,
  `recent`) maps to real columns in `LawyerDirectoryController::SORTS`
  — the client-supplied `sort` value is never passed into `orderBy()`
  directly. It's also validated with `Rule::in()` in the Form Request,
  so an invalid value is rejected before the controller even runs
  (defense in depth: two independent layers, not one).
- **Pagination**: `paginate(12)->withQueryString()` — filters, search
  and sort persist across page links; nothing loads the full lawyer
  table into memory.
- **Performance**: `select()` limits `lawyer_profiles` to the columns
  the page actually needs (no CNIC/face paths — those live in a
  completely separate `lawyer_verifications` table this controller
  never queries at all), and `with(['services:id,name,slug',
  'specializations:id,name,slug'])` eager-loads both relations to
  avoid N+1 queries when the view lists each lawyer's services/tags.
- **UI**: `resources/views/lawyers/index.blade.php` and `show.blade.php`
  reuse the exact existing CSS classes from `legal-connect.css`
  (`search-layout`, `filter-panel`, `lawyer-result`, `tag-row`,
  `profile-hero`, `profile-content`, `specialties`, etc.) and a new
  `resources/views/layouts/site.blade.php` that reproduces the Phase 1
  header/footer markup (same classes, dark mode, mobile nav toggle)
  server-side — using real `@auth`/`@guest` for the nav instead of the
  demo's `window.LC_AUTH` JS hack. It deliberately does **not** load
  `legal-connect.js` (that script's router assumes the SPA's `#view-*`
  sections exist in the DOM), so the demo-only notification
  bell/global-search popover/AI assistant button are left out rather
  than faked with no backend behind them.
- **Privacy**: the profile/directory views only ever touch
  `LawyerProfile` + `services`/`specializations` — `LawyerVerification`
  (CNIC path, face path, rejection reason, reviewer) is never loaded,
  queried, or referenced anywhere in this controller or these views.

### Routes added

```
GET /lawyers               lawyers.index
GET /lawyers/{lawyer}      lawyers.show   (numeric ID, constrained with whereNumber)
```

Both are public (no `auth` middleware), sitting next to `home` at the
top of `routes/web.php`.

### Testing

- `tests/Feature/Lawyers/DirectoryTest.php` — page loads; approved+active
  appears; pending/rejected/suspended/inactive/not-public all excluded;
  search by name/title/city/service-name/specialization-name and a
  combined search+filter; filter by service slug, specialization slug,
  city, min fee, max fee, min experience; fee-sort ordering; an
  injected `sort` value is rejected by validation; pagination splits
  15 lawyers across pages and filters persist in the pagination links.
- `tests/Feature/Lawyers/ProfileTest.php` — approved+active profile
  viewable; pending/rejected/suspended-inactive/not-public/nonexistent
  IDs all 404 identically; CNIC/face storage paths, rejection reason,
  and reviewer name are all asserted absent from both the profile page
  and the directory listing even when a `LawyerVerification` record
  with that data exists for the same lawyer.

### What was actually executed in this pass

- `php -l` against every PHP file in the project (not just Phase 5's) —
  **all passed, zero syntax errors**.
- `composer install` was attempted again to get `vendor/` for
  `php artisan test`; it still fails with `HTTP 403` from
  `repo.packagist.org` (this sandbox's network allowlist doesn't
  include Packagist). **PHPUnit was not executed in this pass either.**
  Run `composer install && php artisan test` in a normal environment
  to get real results for the new Directory/Profile tests above (and
  to finally execute the Phase 3/4 suites too).
- Every new Blade view's CSS classes were checked by hand against
  `public/css/legal-connect.css` before use — no invented class names,
  **no CSS file was edited**.

### Security review performed on Phase 5

- SQL injection / unsafe `orderBy`: sort is a fixed PHP allow-list
  array (`SORTS`) plus `Rule::in()` validation — the request's `sort`
  string is never concatenated or passed into a query method.
- IDOR / information disclosure: the profile route's only access
  control is `scopeSearchable()->findOrFail()` — by design, a
  non-eligible lawyer's existence cannot be distinguished from a
  nonexistent ID (identical 404 either way), and the query never
  touches `lawyer_verifications` at all, so there's no path from this
  controller to CNIC/face/rejection-reason/reviewer data.
- Mass assignment: this phase is read-only (no `create`/`update` calls
  at all in `LawyerDirectoryController`).
- XSS: all output uses default escaped Blade (`{{ }}`) — no `{!! !!}`
  was introduced anywhere in the new views.
- No debug statements, no secrets, no `.env` changes — verified by
  grep across every new file.

### Strict phase boundary (confirmed not implemented)

Appointment booking/scheduling, chat, the AI assistant/matching
engine, payments, advanced admin analytics, and new case-management
features are **not** part of this pass — the profile page shows no
"book" action and no fabricated availability/review data.

---

## Phase 5 Half 2 — Lawyer Matching Foundation, Match Scoring & AI-Ready Data Layer

> **This is deterministic matching infrastructure. It is NOT the AI
> Assistant.** There is no LLM, no external AI API, no chatbot UI, no
> generated legal advice and no case-outcome prediction anywhere in this
> phase. The Phase 11 AI Assistant is expected to *call* this layer.

### What it answers

"Which approved, active lawyers best match this customer's legal
problem, budget, location, service and specialization?" — returned as
ranked lawyers, each with a 0–100 score, plain-language reasons, unmet
criteria and a per-criterion breakdown.

### Eligibility (non-negotiable)

Every match query starts from `LawyerProfile::searchable()`:
`verification_status = approved` AND `is_active` AND `is_public` AND the
owning user account `status = active`. Pending, rejected, suspended,
inactive, non-public and suspended-account lawyers can never be returned,
whatever the criteria. `isEligible()` mirrors the same rule for a single
instance.

> Phase 5 Half 2 **tightened** the existing scope by adding the
> owning-account check (an EXISTS subquery on `users`). This also applies
> to the Half 1 directory and public profile: a lawyer whose *user
> account* is suspended/inactive is now hidden there too (profile → 404).

### Input criteria (`App\Services\Matching\MatchCriteria`)

| Query param | Meaning | Notes |
|---|---|---|
| `problem` | free-text legal problem | ≤ 500 chars; see "Legal problem reading" |
| `service` | `services.id` | must exist and be active |
| `specialization` | `specializations.id` | must exist; must belong to `service` if both sent |
| `city` | city name | exact match, case-insensitive |
| `min_budget`, `max_budget` | consultation-fee range (Rs.) | `max ≥ min` when both sent |
| `min_experience` | minimum years | 0–70; `0` = not requested |
| `available_day` | 0 (Sunday) … 6 (Saturday) | needs a recurring, available slot |
| `strict[]` | `budget`, `location`, `experience` | turn that criterion into a hard filter |
| `limit` | results returned | 1–50, default 10 |

At least one *criterion* (not `strict`/`limit`) is required. A criterion
that is not supplied is never scored (`matches.<name>` is `null`).

### Scoring

`score = round( Σ weight × fraction  /  Σ weight of the SUPPLIED criteria × 100 )`

So the score is always an integer 0–100, and a lawyer satisfying
everything that was asked scores exactly 100 regardless of how many
criteria were used. Weights live in `config/lawyer_matching.php` (only
their ratios matter; 0 disables a component):

| Component | Weight | Fraction |
|---|---|---|
| service | 25 | 1 if the lawyer offers it, else 0 |
| specialization | 20 | 1 if offered, else 0 |
| problem | 20 | best tier: specialization-direct 1.00 · service-direct 0.90 · specialization-related 0.80 · service-related 0.65 · profile-text 0.35 |
| location | 15 | 1 if same city, else 0 |
| budget | 10 | within range 1.0 · near (within 20 % of the violated bound) 0.5 · outside / no fee 0 |
| experience | 7 | below minimum 0 · meets 0.70 · exceeds → up to 1.0 over 10 extra years |
| availability | 3 | 1 if a recurring available slot exists that weekday |

Ranking is a total order: score desc → years of experience desc →
display name → id, so identical input always yields identical output.
Rounding is only for display; ordering uses the unrounded value.

### Strict vs flexible (budget, location, experience)

* **Flexible (default):** the criterion only affects the score. A lawyer
  outside the budget is still returned (with a `gaps` entry) as long as
  they match the topic — the result set is not emptied by a budget.
* **Strict (`strict[]=budget`):** a hard SQL filter using the existing
  `minFee` / `maxFee` scopes (`inCity` semantics for location,
  `minExperience` for experience). A lawyer with no listed fee is
  excluded by strict budget, and never counted as within budget.

### Topical gate

If a service, specialization or usable problem was supplied, a lawyer
must match at least one of them to be a candidate — the right city or
fee never rescues the wrong area of law. With no topical criterion, a
lawyer must satisfy at least one supplied location / budget / experience
/ availability criterion. Lawyers scoring 0 are dropped.

### Legal problem reading (`ProblemKeywordResolver`)

Conservative word/phrase matching — **not** NLP, **not** AI:

1. **Name match** against real services (whole name or a distinctive word,
   e.g. "criminal" → Criminal Law) and specializations (whole phrase only
   — generic words like "disputes" are shared by unrelated
   specializations and would false-match).
2. **Synonym table** `problem.keywords` in the config (phrase → service /
   specialization slugs, e.g. "inheritance" → Family Law, Property Law).
   Unknown slugs are ignored; nothing is invented.
3. **Profile text**: remaining meaningful words are searched in the
   lawyer's own title / bio / experience summary (weakest tier).

Plural forms are folded on both sides. Filler-only text ("I need a
lawyer") is ignored rather than matching everyone. Service/specialization
*descriptions* are intentionally not used (marketing prose → false
positives). New admin-added services/specializations are matched by name
automatically.

### Reasons and gaps

Every entry in `reasons` is generated from a condition that actually held
for that lawyer ("Offers the requested service: Family Law", "Based in
Lahore", "Consultation fee (Rs. 2,500) is within your budget", …).
Requested-but-unmet criteria appear in `gaps`. "Near" budget is worded
"close to your budget", never "within".

### Result structure

`MatchResultSet` → `matches(): Collection<LawyerMatch>` and `meta()`.
Each `LawyerMatch`: `lawyer` (model, public columns only), `score`,
`reasons`, `gaps`, `matches` (`true` / `false` / `null` = not requested,
keys `service, specialization, problem, location, budget, experience,
availability`), `breakdown` (weight / fraction / points per criterion,
plus `fit` for budget and `tier` for problem). `meta`: applied criteria,
applied components + weights, how the problem text was read,
`candidates_considered`, `candidate_cap_reached`, `returned`.

### Endpoint

`GET /lawyers/match` (`lawyers.match`) — public, read-only, throttled
(30/min), registered before `/lawyers/{lawyer}`.

* Browser: an internal results page in the existing design language
  (form + ranked cards with score and reasons). Invalid input redirects
  back to the form with errors. No params → form only.
* JSON (`Accept: application/json`): `{ "data": [ {lawyer, score,
  reasons, gaps, matches, breakdown} ], "meta": {...} }`; invalid input
  → 422; no criterion → 422.

```
GET /lawyers/match?problem=divorce&city=Karachi&max_budget=5000&strict[]=budget
```

`LawyerMatchResource` is an explicit allow-list: id, name, title, city,
years of experience, consultation fee, 200-char bio excerpt, profile
image URL, profile URL, services, specializations. It never contains
CNIC / face images / verification paths, rejection reasons, admin notes,
reviewer info, email, phone, address, license or bar-council data — and
the service does not even `SELECT` those columns.

### Directory integration

`/lawyers` (search, filters, sorting, pagination, profile pages) is
unchanged in behaviour. It and the matcher share the single
`searchable()` eligibility scope and the same `minFee` / `maxFee` /
`minExperience` scopes; the directory controller was deliberately not
refactored onto the matcher because it needs hard filters + sort +
pagination, not ranking.

### Performance

Candidates are narrowed in SQL (eligibility + topical/criteria gate +
strict filters), services/specializations are eager-loaded and
availability is a `withExists` subquery, so the number of queries is
constant (tested: identical with 3 and 43 lawyers). Scoring in PHP runs
only on that narrowed set, hard-capped at `limits.candidate_cap` (300);
`meta.candidate_cap_reached` reports when the cap bit.

### Phase 11 AI boundary

Phase 11 should call `LawyerMatchingService::match(MatchCriteria)` from
server-side code (not via HTTP) after *extracting structured criteria*
from the customer's conversation. The AI may choose criteria and phrase
explanations; it must not re-implement eligibility or ranking, and the
`reasons`/`gaps`/`matches` here are the ground truth to present.

### Fixes to earlier phases found while running the real test suite

1. `LawyerDirectoryRequest`: `gte:min_fee` rejected a lone `?max_fee=…`
   (Laravel's `gte` fails when the other field is absent) — now applied
   only when `min_fee` is sent.
2. `composer.json` had no `autoload-dev` for `Tests\` — added.
3. `LoginTest` / `SecurityTest` expected a direct dashboard redirect; the
   app (unchanged) redirects via `dashboard.redirect`. Tests corrected.
4. `OnboardingTest` rollback test mocked the whole `DB` facade and could
   not pass; rewritten to fail inside the real transaction and to assert
   uploaded files are cleaned up.

### Tests

`tests/Feature/Matching/*` (99 tests: eligibility, scoring, problem
reading, endpoint/validation, security, performance) and
`tests/Feature/Lawyers/EligibilityScopeTest.php` (4).

**What was actually executed:** the full suite — **233 tests / 986
assertions, all passing** — on PHPUnit 11.5.56, PHP 8.3.6, SQLite
in-memory, plus `migrate:fresh --seed` on SQLite. Because
`repo.packagist.org` returns HTTP 403 in the build sandbox, `vendor/` was
assembled from GitHub tags via Composer path repositories (Laravel
framework from the 12.x branch), **not** resolved from Packagist.
Please re-run `composer install && php artisan test` in your own
environment. Not run: MySQL (the project targets MySQL; tests use
SQLite), browser/visual checks of the new page.

### Strict phase boundary (confirmed not implemented)

No OpenAI/LLM/external AI, no chatbot UI, no legal advice or outcome
prediction, no appointment booking, payments, lawyer–client chat or
advanced analytics.

## Phase 6 Part 1 — Lawyer Availability + Appointment Booking Foundation

**Scope.** Lawyer availability management (CRUD) and the customer
appointment *request* foundation only. Lawyer accept/reject,
rescheduling, cancellation, expanded appointment history/notifications,
calendar UI, chat, payments, AI and case management are explicitly
**Phase 6 Part 2** and are not implemented here.

### Source of truth / groundwork reused

Built directly on `LegalConnect-Phase-5-Half-2__1_.zip`. The
`appointments` and `lawyer_availabilities` tables, the `Appointment`
and `LawyerAvailability` models, their factories, and the
`AppointmentStatus` / `ConsultationType` enums already existed as
Phase 2 groundwork and needed **no migration changes** — every column
this phase needed (`day_of_week`, `specific_date`, `start_time`,
`end_time`, `is_recurring`, `is_available`, `reference`, `customer_id`,
`lawyer_profile_id`, `service_id`, `status`, `reason`) was already
there. Lawyer eligibility reuses `LawyerProfile::scopeSearchable()` /
`isEligible()` exactly as Phase 5 defined it — nothing here re-derives
"approved + active + public + owning account active".

### Availability architecture

- A `LawyerAvailability` row is either a **weekly recurring** window
  (`day_of_week` 0–6, Sunday=0, matching the convention already used by
  `LawyerMatchingService::DAY_NAMES`) or a **one-off date override**
  (`specific_date`) — never both, never neither. This is enforced in
  `StoreLawyerAvailabilityRequest`/`UpdateLawyerAvailabilityRequest`,
  not just in the UI.
- **Overlap is rejected**, not just discouraged: two windows for the
  same lawyer on the same `day_of_week` (or the same `specific_date`)
  cannot have overlapping `[start_time, end_time)` ranges. Back-to-back
  windows (one ends exactly when another begins) ARE allowed — this is
  a half-open interval check.
- **CRUD**: `GET /lawyer/availability` (list), `GET|POST
  /lawyer/availability/create` (add), `GET|PUT
  /lawyer/availability/{id}/edit` (edit), `PATCH
  /lawyer/availability/{id}/toggle` (activate/deactivate), `DELETE
  /lawyer/availability/{id}` (remove). All under the existing
  `role:lawyer` + `verified` route group.
- **Ownership**: every action is scoped to
  `$request->user()->lawyerProfile` (never a request-supplied id) *and*
  independently gated by `LawyerAvailabilityPolicy` on the specific
  record. A lawyer with no completed onboarding is redirected to
  onboarding rather than shown an empty/broken page.
- UI: `resources/views/lawyer/availability/{index,create,edit}.blade.php`,
  built entirely from the existing design system (`.panel`, `.field`,
  `.data-table`, `.btn`, `layouts.minimal`) — no new CSS, no new JS
  framework. A "Manage availability" link was added to the existing
  lawyer dashboard panel.

### Appointment booking foundation

- `App\Services\AppointmentAvailabilityService` is the single reusable
  place for: availability-window lookup for a date
  (`windowsForDate()`), whether a time range sits inside availability
  (`isWithinAvailability()`), conflict detection
  (`hasConflict()` — pure SQL, never loads every appointment into PHP),
  free-slot generation (`availableSlots()`), and the transactional
  `book()` method. **This is the class Phase 6 Part 2 should extend**
  for reschedule/accept-reject logic rather than duplicating any of
  this.
- **Fixed 30-minute consultation duration**, centralized in
  `config/appointments.php` (`default_duration_minutes`), along with
  the booking horizon (`booking_window_days`, default 60 days) — no
  hardcoded duration numbers anywhere else.
- **Customer identity**: always `auth()->user()`. `customer_id` is
  never a request field, on any form or endpoint.
- **Lawyer identity**: always the `{lawyer}` route segment, re-resolved
  through `LawyerProfile::query()->searchable()->findOrFail()` in the
  controller *and* independently re-checked inside
  `StoreAppointmentRequest::withValidator()`. A
  pending/rejected/inactive/suspended-account lawyer id fails
  validation with a `lawyer` error (`422`/redirect-with-errors) on the
  booking `POST`, and 404s on the `GET` create/slots endpoints (which
  have no Form Request layer to validate against).
- **Service validation**: the submitted `service_id` must belong to
  the resolved lawyer's own `services()` relation (and be active) —
  checked once in the Form Request, then re-checked in the controller
  via `$lawyer->services()->active()->findOrFail($id)` before booking,
  so an id that happens to exist but isn't offered by this lawyer can
  never slip through.
- **Date/time validation**: `appointment_date` must be
  today-or-later (app timezone, via `now()`/`Carbon`, never a
  client-supplied timezone) and within the configured booking window;
  a same-day date whose `start_time` has already passed is rejected
  explicitly; `start_time` must be a valid `H:i`, and the resulting
  slot must fall entirely inside one of the lawyer's availability
  windows for that date.
- **Conflict detection**: `hasConflict()` uses the standard half-open
  overlap test (`existing.start < new.end AND existing.end > new.start`)
  run entirely in SQL against `appointments` filtered to that lawyer +
  date + not cancelled/rejected. This correctly rejects exact
  duplicates, partial overlaps, and either interval containing the
  other, while allowing back-to-back appointments and appointments for
  a *different* lawyer at the same time.
- **Transaction / double-booking race protection**: `book()` runs
  inside `DB::transaction()`. Before the availability/conflict checks
  are (re-)run, the lawyer's appointments for that date are pulled
  with `lockForUpdate()`. On MySQL (this project's target database)
  this takes row locks that serialize two concurrent booking attempts
  for the same lawyer/date, so the second transaction's conflict check
  only runs after the first commits or rolls back — closing the
  classic check-then-insert race. **Caveat, stated plainly**: SQLite,
  used by this project's automated test suite, does not implement
  row-level locking the same way (`lockForUpdate()` is a no-op there),
  so genuine concurrent-request races are only fully guarded against
  under the MySQL configuration the application actually runs on in
  production — the tests verify the *logical* conflict rules, not
  real concurrency, since a single SQLite test connection cannot
  simulate two simultaneous transactions meaningfully. Any exception
  anywhere in `book()` rolls back the whole transaction — there is
  no partial appointment row on any failure path.
- **Initial status**: every new appointment request is created with
  `AppointmentStatus::PENDING` — nothing here ever auto-confirms an
  appointment. The accept/reject transition is Phase 6 Part 2.
- **Routes** (all under `role:customer` + `verified`):
  `GET /customer/appointments` (own appointments), `GET
  /customer/appointments/{appointment}` (own appointment detail, via
  `AppointmentPolicy::view`), `GET /customer/lawyers/{lawyer}/book`
  (booking form), `GET /customer/lawyers/{lawyer}/slots` (JSON —
  server-computed free slots for a date), `POST
  /customer/lawyers/{lawyer}/book` (create the request).
- UI: `resources/views/customer/appointments/{create,index,show}.blade.php`,
  again built from the existing design system. The booking form uses a
  small vanilla-JS `fetch()` call (no SPA framework) against the slots
  endpoint to populate the time dropdown as the date changes — the
  slots endpoint is the actual source of truth the `store()` action
  re-derives independently, so a stale/tampered client-side slot list
  can never bypass server-side validation. A "Request appointment" CTA
  was added to the existing public lawyer profile page
  (`resources/views/lawyers/show.blade.php`, inside the previously
  unused `.profile-actions` slot the design already had CSS for).

### Security decisions

- **IDOR**: `AppointmentPolicy` restricts `view()` to the appointment's
  own `customer_id` (for a customer) or the appointment's own
  `lawyer_profile_id` matched against `auth()->user()->lawyerProfile`
  (for a lawyer) — nobody else, no admin blanket access in this phase.
  `LawyerAvailabilityPolicy` restricts every action to the
  authenticated lawyer's own `lawyerProfile->id`. Both policies are
  additionally backed by route-scoped queries (never trusting policy
  alone).
- **Mass assignment**: `Appointment`/`LawyerAvailability` fillable
  lists do include foreign keys (`customer_id`, `lawyer_profile_id`),
  which is why they are **only ever set from server-resolved values**
  (`auth()->user()`, the route-resolved+re-validated `LawyerProfile`,
  the lawyer's own `services()` relation) inside
  `AppointmentAvailabilityService::book()` and
  `AvailabilityController` — never from `$request->all()` or a raw
  `$request->validated()` array merged with request input. A field
  like a spoofed `customer_id`/`lawyer_profile_id`/`service_id` in the
  POST body is simply never read.
- **CSRF**: every form uses `@csrf`; all mutating routes are `POST`/
  `PUT`/`PATCH`/`DELETE`.
- **XSS**: all Blade output uses `{{ }}` escaping; no `{!! !!}` was
  introduced.
- **SQL injection**: no raw SQL string interpolation was introduced;
  every new query uses the query builder / Eloquent with bound
  parameters.
- **Status tampering**: `status` is never a field a customer can set;
  it is hardcoded to `PENDING` inside `book()`.
- **Timezone**: every date/time comparison uses `now()`/`Carbon`
  against the application's configured `config('app.timezone')` — no
  client-supplied timezone or client clock is trusted for "is this in
  the past" checks.

### Tests

New: `tests/Feature/Lawyer/AvailabilityTest.php`,
`tests/Feature/Customer/AppointmentBookingTest.php`,
`tests/Feature/Appointments/{ConflictTest,SecurityTest,TransactionTest}.php`.
Full list of what they cover is in the class docblocks; see also the
"Test execution" note below for **what was actually run**.

### Test execution — read this before trusting green checkmarks

**No PHP runtime is available in the sandbox this phase was built in**
(no `php`, no `composer` binary at all — a stronger restriction than
the Packagist-403 workaround earlier phases used, where PHP/Composer
existed but `repo.packagist.org` didn't). This means, honestly:

- PHPUnit was **not run**.
- `php -l` was **not run** (no `php` binary to run it with).
- What **was** done instead: every new/changed PHP file was checked
  with a Python-based bracket/paren/quote-balance analyzer (comments
  stripped before strings, to avoid false positives from apostrophes
  in prose) — all files balanced. Every new Blade file's
  `@section`/`@endsection`, `@foreach`/`@endforeach`, `@if`/`@endif`,
  `@auth`/`@endauth`, `@push`/`@endpush` pairs were checked and
  matched (the one apparent "mismatch" — two `@section(` calls but one
  `@endsection` — is the standard, already-used-elsewhere Blade
  pattern of one inline `@section('title', '...')` plus one block
  `@section('content') ... @endsection`, not a bug). Every new class's
  namespace/class name was checked against its file path. Every model
  relation, scope, enum case, and factory state this phase's code and
  tests rely on (`isCustomer()`/`isLawyer()`, `lawyerProfile()`,
  `appointmentsAsCustomer()`, `scopeSearchable()`, `scopeActive()`,
  `AppointmentStatus`/`ConsultationType` case values, factory
  `approved()`/`pending()`/`rejected()`/`suspended()` states) was
  cross-checked by reading the actual source, not assumed from memory.
  Every route name used in a test or a view was cross-checked against
  `routes/web.php`. Every middleware behavior a test relies on
  (`EnsureUserHasRole` → 403 for wrong role / redirect-to-login for
  guest; `EnsureAccountIsActive` → logout + redirect-to-login mid
  session) was confirmed by reading the actual middleware source, not
  assumed.
- **This is a real limitation, not a formality**: static
  balance-checking and source cross-referencing catch a large class of
  mistakes (typos in route/method names, wrong enum values, unbalanced
  syntax, wrong assumption about middleware order) but cannot catch
  everything a real PHPUnit run would (e.g. a subtle Eloquent query
  bug that's syntactically valid but returns the wrong rows). **Please
  run `composer install && php artisan test` in a real environment
  before treating this phase's test suite as verified-green.**

### Regression

Nothing in Phases 1–5 Half 2 was modified except: `routes/web.php`
(additive route registrations only — no existing route changed),
`resources/views/dashboard/lawyer.blade.php` (one new panel added, the
existing "Your account" panel's copy was updated to remove
"appointments, availability" from its now-inaccurate list of
not-yet-built things), and `resources/views/lawyers/show.blade.php`
(one new `.profile-actions` block added inside the existing
`.profile-hero` `<section>`, using CSS classes the stylesheet already
defined but which were previously unused). No migration, model, enum,
factory, seeder, middleware, or existing controller/policy from any
earlier phase was changed. All Phase 1–5 Half 2 routes, views, and
tests are untouched.

### Strict phase boundary (confirmed not implemented)

No lawyer accept/reject workflow, no reschedule, no cancellation, no
expanded appointment history/notifications beyond the pending request
itself, no calendar UI, no chat, no payments, no AI, no case
management. These are Phase 6 Part 2.

## Phase 6 Part 2 — Complete Appointment Management

### Source of truth / groundwork reused

Built directly on top of Phase 6 Part 1's `Appointment` model,
`AppointmentAvailabilityService`, `AppointmentPolicy` shell, and the
existing `appointments` migration — no duplicate appointment system,
status enum, or availability logic was created. The `appointments`
table already carried `cancellation_reason` and `rescheduled_from_id`
columns from Part 1, so **no new migration was needed**; both fields
were simply put to use.

### Appointment lifecycle (single source of truth)

`App\Enums\AppointmentAction` is the one place the lifecycle is
defined — one enum case per user-facing action (`confirm`, `reject`,
`cancel`, `complete`, `reschedule`), each declaring which roles may
take it, which statuses it may start from *per role*, and whether it
requires the start time to still be in the future or to have already
passed:

| Action     | From                 | To          | Who                            |
|------------|----------------------|-------------|---------------------------------|
| confirm    | pending              | confirmed   | assigned lawyer                |
| reject     | pending              | rejected    | assigned lawyer                |
| cancel     | pending, confirmed   | cancelled   | customer                       |
| cancel     | confirmed            | cancelled   | assigned lawyer                |
| complete   | confirmed (started)  | completed   | assigned lawyer                |
| reschedule | pending, confirmed   | rescheduled | customer or assigned lawyer    |

A lawyer only ever *answers* a pending request (confirm/reject); a
lawyer's own cancellation right only applies once they have already
confirmed it — this mirrors how the master prompt frames the lawyer
side ("accepts or rejects") versus the customer's broader
"pending/accepted → cancelled" rights. `rejected`, `cancelled`, and
`completed` are terminal; `rescheduled` is also terminal for the *old*
row (see below).

`App\Services\AppointmentStatusService` is the **only** code that ever
assigns `Appointment::$status`. No controller, Form Request, or view
ever reads `$request->status` — every mutating route maps to exactly
one of `confirm()`, `reject()`, `cancel()`, `complete()`,
`reschedule()` on this service, which re-authorizes via
`AppointmentPolicy` (`Gate::forUser($actor)->authorize(...)`) and
re-validates the *current, freshly locked* row against
`AppointmentAction::allowedFrom()` before applying anything.

### Rescheduling model

Rescheduling does **not** edit the existing row's date/time in place.
It sets the old appointment's status to `rescheduled` (which
immediately frees its slot — see `AppointmentStatus::slotBlocking()`)
and creates a **new** `Appointment` row with `rescheduled_from_id`
pointing back at it, carrying over service/consultation
type/reason. A customer-initiated reschedule produces a new `pending`
row (the lawyer must confirm it again, exactly like a fresh booking);
a lawyer-initiated reschedule produces a new `confirmed` row (the
lawyer is the authority on their own calendar, and the customer is
notified and can still cancel or reschedule it again). This preserves
full history (`appointment->rescheduledFrom` /
`appointment->rescheduledTo`) instead of silently mutating a booking
record, and lets the exact same
`AppointmentAvailabilityService::assertSlotAvailable()` conflict/
availability check that booking uses be reused unchanged, with
`$excludeAppointmentId` set to the row being moved so it can never
conflict with itself (requirement: exclude current appointment from
the conflict query).

### Conflict protection, locking and concurrency

`AppointmentAvailabilityService::assertSlotAvailable()` is now the one
method that answers "may this lawyer be booked for this interval" —
`book()` (Part 1), `confirm()`, and `reschedule()` (Part 2) all call
it, inside a transaction, with the same half-open-interval overlap SQL
query used since Part 1 (`hasConflict()`, unchanged), so partial
overlap, containing overlap, contained overlap, and exact-match
overlap are all rejected identically everywhere, and exact
back-to-back slots are still allowed everywhere.

Every action that claims a slot (`book`, `confirm`, `reschedule`)
first calls `AppointmentAvailabilityService::lockLawyerCalendar()`,
which takes a `lockForUpdate()` row lock on the **lawyer's profile
row** (not the appointment being changed — a lock on a row that may
not exist yet, e.g. an empty date, protects nothing). On MySQL/InnoDB
this serializes concurrent state changes for the same lawyer: a
second transaction for that lawyer blocks until the first commits or
rolls back, and only then runs its own conflict check against the
now-committed data. **SQLite, the database the automated test suite
runs against, does not support row-level locking**, so this
protection is real on MySQL (the intended production database, per
`config/database.php`'s default) but not exercised by the test suite
itself — documented honestly rather than claimed as verified by a
passing test. Every status-changing method additionally takes
`lockForUpdate()` on the **appointment row itself** before re-checking
its status, so two simultaneous "confirm" and "cancel" (or two
simultaneous reschedules) requests for the same appointment cannot
both succeed — the second one re-reads the row after the first
commits and finds it in a state its action no longer allows.

### Timezone handling

`appointment_date` and `start_time`/`end_time` are stored as plain
date/time-of-day values with no UTC offset. `Appointment::startsAt()`
/ `endsAt()` (new in Part 2) parse them explicitly against
`config('app.timezone')` — the same zone Laravel's `now()` helper
uses throughout the app — so every "has this started", "is this in
the future", and "is this too far out" comparison in
`AppointmentStatusService` and the reschedule Form Request compares
like with like. No raw string date comparisons are used anywhere in
the new code; every comparison goes through a `Carbon` instance.

### Lawyer appointment management

`Lawyer\AppointmentController`: `index()` (own appointments only,
scoped through `$request->user()->lawyerProfile->appointments()`,
paginated, filterable via `Appointment::scopeFilter()` —
`all` / `upcoming` / one of the six status values), `show()` (policy
`view`), `confirm()`, `reject()`, `cancel()`, `complete()`,
`rescheduleForm()` / `rescheduleSlots()` (JSON, same slot-picker
pattern as booking) / `reschedule()`. A lawyer with no `LawyerProfile`
yet is redirected to onboarding rather than erroring. Eager loads
`customer:id,name,email` and `service:id,name` on the index to avoid
N+1; `show()` additionally loads `rescheduledFrom`/`rescheduledTo`.

### Customer appointment management

`Customer\AppointmentController` (Part 1's booking controller,
extended): `index()` now supports the same filter query string,
`show()` now surfaces the actions available (`$actions` computed by
`AppointmentStatusService::availableActions()`, itself just
`AppointmentPolicy` + the enum's transition table — never a second,
divergent set of rules), plus `cancel()`, `rescheduleForm()` /
`rescheduleSlots()` / `reschedule()`. No new admin-style panel — same
`layouts.minimal` shell, `.panel`/`.data-table`/`.status`/`.btn`
classes already defined in `public/css/legal-connect.css`, no new CSS
file, no Tailwind/React/Vue.

### Authorization

`AppointmentPolicy` (Part 1's `view`, extended in Part 2):
`confirm`/`reject`/`cancel`/`complete`/`reschedule` abilities, each
checking the acting user's role is even eligible for that action
(`AppointmentAction::allowedRoles()`) *and* that `view()` passes
(active account + ownership: customer must own `customer_id`, lawyer
must own `lawyer_profile_id` via their own `lawyerProfile`). Admin
appointment oversight was deliberately **not** added — it isn't part
of the existing product design, matching the instruction not to grant
it without an explicit existing requirement. Auto-discovered by
Laravel's model→policy naming convention, exactly like
`LawyerAvailabilityPolicy` already was in Part 1 — no explicit
`Gate::policy()` registration needed or added.

Every mutating route sits under the same `['auth','active']` outer
group and `role:customer|lawyer` + `verified` inner groups Part 1
established; every one additionally calls `$this->authorize(...)` (or
goes through `AppointmentStatusService`, which calls
`Gate::forUser($actor)->authorize(...)` itself) — two independent
layers, so a route ever reachable by the wrong role/account still 403s
at the policy layer even if a middleware group were misconfigured.

### Form Requests

`CancelAppointmentRequest` (optional `reason`, max 1000 chars) and
`RescheduleAppointmentRequest` (`appointment_date` — `Y-m-d`,
today..booking-window; `start_time` — `H:i`) validate shape only.
Neither trusts nor accepts `status`, `customer_id`, `lawyer_id`, or
any reviewer/internal field — those are never referenced from
`$request` anywhere in the new controllers; `status` is set exactly
once per action, inside `AppointmentStatusService`, from a hardcoded
`AppointmentAction::targetStatus()`, never from user input. A
dedicated test (`test_status_cannot_be_set_directly_through_the_cancel_request`,
`test_reschedule_request_cannot_smuggle_a_status_or_ownership_change`)
posts a spoofed `status`/`customer_id` field alongside a legitimate
cancel/reschedule request and asserts it has no effect.

### Notifications

`App\Notifications\AppointmentNotification` (database channel only,
same pattern as the existing lawyer-verification notifications — no
mail server required for local development) is dispatched by
`AppointmentStatusService` after each transaction **commits** (never
inside the transaction, and a delivery failure is reported via
`report()` but never rolls back or hides the state change the user
just made): booking → lawyer, confirmed/rejected → customer,
cancelled/rescheduled → whichever party didn't perform the action. The
notification payload is deliberately minimal: an event name, a fixed
human-readable message, the appointment id/reference, and its
date/time only — never the customer's notes/reason, the cancellation
reason, contact details, CNIC/face verification images, or any
internal/admin metadata. Verified by
`test_notification_payload_carries_no_sensitive_data`.

### Security decisions (Part 2 additions)

- **IDOR**: every `show`/action route resolves `{appointment}` via
  route-model binding and then re-checks ownership through
  `AppointmentPolicy::view()` — a valid appointment ID belonging to
  someone else 403s regardless of which role's URL prefix it's
  requested under. A customer hitting a `/lawyer/*` route (or vice
  versa) is blocked earlier, at `role` middleware, with a 403.
- **Status tampering**: covered above — no code path assigns `status`
  from request input.
- **Mass assignment**: `Appointment::$fillable` is unchanged from Part
  1; nothing in Part 2 calls `Appointment::create()`/`update()` with
  raw `$request->all()` — every write uses explicit `validated()`
  keys or a hardcoded enum value.
- **CSRF**: all mutating routes are standard `POST` routes inside the
  default `web` middleware group, so Laravel's CSRF verification
  applies automatically; every form includes `@csrf`.
- **XSS**: all appointment data is rendered through Blade's default
  `{{ }}` escaping; nowhere in the new views is `{!! !!}` used.
- **SQL injection**: no raw SQL/string interpolation was added — every
  new query is parameterized Eloquent/query-builder (`whereDate`,
  `whereIn`, `where('start_time', '<', $end)`, etc.).
- **Race conditions / double booking**: see "Conflict protection,
  locking and concurrency" above.
- **Transaction integrity**: every state-changing service method
  (`confirm`, `reject`, `cancel`, `complete`, `reschedule`) runs
  entirely inside `DB::transaction()`; on any exception (validation
  failure, authorization failure) nothing is written.

### Tests

New: `tests/Feature/Appointments/LifecycleTest.php` — 30+ tests
covering: customer/lawyer viewing their own vs. IDOR on another
party's appointment, guest redirect, cross-role route blocking,
confirm (success, wrong role, wrong lawyer, wrong starting status ×2),
reject (success, wrong starting status), cancel (customer from
pending/confirmed, lawyer only from confirmed not pending, terminal
statuses rejected, IDOR), complete (only after start, not before),
reschedule (customer→pending, lawyer→confirmed, into an occupied slot
rejected, excludes itself from its own conflict check, exact-same-time
rejected, past date rejected, terminal statuses rejected, IDOR),
status-tampering resistance (×2), notification payload
content, and status-filtered/scoped listings. Existing Part 1 tests
(`Customer/AppointmentBookingTest`, `Appointments/ConflictTest`,
`Appointments/SecurityTest`, `Appointments/TransactionTest`) were left
untouched and still exercise the booking foundation this phase builds
on.

### Test execution — read this before trusting green checkmarks

This sandbox **does** have a working `php` binary this time (unlike
Part 1's environment), so:

- **`php -l` was actually run** against every PHP file in the project
  (`app/`, `routes/`, `database/`, `tests/`) — all pass, zero syntax
  errors.
- **PHPUnit was NOT executed.** `composer install` cannot run:
  `repo.packagist.org` and `getcomposer.org` are both blocked by this
  sandbox's network egress policy (`x-deny-reason: host_not_allowed`
  on both), and no `vendor/` directory (hence no Composer autoloader,
  no PHPUnit binary) ships with or can be reconstructed for this
  environment. This is the same category of limitation Phase 5 Half 2
  hit with `repo.packagist.org` returning 403, one level more
  restrictive.
- What was done instead, beyond `php -l`: every new class's
  namespace/method signatures were cross-checked by reading the actual
  source of everything they call (`AppointmentAvailabilityService`'s
  real method names/signatures, `Appointment`'s real relations/casts/
  fillable list, `User`/`LawyerProfile`'s real relation names,
  `EnsureUserHasRole`'s real 403-vs-redirect behavior, the real
  `appointments` table columns) rather than assumed from memory or
  from this document's own earlier prose. Every route name referenced
  in a new controller, view, or test was cross-checked against the
  actual `routes/web.php` this phase produced. Every Blade
  `@if`/`@endif`, `@foreach`/`@endforeach`, `@push`/`@endpush` pair in
  every new/changed view was read back and matched.
- **This is a real limitation, not a formality.** Static
  cross-referencing and `php -l` catch typos, wrong method/route
  names, unbalanced syntax, and wrong assumptions about how existing
  code behaves — they cannot catch a subtly wrong Eloquent query that
  is syntactically valid but returns the wrong rows, or a genuine
  runtime-only bug. **Please run
  `composer install && php artisan test` in a real environment before
  treating this phase's test suite as verified-green.**

### Regression

Confirmed unmodified: every Phase 1–5 Half 2 file, and every Phase 6
Part 1 migration, factory, and enum. Phase 6 Part 1 files that Part 2
**extended** (never replaced) are: `app/Models/Appointment.php` (added
scopes/helpers only — every Part 1 field, relation, and cast is
untouched), `app/Services/AppointmentAvailabilityService.php` (`book()`
now calls two new extracted methods, `lockLawyerCalendar()` and
`assertSlotAvailable()`, instead of inlining the same two checks —
behavior and its public signature are unchanged; `hasConflict()` now
reads its "still occupies the calendar" status list from
`AppointmentStatus::slotBlockingValues()` instead of a hardcoded
`whereNotIn`, which is the same three statuses (`pending`, `confirmed`,
`completed`) Part 1 already blocked on; `availableSlots()` gained one
new optional trailing parameter, `$excludeAppointmentId`, defaulted to
`null`, so every existing call site is unaffected),
`app/Http/Controllers/Customer/AppointmentController.php` (new methods
added; `store()` gained one new line calling
`AppointmentStatusService::notifyBooked()` after the existing booking
call — the booking logic itself is untouched), `routes/web.php`
(additive route registrations only, inside the existing route groups —
no existing route changed), `resources/views/dashboard/lawyer.blade.php`
and `resources/views/dashboard/customer.blade.php` (one new panel
added to each; the "still the existing frontend demo" placeholder copy
was updated to drop "appointments" from its now-inaccurate list, since
appointments are real as of Part 1/Part 2). No migration, factory,
seeder, or middleware from any earlier phase was changed. All Phase
1–6 Part 1 routes, views, and tests are untouched and still pass their
own static verification.

### Strict phase boundary (confirmed not implemented)

No admin appointment oversight (not part of the existing product
design), no chat, no payments, no AI, no case management, no document
management expansion, no video calls, no subscriptions/billing, no
advanced analytics. These remain later phases.

## Phase 7 — Case Management + Secure Document Management

### Source of truth / groundwork reused

Built directly on the `LegalCase`/`CaseDocument` models, migrations,
factories, and `CaseStatus` enum Phase 2 already established — no
duplicate case table, document table, enum, storage system, or
relationship was created. `LegalCase` (the name Phase 2 chose, since
`Case` is a reserved PHP word) is preserved as-is. `User::casesAsCustomer()`,
`User::casesAsLawyer()`, `User::uploadedDocuments()`, and
`LawyerProfile::cases()` already existed and needed no changes.
`App\Services\LawyerFileUploadService` (built in Phase 4 for CNIC/face
verification uploads) is reused unchanged for case documents — its
logic was already generic (UUID filename, private disk, directory
parameter), not lawyer-specific despite the name, so extracting a
"new" service would have been a pointless duplicate.

**One schema addition, deliberately minimal:** `CaseStatus` gained a
`CANCELLED` case. The original five values (`pending`, `active`,
`in_progress`, `completed`, `closed`) are untouched, so every Phase 6
row and the Phase 2/6 `DomainSchemaTest` assertions against them still
pass unchanged. `CANCELLED` was added because nothing else in the
original five could represent "this case will not proceed" (a
customer withdraws before a lawyer engages, a lawyer cannot take it
on, or an admin halts a disputed case) — every other value implies
either ongoing or successful work. No second status enum was created;
`cases.status` remains a single `string(20)` column, so the new value
fit with no migration.

### Case lifecycle (single source of truth)

`App\Enums\CaseAction` is the one place the lifecycle is defined,
mirroring `AppointmentAction` from Phase 6 Part 2 — one case per
user-facing action, each declaring which roles may take it and which
statuses it may start from *per role*:

| Action         | From                          | To          | Who                     |
|----------------|-------------------------------|-------------|-------------------------|
| activate       | pending                       | active      | assigned lawyer         |
| start_progress | active                        | in_progress | assigned lawyer         |
| complete       | in_progress                   | completed   | assigned lawyer         |
| close          | completed                     | closed      | assigned lawyer, admin  |
| cancel         | pending                       | cancelled   | customer                |
| cancel         | pending, active               | cancelled   | assigned lawyer         |
| cancel         | pending, active, in_progress  | cancelled   | admin                   |

Cancellation reach is deliberately widest for the customer at the
narrow end and widest for admin oversight at the other: a customer may
only withdraw a request no lawyer has engaged with yet; the assigned
lawyer may step away up until the substantive work is complete; an
admin has the broadest reach, for dispute resolution, but never over a
case that has already reached a terminal status (`closed`/`cancelled`).
Reassigning a case to a different lawyer is a **separate** concept —
it never touches `status` — see "Assignment" below.

`App\Services\LegalCaseStatusService` is the **only** code that ever
assigns `LegalCase::$status` or `$lawyer_profile_id`. No controller,
Form Request, or view ever reads `$request->status`. Every mutating
route maps to exactly one of `activate()`, `startProgress()`,
`complete()`, `close()`, `cancel()`, or `reassignLawyer()`, each of
which re-authorizes via `LegalCasePolicy`
(`Gate::forUser($actor)->authorize(...)`), opens a transaction,
`lockForUpdate()`s and re-reads the case row, and re-validates the
*current, freshly locked* status against `CaseAction::allowedFrom()`
before applying anything — status-tampering via a smuggled `status`
field in a cancel/update request body is a no-op (tested explicitly).

### Case creation

A customer opens a case *with a specific lawyer*
(`GET/POST /customer/lawyers/{lawyer}/cases`), mirroring the existing
appointment-booking flow rather than inventing a new UX pattern: the
`{lawyer}` id is re-resolved through `LawyerProfile::scopeSearchable()`
in both the controller and `StoreCaseRequest::withValidator()` — the
same eligibility scope the Phase 5 directory and Phase 6 booking
already use — so a case can never be opened against a
pending/rejected/inactive/suspended lawyer profile. The customer is
always `auth()->user()->id`; `customer_id` is never accepted from
request input (tested explicitly, including a smuggled `customer_id`
in the POST body). The new case is created `pending`, and the assigned
lawyer is notified (`CaseEvent::ASSIGNED`).

### Assignment

`lawyer_profile_id` on `cases` (already present in the Phase 2 schema)
**is** the assignment mechanism — no second table or column was added
for it. A customer assigns a lawyer at creation time by choosing which
lawyer's case-creation route to submit to. An admin may **reassign** an
existing, non-terminal case to a different lawyer via
`LegalCaseStatusService::reassignLawyer()` — deliberately not a
`CaseAction` (it never changes `status`), authorized by its own
`LegalCasePolicy::assignLawyer()` ability, admin-only, and both the
previous and new assigned lawyer are notified
(`CaseEvent::REASSIGNED`). Neither the customer nor a lawyer (current
or prospective) can reassign a case themselves.

### Authorization model

`App\Policies\LegalCasePolicy` mirrors `AppointmentPolicy`'s shape: a
case is visible to the customer it belongs to, the lawyer currently
assigned to it (`user->lawyerProfile->id === case->lawyer_profile_id`,
never the other direction), and an admin. Every lifecycle-action
ability (`activate`, `start_progress`, `complete`, `close`, `cancel`)
routes through one private `mayPerform()` that checks
`CaseAction::allowedRoles()` plus the same `view()` ownership check —
ownership is defined in exactly one place. Admin oversight is
deliberately narrow (requirement: "should not automatically gain every
mutation permission merely because they have an ADMIN role"): an admin
can view any case, reassign it, close a completed one, and cancel one
for oversight — but **cannot** activate, progress, or complete a case
(that operational work belongs to the assigned lawyer), and cannot
upload a document on a party's behalf (`uploadDocuments()` explicitly
excludes admin). Deletion is a separate ability again
(`LegalCasePolicy::delete()`, admin-only), and is further restricted at
the controller level to an already-`cancelled` case with zero remaining
documents — a data-hygiene action for abandoned requests, never a way
to erase a case with real history.

`App\Policies\CaseDocumentPolicy` deliberately does **not**
re-implement case ownership: `view()`/`download()` delegate to
`Gate::forUser($user)->allows('viewDocuments', $document->case)`, so
document visibility follows the case's own policy with no second
definition to drift out of sync. `delete()` is narrower than viewing on
purpose: viewing follows the case (customer, assigned lawyer, admin),
but deleting a document is restricted to whoever actually uploaded it,
or an admin — a customer cannot delete a lawyer's upload on their own
case, or vice versa, even though both parties can see it (tested
explicitly both directions).

### Secure document management

- **Private storage.** Every case document is written to the `local`
  (private) disk via the existing `LawyerFileUploadService::store()` —
  never `public`. There is no direct public URL for a case document;
  the only way to reach one is `CaseDocumentController::download()`,
  which is authorization-gated.
- **Safe filenames.** The stored filename is always a fresh UUID
  (`LawyerFileUploadService`, unchanged from Phase 4) — the
  client-supplied filename is kept only as display metadata
  (`original_filename`) and is **never** used to build a filesystem
  path. A path-traversal-laden filename (`../../../etc/passwd.pdf`) is
  stored safely with a normal UUID path; tested explicitly.
- **MIME/extension/size validation.** `StoreCaseDocumentRequest`
  validates against a fixed allow-list from `config/cases.php`
  (`pdf, jpg, jpeg, png, doc, docx`, 10 MB cap by default). Laravel's
  `mimes:` rule checks the file's *actual detected content type*, not
  the client-supplied `Content-Type` header or filename extension
  alone — an ELF-header file renamed to `malware.pdf` is rejected
  (tested explicitly), as is a `.php` upload and an oversized file.
- **Forced-attachment download.** `CaseDocumentController::download()`
  always uses `Storage::download()` (forces `Content-Disposition:
  attachment`), never an inline response — a browser never renders an
  uploaded file's content in the app's own origin, closing the
  stored-content/XSS angle regardless of what MIME type a client
  claimed at upload time.
- **Route-level cross-check.** Every document route is
  `/cases/{case}/documents/{document}`; the controller verifies
  `$document->case_id === $case->id` (404 otherwise) *before* running
  the policy check, so combining a case the caller can see with a
  document id from an unrelated case 404s exactly like a nonexistent
  document — it never distinguishes "wrong case" from "doesn't exist"
  (tested explicitly).
- **Orphan-safe upload/delete.** Upload wraps the file write + DB
  insert so that if the DB insert fails after the file was written,
  the orphaned file is deleted (`LawyerFileUploadService::delete()`).
  Delete removes the DB row first (the actual access-control boundary)
  inside a transaction, then best-effort removes the physical file; a
  failure to remove the physical file is `report()`-ed, never
  surfaced as a failed deletion to the user and never left as a
  half-deleted, still-downloadable record (the DB row — the only thing
  that makes a file reachable through the app — is already gone by
  that point).
- **Metadata only.** `CaseDocument::$hidden = ['stored_path']` — the
  private filesystem path is never exposed through an accidental
  `toArray()`/`toJson()`. Views render only `original_filename`,
  `category`, uploader name, and size.
- **Category enum.** `App\Enums\CaseDocumentCategory` (general,
  evidence, contract, identification, correspondence) is new — no
  enum previously existed for the free-string `category` column — but
  its values were chosen to exactly match what
  `CaseDocumentFactory`/`DomainSchemaTest` already use, so no data
  migration was needed and existing rows are already valid.

### Case ↔ appointment relationship (deliberately not added)

The Phase 2 `cases` migration carries no `appointment_id` column and
no relationship to `appointments` exists anywhere in the schema this
phase inherited. Per the phase boundary ("if linking a case to an
appointment is already supported, expose it; do not redesign the
appointment system"), since no such link is supported, none was added
— inventing one would be a schema change outside this phase's
inspect-first mandate. The `appointments` table/model/controllers are
completely untouched by this phase.

### Case history / audit trail (deliberately minimal)

No dedicated audit-log table was built. The Phase 2 schema has no
audit/history infrastructure to reuse, and the phase boundary is
explicit that a phase should not build "a massive audit framework"
just for itself. Traceability instead comes from two things already in
the stack: the `notes` field (a cancellation always appends a
timestamped, attributed line — "Cancelled by Lawyer on 2026-09-26.
Reason: ..." — never overwriting prior notes), and the `notifications`
table, which already records every lifecycle event with its timestamp
per recipient. This is a genuine limitation, not an oversight: there
is no single queryable "full history of case X" view. If a future
phase needs one, the existing `notifications` rows plus
`cases.updated_at` are the honest starting point, not a reason to bolt
on a new audit table under this phase's scope.

### Notifications

`App\Notifications\CaseNotification` mirrors `AppointmentNotification`
— database channel only, dispatched by `LegalCaseStatusService` only
*after* a transaction commits, with delivery failures caught and
`report()`-ed rather than surfaced to the user or rolled back. The
payload (`App\Enums\CaseEvent`) is deliberately minimal: event name,
fixed message, case id, case number, title. It never carries the case
description, notes, a cancellation reason, or any document content or
filesystem path (tested explicitly — a case description containing
"Confidential: ..." never appears in a notification's stored JSON).

### Transactions

Every multi-step case mutation is wrapped: case creation (single
insert, no related records needed at creation time in this schema),
every lifecycle transition and reassignment
(`LegalCaseStatusService::transition()`/`reassignLawyer()` —
lock + validate + apply, atomically), and document upload/delete
(file + DB row kept in sync, with orphan cleanup as described above).
A failure at any step rolls back the whole operation; nothing is left
half-created.

### Testing

`tests/Feature/Cases/LifecycleTest.php` and
`tests/Feature/Cases/DocumentTest.php` cover: case creation and
mass-assignment resistance (smuggled `customer_id`, `status`,
`case_number`); viewing/IDOR for customer, lawyer, and admin, including
a manipulated numeric route id and a suspended account; every lifecycle
transition and its illegal-transition counterpart; cancellation reach
per role; admin reassignment (success, non-admin rejection, terminal-
case rejection, unapproved-lawyer rejection); admin deletion
(cancelled + document-free only); status-tampering resistance through
both the cancel and update endpoints; notification dispatch and
payload safety; listing/filtering scoped correctly per role; document
upload (valid, wrong extension, spoofed-content, oversized, invalid
category, upload-after-close); safe-filename storage under a
path-traversal-laden original name; document download IDOR in every
direction (customer↔customer, lawyer↔lawyer, unrelated user, admin
override, cross-case document-id manipulation, missing-from-disk); and
document deletion authorization (uploader-only, not the other party on
the same case, admin override, DB-succeeds-storage-already-gone).

**Test execution honesty, same limitation as every phase since Phase 5
Half 2:** this container has no `vendor/`, no `composer`, and — unlike
the Phase 6 Part 2 sandbox — **no `php` binary at all** (`php -v` and
`apt-get install php-cli` both failed; the network egress proxy denies
`repo.packagist.org`/`getcomposer.org`/general package hosts the same
way it denied them before). **No PHPUnit run, and not even a raw
`php -l` syntax check, was possible in this environment.** What was
done instead:
- A scripted brace/parenthesis-balance and `<?php`-open-tag sweep
  across every new/changed PHP file (a poor-man's syntax smoke test —
  it catches an unclosed block or missing tag, nothing more).
- Every new class's namespace, method signature, and call target was
  cross-checked by reading the *actual source* of everything it calls
  — `LawyerFileUploadService::store()`/`delete()`'s real signature,
  `LegalCase`/`CaseDocument`'s real fillable/casts/relations,
  `User`/`LawyerProfile`'s real relation and helper-method names,
  `LawyerProfile::scopeSearchable()`/`Service::scopeActive()`'s real
  behavior, `EnsureUserHasRole`'s real 403-vs-redirect behavior, the
  real `cases`/`case_documents` migrations — rather than assumed from
  this document's own prose or from the equivalent Phase 6 code by
  memory.
- Every route name referenced in a controller, view, or test was
  cross-checked against the actual `routes/web.php` this phase
  produced. Every Blade `@if`/`@endif`, `@foreach`/`@endforeach` pair
  in every new view was read back and matched.
- **This is a real limitation, not a formality — please run
  `composer install && php artisan test` in a real environment before
  treating this phase's test suite as verified-green.**

### Regression

Confirmed unmodified: every Phase 1–6 Part 2 migration, factory,
policy, controller, and route from before this phase. Files this phase
**extended** (never replaced): `app/Enums/CaseStatus.php` (added
`CANCELLED` + `isTerminal()`; the original five cases and `label()`
behavior for them are untouched — `DomainSchemaTest`'s
`CaseStatus::ACTIVE` assertion is unaffected), `app/Models/LegalCase.php`
(added `scopeForCustomer()`/`scopeForLawyerUser()`; every existing
field, relation, and cast is untouched), `app/Models/CaseDocument.php`
(added `$hidden = ['stored_path']` and a `category` enum cast — a
purely additive cast that accepts the exact string values already in
use, so no existing row or factory output becomes invalid),
`routes/web.php` (additive route registrations only, inside the
existing customer/lawyer/admin groups — no existing route changed),
`resources/views/dashboard/{customer,lawyer,admin}.blade.php` (one new
"Cases" panel added to each; placeholder copy updated to drop "cases"
from its now-inaccurate list). No appointment file, availability file,
verification file, or earlier migration/seeder/middleware was touched.

### Strict phase boundary (confirmed not implemented)

No chat, no AI assistant/recommendations, no payments, no billing, no
subscriptions, no video calls, no advanced analytics, no unrelated
admin systems, no redesign of any existing page. These remain later
phases.

## Phase 8 — Private Client ↔ Lawyer Messaging

### Source of truth / groundwork reused

The `conversations`/`messages` tables, `Conversation`/`Message` models,
and `ConversationStatus` enum already existed from Phase 2 and were
untouched at the schema level (only two additive index migrations were
added — see "Database changes" below). Reused rather than re-derived:
`LawyerProfile::scopeSearchable()` (the exact same
approved+active+public+account-active definition the Phase 5 directory
and matching engine already use — no second eligibility rule was
invented), the `role:customer|lawyer` + `verified` middleware pattern
from every earlier phase, and the Controller → FormRequest → Policy →
Service layering `Customer\CaseController`/`Lawyer\CaseController`
already established.

### Participant model

Every conversation is exactly one customer and one lawyer, enforced at
the database level by the pre-existing
`unique(customer_id, lawyer_profile_id)` constraint. Ownership itself
is defined in exactly one place — `Conversation::isParticipant(User $user)`
and `Conversation::otherPartyFor(User $user)` on the model — and
`ConversationPolicy`, `MessagingService`, and `MessagePolicy` (via
`Gate::forUser()`) all delegate to it rather than re-deriving
"is this user a party to this conversation" three separate ways.
Identity is always taken from `auth()->user()`/`$request->user()`,
never from request input — there is no code path anywhere in Phase 8
where a client-supplied `sender_id`, `customer_id`, or
`lawyer_profile_id` can reach a database write.

### Conversation creation / reuse

`MessagingService::startOrResumeConversation()`:
1. Confirms the caller is a customer (`abort_unless`).
2. Re-resolves the lawyer through `LawyerProfile::searchable()->find()`
   — a pending, rejected, inactive, suspended, or privately-unlisted
   lawyer, or one whose owning `User` account is suspended, is a 404,
   never a conversation.
3. Inside `DB::transaction()`, `SELECT ... FOR UPDATE`s for an existing
   row first (reuse), and only `INSERT`s if none exists.
4. Catches the `QueryException` a concurrent request's unique-
   constraint violation would throw, and re-`SELECT`s instead of
   surfacing a 500 — two browser tabs (or two rapid clicks) racing to
   start the same conversation both end up looking at the one row that
   was actually created.

### Sending a message

`MessagingService::sendMessage($sender, $conversation, $body)` takes
an already-authenticated `User` — never anything derived from request
input — re-checks `$conversation->isParticipant($sender)` defensively
(even though the controller already ran `ConversationPolicy::sendMessage`
first), trims and rejects an empty body, creates the `Message` with an
explicit, minimal attribute array (`conversation_id`, `sender_id`,
`body` — never a mass-assigned `$request->all()`), stamps
`conversations.last_message_at`, and dispatches
`NewMessageNotification` to the *other* party only. `StoreMessageRequest`
accepts exactly one field, `body`; there is no `sender_id`,
`conversation_id`, `read_at`, or `status` field anywhere in the
request surface for this feature, so nothing ownership-related is
smugglable through it regardless of what a POST body contains.

### Authorization

`ConversationPolicy` — `create()` (active customers only; lawyers
never initiate — enforced at the policy layer, not just by omitting a
"start" route on the lawyer side), `view()`/`sendMessage()`
(`Conversation::isParticipant()` + active account). `MessagePolicy`
delegates to `ConversationPolicy` through `Gate::forUser()`, mirroring
`CaseDocumentPolicy`'s relationship to `LegalCasePolicy`. **Deliberately
no admin access** — a conversation is private to its two parties only
(requirement #15); `AuthorizationTest::test_admin_cannot_view_a_private_conversation()`
asserts this directly against the Gate, and no admin route for
messaging exists at all.

### IDOR protections

Both `Customer\MessagingController` and `Lawyer\MessagingController`
call `$this->authorize('view'|'sendMessage', $conversation)` before
touching a `{conversation}` route-model-bound record, exactly like
every other controller in this app. `tests/Feature/Messaging/AuthorizationTest.php`
walks the attack matrix from the spec directly: customer↔customer,
lawyer↔lawyer, customer through the lawyer route and vice versa,
unrelated third party, and a manipulated numeric conversation id in
the URL — every one asserts `403`, not a redirect or a silently
filtered view.

### Mass assignment security

`Conversation::create()`/`Message::create()` are only ever called from
`MessagingService` with an explicit, hand-built attribute array — the
service methods don't accept a `$data` array from the caller at all,
so there is structurally no path from request input to those calls.
`tests/Feature/Messaging/MessageTest.php` and `UiTest.php` prove this
empirically: posting a smuggled `sender_id`, `conversation_id`,
`customer_id`, `lawyer_profile_id`, or `status` alongside a legitimate
request has zero effect on the record actually written.

### XSS / content security

Message bodies are stored as literal text (no sanitization/stripping —
there is nothing to sanitize since nothing is ever rendered as HTML)
and rendered exclusively through Blade's `{{ }}` escaping; no `{!! !!}`
appears anywhere in the Phase 8 views. Newlines are preserved safely
via CSS `white-space:pre-wrap` on the message bubble, not `nl2br()` +
raw output. `MessageTest::test_a_message_containing_html_is_escaped_when_rendered_not_executed()`
posts a literal `<script>` payload and asserts the response contains
the HTML-entity-encoded form, never the raw tag.

### CSRF

No route in this feature is excluded from the `web` middleware
group's CSRF verification (Laravel 11/12's default
`ValidateCsrfToken`, applied automatically — nothing in
`bootstrap/app.php` excludes any Phase 8 route). Every form
(`chat-compose`, the profile-page "Message" button) includes `@csrf`.

### Read/unread

`read_at` (already nullable on `messages` from Phase 2) is the single
source of truth. `MessagingService::markConversationRead()` marks
every message NOT sent by the viewer as read when a conversation is
opened, and is a no-op (never an error, never a write) for a caller
who isn't a participant — a defensive second gate behind the policy
check, not a substitute for it. `totalUnreadCount()` powers both the
dashboard badges and is the exact same query family the inbox list's
`withCount()` uses, so the two can never disagree.

### Notifications

`NewMessageNotification`, database channel only (matching every other
notification in this app). The payload deliberately carries only the
sender's name and the conversation id — **never the message body** —
so notification data (a lower-trust surface than the
authorization-protected conversation view) can never leak private
message content. Always sent to `Conversation::otherPartyFor($sender)`,
never to the sender, never to a third party.

### Performance / N+1

- Conversation list: one query for the page of conversations
  (`with(['customer', 'lawyerProfile', 'latestMessage'])` +
  `withCount('messages as unread_count')`), not one query per row.
  `latestMessage` uses Eloquent's `latestOfMany()` (a correlated
  subquery), not a per-row `->messages()->latest()->first()`.
- Message history: one paginated query + one batched eager load of
  `sender`.
- Two new index migrations (see below) cover the query shapes Phase 8
  actually introduces that the Phase 2 schema didn't yet need an index
  for.

### Database changes

`database/migrations/2026_09_29_100000_add_messaging_performance_indexes.php`
— purely additive, no column/data changes:
- `conversations.lawyer_profile_id` — a lawyer's conversation list
  filters on this alone; the existing
  `unique(customer_id, lawyer_profile_id)` index is led by
  `customer_id` and can't serve that lookup.
- `conversations.last_message_at` — conversation-list ordering.
- `messages(conversation_id, read_at)` — unread counts / mark-as-read.

### UI

`resources/views/{customer,lawyer}/messages/index.blade.php` — a
paginated inbox (reuses the existing `.table-wrap`/`.data-table`
pattern from the cases index for consistency). `show.blade.php` — the
full `.chat-view`/`.conversation-list`/`.chat-pane`/`.messages`/
`.chat-compose` CSS the Phase 1 prototype already shipped for exactly
this screen, now driving real, authorized, paginated data instead of
static demo markup. The sidebar in `show.blade.php` doubles as a quick
conversation switcher. Dashboard integration: a "Messages" panel with
an unread-count badge on both `dashboard/customer.blade.php` and
`dashboard/lawyer.blade.php` (the stale "messaging is still the demo"
placeholder copy on both was removed). Entry point:
`resources/views/lawyers/show.blade.php` gained a "Message" button
next to "Request appointment", visible only to authenticated
customers.

### Responsive / accessibility

The `.chat-view` CSS was already built mobile-first by the Phase 1
prototype (`.conversation-list` collapses under 780px; `.chat-pane`
takes the full width, and remains reachable via the "All conversations"
link back to the inbox). The inbox tables sit inside `.table-wrap`
(`overflow:auto`), so a narrow viewport scrolls the table horizontally
inside its own container rather than breaking page layout. The message
composer input uses `aria-label="Message"` (a visually-hidden `<label>`
was considered and rejected — the existing `.hidden` utility class is
`display:none`, which would also hide it from screen readers, not just
sighted users). Read/unread state is never color-only: unread count is
a numeral in a badge, and the composer's validation error renders as
visible text, not a color change alone.

### Tests

`tests/Feature/Messaging/{ConversationTest,AuthorizationTest,MessageTest,ReadStateTest,NotificationTest,UiTest}.php`
— roughly 50 test methods covering: conversation creation and reuse
(including a concurrent-duplicate database-level test), lawyer
eligibility in every ineligible state (pending/rejected/suspended
profile, suspended owning account, privately-unlisted), a lawyer being
structurally unable to initiate a conversation; the full IDOR matrix
from the spec (customer↔customer, lawyer↔lawyer, cross-route, admin,
manipulated conversation id) plus a direct `MessagePolicy` check;
sender-identity integrity against a smuggled `sender_id` and a
smuggled `conversation_id`; empty/whitespace-only/oversized/
max-length-boundary message validation; stored-content vs. rendered-
escaping for an XSS payload; read/unread correctness including "a
non-participant cannot mark someone else's messages read" and "opening
a conversation never marks your *own* sent messages as read"; total
unread counts summed across multiple conversations; notification
recipient correctness (both directions), no-self-notification, no
third-party notification, and a direct assertion that the notification
payload's JSON never contains the message body; conversation-list
scoping per role, pagination page sizes on both the inbox and the
message history, and mass-assignment resistance on conversation
creation (smuggled `customer_id`/`lawyer_profile_id`/`status`).

### Test execution honesty

**Real progress over Phase 7's sandbox, but still not a green
PHPUnit run.** This session had no `php` binary or `vendor/` either,
same as Phase 7's — but `php8.3-cli` (plus `sqlite3`, `mbstring`,
`xml`, `curl`, `dom`, `bcmath`, `zip`) installed cleanly via `apt-get`
(the egress proxy allows `archive.ubuntu.com`/`security.ubuntu.com`),
and `composer.phar` itself downloaded fine from
`github.com/composer/composer`'s release assets. What still isn't
reachable: `repo.packagist.org` (`composer install` fails with
`HTTP 403`, confirmed via `composer diagnose`) — so no package can be
resolved and Laravel itself can never boot in this container. This is
the same class of limitation as every phase since Phase 5 Half 2, just
one layer further along (working PHP now, but still no way to fetch
dependencies).

What was actually done given that:
- **`php -l` syntax-checked every single PHP file this phase
  added or touched** — a real check this time, not the brace-balance
  approximation Phase 7 had to fall back to. All clean.
- A scripted `@directive`/`@enddirective` balance sweep across every
  new/changed Blade view (`@if`/`@endif`, `@foreach`/`@endforeach`,
  `@forelse`/`@endforelse`, `@auth`/`@endauth`, `@error`/`@enderror`,
  `@section`/`@endsection`) — all paired correctly.
- Every route name referenced in a controller, service, view, or test
  was cross-checked against the real `routes/web.php`. Every model
  method/relation/scope this phase calls
  (`LawyerProfile::scopeSearchable()`, `Message::scopeUnread()`,
  `User::isCustomer()`/`isLawyer()`, `Conversation::messages()`, the
  real `messages`/`conversations` migrations) was read from its actual
  source, not assumed.
- Every CSS class used in the new views (`.chat-view`,
  `.conversation-list`, `.conversation`, `.message`, `.message.mine`,
  `.chat-compose`, `.unread`, `.table-wrap`, `.data-table`,
  `.dashboard-head`, `.panel`, `.btn.gold/.outline/.ghost/.small`,
  `.empty`) was grepped out of the actual `legal-connect.css` and
  confirmed to exist, rather than assumed from the prose describing
  the Phase 1 prototype.
- A full `diff -rq` between a pristine re-extraction of the Phase 7
  source and this phase's working copy, to confirm the change set is
  *exactly* the 7 files intentionally modified plus the new files
  listed below — nothing else in Phases 1–7 was touched, accidentally
  or otherwise.
- **This is still a real limitation, not a formality — please run
  `composer install && php artisan test` in a real environment before
  treating this phase's test suite as verified-green.** If a future
  session gets real Composer/Packagist access, that should be the very
  first thing done, before adding anything past Phase 8.

### Files (Phase 8)

New: `app/Services/MessagingService.php`,
`app/Policies/{ConversationPolicy,MessagePolicy}.php`,
`app/Notifications/NewMessageNotification.php`,
`app/Http/Requests/Messaging/StoreMessageRequest.php`,
`app/Http/Controllers/Customer/MessagingController.php`,
`app/Http/Controllers/Lawyer/MessagingController.php`,
`database/migrations/2026_09_29_100000_add_messaging_performance_indexes.php`,
`resources/views/{customer,lawyer}/messages/{index,show}.blade.php`,
`tests/Feature/Messaging/{ConversationTest,AuthorizationTest,MessageTest,ReadStateTest,NotificationTest,UiTest}.php`.

Extended (not replaced): `app/Models/Conversation.php` (added
`latestMessage()`, `isParticipant()`, `otherPartyFor()` only — every
existing field, relation, and cast untouched), `routes/web.php`
(additive route registrations inside the existing customer/lawyer
groups only), `app/Http/Controllers/{Customer,Lawyer}/DashboardController.php`
(injected `MessagingService`, added one `unreadMessages` view
variable), `resources/views/dashboard/{customer,lawyer}.blade.php`
(one new "Messages" panel each; stale placeholder copy about
messaging removed), `resources/views/lawyers/show.blade.php` (one new
"Message" button).

### Regression

Confirmed via a full `diff -rq` against a pristine re-extraction of
the uploaded Phase 7 source: every Phase 1–7 migration, factory,
policy, controller, and route not listed above is byte-for-byte
unmodified. No appointment file, availability file, case file,
verification file, or earlier migration/seeder/middleware was touched.

### Strict phase boundary (confirmed not implemented)

No group chat, no customer-to-customer or lawyer-to-lawyer chat, no
public chat rooms, no video/voice calling, no voice messages, no AI
chatbot/legal advice/OpenAI/LLM integration, no payments/billing/
subscriptions, no unrelated admin features, no redesign of any
existing page. The `messages.attachment_path` column (already present
in the Phase 2 schema) is intentionally left unused — file attachments
were never in this phase's scope and remain a later phase's decision.
Real-time delivery (websockets/polling) was also intentionally not
added — the Phase 2 migration's own comment already flagged this as a
later phase, and page-load-based read state keeps this phase's surface
area matched to what was actually asked for.

## Phase 9 — Reviews, Ratings & Lawyer Reputation

### Source of truth / groundwork reused

The `reviews` table, `Review` model, `ReviewStatus` enum, and
`ReviewFactory` already existed from Phase 2 and had sat completely
unused ever since — no controller, policy, service, route, or view
referenced them until this phase. The migration's own shape (a
nullable `appointment_id` with `nullOnDelete()`, a unique index on
`(customer_id, appointment_id)`, `Review::scopePublished()`) was
reused exactly as-is; nothing about the schema needed to change.
`Appointment::review()`, `LawyerProfile::reviews()`, and
`User::reviews()` also already existed and needed no changes either.
Reused rather than re-derived: `LawyerProfile::scopeSearchable()` for
who is publicly visible at all, the `role:customer|lawyer|admin` +
`verified` middleware pattern, and the Controller → FormRequest →
Policy → Service layering every earlier phase established (most
directly mirrored from `Customer\CaseController`/`LegalCasePolicy`/
`LegalCaseStatusService`).

### Eligibility: the one rule everything else hangs off

`ReviewPolicy::create(User $user, Appointment $appointment)` is the
single place the phase's entire business rule lives: an active
customer, who owns the appointment (`$appointment->customer_id`),
where the appointment actually has a lawyer, where that lawyer is not
the acting user (belt-and-braces — structurally already impossible,
since only customers own appointments), where the appointment's status
is exactly `AppointmentStatus::COMPLETED` (no other status, however far
along, ever qualifies), and where no review already exists for it. This
one method is called unchanged from three places — `ReviewController`'s
`create`/`store` actions (`$this->authorize()`), `ReviewService::create()`
(re-checked after locking the appointment row), and the Blade
"Leave a review" button (`@can('create', [Review::class, $appointment])`)
— so a hidden button was never the only thing standing between a
customer and an ineligible review.

### Submitting, editing, withdrawing a review

`ReviewService::create()` re-authorizes, then opens a `DB::transaction()`
and `lockForUpdate()`s the appointment row before re-checking eligibility
a second time and inserting — closing the race where two near-simultaneous
submissions for the same appointment (a double click, two browser tabs)
could otherwise both pass the first check. `customer_id` and
`lawyer_profile_id` are written exclusively from the locked appointment
row; `StoreReviewRequest`/`UpdateReviewRequest` do not define those as
fields at all, so nothing supplied in the request body can ever reach
them. The database's own unique constraint on `(customer_id,
appointment_id)` is the final backstop if all of that were ever bypassed.
Editing (`ReviewService::update()`) and withdrawal (`::delete()`) are
owner-only (`ReviewPolicy::update()`/`delete()`) and never touch the
appointment/lawyer relationship — only `rating`/`comment` are mutable.

### Publish-immediately, not a moderation queue

Reviews are published the moment they're created (`ReviewStatus::PUBLISHED`
is set explicitly in `ReviewService::create()`, never relying on the
column's own `pending` default). This was a deliberate decision: unlike
lawyer verification, a review's authenticity is already fully established
by the hard eligibility rule it had to pass to be created at all (a
genuine completed appointment, one review per appointment, owner-only
editing) — there is nothing left for a human moderator to pre-approve,
and gating every review behind an admin queue would have turned Phase 9
into the "huge unrelated admin system" the brief explicitly said not to
build. The pre-existing `pending`/`published`/`rejected` status column
is used in the *other* direction instead: after-the-fact abuse handling.
`Admin\ReviewController` + `ReviewPolicy::moderate()` let an admin move a
review between `published`/`rejected` to hide abusive or spam content,
but grant no ability to edit a review's rating/comment and no hard-delete
— the moderation trail is preserved rather than erased. This is narrow by
design, matching how `LegalCasePolicy` scopes admin case oversight.

### Reputation aggregation

`ReviewService::reputation(LawyerProfile $lawyer)` returns the average
rating (rounded to one decimal, `null` when there are zero published
reviews — never a fabricated `0.0`), the published review count, and a
1–5 star breakdown, computed with two lightweight `SELECT
COUNT(*)/AVG(rating) ... GROUP BY rating` aggregate queries — no review
row is ever loaded into memory just to count it. This one method is the
only source of truth for every rating figure shown anywhere: the public
lawyer profile, the lawyer directory card, the lawyer's own
reviews/reputation dashboard, and the lawyer landing-page panel. The
lawyer directory listing (`LawyerDirectoryController::index()`) adds
`withCount()`/`withAvg()` as correlated subqueries alongside the existing
listing query — one round trip for a whole page of lawyer cards, not one
extra query per card. `ReviewService::publicReviews()` returns a
newest-first paginated list with `customer:id,name` eager-loaded (and
nothing else of the customer) so no reviewer email/phone/private data can
ever leave that query, regardless of what the view does with it.

### Security audit (requirement #23)

- **IDOR** — a customer can only ever reach their own review via
  `customer.reviews.{edit,update,destroy}` (`ReviewPolicy::update()`/
  `delete()`); tested directly (`SecurityTest`) and the customer's own
  review-history page never renders another customer's review.
- **Appointment/customer/lawyer spoofing** — `customer_id`,
  `lawyer_profile_id`, and `appointment_id` are not fields on
  `StoreReviewRequest`/`UpdateReviewRequest` at all; a spoofed value in
  the POST body for any of them is silently ignored because
  `ReviewService` never reads request input for those columns, only the
  locked `Appointment` model. Tested directly for all three.
- **Self-review** — blocked both structurally (only customers own
  appointments, and the `role:customer` middleware rejects a lawyer's
  request before it reaches the controller) and by an explicit guard in
  `ReviewPolicy::create()` for the contrived data-integrity edge case.
- **Duplicate review** — blocked at the policy layer (an appointment
  that already has a `review` fails `ReviewPolicy::create()`), the
  service layer (locked re-check), and the database (pre-existing
  unique constraint, verified in `DomainSchemaTest`, not re-tested here
  to avoid duplicating that file per the phase brief).
- **Premature review** — every non-`COMPLETED` status (pending,
  confirmed, cancelled, rejected, rescheduled) is data-provider tested.
- **Rating manipulation** — `integer`+`between:1,5` rejects 0, negative,
  6+, decimals (`filter_var(..., FILTER_VALIDATE_INT)` rejects "4.5"
  outright), and missing ratings.
- **Mass assignment** — `Review::$fillable` never includes anything an
  attacker could use to attach a review to someone else's account/
  appointment; every write goes through `ReviewService`'s explicit
  attribute array, never `$request->all()`.
- **XSS** — comments are plain Blade `{{ }}` output everywhere
  (customer history, lawyer dashboard, admin inspection, public
  profile) — no `{!! !!}`, no raw HTML stored or rendered; tested
  directly with a `<script>` payload against the public profile.
- **CSRF** — every state-changing review route lives inside the
  existing `auth`/`active`/`role:*`/`verified` groups under the
  framework's default `web` middleware group; verified by resolving
  each named route's middleware, the same technique
  `tests/Feature/Auth/SecurityTest.php` already established (Laravel's
  test client itself bypasses CSRF for all tests, so this checks route
  membership, not a live token — that's the honest and correct thing
  to test here).
- **Unauthorized moderation** — `admin.reviews.reject`/`restore` are
  admin-only at both the `role:admin` middleware layer and
  `ReviewPolicy::moderate()`; a customer or the reviewed lawyer
  themselves both get a 403, and rejection never mutates the review's
  rating/comment (tested directly).
- **Private data leakage** — the public profile and directory only ever
  render `reputation()`'s aggregate figures and `publicReviews()`'s
  `customer:id,name`-scoped rows; a reviewer's email/phone is asserted
  absent from the public profile response directly in `PublicProfileTest`.

### N+1 / performance audit (requirement #27)

Directory listing: `withCount()`/`withAvg()` correlated subqueries, not
a query per lawyer — asserted directly with `DB::enableQueryLog()` in
`AggregationTest` (query count stays flat regardless of how many
lawyers/reviews exist on the page). Public profile: `reputation()` is
two aggregate queries total regardless of review count;
`publicReviews()` is paginated (`10`/page by default,
`config('reviews.public_page_size')`), never a full unbounded
`->get()`. Admin queue and customer/lawyer review lists are likewise
paginated (`config('reviews.{admin,customer,lawyer}_page_size')`).

### UI

New Blade views only where a genuinely new screen was needed, reusing
every existing class (`.panel`, `.field`, `.btn.{gold,outline,danger,small}`,
`.data-table`/`.table-wrap`, `.status`, `.subtext`, `.two-field`) rather
than inventing a parallel design language:
`customer/reviews/{index,create,edit,_form}.blade.php`,
`lawyer/reviews/index.blade.php`, `admin/reviews/{index,show}.blade.php`.
Integration into existing screens: `customer/appointments/show.blade.php`
gained a "Your review" panel (shown only when the appointment is
completed) that shows the existing review, an eligibility-gated "Leave a
review" button, or neither; `lawyers/index.blade.php` cards and
`lawyers/show.blade.php` (a new "CLIENT REVIEWS" section: aggregate,
1–5 star breakdown bars, paginated review list) both show real,
database-backed ratings for the first time — the Phase 1 prototype's own
demo profile page already established this exact visual pattern (a
`.rating` badge + a "CLIENT REVIEWS" block with repeated star icons) in
`legal-connect.js`, so Phase 9 is deliberately styled to match what that
localStorage demo already showed, just backed by the real database
instead. `dashboard/lawyer.blade.php` gained a "Reviews & reputation"
panel (average + count + link) matching the existing Messages/Cases
panel pattern exactly; `dashboard/admin.blade.php` gained a "Review
oversight" panel matching the existing "Case oversight" one. A small,
additive block of CSS was appended to `public/css/legal-connect.css`
for the star-rating input, the rating-breakdown bars, and the
`.review-item`/`.rating-summary` layout — no existing rule was changed,
no design system introduced.

### Accessibility (requirement #30)

The 1–5 star input (`customer/reviews/_form.blade.php`) is five real
`<input type="radio">` elements inside a `role="radiogroup"` — visually
hidden with a clip-based `.sr-only-input` technique (not `display:none`,
which would also remove them from the tab order), so the control is
fully keyboard-operable via native radio-group arrow-key behavior, has
a visible `:focus-visible` outline, and is screen-reader-navigable with
a per-star `aria-label` ("3 stars"). A live `aria-live="polite"` text
readout ("3 out of 5 stars" / "No rating selected yet") means the
rating is never conveyed by star icons alone, satisfying "do not make
stars the only source of rating information" directly. Every rendered
star rating elsewhere (customer history, lawyer dashboard, admin queue,
public profile) is always paired with a plain-text "`X/5`" or "`X out
of 5`" value next to the icon, for the same reason.

### Tests

`tests/Feature/Reviews/{SubmissionTest,SecurityTest,ModerationTest,AggregationTest,PublicProfileTest}.php`
— roughly 45 test methods covering: eligible completed-appointment
review creation (with and without a comment); guest rejection;
appointment-ownership/IDOR on submission; a data-provider over every
non-`COMPLETED` status (pending/confirmed/cancelled/rejected/
rescheduled) proving none of them can be reviewed; the "leave a review"
form itself becoming unreachable once a review exists; a suspended
customer's mid-session request being redirected to login; the full
rating-validation matrix (1 and 5 accepted; 0, 6, negative, and decimal
rejected; missing rating rejected; an overly long comment rejected; a
whitespace-only comment normalized to `null`); `customer_id`/
`lawyer_profile_id`/`appointment_id` spoofing all proven inert; a
lawyer being structurally unable to review their own appointment;
owner-only editing/deletion with the reviewed lawyer and another
customer both proven forbidden on every write action; editing proven
unable to change a review's appointment/lawyer; a customer's own
review-history page proven to never leak another customer's review;
XSS-payload escaping on the public profile; the CSRF/`web`-middleware
route-membership check; admin reject/restore authorized and narrow
(never mutates rating/comment, a customer and the reviewed lawyer both
forbidden, a guest redirected to login), plus status filtering on the
admin queue; a rejected review disappearing from the public profile;
the review-submission notification firing to the correct lawyer while
never carrying the comment text in its payload; reputation
average/count/breakdown correctness including the zero-reviews case and
rejected reviews being excluded; public review pagination and
newest-first ordering; and a direct query-count assertion against the
directory listing to guard against an N+1 regression. Per the phase
brief, the raw model-relationship correctness and the database-level
unique-constraint duplicate check are **not** duplicated here — they
already live in `tests/Feature/DomainSchemaTest.php` (added in Phase 2)
and are referenced, not repeated.

### Test execution honesty

**This session had no PHP at all, and no usable network access —
a step back from Phase 8's environment, not a step forward.** Unlike
Phase 8 (where `apt-get install php8.3-cli` succeeded because
`archive.ubuntu.com`/`security.ubuntu.com` were reachable even though
Packagist wasn't), this session's `apt-get update` returned
`403 Forbidden` on every configured repository
(`archive.ubuntu.com`, `security.ubuntu.com`, `deb.nodesource.com`) —
not just Packagist the way Phase 8 saw — so there was no way to
obtain a `php` binary, `composer`, or `vendor/` by any means. **Nothing
was run: no `php -l`, no `phpunit`, no `php artisan test`, no
route-list dump, no migration check.** Every
verification claim in this section is the result of careful manual
reading and scripted *text-level* checks, not code execution:

- A Python brace/paren-count sanity pass across every new/changed PHP
  file (all balanced) — a much weaker check than `php -l` (Phase 8 had
  real syntax checking; this phase does not), so it can catch a missing
  brace but not a genuine syntax error like a bad type hint or a stray
  keyword. **This is a materially weaker guarantee than Phase 8's
  green `php -l` run, and should be treated as such.**
- A scripted `@directive`/`@enddirective` balance sweep across every
  new/changed Blade view (`@if`/`@endif`, `@foreach`/`@endforeach`,
  `@for`/`@endfor`, `@forelse`/`@endforelse`, `@php`/`@endphp`,
  `@section`(block-form)/`@endsection`) — all paired correctly.
- Every model relation, scope, enum case, factory state, and helper
  method this phase calls (`LawyerProfile::scopeSearchable()`,
  `Review::scopePublished()`, `Appointment::review()`,
  `User::reviews()`/`isCustomer()`/`isLawyer()`/`isAdmin()`,
  `AppointmentStatus`/`ReviewStatus` enum cases,
  `UserFactory::lawyer()`/`admin()`/`suspended()`,
  `LawyerProfileFactory::approved()`/`pending()`,
  `AppointmentFactory::completed()`) was read from its actual existing
  source file and confirmed to exist with that exact name/signature
  before being used, not assumed from the earlier phases' prose.
- Every route name referenced anywhere (controllers, service,
  notification, every view, every test) was cross-checked against the
  literal strings registered in `routes/web.php`, including confirming
  no route-name collisions were introduced (the `customer.`/`lawyer.`/
  `admin.` name-prefix groups keep `reviews.index` etc. distinct across
  roles, the same pattern `cases.index`/`messages.index` already used
  safely in Phases 7–8).
- Every CSS class used in the new/changed views was grepped out of the
  actual `public/css/legal-connect.css` (including the handful of
  additive rules this phase appended to it) and confirmed to exist,
  rather than assumed.
- **This is a real, material limitation, not a formality. Please run
  `composer install && php artisan test` in a real environment before
  treating any part of this phase's test suite as verified-green.** If
  a future session has real PHP/Composer/Packagist access, running the
  full suite (`tests/Feature/Reviews/*` plus the full existing suite,
  to confirm no Phase 1–8 regression) should be the very first thing
  done, before anything past Phase 9.

### Files (Phase 9)

New: `config/reviews.php`, `app/Policies/ReviewPolicy.php`,
`app/Services/ReviewService.php`,
`app/Http/Requests/Reviews/{Store,Update}ReviewRequest.php`,
`app/Notifications/ReviewNotification.php`,
`app/Http/Controllers/{Customer,Lawyer,Admin}/ReviewController.php`,
`resources/views/customer/reviews/{index,create,edit,_form}.blade.php`,
`resources/views/lawyer/reviews/index.blade.php`,
`resources/views/admin/reviews/{index,show}.blade.php`,
`tests/Feature/Reviews/{SubmissionTest,SecurityTest,ModerationTest,AggregationTest,PublicProfileTest}.php`.

Extended (not replaced): `routes/web.php` (additive route
registrations inside the existing customer/lawyer/admin groups only),
`app/Http/Controllers/Customer/AppointmentController.php` (`show()`
now eager-loads the `review` relation — one added array entry),
`app/Http/Controllers/LawyerDirectoryController.php` (injected
`ReviewService`; `index()` gained two `withCount()`/`withAvg()`
aggregates, `show()` gained two new view variables — every existing
query clause and view variable untouched),
`app/Http/Controllers/Lawyer/DashboardController.php` (injected
`ReviewService`, added one `reputation` view variable),
`resources/views/lawyers/{index,show}.blade.php` (rating badges/section
added, nothing removed), `resources/views/dashboard/{lawyer,admin}.blade.php`
(one new panel each), `public/css/legal-connect.css` (additive rules
appended at the end of the file only).

### Regression

No file from Phases 1–8 was deleted, renamed, or had existing content
removed — every edit above is additive (new array entries, new view
variables, new panels/sections). The pre-existing `reviews` migration,
`Review` model, `ReviewStatus` enum, and `ReviewFactory` were read and
reused verbatim, not altered. `tests/Feature/DomainSchemaTest.php`'s
existing review-relationship and duplicate-constraint tests were left
untouched and are relied on rather than duplicated. Every Phase 1–8
route name, controller, policy, service, and migration not listed under
"Files (Phase 9)" above is unmodified.

### Strict phase boundary (confirmed not implemented)

No AI lawyer recommendations, no AI chatbot, no payments/subscriptions/
billing/Stripe, no video/voice calls, no group chat, no marketplace
payments or lawyer payouts, no advertising, no unrelated admin features
beyond the narrow review-oversight panel described above, no redesign
of any existing page. A pre-publication moderation queue was
deliberately not built (see "Publish-immediately, not a moderation
queue" above) — this was a considered design decision, not an omission.


## Phase 10 - Notifications & Communication Center

`/notifications` (all roles, `auth` + `active`) is the single place to see
what is new across appointments, messages, cases, documents, reviews and
system events.

- **Reuses** Laravel's `notifications` table and the existing notification
  classes - no duplicate system. Category comes from the payload `type`.
- **Filters:** All / Unread / Read, paginated (15). Empty states per filter.
- **Read state:** mark read, mark unread, mark all read (POST, CSRF). Only the
  signed-in user's own notifications can be read or changed; another user's id
  returns 404.
- **Navbar/dashboards:** bell with real unread count on authenticated pages;
  "Notifications" panel with the latest 5 on each dashboard.
- **Links:** "Open" goes to the existing appointment/conversation/case/reviews
  page, which still enforces its own policy. Documents link to the case page,
  never a file path. Payloads never expose message bodies or private data.
- **Schema:** one additive index migration.
- **Tests:** `tests/Feature/Notifications/` (access, read state, IDOR/CSRF/XSS
  security, integration with real notification classes, UI/navbar/query count).

**Test status / limitation:** PHPUnit could not be executed here because
Composer could not reach Packagist (HTTP 403 `host_not_allowed`). `php -l`
passed on all PHP files. Run `composer install && php artisan test` before
relying on the new tests. See `brain.md` (Phase 10) for details.

## Phase 11 — QA & Stabilization
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
