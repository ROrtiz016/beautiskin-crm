# BeautiSkin CRM — Feature Documentation

This document describes the **web application**, **admin tools**, **policies**, **scheduling**, and **supporting infrastructure** beyond the original JSON API. For API route names and HTTP verbs, see `routes/web.php` and the “HTTP API” section in `README.md`.

---

## Table of contents

1. [Authentication and access control](#1-authentication-and-access-control)
2. [Staff-facing CRM (authenticated users)](#2-staff-facing-crm-authenticated-users)
3. [Appointments](#3-appointments)
4. [Customer profile](#4-customer-profile)
5. [Admin control board](#5-admin-control-board)
6. [Operations dashboard](#6-operations-dashboard)
7. [Reporting](#7-reporting)
8. [Clinic settings and policy engine](#8-clinic-settings-and-policy-engine)
9. [Scheduled jobs and Artisan](#9-scheduled-jobs-and-artisan)
10. [Notifications](#10-notifications)
11. [Dashboard layouts (drag-and-drop)](#11-dashboard-layouts-drag-and-drop)
12. [Data model additions (summary)](#12-data-model-additions-summary)
13. [Performance and caching (technical)](#13-performance-and-caching-technical)

---

## 1. Authentication and access control

### Registration and login

- **Register** (`/register`): creates a new user account.
- **Login** (`/login`): session-based authentication.
- **Logout** (`POST /logout`): ends the session.

### Password reset

- **Request reset** (`GET/POST /forgot-password`): email-based password reset link flow.
- **Set new password** (`GET /reset-password/{token}`, `POST /reset-password`): completes reset using the signed token.

Uses Laravel’s password reset broker and `lang/en/passwords.php` for broker messages.

### Admin board access (`can:access-admin-board`)

Users who are **admins** (`users.is_admin`) or have the **`manage_users`** permission may access routes under `/admin/*` (control board, reports, operations, GDPR export, etc.). The gate is defined in `AppServiceProvider` as `access-admin-board`.

**Impersonation** is blocked from using the admin board while an impersonation session is active (same gate logic).

### Feature flags gate (`manage-feature-flags`)

Only **admins** may update clinic **feature flags** (e.g. experimental UI) on the operations dashboard. Gate: `manage-feature-flags`.

### Experimental UI (`view-experimental-ui`)

Admins may see experimental UI when the clinic flag **`experimental_ui`** is enabled in `clinic_settings.feature_flags`. Gate: `view-experimental-ui`.

---

## 2. Staff-facing CRM (authenticated users)

### Customers (`/customers`)

- **Index**: searchable, sortable list with pagination.
- **Create / edit / update / delete**: standard CRUD for customer records (soft deletes where applicable).
- **Show**: profile with appointments, memberships, payment summary, modals for contact edit and booking (see [Customer profile](#4-customer-profile)).

### Services (`/services`)

- Catalog management: list (with staff and membership coverage), create, update, delete.
- Links services to **staff** who can provide them and **memberships** that cover them.

### Memberships (`/memberships`)

- List and manage membership **plans** (create, update, destroy).
- Web UI for plan definitions used with customer memberships.

### HTTP API (`/api/*`)

JSON API for customers, services, memberships, customer-memberships, and appointments remains available for integrations (see `README.md`).

---

## 3. Appointments

**Route prefix:** mostly `/appointments` and related `customers/{customer}/appointments` routes.

### Calendar and day panel

- **Index** (`GET /appointments`): month calendar, **selected day** panel, **today** strip, filters (status, customer, search, service, arrival, staff).
- **Day fragment** (`GET /appointments/day`): JSON with rendered HTML for the selected-day panel (used when switching dates without a full page reload).

### Filters

Query parameters drive `appointmentsFilteredQuery`: status, `customer_id`, text search on customer fields, `service_id`, `arrived` (yes/no), `staff_user_id`.

### Booking and updates

- **Create** (`POST /appointments`): new appointment with services; subject to `AppointmentPolicyEnforcer` (deposits, max bookings per day, etc.).
- **Update** (`PATCH /appointments/{appointment}`): reschedule details, services, notes, etc.
- **Reschedule** (`PATCH /appointments/{appointment}/reschedule`): dedicated endpoint for moving an appointment (e.g. drag-and-drop); validates policy and conflicts.
- **Status** (`PATCH /appointments/{appointment}/status`): booked / completed / cancelled / no_show.
- **Arrival** (`PATCH /appointments/{appointment}/arrival`): arrived confirmation flag.
- **Staff** (`PATCH /appointments/{appointment}/staff`): assign or change staff.

### Waitlist

- **Add to waitlist** (`POST /appointments/waitlist`): creates a `waitlist_entries` row (customer, service, optional staff, preferred date/time, notes).
- **Update waitlist status** (`PATCH /appointments/waitlist/{waitlistEntry}/status`): e.g. waiting → contacted.

Waitlist rows appear on the selected day when `preferred_date` matches.

### Email reminders

- **Send email reminder** (`POST /appointments/{appointment}/reminders/email`): queues/sends `AppointmentReminderNotification` using clinic **messaging templates** and **from** settings when configured.

### Membership coverage UI

The selected-day panel shows whether the customer’s **active membership** covers booked services (full / partial / none), using `customer.memberships` and `membership.coveredServices` data loaded for that day’s appointments.

---

## 4. Customer profile

**Route:** `GET /customers/{customer}` (and nested appointment/contact routes).

- **Next appointment** and **booked** future list with actions (update, mark completed, mark cancelled).
- **Past appointments** (capped list for performance; see `CustomerWebController` constants).
- **Payment history**: recent completed visits (capped); **total paid** is a separate aggregate over **all** completed appointments so the headline figure stays accurate.
- **Services received**: aggregated line items from `appointment_services`.
- **Current / past memberships**.
- **Modals** to edit contact details and add/update appointments (uses cached **services** and **staff** dropdowns shared with the appointments screen).

---

## 5. Admin control board

**Base URL:** `/admin/control-board`  
**Middleware:** `auth`, `can:access-admin-board`

High-level areas (exact panel order can be customized via [Dashboard layouts](#11-dashboard-layouts-drag-and-drop)):

### Users and access

- Create users, set **admin** flag and **permission** list (`manage_users`, etc.).
- **Deactivate** (soft delete) and **restore** users.
- **Impersonate** (`POST /admin/impersonate/{adminUser}`): act as another user; **leave** via `POST /admin/impersonate/leave` (available to the impersonated session).

### Pricing

- Quick **service** and **membership** price updates from the board.
- **Scheduled price changes**: schedule a new price for a service or membership at an `effective_at` time; cancel pending rows.
- **Promotions**: create/update rules (discount type/value, date range, targets, caps, stackable flag, active status) with optional links to specific services and memberships.

### Clinic profile and tax

- **Clinic profile** patch: name, timezone, business hours, default appointment length, reminder lead times.
- **Tax / rounding**: default tax rate and price rounding rule stored on `clinic_settings`.

### Messaging

- **Email/SMS template toggles**, from-address/name, subject/body/SMS templates with `{{placeholder}}` replacement.
- **Test send** posts to `messaging-settings/test-send` to verify configuration (`BusinessSettingsTestNotification`).

### Data retention and compliance

- **Per-customer export** (GDPR-oriented data export).
- **GDPR delete** for a customer record (uses `gdpr_deleted_at` / related flows as implemented in `AdminControlBoardController`).
- **Backup snapshot** download: bundled export of core tables for offline backup (admin-only).

### Audit trail

- Sensitive admin actions are written to **`admin_audit_logs`** (actor, action key, entity type/id, old/new values, IP, user agent).

---

## 6. Operations dashboard

**URL:** `/admin/operations`

Day-to-day operational view (not full financial ERP):

- **Timezone-aware “today”** metrics: revenue (completed), no-shows, **waitlist depth** (waiting + contacted).
- **Staff utilization**: rough same-day utilization vs an 8-hour workday model, using appointment duration or clinic default length.
- **Appointment policy** form (`PATCH /admin/operations/appointment-policy`): cancellation window (hours), **deposit required**, default deposit amount, **max bookings per day** (optional cap).
- **Feature flags** (`PATCH /admin/operations/feature-flags`): e.g. `experimental_ui` (admins only).

Changes are audited via `AdminAuditLog`.

---

## 7. Reporting

**URLs:**

- `GET /admin/reports` — date range (from/to, clinic timezone), summary KPIs, status breakdown, new customers, waitlist opens, top services by revenue, **daily** breakdown table.
- `GET /admin/reports/export` — CSV download of the daily summary for the same range.

Reporting uses `scheduled_at` bounded in application timezone derived from the clinic timezone for the selected calendar dates.

---

## 8. Clinic settings and policy engine

### `ClinicSetting` model (`clinic_settings` table, singleton `id = 1`)

Holds clinic-wide configuration: branding, timezone, hours, appointment defaults, reminder leads, email/SMS templates and toggles, tax/rounding, **appointment policy** fields (cancellation hours, deposits, max bookings per day), and **feature_flags** JSON.

`ClinicSetting::current()` returns the singleton; it is **memoized per request** and cleared when the row is saved or deleted so policy changes apply immediately within the same deployment patterns.

### `AppointmentPolicyEnforcer` service

Centralizes rules used by web and API flows, including:

- Clinic **timezone** and **day bounds** for “calendar day” checks.
- **Max bookings per day** enforcement (when configured).
- **Deposit** validation on create/update payloads when deposits are required.
- Other guards coordinated with `ClinicSetting`.

---

## 9. Scheduled jobs and Artisan

### Scheduler (`routes/console.php`)

- **`clinic:apply-scheduled-prices`** runs **every fifteen minutes** to apply due `scheduled_price_changes` and update service/membership prices.

### Command: `clinic:apply-scheduled-prices`

Implemented in `App\Console\Commands\ApplyScheduledPrices`:

- Selects pending changes with `effective_at <= now()`.
- Applies price updates in a DB transaction.
- Marks changes **applied** (or **cancelled** if the target model is missing).
- Optionally records **`admin_audit_logs`** as a system-style application event.

**Production:** ensure the Laravel scheduler is running (`* * * * * php artisan schedule:run`).

---

## 10. Notifications

### `AppointmentReminderNotification`

Email notification for appointment reminders; content is built from clinic templates and appointment/customer/staff context.

### `BusinessSettingsTestNotification`

Used by the admin **messaging test send** to verify mail configuration.

---

## 11. Dashboard layouts (drag-and-drop)

**Endpoint:** `PATCH /user/dashboard-layout` (authenticated JSON)

- Payload: `dashboard` (`operations` | `control_board`) and `order` (array of panel IDs).
- Validated against `DashboardLayoutRegistry` allow-lists; order is normalized server-side.
- Persisted on `users.dashboard_layouts` (JSON) via a targeted update for performance.

Admin **operations** and **control board** Blade views read the saved order to render panels.

---

## 12. Data model additions (summary)

| Area | Notes |
|------|--------|
| **Waitlist** | `waitlist_entries` with customer, service, optional staff, preferred date/time, status, notes. |
| **Clinic** | `clinic_settings` singleton; migrations extend over time for business/messaging fields. |
| **Promotions** | `promotions` plus pivot tables for targeted services/memberships; rules JSON-ish via columns. |
| **Scheduled prices** | `scheduled_price_changes` polymorphic to service/membership; statuses pending/applied/cancelled. |
| **Admin** | `admin_audit_logs`; `users` extended with `is_admin`, `permissions`, soft deletes, `dashboard_layouts`. |
| **Appointments** | Fields such as `email_reminder_sent_at`, arrival flags as per migrations. |
| **Customers** | `gdpr_deleted_at` and related admin flows. |
| **Indexes** | Migrations add indexes for reporting/calendar hot paths (`scheduled_at`, staff/customer composites, etc.). |

Refer to `database/migrations/` for the exact schema timeline.

---

## 13. Performance and caching (technical)

These behaviors are implementation details but useful for operators and developers:

- **Appointment month grid**: loads minimal appointment rows for counts; **full** eager loads only for the selected day (and today when needed), reducing memory and query depth on busy months.
- **Operations dashboard**: reuses the same day’s appointment collection for revenue and no-show counts where possible.
- **Reporting**: combines range aggregates into fewer queries; daily CSV rows use chunked/lazy reads for large ranges.
- **`AppointmentFormLookupCache`**: caches customer, active service, and staff dropdown lists; **invalidated** on `Customer`, `Service`, and `User` lifecycle events.
- **Customer show**: caps in-memory appointment history lists; **total paid** uses a SQL `SUM` over all completed rows.
- **Tests**: `tests/TestCase.php` flushes the cache between tests to avoid stale form lookups.

---

## Related files

| Topic | Location |
|--------|-----------|
| Web routes | `routes/web.php` |
| Scheduler | `routes/console.php` |
| Gates | `app/Providers/AppServiceProvider.php` |
| Policy / timezone | `app/Services/AppointmentPolicyEnforcer.php` |
| Clinic config | `app/Models/ClinicSetting.php` |
| Form cache | `app/Support/AppointmentFormLookupCache.php` |
| Panel IDs | `app/Support/DashboardLayoutRegistry.php` |

For Docker, Composer, and baseline API documentation, see the root **`README.md`**.
