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
- Full delegate registration with server-side validation:
  - name, gender, age bracket, email, phone/WhatsApp, category, zone;
  - **next-of-kin phone** and **allergies/existing ailment**;
  - under-18 guardian fields;
  - full payment or 10-instalment preference.
- Automatic class placement from category and unique registration number (`SB-26-XXXXXX`).
- Personal delegate portal (`/portal/{token}`) with balance, payment history, payment buttons, camp details and ID card.
- Server-authoritative pricing (early bird vs flat rate); browser-submitted totals are never trusted.
- Instalment plan: 10 parts, last part absorbs rounding; closes with early bird.
- Payment modes:
  - **mock** — local sandbox checkout for testing;
  - **manual** — bank transfer + WhatsApp receipt + finance-admin approval;
  - **hosted** — PayVessel / PayFlow hosted checkout with server-side verification.
- Server-side callback verification, idempotent verification, underpayment rejection and signed webhook handling.
- Registration status lookup by registration number + surname (rate-limited, minimal data).
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
- Participants: combined filters (search, gender, category, payment, attendance, house, zone, date range), pagination, filtered CSV export, detail view with protected welfare fields, check-in/undo, house assignment, soft-delete.
- Payments: status tabs, search, verified total, manual approval, refund with reason, CSV export.
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
- Underpayment, duplicate payments and amount mismatches are rejected/logged.
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

All payment settings are in `.env`; the active mode is editable in **Admin → Settings** (stored in `settings.payment_mode`).

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

- `POST {PAYMENT_BASE_URL}/api/authorize` with `api-key` + `Authorization: Bearer …`.
- Body: `email`, `amount` (naira), `callback`, `name`, `phone`, `tickets` (our reference).
- Response: `data.authorization_url` / `data.checkout_url`, `data.reference`, `data.access_code`.
- `GET {PAYMENT_BASE_URL}/api/verify/TXN{reference}` with the same headers.
- Verification is server-side; amount and status are checked; pending is rejected unless `PAYMENT_ALLOW_PENDING=true`.
- Callback: `GET /index.php?r=payment/callback&reference=…`.
- Webhook: `POST /index.php?r=payment/webhook` with the HMAC-SHA512 signature header (default `Payvessel-Http-Signature`). Optional IP allowlist. Duplicate deliveries are recorded as `DUPLICATE_PAYMENT` and never double-credit.

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
| Super Admin | Everything, including users, settings and audit |
| Registration Admin | Participants, check-in desk, newsletter |
| Finance Admin | Payments, approvals, refunds, exports |
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
- `participants` — delegate record, including `nok_phone`, `nok_name`, `allergies`, `health_challenges`, guardian fields, payment totals, soft delete.
- `payments` — one row per attempt/instalment: reference, provider reference, amount, type, plan, status, account/checkout details.
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
/register                  registration form (GET/POST)
/portal/{token}            delegate portal
/pay/start                 start a payment (POST)
/pay/mock/{reference}      sandbox checkout
/payment/callback          gateway return / status page
/payment/webhook           signed provider webhook (POST)
/status                    registration lookup
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
/admin/check-in            check-in desk
/admin/houses              houses / auto-assign
/admin/reports             CSV exports
/admin/newsletter          subscribers / broadcast
/admin/users               admin accounts
/admin/settings            programme configuration
/admin/audit               audit log
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
3. Instalments are 10 parts, early bird only; the last part absorbs rounding.
4. A verified payment can only create one verification event per provider reference.
5. Underpaid or duplicate transfers never mark a delegate paid.
6. House assignment is random/least-occupied from the gender list, and only for fully paid delegates.
7. Each edition writes to the same normalised tables (edition year is a setting); historical isolation can be added with an edition column.
8. Failed/cancelled/expired payments never count as paid.
9. Soft-deleted delegates disappear from reports, lists and exports.
10. Mail/webhook failures never block a completed registration/payment.

---

## 12. Verification performed in this build

- `php -l` across every PHP file: clean.
- Fresh SQLite install: tables, settings, 3 classes, 4 houses and Super Admin created.
- Public home, register, status, checklist, gallery assets, ID card, `.ics`, login pages return 200.
- Full registration → portal → mock checkout → verified payment → `FULLY_PAID` → automatic house assignment.
- Status lookup by registration ID + surname.
- Newsletter AJAX subscribe.
- Admin login, dashboard, participants, payments, check-in, houses, reports, users, settings, audit and newsletter pages all 200.
- Filtered CSV export with correct headers and escaped values.
- Admin check-in for a fully paid delegate, with state update.
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
- The portal defaults to **SQLite** so it runs immediately in this environment; MySQL is fully supported and documented.
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
