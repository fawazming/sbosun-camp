# SB Osun Camp Portal

A production-oriented, mobile-first camp registration and operations portal for **Standard Bearers Islamic Organization, Osun State** — built with **raw PHP 8.1+**, PDO and vanilla JavaScript. No framework, no Composer, no Node build step.

The interface, brand system and user journeys were extracted from the SB HIC Next.js app in `sb-hic`, then rebuilt as server-rendered PHP and extended with the brief in `newC`:

- Camp dates updated to **24–28 December 2026**.
- Added **next-of-kin phone number**.
- Added **allergies / existing ailment** (protected welfare field).
- Raw PHP implementation of the public site, delegate portal and admin console.

---

## 1. What is included

### Public site

- Branded landing page: hero, live countdown, early-bird pricing, classes, 9-panel scrollytelling camp highlights with real photos/video, 22-photo gallery with lightbox, lecturer carousel, newsletter signup, enquiry footer and “Memory Lane” audio player.
- **Pay-first registration**: the delegate chooses email, phone, category and payment plan, pays, and only then unlocks the full delegate form (step 2). A lightweight participant shell is created at payment time, so no full profile is collected before money is confirmed.
- Full delegate profile after payment:
  - name, gender, age bracket, email, phone/WhatsApp, category, zone;
  - **next-of-kin phone** and **allergies/existing ailment**;
  - under-18 guardian fields, optional health notes.
- Automatic class placement from category and unique registration number (`SB-26-XXXXXX`).
- Personal delegate portal (`/portal/{token}`) with payment status, completion CTA, balance, payment history, camp details and ID card.
- **Find Registration** supports two methods:
  - payment reference + payer email (works immediately after payment, before the form is completed);
  - registration ID + surname (works after the profile is completed).
- **Bulk registration & payment**: one buyer pays for up to 50 seats; after payment, the buyer portal issues a unique one-time completion link per delegate. Each seat becomes its own participant, portal and ID card.
- Server-authoritative pricing (early bird vs flat rate); browser-submitted totals are never trusted.
- **Smarter instalments**: every next amount is quoted from the real outstanding balance and remaining instalments, not by repeatedly dividing the old price. If the general camp fee or phase changes, pending amounts are recalculated; unpaid hosted checkouts are retired and a fresh checkout is started. Full payments lock in the price actually paid.
- Payment modes controlled by **`PAYMENT_MODE` in `.env`**:
  - **mock** — sandbox checkout for local testing;
  - **manual** — bank transfer + WhatsApp receipt + finance-admin approval;
  - **hosted** — PayVessel / PayFlow hosted checkout with server-side verification.
- Server-side callback verification, idempotent verification, underpayment rejection and signed webhook handling.
- Registration status lookup by payment reference + email or registration number + surname (rate-limited, minimal data).
- Printable delegate ID card (no phone/email/payment reference/welfare data on the card).
- `.ics` calendar download.
- Newsletter subscribe/unsubscribe with token and one-click unsubscribe.
- Maintenance mode and registration-closed pages.
- Branded 403/404/419/500 pages.

### Admin console

- Secure email/password login with bcrypt, session regeneration, idle timeout, login rate limiting and audit logging.
- Role-based access:
  - Super Admin, Registration Admin, Finance Admin, Attendance Admin, House Admin, Viewer, Zone Admin.
- Dashboard: registrations, paid/partial/pending, expected vs present, attendance %, verified revenue, pending transactions, zone/category/gender splits, house occupancy, latest registrations and pending payments.
- Participants: combined filters (search, gender, category, payment, attendance, house, zone, date range), pagination, filtered CSV export, detail view with protected welfare fields, incomplete-profile badge, check-in/undo, house assignment, soft-delete.
- Payments: status tabs, search (including bulk buyers), verified total, manual approval, refund with reason, CSV export.
- **Bulk orders**: buyer, seats, amount, payment status and form-completion progress, with a direct link to the buyer portal.
- **Database viewer** (Super Admin only): browse every table with row counts, column metadata, paginated data (password hashes masked), and a read-only SELECT/WITH query console capped at 200 rows. Write/schema statements are blocked.
- Check-in desk: large fast search, payment gate, one-tap check-in, undo with logged reason, recent arrivals and live counts.
- Houses: create, activate/deactivate, capacity progress, auto-assign fully-paid delegates, manual assignment.
- Reports: registration, payment, attendance, house and newsletter CSV exports.
- Newsletter: subscribers, active/total counts, delete, broadcast with per-recipient unsubscribe (SMTP required), CSV export.
- Admin users: create, role/zone assignment, deactivate/reactivate, password reset.
- Settings: programme dates/venue/support, fees, early-bird deadline, house capacity, registration state, maintenance mode, payment mode, manual bank details.
- Audit log: logins, failed logins, exports, approvals, refunds, check-ins, house assignment, settings changes.

### Security / reliability

- PDO prepared statements everywhere.
- CSRF protection on every state-changing form.
- Output escaping in HTML and attributes.
- bcrypt password hashing; session ID regenerated on login/logout.
- Login, registration, status-lookup and newsletter throttles.
- Admin pages are `noindex, nofollow`.
- Secrets only in `.env`; never rendered in HTML.
- Payment verification always server-side; callbacks and webhook are idempotent.
- Soft deletes for delegates.
- Underpayment, overpayment, duplicate payments and amount/access-code mismatches are rejected and logged.
- Mail/Telegram-style alerts never block a registration (mail is optional).
- Transactional SQLite/MySQL schema installer.

---

## 2. Requirements

- PHP **8.1+** (tested on PHP 8.2.12) with `pdo_sqlite` (default) or `pdo_mysql`, `curl`, `mbstring`, `openssl`.
- Apache/Nginx (XAMPP works), or PHP’s built-in server for local testing.
- MySQL 8 / MariaDB 10.4+ only if you choose MySQL. SQLite needs zero setup.
- No Composer, Node, npm, CDN or external front-end dependency.

---

## 3. Quick start (XAMPP, this build)

1. The folder is already installed and seeded locally:
   - **Public:** `http://localhost/sbosun-camp/`
   - **Admin:** `http://localhost/sbosun-camp/index.php?r=admin/login`
   - **Local demo admin:** `admin@sbosun.org` / `SB-Osun@2026`
   - **Payment mode:** `mock` (sandbox checkout)
2. **Change the admin password and `.env` values before any public launch.**
3. For a clean database, delete `storage/camp.sqlite` and run the installer again.

### Fresh install (web)

1. Open `http://localhost/sbosun-camp/install.php`.
2. Enter the Super Admin name, email and a password of at least 8 characters.
3. The installer creates all tables, settings, classes and houses.

### Fresh install (CLI)

```bash
php install.php
# or
php install.php --email=admin@example.org --password='a-strong-password' --name='Camp Admin'
```

The installer is idempotent: it will not overwrite an existing admin or seed data.

### PHP built-in server (no Apache)

```bash
cd sbosun-camp
php -S 127.0.0.1:8099 -t .
```

Open `http://127.0.0.1:8099/index.php`.

---

## 4. MySQL instead of SQLite

Create the database first:

```sql
CREATE DATABASE sbosun_camp CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

Then set in `.env`:

```dotenv
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sbosun_camp
DB_USERNAME=sbosun
DB_PASSWORD=change-me
```

Run `php install.php` once. A reference schema is included at `database/schema.mysql.sql`.

---

## 5. Payment configuration

**`PAYMENT_MODE` in `.env` is the single switch.** Admin → Settings displays the active mode read-only. Change the environment value and reload:

```dotenv
PAYMENT_MODE=mock      # sandbox, no real money
PAYMENT_MODE=manual    # bank transfer + admin approval
PAYMENT_MODE=hosted    # live PayVessel / PayFlow checkout
```

For a live deployment set `PAYMENT_MODE=hosted`, add live keys and callback URL, then confirm a small live transaction before opening registration. The mock route returns 404 whenever the mode is not `mock`, and is always refused when `APP_ENV=production`.

### 5.1 Mock (default, safe for local testing)

```dotenv
PAYMENT_MODE=mock
```

The delegate is sent to `/pay/mock/{reference}` and chooses **Pay** or **Decline**. A successful test marks the payment verified, recalculates the delegate balance and auto-assigns a house when fully paid. No money moves and the mock gateway refuses to create delegates for declined payments.

### 5.2 Manual bank transfer

```dotenv
PAYMENT_MODE=manual
PAYMENT_BANK_NAME=Bank name
PAYMENT_ACCOUNT_NAME=Account name
PAYMENT_ACCOUNT_NUMBER=0123456789
PAYMENT_WHATSAPP=09053444178
```

These four values can also be edited in **Admin → Settings → Manual transfer account**. The delegate gets exact account details and a WhatsApp receipt button. A Finance Admin approves the pending payment after confirming the credit; approval is audit-logged.

### 5.3 Hosted PayVessel / PayFlow

```dotenv
PAYMENT_MODE=hosted
PAYMENT_BASE_URL=https://payflow.sgm.ng
PAYMENT_API_KEY=PVKEY-...
PAYMENT_SECRET_KEY=PVSECRET-...
PAYMENT_BUSINESS_ID=...
PAYMENT_CALLBACK_URL=https://your-domain.example/index.php?r=payment/callback
PAYMENT_WEBHOOK_SECRET=...
PAYMENT_WEBHOOK_ALLOWED_IPS=3.255.23.38,162.246.254.36
```

Contract implemented in `app/payments.php`:

- `POST {PAYMENT_BASE_URL}/api/authorize` with `api-key` + `Authorization: Bearer …` (header names and scheme configurable).
- Body: `email`, `amount` (naira by default; set `PAYMENT_AMOUNT_UNIT=minor` to send kobo), `callback`, `tickets` (our reference).
- Response: `data.authorization_url` / `data.checkout_url` (plus common aliases), `data.reference`, `data.access_code`. Redirect URLs are validated as absolute `http`/`https` before use.
- `GET {PAYMENT_BASE_URL}/api/verify/TXN{reference}` with the same headers. The `TXN` prefix is configurable via `PAYMENT_VERIFY_REFERENCE_PREFIX`.
- Verification is server-side and tolerant of the gateway's field aliases. The amount must match exactly (missing amount is rejected, not treated as zero); access codes are compared in constant time. Pending is rejected unless `PAYMENT_ALLOW_PENDING=true`.
- Callback: `GET /index.php?r=payment/callback&reference=…` or `?trxref=`, `?paymentReference=`, `?transaction_ref=`, `?transactionRef=`, `?ref=`, plus the path form `/payment/callback/{reference}`. The callback is public, rate-limited and never trusts the query string for paid/amount.
- Opening `/complete/{token}` or the portal after a closed gateway tab re-runs verification for pending hosted payments. Admins can also press **Re-verify** on a pending gateway payment.
- Webhook: `POST /index.php?r=payment/webhook` with the HMAC-SHA512 signature header (default `Payvessel-Http-Signature`). Optional IP allowlist. Duplicate deliveries are recorded as `DUPLICATE_PAYMENT` and never double-credit. Failed/pending statuses are applied rather than blindly marking the order paid.
- The sandbox is refused when `APP_ENV=production`, so a live deployment can never let a buyer mark their own order paid via `PAYMENT_MODE=mock`.

No secret is displayed in the admin UI. Settings shows only “configured / not configured”.

---

## 6. Email (optional)

```dotenv
MAIL_ENABLED=true
MAIL_HOST=smtp.example.com
MAIL_PORT=465
MAIL_USERNAME=no-reply@example.org
MAIL_PASSWORD=app-specific-password
MAIL_ENCRYPTION=ssl
MAIL_FROM_EMAIL=no-reply@example.org
MAIL_FROM_NAME=SB Osun
```

Registration confirmation and newsletter emails are attempted after the database transaction commits. If mail is not configured or fails, the registration/payment still succeeds and the failure is logged to `storage/logs/mail.log`.

---

## 7. Admin roles

| Role | Access |
|---|---|
| Super Admin | Everything, including users, settings, audit and the database viewer |
| Registration Admin | Participants, check-in desk, newsletter, bulk orders |
| Finance Admin | Payments, approvals, refunds, exports, bulk orders |
| Attendance Admin | Check-in desk |
| House Admin | House allocation |
| Zone Admin | Read access scoped to their zone |
| Viewer | Read-only participants, payments, houses, check-in, newsletter |

Admin passwords are stored with `password_hash()`; there is deliberately no public “forgot password” flow. A Super Admin resets passwords in **Admin → Admin users**.

---

## 8. Data model

- `settings` — all editable programme values.
- `classes` — class names and eligible categories.
- `houses` — gender-specific houses and capacities.
- `participants` — delegate record, including pay-first `profile_completed`, `nok_phone`, `nok_name`, `allergies`, `health_challenges`, guardian fields, payment totals, soft delete.
- `bulk_orders` — buyer email/phone, seats, amount, status, reference, buyer portal token.
- `bulk_tickets` — one row per bulk seat: unique one-time token, category, price, used flag, linked participant.
- `payments` — one row per attempt/instalment: reference, provider reference, amount, type, plan, status, account/checkout details, nullable `bulk_order_id` for bulk payments.
- `payment_events` — idempotent webhook/verification ledger with raw payload.
- `attendance` — check-in state, staff member, corrections.
- `admin_users` — role, zone, protected flag, status, last login.
- `audit_logs` — actor, action, target, before/after JSON, IP, timestamp.
- `newsletter` — email, token, status, source, dates.
- `login_attempts`, `throttle` — rate limiting.

---

## 9. Routes

Public (query-string URLs; clean URLs work with the included `.htaccess`):

```text
/                          home
/register                  pay-first registration (GET/POST)
/complete/{token}          step 2 delegate form (after payment)
/portal/{token}            delegate portal
/bulk                      bulk order form (GET/POST)
/bulk/{token}              buyer portal with per-seat links
/bulk/retry/{token}        refresh/retry bulk payment (POST)
/complete-ticket/{token}   one-time bulk delegate completion
/pay/start                 start a payment (POST)
/pay/mock/{reference}      sandbox checkout (only when PAYMENT_MODE=mock)
/payment/callback          gateway return / status page
/payment/webhook           signed provider webhook (POST)
/status                    find registration (reference+email or ID+surname)
/id-card/{token}           printable ID card
/event.ics                 calendar download
/newsletter                subscribe (POST)
/newsletter/unsubscribe/{token}
```

Admin:

```text
/admin/login               sign in
/admin/dashboard           metrics
/admin/participants        list / filters / export
/admin/participants/{id}   detail / check-in / house / archive
/admin/payments            transactions / approve / refund / export
/admin/bulk                bulk orders and completion progress
/admin/check-in            check-in desk
/admin/houses              houses / auto-assign
/admin/reports             CSV exports
/admin/newsletter          subscribers / broadcast
/admin/users               admin accounts
/admin/settings            programme configuration (payment mode is read-only)
/admin/audit               audit log
/admin/database            Super Admin read-only database viewer + SELECT console
```

With query routing, the same paths are `index.php?r=admin/dashboard`, etc. The app generates query URLs by default so it works without `mod_rewrite`; clean URLs are supported by `.htaccess`.

---

## 10. Project structure

```text
sbosun-camp/
  index.php                 front controller
  install.php               one-time installer
  config.php                central non-secret configuration
  routes.php                route table
  .env / .env.example       secrets & deployment values
  .htaccess                 rewrite + directory protection
  app/
    bootstrap.php           config, session, error handling
    helpers.php             settings, views, CSRF, router, throttle
    db.php                  PDO + schema installer
    auth.php                admin sessions, roles, rate limits
    payments.php            fees, payments, hosted gateway, webhook
    mailer.php              SMTP mailer + templates
    controllers/public.php  public journeys
    controllers/admin.php   admin console
  views/
    layout.php              public shell
    public/                 home, register, portal, status, callback, ID card…
    admin/                  login, dashboard, participants, payments…
    errors/                 403, 404, 419, 500
  assets/
    css/app.css             extracted brand design system
    js/app.js               vanilla interactions
    images/ videos/         real SB HIC assets
    memory_lane.mp3
  database/schema.mysql.sql reference MySQL schema
  storage/                  SQLite DB, logs (gitignored)
```

---

## 11. Business rules

1. Early bird applies while `now <= early_bird_deadline` in `Africa/Lagos`; otherwise flat rate.
2. The server computes every price; client prices are ignored.
3. **Pay first, profile second.** No full delegate profile is required to start a payment. A verified payment unlocks the completion form.
4. Instalments are available before the early-bird deadline. An existing instalment plan may continue after the deadline at the flat rate, but new plans cannot start.
5. Every next instalment is `outstanding balance ÷ remaining instalments` (integer division, last payment absorbs the remainder), so price changes and prior payments are always reflected.
6. A full payment locks in the amount actually paid; later price changes never create a balance for that delegate.
7. Pending mock/manual payments are recalculated when the general fee changes. A stale hosted checkout is retired and a fresh checkout is required.
8. A verified payment can only create one verification event per provider reference.
9. Underpaid or duplicate transfers never mark a delegate paid.
10. House assignment is random/least-occupied from the gender list, and only for fully paid **and profile-completed** delegates.
11. Bulk orders are one payment for up to 50 seats; each seat’s one-time link can create exactly one participant. A used link is rejected.
12. Each edition writes to the same normalised tables (edition year is a setting); historical isolation can be added with an edition column.
13. Failed/cancelled/expired payments never count as paid.
14. Soft-deleted delegates disappear from reports, lists and exports.
15. Mail/webhook failures never block a completed registration/payment.

---

## 12. Verification performed in this build

- `php -l` across every PHP file: clean.
- Fresh SQLite install: tables, settings, 3 classes, 4 houses and Super Admin created. In-place upgrade added `profile_completed`, `bulk_order_id`, `bulk_orders` and `bulk_tickets`.
- Public home, register, bulk, status, ID card, `.ics` and login pages return 200 at 1440 px and 390 px with no horizontal overflow or console errors.
- **Pay-first flow**: pay-only form → mock checkout → verified payment → `profile_completed=0` → completion CTA → full profile → `profile_completed=1` → automatic house assignment.
- **Find Registration**: payment reference + email opened the portal; registration ID + surname lookup also verified.
- **Instalment intelligence**: after changing the early-bird fee, the next quote recalculated from the outstanding balance; a stale pending amount (`₦1,016`) was automatically refreshed to the restored amount (`₦850`). Lapsed phase correctly moved the plan to the flat rate.
- **Bulk flow**: 3 seats / ₦25,500 in one mock payment → buyer portal with 3 one-time links → each link created a separate participant, payment row, portal and house; order moved to `COMPLETED`; reusing a link was rejected.
- **Bulk lookup** by payment reference + buyer email redirected to the buyer portal.
- **Env switch**: changing `PAYMENT_MODE` in `.env` to `manual` immediately changed `payment_mode()` and made the mock route return 404; restored to `mock`.
- Admin login, dashboard, participants, payments, bulk orders, check-in, houses, reports, users, settings, audit, newsletter and database viewer all 200.
- Database viewer loaded table data, executed a read-only SELECT, and rejected a `DELETE` statement.
- Filtered CSV export with correct headers, escaped values and formula-injection guard.
- Manual payment flow: pending transfer → finance admin approval → `PARTIALLY_PAID` → refund → `NOT_PAID` with audit entry.
- Settings save with re-rendered pricing phase.
- No PHP warnings/errors in tested pages or the server log.

Not tested live because no credentials were supplied: real SMTP delivery and live PayVessel checkout. Both are implemented behind configuration and their failure paths are logged without blocking registration.

---

## 13. Deployment checklist

- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] Point the virtual host document root at this folder (or a `public` subfolder if you move it).
- [ ] Serve over HTTPS; secure cookies are enabled automatically behind HTTPS.
- [ ] Change the admin email/password; generate a fresh hash with the installer or password reset.
- [ ] Configure `PAYMENT_MODE`, gateway keys, callback URL and webhook secret.
- [ ] Configure SMTP if you want email.
- [ ] Back up the database (`storage/camp.sqlite` or your MySQL database) before launch.
- [ ] Protect `storage/` and `.env` at the web-server level; the included `.htaccess` does this on Apache.
- [ ] Run a test registration, payment, check-in, export and ID card print.

---

## 14. Assumptions / decisions

- **SB Osun** branding and the real event details from the existing `sb-hic` UI were retained: Standard Bearers Islamic Organization, Osun State; Oduduwa College, Ile-Ife.
- Dates follow `newC/improve.txt`: **24–28 December 2026** (the old UI said 23–27; changed as instructed).
- Categories are the real ones in the existing form: `JSS 1-3`, `SS1- SSS 3`, `School Leaver`, `Undergraduate`, `Graduate`, `Others`.
- Fees remain **₦8,500 early bird / ₦12,000 flat**, early-bird closing **30 November 2026**.
- The portal defaults to **SQLite** so it runs immediately in this environment; MySQL is fully supported and documented. Super Admins can browse the SQLite data in **Admin → Database** and run read-only SELECT queries.
- The pay-first decision means a paid delegate who has not finished step 2 appears in admin as “details pending”. Check-in and house allocation are deliberately blocked until the profile is complete.
- Bulk orders are full-payment only; delegates who need instalments should register individually.
- The admin console is server-rendered PHP with small vanilla-JS enhancements; no SPA or build pipeline.
- UI assets (gallery, highlights, lecturers, videos, Memory Lane audio) were copied from the `sb-hic` app.
- This folder is its own Git repository. Add a remote and push when ready:

```bash
git remote add origin <your-repository-url>
git push -u origin main
```

---

## 15. License

Built for Standard Bearers Islamic Organization, Osun State. Assets remain the property of their owners. Replace demo data, branding contacts and credentials before public launch.
