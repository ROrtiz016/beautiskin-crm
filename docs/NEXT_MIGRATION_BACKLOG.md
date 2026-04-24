# Next.js product migration — backlog

This document turns “complete migration” into **explicit work**: staff mutations on `/api`, impersonation (or a replacement), optional single-origin hosting, and retiring Blade where safe.

For how the SPA and Laravel cooperate today, see [README.md](../README.md) (Next.js staff UI) and [FEATURES.md](FEATURES.md).

---

## Definition of done (pick what applies)

Use these as release criteria for a **Blade-off** staff product (optional; many teams keep Blade for fallback indefinitely).

1. **Mutations:** Every action a staff member can take from the Next app is implemented as **Sanctum-authenticated** `POST` / `PATCH` / `PUT` / `DELETE` under `/api` (or `/api/spa/...` only for read payloads if you keep that split), with validation, authorization, and audit parity where Blade had it.
2. **Next clients:** No production reliance on **browser HTML forms** posting to Laravel `routes/web.php` for staff flows (session CSRF on `APP_URL`). Session auth remains acceptable for **guest** login/register/reset flows if those pages stay on Laravel or are duplicated on Next.
3. **Impersonation:** Either **replicated for tokens** (new design) or **formally dropped** with an approved alternative (e.g. read-only “view customer as” using scoped APIs).
4. **Hosting (optional):** One public origin (reverse proxy) if you do not want staff juggling two ports/hosts.
5. **Blade retirement:** Remove or gate Blade **staff** views/routes only after (1)–(3) are true and tests cover `/api`; keep mail templates, error pages, or legal PDFs as needed.

---

## 1. Staff mutations on `/api` (audit matrix)

**Method:** For each area below, confirm (a) an API route exists, (b) policies/validation match web controllers, (c) the Next page uses `spaFetch` (or equivalent) against `/api/...`, not only legacy web URLs.

| Area | Web mutations (`routes/web.php`) | API direction (`routes/api.php`) | Backlog notes |
|------|-----------------------------------|----------------------------------|----------------|
| Auth (guest) | `login`, `register`, `forgot-password`, `reset-password` | `POST /api/auth/*` | SPA already uses Sanctum; session web login can remain for edge cases. |
| Customers | `resource` store/update/destroy; nested appointments & contact | `apiResource` customers; nested routes partial | Confirm **customer create/update/delete** and **profile appointment** flows in Next call **API** controllers, not only web. |
| Services / memberships | `resource` store/update/destroy | `apiResource` | Wire Next catalog pages to `/api/services`, `/api/memberships` if not already. |
| Packages | POST/PATCH/DELETE packages | `/api/packages` | Align Next packages UI with API; retire web forms. |
| Quotes | quotes + lines + link-appointment | `/api/quotes` + related | Confirm quote builder in Next uses API routes exclusively. |
| Tasks | CRUD + complete/reopen | `/api/tasks` | Confirm tasks SPA uses API. |
| Pipeline / opportunities | store/update/stage/destroy | `/api/opportunities` | Confirm pipeline client uses API. |
| Appointments | Many `AppointmentWebController` routes | `AppointmentController` + reschedule, reminders, waitlist, payment entries, retail lines | **Largest gap risk:** compare web vs `Api\AppointmentController` (and related) for parity; Next calendar should prefer `/api/appointments` and related endpoints. |
| Waitlist | web `appointments/waitlist*` | `/api/waitlist-entries*` | Path names differ — document mapping in code or here when audited. |
| Customer comms / timeline | web posts | `/api/customers/{id}/timeline-notes`, communications | Already partially mirrored; verify templated comms. |
| Admin board | Full web admin group | `prefix('admin')` under Sanctum + `can:access-admin-board` | Large surface; track each control-board action until Next + API only. |
| Impersonation | `POST …/impersonate/*` | **None** | See section 2. |
| Dashboard layout | `PATCH user/dashboard-layout` (web + api duplicate) | `/api/user/dashboard-layout` | Prefer single canonical route in docs/tests. |

**Concrete tasks**

- [ ] Run a **grep** across `frontend/src` for `fetch(`, `action=`, and non-`/api` hosts; ensure staff mutations go to `/api`.
- [ ] Add or extend **Feature tests** that hit `/api/...` for each critical staff mutation (happy path + 403).
- [ ] Document any **intentional** web-only mutation (if any remain) in this file with rationale and sunset date.

---

## 2. Impersonation or a replacement

**Today:** Session-only (`impersonator_id`), started from Blade control board; see [FEATURES.md §1](FEATURES.md#impersonation-laravel-web-session).

**Options (choose one product direction):**

- [ ] **A. Token impersonation:** New API (e.g. short-lived “act as” token issued only to admins, audited), Next banner + “leave”, strict gates on dangerous actions.
- [ ] **B. Scoped read-only:** Replace “view as” with safer support tools (e.g. read-only customer context API) without full login-as.
- [ ] **C. Deprecate:** Remove impersonation after stakeholder sign-off; update docs and training.

---

## 3. Single-origin hosting (optional)

**Goal:** Browser sees one host (e.g. `https://crm.example.com`); `/` and staff routes are served by Next (or static export), `/api` proxied to Laravel.

**Tasks**

- [ ] Reverse proxy config (nginx, Caddy, Traefik, or cloud LB) with TLS.
- [ ] Set `FRONTEND_URL` and `APP_URL` to the **public** origin; adjust `SANCTUM_STATEFUL_DOMAINS` / cookie settings if you mix cookie and token auth.
- [ ] Next `rewrites` / `LARAVEL_API_URL` for server-side fetches vs browser `/api` prefix — verify in `frontend/next.config.ts`.
- [ ] Smoke-test login, file download (CSV, backup), and large payloads through the proxy.

---

## 4. Retiring Blade staff paths

**Do last**, after API + Next coverage and production monitoring.

- [ ] Identify Blade views only used for **staff** GETs (customers, appointments, admin, …).
- [ ] Replace or remove web routes behind `FRONTEND_URL` checks **or** a dedicated `config` flag if you need staged rollout.
- [ ] Keep Blade (or minimal routes) for **mail**, **errors**, **password reset** landing pages if those stay server-rendered.
- [ ] Update `tests/Feature` that still `get()` Blade URLs to use API or feature flags.

---

## 5. Maintenance

- Revisit this checklist after each major CRM release.
- When an item is done, move it to your issue tracker archive or delete the row to avoid stale documentation.
