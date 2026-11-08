# Payment Gateway Integration Guide

This document describes the payment subsystem of this application so it can be
reimplemented in another codebase or ported to another hosted payment provider.
It covers the flow, the gateway contract, the endpoints a merchant application
must expose, the database invariants, and the failure modes that matter.

The reference implementation is CodeIgniter 4 / PHP, but the rules are
framework-agnostic. File names are included so you can compare behaviour.

---

## 1. Mental model

This application is the **merchant**. It never collects or stores card data.
It redirects the buyer to a **hosted checkout page** and then asks the gateway,
server-to-server, whether the payment really succeeded.

There are two independent sources of truth:

1. **The browser return** (a GET request to `/payment/callback`). It is only a
   trigger that says "go and check now".
2. **The server-to-server verification call** to the gateway. This is the only
   source of truth for paid/not-paid, amount, and gateway reference.

If the browser never returns, the payment must still be recoverable by opening
the completion page again (the app re-runs verification on demand). A production
implementation should also have a webhook or reconciliation job for payments that
are never revisited by the buyer.

```text
Buyer ──► /register or /bulkregister
             │
             │ 1. validate input + price server-side
             ▼
        transactions row  ──► 2. POST /api/authorize ──► Gateway
             │                                            │
             │ 3. store pending + payment_url              │
             ▼                                            ▼
        redirect buyer ─────────────────────────► Hosted checkout
                                                          │
                                    4. buyer pays / fails / cancels
                                                          │
                                                          ▼
        /payment/callback?reference=…  ◄──────── gateway return
             │
             │ 5. GET /api/verify/TXN{reference}
             ▼
        verify amount, status, access code
             │
             ├── paid ──► transaction = verified
             │             ├── single ──► /complete/{access_token}
             │             └── bulk   ──► /tickets/{access_token}
             │
             └── pending/failed ──► user-facing message, no ticket issued
```

---

## 2. Reference implementation map

| Responsibility | File |
|---|---|
| Non-secret gateway config + env overrides | `app/Config/Payment.php` |
| Business logic / state machine | `app/Libraries/PaymentService.php` |
| Provider interface | `app/Libraries/Payment/PaymentProviderInterface.php` |
| Hosted gateway adapter (PayFlow / PayVessel) | `app/Libraries/Payment/HostedProvider.php` |
| Local sandbox provider | `app/Libraries/Payment/MockProvider.php` |
| Browser callback endpoint | `app/Controllers/PaymentController.php` |
| Sandbox hosted page | `app/Controllers/MockPaymentController.php` |
| Registration initiation | `app/Controllers/RegistrationController.php` |
| Bulk ticket pages / completion | `app/Controllers/BulkController.php` |
| Transaction and ticket models | `app/Models/Transaction.php`, `app/Models/Ticket.php` |
| Ticket consumption / delegate creation | `app/Libraries/RegistrationService.php` |
| Schema | `app/Database/Migrations/2026-01-01-000001_CreateCoreTables.php` |

Key design decision: **the provider is an adapter, not controller logic.** The
rest of the app calls `authorize()` and `verify()` through an interface, so the
gateway can be changed without touching the registration and completion flows.

---

## 3. End-to-end flow

### 3.1 Payment initiation (single ticket)

1. Buyer submits `email` and `category` to `POST /register`.
2. The server validates:
   - registration is enabled and the event has not ended;
   - IP rate limit (the reference uses 10 attempts / 10 minutes);
   - email is valid;
   - category exists in the central event config.
3. `PaymentService::initiate()` resolves the price from the server-side config
   for the current pricing phase. Browser-submitted prices are never accepted.
4. Inside one database transaction it:
   - inserts a `transactions` row with status `initiated`;
   - inserts one `tickets` row per seat with a unique 7-character code.
5. After the DB commit it calls the gateway's `authorize` endpoint.
6. On success it updates the transaction to `pending`, stores `payment_url`,
   `access_code`, provider name and mode, then returns the redirect URL.
7. The controller validates that the redirect is an absolute `http`/`https` URL
   and redirects the browser there.

If authorization fails, the transaction is marked `failed` and the buyer gets a
friendly error. The pre-created ticket rows remain unused; they are only
consumable after verification.

### 3.2 Payment initiation (bulk)

Same flow with an array of `{category, quantity}` lines. The server merges
duplicate categories, rejects unknown categories, rejects non-positive
quantities, caps the order (50 in this implementation), recomputes every line
price and the grand total, and creates one transaction plus N tickets. Kind is
stored as `bulk`.

The description is canonical and used later to recover the ticket category:

```text
camp{editionYear}:{category}   → single
camp{editionYear}:bulk         → bulk
```

### 3.3 Browser return / callback

The gateway sends the buyer back to:

```text
GET /payment/callback?reference=…&status=…
GET /payment/callback/{reference}
```

The callback:

1. Rate-limits by IP (60 checks / 60 seconds).
2. Resolves the reference from the path or from common query names:
   `reference`, `trxref`, `paymentReference`, `transaction_ref`,
   `transactionRef`, `ref`.
3. Normalises the reference (configured prefix stripping; optional `TXN` retry).
4. Loads the local transaction by `provider_reference` **or**
   `internal_reference`.
5. If already `completed` or `verified`, returns success immediately.
6. If already `failed` or `cancelled`, returns failure immediately.
7. Calls the gateway verification endpoint server-to-server.
8. Applies the decision rules in section 8.
9. On verified: sends notifications, optionally emails bulk links, and redirects
   to completion.
10. On pending/unverified: shows a "check again" page.
11. On failure: shows a failure page with the support contact.

The callback is a public **GET** and must not require login, CSRF, or
maintenance mode. It returns HTML, not JSON, because a human browser follows it.

### 3.4 Completion

- **Single:** `/complete/{access_token}`. The token is a 64-character random hex
  string stored on the transaction. If the transaction is not yet verified, the
  page re-runs verification. After the form is submitted, the server re-checks
  the transaction status and creates exactly one delegate.
- **Bulk:** `/tickets/{access_token}` lists one link per ticket. Each
  `/t/{ticket_code}` can complete one delegate. The ticket is marked used in a
  locked transaction; the transaction becomes `completed` when the last unused
  ticket is consumed.

Completion is described in more detail in section 9 because it is where
double-spend and duplicate-delegate bugs usually appear.

---

## 4. Data model

### 4.1 `transactions`

```sql
id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
provider_reference     VARCHAR(191) NOT NULL   -- gateway reference, UNIQUE
internal_reference     VARCHAR(191) NOT NULL   -- merchant reference, UNIQUE
business_id            VARCHAR(191) NULL
email                  VARCHAR(191) NOT NULL
amount                 INT UNSIGNED NOT NULL   -- integer, major currency unit
currency               VARCHAR(8) NOT NULL DEFAULT 'NGN'
access_code            VARCHAR(191) NULL
callback_url           VARCHAR(255) NULL
status                 VARCHAR(32) NOT NULL DEFAULT 'initiated'
description            VARCHAR(255) NULL        -- camp2026:category / camp2026:bulk
kind                   VARCHAR(16) NOT NULL DEFAULT 'single' -- single|bulk
related_transaction    JSON NULL               -- immutable order snapshot
access_token           VARCHAR(64) NOT NULL    -- completion link token, UNIQUE
payment_url            VARCHAR(500) NULL
provider               VARCHAR(32) NULL
mode                   VARCHAR(16) NULL
failure_reason         VARCHAR(255) NULL
verified_at            DATETIME NULL
completed_at           DATETIME NULL
created_at             DATETIME NOT NULL
updated_at             DATETIME NULL
```

Important constraints:

- `provider_reference`, `internal_reference` and `access_token` are unique.
- `amount` is an integer in the **major** unit (naira), not a float and not kobo.
- `related_transaction` stores the order snapshot:
  `{phase, lines: [{category, quantity, unit_price, label}], total, quantity}`.
  It is the authoritative record of what was sold.

### 4.2 `tickets`

```sql
id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
transaction_id  INT UNSIGNED NOT NULL
code            VARCHAR(16) NOT NULL          -- UNIQUE, one-use
category        VARCHAR(64) NOT NULL
price           INT UNSIGNED NOT NULL         -- major unit at purchase time
used            TINYINT(1) NOT NULL DEFAULT 0
used_at         DATETIME NULL
delegate_id     INT UNSIGNED NULL
created_at      DATETIME NOT NULL
FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
```

Ticket codes are 7 characters from a 32-symbol alphabet that excludes ambiguous
characters (`ABCDEFGHJKLMNPQRSTUVWXYZ23456789`), generated with a CSPRNG and
retried until the unique constraint is satisfied.

---

## 5. Outbound gateway contract

The reference adapter implements the **PayFlow / PayVessel** hosted checkout
contract. Treat these as values to make configurable, not constants.

### 5.1 Authentication headers

Every API call sends both headers:

```http
api-key: PVKEY-xxxxxxxxxxxxxxxx
Authorization: Bearer PVSECRET-xxxxxxxxxxxxxxxx
Content-Type: application/json
Accept: application/json
```

`api-key` header name, auth header name and auth scheme are configurable.
The two keys must belong to the same gateway business.

### 5.2 Create a payment session

```http
POST {PAYMENT_BASE_URL}/api/authorize
```

Request body used by this app:

```json
{
  "email": "buyer@example.com",
  "amount": 8150,
  "callback": "https://merchant.example/payment/callback",
  "tickets": "HIC2026-a1b2c3d4e5f60718293a"
}
```

- `amount` is an integer in the gateway's expected unit. The config supports
  `major` (default; send naira) and `minor` (multiply by 100).
- `callback` is an absolute public HTTPS URL.
- `tickets` is a free-form order identifier; the gateway stores it for
  reconciliation. The reference implementation sends the merchant reference.
- Optional gateway fields such as `name` and `phone` are not used here.

A 2xx response is expected to look like:

```json
{
  "status": true,
  "message": "Authorization URL created",
  "data": {
    "authorization_url": "https://gateway.example/pay/9f86d081884c7d65",
    "checkout_url": "https://checkout.example/session/abc123",
    "access_code": "9f86d081884c7d65",
    "reference": "1029384756",
    "expires_at": "2026-10-22 14:45:00"
  }
}
```

The adapter accepts the redirect URL from any of these paths:
`data.authorization_url`, `data.authorizationUrl`, `data.checkout_url`,
`data.checkoutUrl`, `authorization_url`, `authorizationUrl`, `checkout_url`,
`checkoutUrl`, `payment_url`, `paymentUrl`, `url`, `redirect_url`.

The reference/access code can come from a similar list of nested and flat
aliases. If no reference is returned, the merchant reference is kept.

**Non-2xx or non-JSON must be treated as a failure**, with a different message
for `5xx` (service unavailable) and `4xx` (payment could not be started). Never
try to parse a redirect out of an error body.

### 5.3 Verify a payment

```http
GET {PAYMENT_BASE_URL}/api/verify/TXN{reference}
```

The live PayFlow verification endpoint requires the `TXN` prefix. The app adds
it automatically when it is not already present (case-insensitive). The
callback may send either the numeric reference or the `TXN`-prefixed form.

Expected successful response:

```json
{
  "status": "success",
  "callback_url": "https://merchant.example/payment/callback",
  "message": "Verification successful",
  "data": {
    "reference": "1029384756",
    "status": "success",
    "status_message": "Transaction successfully processed",
    "amount": "8150.00",
    "email": "buyer@example.com",
    "created_at": "2026-10-22 14:30:00",
    "expires_at": "2026-10-22 14:45:00",
    "validated_at": "2026-10-22 14:31:18"
  }
}
```

Fields are read tolerantly:

- **Status:** `data.status`, `data.paymentStatus`, `data.transactionStatus`,
  `status`, `paymentStatus`, `transactionStatus`, `message` (first non-empty).
- **Amount:** `data.amount`, `data.transactionAmount`, `data.totalAmount`,
  `amount`, `transactionAmount`, `totalAmount`. Currency symbols/commas are
  stripped, then the value is rounded to an integer. If the provider returns
  minor units, set `PAYMENT_VERIFY_AMOUNT_UNIT=minor` to divide by 100.
- **Access code:** `data.access_code`, `data.accessCode`, `access_code`,
  `accessCode`, `data.uniquePaymentId`, `uniquePaymentId`.

Statuses treated as paid:

```text
success, successful, completed, complete, paid, approved, verified, 00
```

Statuses treated as pending:

```text
pending, processing, initiated, awaiting_payment, ongoing
```

Statuses always treated as failed:

```text
failed, failure, cancelled, canceled, expired, reversed,
declined, invalid, amount_invalid, amt_invalid, error, abandoned
```

If you change providers, keep these lists configurable and log unknown statuses.

### 5.4 Other gateway endpoints (not used by this app)

The provider documentation also describes `/verify/{reference}` (public polling),
`/payment/return/{reference}`, `/pay/{access_code}`, `/pay/OTP`, `/api/cva` and a
signed webhook. This app does not call them, but a different implementation may
need them. See section 13 for webhook rules.

---

## 6. Inbound endpoints your application must expose

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/payment/callback` | Public, no CSRF | Browser return; verify and redirect |
| `GET` | `/payment/callback/{reference}` | Public, no CSRF | Same, path form |
| `POST` | `/register` | Public, CSRF | Start a single-ticket payment |
| `POST` | `/bulkregister` | Public, CSRF | Start a bulk payment |
| `GET` | `/complete/{access_token}` | Token in URL | Single completion form |
| `POST` | `/complete` | CSRF | Create the single delegate |
| `GET` | `/tickets/{access_token}` | Token in URL | Bulk ticket link list |
| `POST` | `/tickets/{access_token}/email` | CSRF + rate limit | Email bulk links |
| `GET` | `/t/{ticket_code}` | Code in URL | Bulk completion form |
| `POST` | `/t/{ticket_code}` | CSRF + rate limit | Create one bulk delegate |
| `GET` | `/status` / `POST /status` | Public, rate limited | Optional lookup by registration number + surname |
| `GET` | `/ticket/{public_token}` | Token in URL | ID card after completion |

Sandbox-only:

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/pay/mock/{reference}` | Simulated hosted checkout page |
| `POST` | `/pay/mock/{reference}` | `action=pay\|fail\|cancel` |

### 6.1 Callback implementation notes

- Register it **outside** the authentication filter, CSRF protection and the
  "new registrations paused" filter. In the reference app, the maintenance
  filter wraps only `/register` and `/bulkregister`.
- Accept both query and path references, and the common aliases listed above.
- Do not require the gateway to send `status`; ignore any status/amount in the
  query string and re-verify.
- Return a user-facing HTML page or redirect. A bare `200 OK` is not enough for
  a human returning from checkout.
- Log warnings for missing or unknown references; log errors for verification
  failures, amount mismatches and access-code mismatches. Never log secrets or
  full gateway payloads.

### 6.2 Sandbox endpoint

The mock provider is a complete hosted-page simulation:

1. `MockProvider::authorize()` returns
   `/pay/mock/{provider_reference}`.
2. The page shows the order snapshot and three buttons.
3. `pay` sets status `mock_paid` and generates an access code; `fail` sets
   `mock_failed`; `cancel` sets `mock_cancelled`.
4. The browser is sent to `/payment/callback?reference=…`.
5. `MockProvider::verify()` maps those local statuses to the same
   `paid/pending/failed` contract used by the real adapter.

This lets the whole verification, idempotency and ticket-consumption path be
tested without network access. **Never enable the mock provider in production**
because it would allow anyone to mark an order paid.

---

## 7. Provider abstraction

If you are porting this to another framework, keep the same two-method boundary:

```php
interface PaymentProviderInterface
{
    public function name(): string;
    public function isConfigured(): bool;

    /**
     * @return array{
     *   ok: bool, redirect_url?: string, reference?: string,
     *   access_code?: string, message?: string
     * }
     */
    public function authorize(array $payment, array $transaction): array;

    /**
     * @return array{
     *   ok: bool, paid: bool, pending?: bool, status: string,
     *   amount?: int|null, access_code?: string|null, message?: string
     * }
     */
    public function verify(string $reference, array $transaction): array;
}
```

Rules for the adapter:

- `authorize()` must return a validated absolute `http`/`https` redirect URL.
- `verify()` must return a **normalised integer amount in major units** or
  `null` if the amount is missing. Do not silently coerce missing to zero.
- `verify()` must never throw for a gateway error; return `ok=false` and a
  machine-readable `status` such as `network`, `invalid_response`, `http_500`.
- Provider selection is configuration (`PAYMENT_PROVIDER=mock|hosted`), not a
  code branch inside controllers.
- HTTP client settings: `timeout=30`, `http_errors=false` (so you can inspect
  non-2xx), TLS verification on.

---

## 8. Transaction lifecycle

```text
                 authorize
initiated ─────────────────────► pending
    │                              │
    │ authorize failed             │ verification paid
    ▼                              ▼
 failed                         verified
                                   │
                                   │ all tickets consumed
                                   ▼
                               completed
```

Additional rules:

- `failed` / `cancelled` are terminal; a callback for them must not verify or
  issue tickets.
- `verified` means money is confirmed but delegate details are not complete.
- `completed` means every ticket in the order has been consumed.
- A duplicate callback for `verified` returns success without re-verifying.
- A duplicate callback for `completed` returns success and does not create a
  second delegate.
- `initiated` and `pending` are the only states that trigger a gateway verify.
- Provider-reported failure statuses (and any unknown non-paid status) mark the
  transaction `failed` with a reason, unless it is already `verified` or
  `completed`.

The reference implementation's decision table is:

| Verification result | Local action |
|---|---|
| `ok=false` (network/HTTP/bad JSON) | No state change; return `unverified`; user can retry |
| `paid=true` | Continue to amount/access-code checks |
| `pending=true`, `allowPending=false` | No state change; return `pending` |
| `pending=true`, `allowPending=true` | Continue as if paid (explicit business opt-in) |
| Not paid, status in failed list or != `unknown` | Mark `failed`; return failure |
| Paid but amount `null` | No state change; return `amount_missing` |
| Paid but amount != transaction amount | Mark `failed`; return `amount_mismatch` |
| Access codes both present and differ | Mark `failed`; return `access_code_mismatch` |
| All checks pass | Mark `verified` inside a row lock |

`pending` is **not** success. Only enable `PAYMENT_ALLOW_PENDING` if the gateway
contract explicitly guarantees settlement.

---

## 9. Callback algorithm (implementation sketch)

```text
function callback(reference):
    reference = normalise(reference)
    if reference is empty:
        return not_found("reference missing")

    tx = find transaction where provider_reference = reference
                      or internal_reference = reference

    if tx is null and reference starts with "TXN":
        tx = find transaction where provider_reference = reference[3:]
                          or internal_reference = reference[3:]

    if tx is null:
        log warning
        return not_found

    if tx.status == completed:
        return ok(completed, already_completed=true)

    if tx.status == verified:
        return ok(verified)

    if tx.status in [failed, cancelled]:
        return failed

    result = provider.verify(reference, tx)

    if not result.ok:
        log error with status
        return unverified   // do NOT change local state

    status = lowercase(result.status)

    if not result.paid:
        if result.pending:
            if not allowPending:
                return pending
            // allowPending=true: fall through
        else:
            if status in failedStatuses or status != "unknown":
                markFailed(tx, "Gateway status: " + status)
            return failed(status)

    if result.amount is null:
        return amount_missing

    if int(result.amount) != int(tx.amount):
        markFailed(tx, "Amount mismatch.")
        return amount_mismatch

    if result.access_code not empty and tx.access_code not empty:
        if not constantTimeEquals(tx.access_code, result.access_code):
            markFailed(tx, "Access code mismatch.")
            return access_code_mismatch

    begin DB transaction
        locked = SELECT * FROM transactions WHERE id = ? FOR UPDATE

        if locked.status in [completed, verified]:
            commit
            return ok(locked.status)

        update transactions set
            status        = verified,
            verified_at   = now,
            failure_reason = null,
            access_code   = result.access_code (when provided),
            provider_reference = reference (only if no other row owns it)
        where id = locked.id
    commit

    send Telegram "payment verified" (failure must not break flow)
    if tx.kind == bulk and email enabled:
        email bulk ticket links (failure must not break flow)

    return ok(verified)
```

Why the row lock: two callback hits can arrive at the same time (buyer refresh,
gateway retry, admin "re-run callback"). Without `SELECT … FOR UPDATE` around
the final state transition, both can pass the pre-check and both can attempt to
consume tickets. The lock plus the `status in [verified, completed]` re-check
makes the transition exactly-once.

### 9.1 Completion and ticket consumption

Completion has a second row lock and a second idempotency check:

```text
begin transaction
    locked = SELECT * FROM transactions WHERE id = ? FOR UPDATE

    if locked.status == completed:
        return existing delegate (already completed)

    if locked.status != verified:
        return "not verified"

    ticket = SELECT * FROM tickets
             WHERE transaction_id = ?
               AND (code = ? OR used = 0)
             ORDER BY id ASC LIMIT 1
             FOR UPDATE

    if ticket is null: rollback, "ticket not found"
    if ticket.used:    commit, return existing delegate / "ticket used"
    if submitted category != ticket.category: rollback, "category mismatch"

    insert delegate
    update ticket set used=1, used_at=now, delegate_id=newId
      where id = ticket.id and used = 0
    if affectedRows != 1: rollback, "ticket used"

    if no unused tickets remain for the transaction:
        update transaction set status=completed, completed_at=now
commit
```

Key points:

- The ticket row is the concurrency boundary, not the transaction row alone.
- The conditional `WHERE used = 0` update is a second line of defence.
- Delegate creation and ticket consumption are in the **same** database
  transaction. If either fails, neither is kept.
- The delegate's `ref` is the transaction's `provider_reference`; for bulk it
  additionally stores the ticket code in `txn` as
  `{provider_reference}-{ticket_code}`.
- Email and Telegram notifications run **after** the commit and are wrapped in
  try/catch. A mail outage must never roll back a paid registration.

---

## 10. Configuration

All secrets belong in the environment. Non-secret contract values can live in a
config class and be overridden by env.

```dotenv
PAYMENT_PROVIDER = mock                 # mock | hosted
PAYMENT_MODE = test                     # test | live

PAYMENT_BASE_URL = https://payflow.example
PAYMENT_API_KEY = PVKEY-...
PAYMENT_SECRET_KEY = PVSECRET-...
PAYMENT_BUSINESS_ID = ...

PAYMENT_CALLBACK_URL = https://merchant.example/payment/callback
PAYMENT_AMOUNT_UNIT = major             # major | minor
PAYMENT_VERIFY_AMOUNT_UNIT = major      # major | minor
PAYMENT_ALLOW_PENDING = false

# Optional overrides
PAYMENT_AUTHORIZE_PATH = /api/authorize
PAYMENT_VERIFY_PATH = /api/verify/{reference}
PAYMENT_AUTH_HEADER = Authorization
PAYMENT_AUTH_SCHEME = Bearer
PAYMENT_API_KEY_HEADER = api-key
PAYMENT_VERIFY_REFERENCE_PREFIX = TXN
PAYMENT_REFERENCE_PREFIX =
```

Provider readiness should be explicit:

```text
mock: always configured
hosted: baseUrl non-empty
        AND PAYMENT_API_KEY non-empty
        AND PAYMENT_SECRET_KEY non-empty
```

If the gateway is not configured, block new payments with a clear message
instead of creating a transaction that can never be paid.

---

## 11. Security invariants (non-negotiable)

1. **Server-side price authority.** Never accept an amount, total, unit price or
   category label from the browser as authoritative. Re-resolve from config.
2. **Server-to-server verification.** The callback's query string is untrusted.
   Always call the gateway verify endpoint and read the amount from the response.
3. **Exact amount comparison.** Compare integers in the same unit. Reject a
   missing amount instead of treating it as zero. Reject mismatches.
4. **Constant-time access-code comparison.** Use `hash_equals` (PHP),
   `crypto.timingSafeEqual` (Node), or equivalent.
5. **No duplicate delegates.** Check transaction status, lock the row, and
   consume the ticket conditionally.
6. **No reuse of a ticket.** The ticket code is one-use. Lock it, check
   `used=0`, and update conditionally.
7. **Idempotent callback.** Refreshing or replaying the callback must not
   change a completed transaction and must not issue a second delegate.
8. **State whitelisting.** Only the service updates payment status. Do not let
   arbitrary request fields or gateway fields overwrite local columns.
9. **URL validation before redirect.** Only absolute `http`/`https` URLs are
   allowed. This prevents open redirects if a gateway response is ever
   compromised.
10. **Secrets stay server-side.** Never put API keys in HTML, JavaScript, logs,
    error messages or source control. Redact gateway payloads in logs.
11. **Public callback, protected actions.** The callback must not require auth
    or CSRF, but the completion form and admin actions must.
12. **Rate limiting.** Limit registration initiation, callback polling, status
    lookup, and bulk-link emails by IP (and by token where possible).
13. **TLS verification.** Keep the HTTP client's certificate verification on.
14. **No secrets in URLs.** Gateway credentials go in headers, never query
    parameters.

---

## 12. Things to watch out for

### Money and units

- Store money as integers in the major unit. Do not use floats for arithmetic.
- The gateway may return `"8150.00"` or `815000` depending on the contract.
  Normalise deliberately and configure `major` vs `minor`.
- Strip currency symbols and thousands separators before converting, but reject
  an empty result rather than defaulting to zero.
- A missing amount is not a valid payment confirmation. Return a retryable
  failure and let an operator investigate.
- Do not compare formatted strings (`"8,150.00"` vs `"8150"`); compare integers.

### References

- Keep a merchant reference (`internal_reference`) and a gateway reference
  (`provider_reference`) separately. Some gateways echo the merchant reference;
  some return their own numeric reference.
- Look transactions up by **either** reference. The callback may use either.
- The live verify endpoint requires a `TXN` prefix even though the callback may
  send the bare numeric reference. Add the prefix for verification, and strip it
  when falling back to lookup.
- Prefix stripping is configurable. If you hardcode it, document it and cover
  both prefixed and unprefixed forms in tests.
- When updating `provider_reference` after authorization, check for a collision
  with an existing row first; the column is unique.
- Generate references with a CSPRNG. The reference implementation uses the
  registration prefix + year + 80 bits of randomness.

### Idempotency and concurrency

- A fast buyer can hit the callback twice; an admin can click "re-run callback";
  the gateway can retry. All three paths must be safe.
- Check status before verification, then lock and re-check before writing.
- Use `SELECT … FOR UPDATE` inside the same transaction as the state change.
- For ticket consumption, also use a conditional update
  (`WHERE used = 0`) and verify `affectedRows == 1`.
- Keep ticket creation, transaction creation, and price calculation in one DB
  transaction; otherwise a crash can leave a transaction with missing tickets.
- Do not send emails or alerts inside the DB transaction.

### Statuses

- `pending` is not success. Only allow it to settle if the provider contract
  explicitly guarantees it.
- Treat unknown non-paid statuses conservatively: mark failed and log, or leave
  pending and reconcile manually. Never assume "not failed" means "paid".
- A status of `success` with a wrong amount is still a failed payment.
- A paid response with a missing/incorrect access code must not verify.
- Some gateways return HTTP 200 with `{"status": false, "message": "..."}` for
  application errors. Check the body status, not just the HTTP code.
- Some gateways return a boolean `status` at the top level and a string status
  under `data.status`; read the nested field first when normalising.

### Network and gateway failures

- Distinguish "gateway is down" (retryable, `5xx`, timeout, non-JSON) from
  "payment was rejected" (terminal, `4xx`/failed status). They need different
  user messages and different operational handling.
- Set a timeout (30 seconds is reasonable) and `http_errors=false` so you can
  inspect non-2xx responses instead of throwing.
- Log only the HTTP status and a short response snippet; never log keys,
  full request bodies, card data or complete payloads.
- Validate the redirect URL before sending the buyer to it. Reject relative or
  non-HTTP URLs.
- If authorization fails after the transaction row is committed, mark it
  `failed`. Unused tickets remain and can be reconciled; they are not valid for
  completion because the transaction is not verified.

### Callback and UX

- The callback should work even if the buyer closes the tab before it loads:
  make `/complete/{token}` and `/tickets/{token}` re-run verification when the
  status is still `pending`/`initiated`.
- Pending and unverified states should show a "check again" link and never say
  the payment failed. A slow gateway is not a failed payment.
- A failure page must include the support email/phone and the reference.
- Do not expose stack traces, gateway responses, SQL errors or environment
  values to the buyer.
- Do not issue tickets based on the redirect alone. The redirect is merely a
  hint to run verification.

### Bulk orders

- Create one ticket per seat at initiation, not after payment, so the order is
  inspectable during support.
- The order snapshot (`related_transaction`) must be immutable; do not
  recompute the cart after payment.
- Each ticket link is a bearer token: anyone with the URL can complete that
  slot. Treat it as secret and add `noindex`.
- The transaction becomes `completed` only when every ticket is used. Partial
  completion is normal and must remain visible to support.
- Category resolution for a single order should use, in order: the canonical
  description (`camp{year}:{category}`), the order snapshot, the single ticket
  row, then amount matching only if exactly one category matches. Legacy
  amount matching alone is ambiguous and dangerous.

### Sandbox and operations

- Never run `PAYMENT_PROVIDER=mock` in production; it would let a buyer mark
  their own order paid.
- Keep a reconciliation view for `pending` transactions. Check the gateway
  dashboard and logs during peak sales.
- Rotate gateway keys after the event and before handover.
- When sales close, disable registration or switch to a no-payment mode rather
  than leaving the checkout open.
- Make the callback URL public and stable, and ensure it is not behind a WAF
  challenge, auth, CSRF or maintenance page.

### If you add webhooks (recommended for production)

The reference app relies on browser returns plus on-demand re-verification.
A more robust implementation should also accept gateway webhooks:

1. Read the **raw** request body; do not verify a re-serialised JSON object.
2. Verify the HMAC signature (the provider docs use HMAC-SHA512 hex in a
   `Payvessel-Http-Signature`-style header) with a constant-time comparison.
3. Optionally enforce a gateway IP allowlist.
4. Deduplicate deliveries by gateway event/reference in a dedicated table.
5. Never trust the webhook body alone: re-verify the payment server-to-server,
   or at minimum reconcile the amount/reference against the local transaction.
6. Respond `2xx` quickly and do slow work asynchronously. Return `5xx` only when
   you want the gateway to retry; `4xx` tells it to stop retrying.
7. Make the webhook handler idempotent; it must converge to the same state as the
   browser callback.

---

## 13. Test matrix

Minimum tests for any reimplementation:

- [ ] Single registration: valid email + valid category creates one transaction,
      one ticket, and redirects to the gateway.
- [ ] Unknown category, invalid email, zero/negative quantity and oversized bulk
      carts are rejected server-side.
- [ ] Browser-submitted price changes are ignored.
- [ ] Successful payment: callback verifies, transaction becomes `verified`,
      completion creates exactly one delegate, ticket becomes used, transaction
      becomes `completed`.
- [ ] Refreshing the callback after completion does not create a second delegate.
- [ ] Two concurrent callbacks produce one verified state, not two.
- [ ] Failed payment: transaction becomes `failed`, no delegate, ticket unused.
- [ ] Cancelled payment: same as failed.
- [ ] Pending payment: no delegate; the user gets a retry page; a later callback
      can still settle it.
- [ ] Gateway timeout / non-JSON / 5xx: no state change, friendly error, retry
      possible, no secret in logs.
- [ ] Amount mismatch: transaction fails, no delegate.
- [ ] Access-code mismatch: transaction fails, no delegate.
- [ ] Bulk order: N tickets created, N unique codes, each link works once,
      transaction completes only after the last ticket.
- [ ] Reusing a bulk link shows "already used" and does not create a delegate.
- [ ] Two buyers cannot consume the same ticket concurrently.
- [ ] A failed registration never sends a confirmation email.
- [ ] Email/Telegram outage after a successful payment does not roll back the
      registration.
- [ ] Callback with an unknown reference shows a support message and logs a
      warning.
- [ ] Callback accepts both numeric and `TXN`-prefixed references.
- [ ] Callback accepts `reference`, `trxref`, `paymentReference`, `ref` and the
      path form.
- [ ] Redirect URL returned by the gateway is validated before use.
- [ ] Mock provider is refused/disabled in production configuration.

---

## 14. Porting checklist

When moving this to another language/framework:

1. Recreate the `transactions` and `tickets` tables with the same uniqueness and
   foreign-key constraints.
2. Port the provider interface first, then the two adapters.
3. Keep all gateway paths, header names and amount units in configuration.
4. Port `PaymentService::initiate` without trusting any client-side price.
5. Port `PaymentService::callback` exactly, including the row lock and the
   re-check inside the lock.
6. Port `RegistrationService::complete` with its ticket lock and conditional
   update.
7. Add the callback route outside auth/CSRF/maintenance.
8. Add rate limits for initiation, callback and link emails.
9. Add structured logs for gateway HTTP status, verification status, amount
   mismatch, access-code mismatch and unknown references (no secrets).
10. Add an operational view listing pending/failed transactions and a manual
    "re-verify" action.
11. Run the full sandbox journey before connecting a live key.
12. Do a small live transaction and confirm the callback, amount, delegate,
    ticket state and ID card. Then test a decline.

If your provider's contract differs, adapt only the adapter and non-secret
config. Do not scatter provider-specific field names through controllers,
models or views.
