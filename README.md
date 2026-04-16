# BeautiSkin CRM (Laravel)

Laravel-based CRM for an aesthetics clinic: **customers**, **services**, **memberships**, **appointments**, **waitlist**, **admin pricing and promotions**, **operations metrics**, **reporting**, and **clinic-wide settings**—with a JSON **API** under `/api` and a **session-based web UI** for staff and administrators.

## Documentation

- **[Feature documentation](docs/FEATURES.md)** — authentication, admin board, operations dashboard, appointments (calendar, waitlist, reminders, policies), reporting, scheduler, notifications, dashboard layouts, data model summary, and performance notes.

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

## Web UI (session auth)

After `php artisan migrate` and creating a user (`/register` or seeder), sign in at `/login`. Staff routes include customers, services, memberships, and appointments. Users with **admin** or **`manage_users`** permission can open **`/admin/control-board`**, **`/admin/operations`**, and **`/admin/reports`**. Details and route names are in [docs/FEATURES.md](docs/FEATURES.md).

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
2. App: `http://localhost:8000`
3. Run migrations inside the app container, e.g. `docker compose exec app php artisan migrate`

Adjust ports and credentials to match your `docker-compose` file if it differs from any example in the repo.

### Useful commands

- Migrate: `docker compose exec app php artisan migrate`
- Composer: `docker compose exec app composer <args>`
- Stop: `docker compose down`
- Remove DB volume: `docker compose down -v`
