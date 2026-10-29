# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

`AGENTS.md` (Laravel Boost guidelines, Pint/Pest rules, repo-layout gotchas) is checked in and applies here too; read it first. This file covers what it doesn't, and corrects it where it is out of date.

## Commands

- Dev (server + queue listener + Vite): `composer run dev`
- All tests: `composer test` (clears config, then `php artisan test`)
- One test file or case: `php artisan test --compact --filter=TicketIssuanceTest` (Pest, SQLite `:memory:` per `phpunit.xml`; feature tests get `RefreshDatabase` via `tests/Pest.php`)
- Format after editing PHP: `vendor/bin/pint --dirty --format agent`
- Frontend: `npm run build` / `npm run dev`
- Scheduler: `php artisan schedule:run` (the only scheduled job is `immigration:send-expiry-reminders`, daily 08:00 Asia/Manila). `php artisan documents:privatise` is a one-off that moves legacy client documents from the public disk to the private one.
- Local app URL: `http://localhost/htdocs2/amegatravelandtour/public/` (XAMPP, MySQL `amega_db`).

## Frontend build gotchas

- Despite what `AGENTS.md` lists, **Tailwind v3 via PostCSS is the only live pipeline**. `@tailwindcss/vite` v4 is in `package.json` but not wired into `vite.config.js`. `tailwind.config.js` is what takes effect.
- `public/build/` is **tracked in git** and the production site serves the committed build. A view that uses a Tailwind class absent from the compiled CSS renders unstyled until you `npm run build` and commit the new build files. Rebuild right before committing, because a rebuild also drops classes no view uses any more.
- `navy` and `primary` are deliberately the same colour ramp under two names; prefer numeric steps (`bg-navy-700`) in new code.
- Fonts: the **staff side is Inter** everywhere (headings at weight 700) through `.staff-type` (admin, Immigration, SRRV, staff logins) or `.portal-minimal` (Ticketing, Visa, Cashier), both in `resources/css/ticketing-theme.css`; staff layouts load only Inter. The **public website** keeps Montserrat headings and Open Sans text, and there `app.css` forces `font-weight: 900` on `h1.font-heading, h2.font-heading` (overriding `font-bold`), so leave `font-heading` off public h1/h2. A new staff layout gets `staff-type` on its `<body>` and the Inter font link.
- UI conventions: olive `#A9BD00` is an accent only; semantic colour is capped at emerald (success), amber (needs action), rose (failed). Admin cards use `rounded-2xl`, controls `rounded-lg`; ticketing portal cards use `rounded-xl`. Never render placeholder metrics: dashboard figures must come from a real query, otherwise show an empty state.

## Architecture

### Roles and access control (the part that spans many files)

The `users.role` column holds **seven** values: `client`, `admin`, `agent`, `ticketing`, `visa_assistance`, `srrv`, `cashier`. It decides the login type, and admins pass everything. What a staff member may *do* is **role-based permissions** (RBAC):

- **The rule book is `App\Support\Permissions`.** `MODULES` lists every page/desk and its actions; a permission is `module.action` (`immigration.edit`, `srrv.approve`, `ticketing.refund`, `prices.manage`, ...). Desks have `view`/`create`/`edit` (own files), `view_all` (everyone's files) and `approve`. **Delete is never a permission: permanent deletes are admin-only**, enforced for every DELETE request by `DeletesAreForAdmins` (web middleware; its `ALLOWED` list holds the few corrections that are not deletes, e.g. removing a wrong scan).
- **Roles** are `StaffRole` rows (`staff_roles`: name, permissions JSON, `is_system`), edited at Users > Roles & Permissions (`AdminStaffRoleController`, admin-only). A staff member has `users.staff_role_id` plus extra `users.permissions`. `User::effectivePermissions()` = role + extras. **An account with neither (set up the old way) is derived from `role` + `allowed_pages` by `Permissions::fromLegacy()`**, which never implies `view_all`, `approve`, `refund`, `prices.manage` or the CRM. Saving the staff form (`AdminAgentController::saveAccess`, `_access.blade.php`) writes explicit permissions and logs the before/after to the activity log.
- **Checks:** `User::hasPermission($key)`; every key is also a Gate (`can:prices.manage` on routes, `@can` in views; `Gate::before` passes admins). `canAccessPage($page)` is now `hasPermission("$page.view")` and still drives `page.access:*`, `AdminNavigation` and the desk middleware. "Dedicated desk agent" (`isTicketingAgent()` etc.) means the only page granted is that desk.
- **Prices and catalogs** (BI pricing/categories, ticketing packages, airlines, insurance plans) need `prices.manage`; everyone else reads them.
- **`OwnFilesScope`** (on `TicketBooking`, `TicketDraft`, `VisaApplication`, `TouristVisaApplication`, `SrrvApplication`, `SrrvRenewal`, `ImmigrationClient`) limits non-admin staff to rows they created, unless they hold the model's desk (`ownFilesDesk()`) `view_all` or `approve`. Immigration folders (`immigration_clients.created_by`) keep unowned older folders visible to the whole counter (`ownFilesShowUnowned()`); immigration cases follow their folder via `InVisibleFolderScope`. Work that must see all files opts out with `withoutGlobalScope(OwnFilesScope::class)`. Admins hand files over with `FileOwnerController`. When adding a desk model, apply the scope, declare `ownFilesDesk()`, and write `created_by`.
- **Approvals are maker-checker** (`App\Support\Approvals`): the approval pages need `{desk}.approve` (an admin, or a Supervisor role); nobody approves a file they sent (`Approvals::refusal`). Only a desk's **sole** approver has their own saves approved on the spot (`DeskApproval::saved`, logged SELF_APPROVE); with two admins, each one's files go to the other. Approval emails go to `Approvals::recipients($desk)`.
- Middleware aliases (`bootstrap/app.php`): `admin` (admin or agent, plus approvers reaching their approvals page), `admin.only`, `page.access:<page>`, one per desk (`ticketing`, `immigration`, `visa`, `srrv`), `cashier`. Desk middleware is prepended ahead of `SubstituteBindings` on purpose, so an officer from another desk is redirected instead of getting a 404 from route-model binding. Staff with two or more desks get a Switch desk menu (`layouts/desk-switcher.blade.php`).

### Portals

`routes/web.php` is organised by portal, each with its own layout in `resources/views/layouts/` (`ticketing`, `immigration`, `visa-assistance`, `srrv`, `admin`, `app`):

- Public site (`PageController`, packages, bookings, contact, guest live chat) with per-route `throttle:*` limiters defined in `AppServiceProvider::configureRateLimiting()`.
- Client portal (`/client/*`) and `/login`, `/agent/login`, `/admin/login` (three separate login forms/guards-by-role in `AuthController`).
- Four staff "desks" under `/ticketing`, `/visa-assistance`, `/srrv`, and `/admin/immigration`, plus the admin panel (`/admin`, `AdminNavigation` builds the menu from `canAccessPage`).
- Desk workflows are staged: a file advances through stages only after each stage's work is recorded (`recordStage` + `advance`), and payments are recorded separately. Ticket issuance is its own consent-gated action and requires payment in full; it is not a payment side effect.
- Ticket bookings go agent → admin → cashier → agent: the wizard creates the booking with `approval_status = pending` (quotations: null) and emails the admins; an admin approves at `/admin/ticket-approvals` (with an accountability checkbox) or returns it with a reason; only an approved booking takes payment, recorded by the cashier at `/cashier` through `TicketPaymentRecorder` (ticketing staff can no longer record payments; admins can); the agent issues once it is fully paid. Editing an approved or returned booking sends it back to pending. `TicketBooking::canTakePayment()` / `canBeIssued()` enforce this.

### Services and cross-cutting concerns

- `ClientAccountService::findOrCreateClient` turns any desk interaction into a `client` user record, matching by email, passport, then phone, among clients only. Duplicate prevention for registrations is the `NotAlreadyRegistered` rule plus `ClientDuplicateCheckController`.
- `CrmSyncService` builds `CrmLead` rows from inquiries, custom package requests, ticket quotations and visa applications.
- `ActivityLogger::log()` writes the audit trail shown in `/admin/activity-logs`.
- Client emails go through `ClientNotifier` and `app/Notifications/*`.
- **Private documents** (passports, government IDs, client photos, desk uploads) live on the non-public disk named by `config('filesystems.documents_disk')` (default `local`), via `App\Support\DocumentStorage`. They are served only through authorising controllers (`UserDocumentController`, the desks' `downloadDocument` actions). Never write them to the `public` disk.
- PDFs (tickets, vouchers, agreements, consent forms) use `barryvdh/laravel-dompdf`. `TicketDocumentPdf`, `BookingAgreementDrafter` and `DataPrivacyConsent` build them.

### Ticketing wizard

`resources/views/ticketing/tickets/create.blade.php` is a single ~3,300-line Alpine wizard.

- Navigation walks `stepSequence` (`[1,2,3,(6),5,10,12]`); never use `currentStep++`. Old step ids 4/7/8/9/11 no longer exist; `restorePending` maps them for drafts saved earlier.
- Validation goes through `errorsForStep` / `checkStep` / `showErrors` and `<x-ticketing.field-error>`; do not use `alert()`.
- **Never put `x-transition` on the step panels**: it freezes `display` when a radio re-renders the wizard. A test in `TicketBookingModuleTest` guards this.
- Alpine `x-show` updates inside `requestAnimationFrame`, so in the in-app browser pane or `--dump-dom` runs it looks frozen even though `x-text`/`x-model` update. Verify visually with a headless Edge screenshot, with a fresh `--user-data-dir` per run because the draft is kept in `localStorage`.

## Previewing auth-gated pages

The browser pane can't sign in. Render through the HTTP kernel instead: call the HTTP kernel's `bootstrap()`, then `auth()->setUser(User::where('role','admin')->first())`, and dispatch a `Request::create` with `SCRIPT_NAME=/htdocs2/amegatravelandtour/public/index.php`. Save the HTML in the scratchpad and serve it with `php -S`.

## Production

Shared cPanel hosting (InMotion). `.cpanel.yml` only copies `public/` into `public_html`, so after "Deploy HEAD Commit" run `php artisan migrate --force` and `php artisan optimize:clear` by hand from `~/repositories/amegatravelandtour`. The scheduler needs a cPanel cron running `php artisan schedule:run` every minute. Mail uses `MAIL_MAILER=sendmail` (SMTP auth failed on the host). The domain is `amegatravelandtour.com` (no "s"); some footer and printed-form copy says `amegatravelandtours.com`, which is not the mail domain.
