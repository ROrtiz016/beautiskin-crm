# BeautiSkin CRM (Laravel)

Laravel-based CRM for an aesthetics clinic: **customers**, **services**, **memberships**, **appointments**, **waitlist**, **admin pricing and promotions**, **operations metrics**, **reporting**, and **clinic-wide settings**—with a JSON **API** under `/api`. Staff-facing **UI** lives in the **Next.js** app in `frontend/`; Laravel continues to own persistence, policies, queues, mail, and webhooks.

## Documentation

- **[Feature documentation](docs/FEATURES.md)** — authentication, admin board, operations dashboard, appointments (calendar, waitlist, reminders, policies), reporting, scheduler, notifications, dashboard layouts, data model summary, and performance notes.
- **[Next migration backlog](docs/NEXT_MIGRATION_BACKLOG.md)** — explicit checklist for “full” product migration (API-only staff mutations, impersonation, single-origin, retiring Blade).

## Core domain

- `customers`: personal and contact details (soft deletes; GDPR-related fields where migrated)
- `services`: treatment catalog with duration and pricing
- `memberships`: membership plans
- `customer_memberships`: subscriptions per customer
- `appointments`: booked sessions linked to customer and optional staff / membership
- `appointment_services`: line items per appointment
- `waitlist_entries`: preferred-date waitlist requests
- `clinic_settings`: singleton configuration (timezone, policy, messaging templates, feature flags, etc.)
- `promotions`, `scheduled_price_changes`, `admin_audit_logs`: admin and pricing workflows

## HTTP API (`/api`)

REST-style JSON resources:

- `GET|POST /api/customers` — `GET|PUT|PATCH|DELETE /api/customers/{customer}`
- `GET|POST /api/services` — `GET|PUT|PATCH|DELETE /api/services/{service}`
- `GET|POST /api/memberships` — `GET|PUT|PATCH|DELETE /api/memberships/{membership}`
- `GET|POST /api/customer-memberships` — `GET|PUT|PATCH|DELETE /api/customer-memberships/{customerMembership}`
- `GET|POST /api/appointments` — `GET|PUT|PATCH|DELETE /api/appointments/{appointment}`

## Next.js staff UI (`FRONTEND_URL`)

1. Set `FRONTEND_URL` in `.env` (see `.env.example`) to the Next app origin (e.g. `http://localhost:3000`).
2. Run the SPA from `frontend/` (`npm install`, then `npm run dev`).

**Making Next the staff UI (operational “cutover”)** — There is no separate code flag: **`FRONTEND_URL` is the cutover.** Set it in **every** environment where staff should land on the SPA (local, staging, production). Leave it unset only where you intentionally want Blade on `APP_URL` (e.g. minimal installs, some automated tests). Same codebase supports both; the difference is configuration and what you bookmark (`APP_URL` vs the Next origin). For **Docker**, `docker-compose.yml` sets `FRONTEND_URL=http://localhost:3000` on the `app` service by default so Laravel and Next agree out of the box; override if your public Next URL differs (TLS, different host). A **single browser origin** for both UI and API still requires a reverse proxy in front of Laravel + Next (outside this repo’s defaults).

**How it fits together**

- **`RedirectWebUiToFrontend`** (see `app/Http/Middleware/RedirectWebUiToFrontend.php`): when `FRONTEND_URL` is set, browser **GET/HEAD** requests that expect HTML on Laravel **web** paths redirect to the **same path** on the Next origin. **POST/PATCH/DELETE** and other non-read methods are **not** redirected: they stay on Laravel (session, CSRF, `routes/web.php` controllers), so login, password reset, logout, and legacy Blade forms still work if something posts to the Laravel app URL. JSON clients (`Accept: application/json`) always continue to Laravel unchanged.
- **Auth**: the SPA uses **Sanctum** bearer tokens (`/api/auth/login`, `/api/auth/user`, `/api/auth/logout`, etc. in `routes/api.php`).
- **Data for screens**: authenticated JSON under `/api/spa/*` (e.g. customers list, customer detail, leads, appointments index payload) plus `/api/*` for REST resources and admin mutations where exposed.
- **Blade**: routes and views under `routes/web.php` and `resources/views/` remain for **fallback** when `FRONTEND_URL` is empty, automated tests, and reference. They are not removed.

**SPA coverage (high level)** — routes live under `frontend/src/app/(crm)/` and include, among others: home, customers (list, new, profile, edit, timeline), appointments, leads, activity, tasks, services, memberships, packages, inventory, quotes, sales and pipeline, and admin (control board, operations, reports). Domain rules and payloads are documented in [docs/FEATURES.md](docs/FEATURES.md).

**Parity notes (not exhaustive)**

- **Appointments:** the Next calendar (`/appointments`, `appointments-client.tsx`) consumes `GET /api/spa/appointments` and covers month view, day selection, filters, booking, waitlist, and many of the same flows as Blade (`resources/views/appointments/index.blade.php`). Edge-case or layout differences may still exist; Blade + `AppointmentWebController` remain the historical reference when `FRONTEND_URL` is unset.
- **Admin impersonation (“View as”):** implemented only for **Laravel web sessions** (`POST /admin/impersonate/{user}`, session key `impersonator_id`, `POST /admin/impersonate/leave`). It is **not** available to staff signed in only via **Sanctum** in the SPA. To use impersonation today, use the control board on the **Laravel app URL** with session login, or treat SPA-only impersonation as future work (e.g. delegated tokens or a dedicated API).

**Staff mutations (recommended vs legacy):** new work in the SPA should use **`/api`** with **Sanctum** (`spaFetch` / bearer token) for create/update/delete, not browser forms pointed at Laravel web URLs. Laravel web **POST/PATCH** routes remain for tests, migration, and direct use of the Laravel origin; they are no longer mis-routed to the SPA home page. Guest and authenticated **GET** redirects for the browser are configured in `bootstrap/app.php` (e.g. toward the SPA login and CRM entry).

## Local setup

1. Install PHP 8.2+, Composer, and a SQL database (MySQL/PostgreSQL).
2. `composer install`
3. `copy .env.example .env` — set `DB_*` (and `MAIL_*` if you use reminders or test email).
4. `php artisan key:generate`
5. `php artisan migrate`
6. `php artisan serve`

Optional front-end assets: `npm install` and `npm run build` (or `npm run dev`) if you use Vite-driven CSS/JS.

**Scheduler (scheduled prices):** in production, run Laravel’s scheduler every minute, e.g. `* * * * * cd /path-to-app && php artisan schedule:run`. The app schedules `clinic:apply-scheduled-prices` every fifteen minutes (`routes/console.php`).

## Docker setup (recommended)

This project can run with Docker (PHP app + MySQL). Typical flow:

1. `docker compose up --build`
2. API / Laravel: `http://localhost:8000` — Next staff UI: `http://localhost:3000` (the `app` container has `FRONTEND_URL=http://localhost:3000` so browser GETs on Laravel redirect to the SPA).
3. Run migrations inside the app container, e.g. `docker compose exec app php artisan migrate`

Adjust ports and credentials to match your `docker-compose` file if it differs from any example in the repo.

### Useful commands

- Migrate: `docker compose exec app php artisan migrate`
- Composer: `docker compose exec app composer <args>`
- Stop: `docker compose down`
- Remove DB volume: `docker compose down -v`
