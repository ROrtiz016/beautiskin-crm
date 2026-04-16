# BeautiSkin CRM (Laravel)

Laravel-based CRM for an aesthetics clinic. This project currently includes SQL schema design and JSON API endpoints for:

- Customer records
- Appointment booking and history
- Services catalog
- Membership plans and customer memberships

## Core Domain

- `customers`: personal and contact details
- `services`: treatment catalog with duration and pricing
- `memberships`: membership plans
- `customer_memberships`: membership subscriptions per customer
- `appointments`: booked sessions linked to customer and optional staff/member plan
- `appointment_services`: line items for services performed in each appointment

## API Endpoints

All endpoints are mounted under `/api`:

- `GET|POST /api/customers`
- `GET|PUT|DELETE /api/customers/{customer}`
- `GET|POST /api/services`
- `GET|PUT|DELETE /api/services/{service}`
- `GET|POST /api/memberships`
- `GET|PUT|DELETE /api/memberships/{membership}`
- `GET|POST /api/customer-memberships`
- `GET|PUT|DELETE /api/customer-memberships/{customerMembership}`
- `GET|POST /api/appointments`
- `GET|PUT|DELETE /api/appointments/{appointment}`

## Local Setup

1. Install PHP 8.2+, Composer, and a SQL database (MySQL/PostgreSQL).
2. Install dependencies:
   - `composer install`
3. Configure environment:
   - `copy .env.example .env`
   - update database settings in `.env`
4. Generate app key:
   - `php artisan key:generate`
5. Run migrations:
   - `php artisan migrate`
6. Start local server:
   - `php artisan serve`

## Docker Setup (Recommended)

This project includes a Dockerized environment with:

- `app` container: PHP 8.3 + Composer + Laravel runtime
- `mysql` container: MySQL 8.4 database

### Start with Docker

1. Build and run:
   - `docker compose up --build`
2. Open app:
   - `http://localhost:8000`
3. MySQL host for external tools:
   - host: `127.0.0.1`
   - port: `3307`
   - database: `beautiskin_crm`
   - username: `beautiskin`
   - password: `beautiskin_password`

### Useful Commands

- Run artisan command:
  - `docker compose exec app php artisan migrate`
- Run composer command:
  - `docker compose exec app composer require vendor/package`
- Stop containers:
  - `docker compose down`
- Stop and remove DB volume:
  - `docker compose down -v`

## Next Suggested Features

- Authentication and staff roles/permissions
- Calendar-style booking UI
- Reporting dashboard (revenue, visit frequency, retention)
- Appointment reminders (SMS/email)
