<?php
declare(strict_types=1);

/**
 * Small in-place upgrade so an already-installed database keeps working.
 * Cheap on every request (one PRAGMA/SHOW COLUMNS check).
 */
function ensure_payment_schema(): void
{
    if (!db_ready()) {
        return;
    }
    $columns = [
        'access_code'    => 'VARCHAR(191) NULL',
        'gateway_charge' => 'INT NOT NULL DEFAULT 0',
    ];
    foreach ($columns as $column => $definition) {
        if (db_column_exists('payments', $column)) {
            continue;
        }
        try {
            db()->exec("ALTER TABLE payments ADD COLUMN {$column} {$definition}");
        } catch (Throwable $e) {
            log_error("Could not add payments.{$column}: " . $e->getMessage());
        }
    }
}

/* ── Fees ────────────────────────────────────────────────────────────── */
function verified_installment_count(int $participantId): int
{
    return (int) db_value(
        "SELECT COUNT(*) FROM payments WHERE participant_id = ? AND payment_type = 'INSTALLMENT' AND status = 'VERIFIED'",
        [$participantId],
        0
    );
}

function has_installment_plan(int $participantId): bool
{
    return (int) db_value(
        "SELECT COUNT(*) FROM payments WHERE participant_id = ? AND payment_type = 'INSTALLMENT' AND status IN ('PENDING','VERIFIED')",
        [$participantId],
        0
    ) > 0;
}

function participant_total_base(): int
{
    return current_fee();
}

/** Base fee already settled (excludes gateway charges). */
function participant_base_paid(int $participantId): int
{
    return (int) db_value(
        "SELECT COALESCE(SUM(amount - gateway_charge), 0) FROM payments
         WHERE participant_id = ? AND status = 'VERIFIED' AND payment_type IN ('FULL','INSTALLMENT')",
        [$participantId],
        0
    );
}

function participant_charge_paid(int $participantId): int
{
    return (int) db_value(
        "SELECT COALESCE(SUM(gateway_charge), 0) FROM payments
         WHERE participant_id = ? AND status = 'VERIFIED' AND payment_type IN ('FULL','INSTALLMENT')",
        [$participantId],
        0
    );
}

function locked_full_payment(int $participantId): ?array
{
    return db_one(
        "SELECT amount, gateway_charge FROM payments
         WHERE participant_id = ? AND payment_type = 'FULL' AND status = 'VERIFIED'
         ORDER BY id LIMIT 1",
        [$participantId]
    );
}

/** Amount required to settle the whole base fee in one payment, including one charge. */
function full_payment_amount(array $participant): ?int
{
    $participantId = (int) $participant['id'];
    if (locked_full_payment($participantId)) {
        return null;
    }
    $baseRemaining = max(0, participant_total_base() - participant_base_paid($participantId));
    if ($baseRemaining <= 0) {
        return null;
    }
    return $baseRemaining + gateway_charge();
}

/** Split a base total into `count` instalments and add the per-transaction charge. */
function installment_cash_amount(int $baseTotal, int $slot, int $count, ?int $charge = null): int
{
    $count = max(1, $count);
    $slot = max(1, min($slot, $count));
    $charge ??= gateway_charge();
    $base = (int) round($baseTotal / $count);
    $baseAmount = $slot < $count ? $base : $baseTotal - ($base * ($count - 1));
    return max(1, $baseAmount) + $charge;
}

function participant_owed(array $participant): int
{
    $participantId = (int) $participant['id'];
    $paid = participant_paid($participantId);

    // A completed full payment locks in what was actually charged.
    $full = locked_full_payment($participantId);
    if ($full) {
        return max($paid, (int) $full['amount']);
    }

    $baseTotal = participant_total_base();
    $basePaid = participant_base_paid($participantId);
    $chargePaid = participant_charge_paid($participantId);
    $charge = gateway_charge();

    if ($basePaid >= $baseTotal) {
        return max($paid, $baseTotal);
    }

    // Instalment plans adapt to the current general price. Every remaining
    // instalment still carries one gateway charge.
    if (has_installment_plan($participantId)) {
        $count = installment_count();
        $verified = verified_installment_count($participantId);
        $remainingSlots = max(1, $count - $verified);
        return $baseTotal + $chargePaid + ($charge * $remainingSlots);
    }

    // A single full payment carries exactly one charge.
    return $baseTotal + $charge;
}

/**
 * Quotes the next instalment from the real remaining base balance. The quote
 * always includes exactly one gateway charge.
 */
function installment_quote(array $participant): ?array
{
    $participantId = (int) $participant['id'];
    if (locked_full_payment($participantId)) {
        return null;
    }
    $baseTotal = participant_total_base();
    $basePaid = participant_base_paid($participantId);
    $baseRemaining = max(0, $baseTotal - $basePaid);
    if ($baseRemaining <= 0) {
        return null;
    }

    $verified = verified_installment_count($participantId);
    $started = has_installment_plan($participantId);
    $count = installment_count();

    if (!$started && phase() !== 'EARLY_BIRD') {
        return null;
    }
    if ($verified >= $count) {
        return null;
    }

    $slotsLeft = max(1, $count - $verified);
    $baseAmount = $slotsLeft === 1 ? $baseRemaining : max(1, intdiv($baseRemaining, $slotsLeft));
    $baseAmount = min($baseAmount, $baseRemaining);
    $charge = gateway_charge();
    $amount = $baseAmount + $charge;
    $paid = participant_paid($participantId);
    $total = $baseTotal + participant_charge_paid($participantId) + ($charge * $slotsLeft);

    return [
        'amount'      => $amount,
        'base_amount' => $baseAmount,
        'charge'      => $charge,
        'slot'        => min($verified + 1, $count),
        'slots_left'  => $slotsLeft,
        'balance'     => max(0, $total - $paid),
        'total'       => $total,
        'started'     => $started,
        'plan_rate'   => phase() === 'EARLY_BIRD' ? 'EARLY_BIRD' : 'FLAT',
    ];
}

function fee_for(string $type, ?int $installment = null, ?array $participant = null): array
{
    $isEarly = phase() === 'EARLY_BIRD';
    $planRate = $isEarly ? 'EARLY_BIRD' : 'FLAT';
    $charge = gateway_charge();

    if ($participant !== null) {
        $participantId = (int) $participant['id'];
        if (locked_full_payment($participantId)) {
            throw new RuntimeException('This registration is already fully paid.');
        }
        $baseTotal = participant_total_base();
        $basePaid = participant_base_paid($participantId);
        $chargePaid = participant_charge_paid($participantId);
        $baseRemaining = max(0, $baseTotal - $basePaid);

        if ($type === 'FULL') {
            if ($baseRemaining <= 0) {
                throw new RuntimeException('This registration is already fully paid.');
            }
            $amount = $baseRemaining + $charge;
            return [
                'plan_rate'      => $planRate,
                'amount'         => $amount,
                'base_amount'    => $baseRemaining,
                'gateway_charge' => $charge,
                'total_fee'      => $baseTotal + $chargePaid + $charge,
                'balance'        => $amount,
            ];
        }

        $quote = installment_quote($participant);
        if ($quote === null) {
            throw new RuntimeException(
                $isEarly
                    ? 'There is no outstanding balance to pay by instalment.'
                    : 'The instalment plan is only available before the early-bird deadline.'
            );
        }
        return [
            'plan_rate'      => $quote['plan_rate'],
            'amount'         => $quote['amount'],
            'base_amount'    => $quote['base_amount'],
            'gateway_charge' => $quote['charge'],
            'total_fee'      => $quote['total'],
            'balance'        => $quote['balance'],
            'installment'    => $quote['slot'],
            'slots_left'     => $quote['slots_left'],
        ];
    }

    // Generic quote (bulk seats / demo data): one charge per transaction.
    $baseTotal = current_fee();
    if ($type === 'FULL') {
        $amount = $baseTotal + $charge;
        return [
            'plan_rate'      => $planRate,
            'amount'         => $amount,
            'base_amount'    => $baseTotal,
            'gateway_charge' => $charge,
            'total_fee'      => $amount,
            'balance'        => $amount,
        ];
    }

    if (!$isEarly) {
        throw new RuntimeException('The instalment plan is only available before the early-bird deadline.');
    }
    $count = installment_count();
    $n = (int) $installment;
    if ($n < 1 || $n > $count) {
        throw new RuntimeException('Instalment number must be between 1 and ' . $count . '.');
    }
    $amount = installment_cash_amount($baseTotal, $n, $count, $charge);
    return [
        'plan_rate'      => $planRate,
        'amount'         => $amount,
        'base_amount'    => $amount - $charge,
        'gateway_charge' => $charge,
        'total_fee'      => $baseTotal + ($count * $charge),
        'balance'        => $baseTotal + ($count * $charge),
        'installment'    => $n,
    ];
}

function participant_paid(int $participantId): int
{
    return (int) db_value(
        "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE participant_id = ? AND status = 'VERIFIED'",
        [$participantId],
        0
    );
}

function refresh_participant_payment(int $participantId): array
{
    $participant = db_one('SELECT * FROM participants WHERE id = ?', [$participantId]);
    if (!$participant) {
        return [];
    }
    $paid = participant_paid($participantId);
    $owed = participant_owed($participant);

    if ($paid >= $owed && $owed > 0) {
        $status = 'FULLY_PAID';
    } elseif ($paid > 0) {
        $status = 'PARTIALLY_PAID';
    } else {
        $status = 'NOT_PAID';
    }

    db_update('participants', [
        'amount_owed'    => $owed,
        'amount_paid'    => $paid,
        'payment_status' => $status,
        'updated_at'     => now(),
    ], 'id = ?', [$participantId]);

    if ($status === 'FULLY_PAID' && empty($participant['house_id']) && (int) ($participant['profile_completed'] ?? 0) === 1) {
        auto_assign_house($participantId, (string) $participant['gender']);
    }

    return db_one('SELECT * FROM participants WHERE id = ?', [$participantId]) ?? [];
}

/* ── Houses / attendance ─────────────────────────────────────────────── */
function auto_assign_house(int $participantId, string $gender): bool
{
    $participant = db_one('SELECT * FROM participants WHERE id = ?', [$participantId]);
    if (!$participant || !empty($participant['house_id'])) {
        return !empty($participant['house_id']);
    }

    $house = db_one(
        "SELECT h.*, (SELECT COUNT(*) FROM participants p WHERE p.house_id = h.id AND p.deleted_at IS NULL) AS occupants
         FROM houses h
         WHERE h.gender = ? AND h.status = 'ACTIVE'
         ORDER BY occupants ASC, h.id ASC",
        [$gender]
    );

    if (!$house || (int) $house['occupants'] >= (int) $house['capacity']) {
        return false;
    }

    db_update('participants', ['house_id' => $house['id'], 'updated_at' => now()], 'id = ?', [$participantId]);
    audit('ASSIGN_HOUSE', 'participant', (string) $participantId, null, ['house' => $house['name'], 'mode' => 'auto']);
    return true;
}

function ensure_attendance(int $participantId): void
{
    if (!db_one('SELECT id FROM attendance WHERE participant_id = ?', [$participantId])) {
        db_insert('attendance', [
            'participant_id' => $participantId,
            'status'         => 'NOT_ARRIVED',
        ]);
    }
}

function check_in_participant(int $participantId, string $adminName): array
{
    $participant = db_one('SELECT * FROM participants WHERE id = ? AND deleted_at IS NULL', [$participantId]);
    if (!$participant) {
        return [false, 'Participant not found.'];
    }
    if ((int) ($participant['profile_completed'] ?? 0) !== 1) {
        return [false, 'This delegate has not completed their registration form yet.'];
    }
    if ($participant['payment_status'] !== 'FULLY_PAID') {
        return [false, 'Payment is not complete — this delegate cannot be checked in.'];
    }
    ensure_attendance($participantId);
    $attendance = db_one('SELECT * FROM attendance WHERE participant_id = ?', [$participantId]);
    if ($attendance && $attendance['status'] === 'PRESENT') {
        return [false, 'Already checked in at ' . nice_datetime($attendance['check_in_time']) . '.'];
    }

    db_update('attendance', [
        'status'        => 'PRESENT',
        'check_in_time' => now(),
        'checked_in_by' => $adminName,
    ], 'participant_id = ?', [$participantId]);
    db_update('participants', ['attendance_status' => 'PRESENT', 'updated_at' => now()], 'id = ?', [$participantId]);
    audit('CHECK_IN', 'participant', (string) $participantId, null, ['by' => $adminName]);
    return [true, 'Checked in successfully.'];
}

function undo_check_in(int $participantId, string $adminName, string $reason): array
{
    $attendance = db_one('SELECT * FROM attendance WHERE participant_id = ?', [$participantId]);
    if (!$attendance || $attendance['status'] !== 'PRESENT') {
        return [false, 'This delegate is not currently checked in.'];
    }
    if (trim($reason) === '') {
        return [false, 'A correction reason is required.'];
    }
    db_update('attendance', [
        'status'            => 'NOT_ARRIVED',
        'check_in_time'     => null,
        'corrected'         => 1,
        'correction_reason' => $reason,
    ], 'participant_id = ?', [$participantId]);
    db_update('participants', ['attendance_status' => 'NOT_ARRIVED', 'updated_at' => now()], 'id = ?', [$participantId]);
    audit('UNDO_CHECK_IN', 'participant', (string) $participantId, ['status' => 'PRESENT'], ['reason' => $reason, 'by' => $adminName]);
    return [true, 'Check-in reversed and logged.'];
}

/* ── Payment initiation ──────────────────────────────────────────────── */
function payment_mode(): string
{
    // The .env PAYMENT_MODE is the single switch. Mock is only used locally.
    $mode = strtolower(trim((string) config('payment.mode', 'mock')));
    return in_array($mode, ['mock', 'manual', 'hosted'], true) ? $mode : 'mock';
}

function start_payment(array $participant, string $type, ?int $installment = null): array
{
    if ($participant['payment_status'] === 'FULLY_PAID') {
        throw new RuntimeException('This registration is already fully paid.');
    }
    if ($type !== 'FULL' && $type !== 'INSTALLMENT') {
        throw new RuntimeException('Choose a valid payment option.');
    }

    $mode = payment_mode();
    if ($mode === 'mock' && !mock_payments_enabled()) {
        throw new RuntimeException('Sandbox payments are disabled on this deployment.');
    }
    if ($mode === 'hosted' && !payment_provider_configured()) {
        throw new RuntimeException('Hosted payments are not configured yet. Please contact support.');
    }

    $fee = fee_for($type, $installment, $participant);
    $slot = $type === 'INSTALLMENT' ? (int) ($fee['installment'] ?? $installment) : null;

    $existing = null;
    if ($slot === null) {
        $existing = db_one(
            "SELECT * FROM payments WHERE participant_id = ? AND installment_number IS NULL
             AND status IN ('PENDING','FAILED') ORDER BY id DESC LIMIT 1",
            [$participant['id']]
        );
    } else {
        $existing = db_one(
            'SELECT * FROM payments WHERE participant_id = ? AND installment_number = ? ORDER BY id DESC LIMIT 1',
            [$participant['id'], $slot]
        );
    }

    if ($existing && $existing['status'] === 'VERIFIED') {
        throw new RuntimeException('This payment has already been completed.');
    }

    $reference = $existing['reference'] ?? ('SBHIC-' . strtoupper(bin2hex(random_bytes(8))));
    $provider = $mode === 'manual' ? 'manual_transfer' : ($mode === 'hosted' ? 'payvessel' : 'mock');
    $account = null;

    if ($mode === 'manual') {
        $number = (string) setting('payment_account_number', config('payment.manual_account_no', ''));
        $name = (string) setting('payment_account_name', config('payment.manual_account_name', ''));
        if ($number === '' || $name === '') {
            throw new RuntimeException('Manual payment account is not configured. Add it in Admin → Settings or .env.');
        }
        $account = [
            'account_number' => $number,
            'account_name'   => $name,
            'bank_name'      => (string) setting('payment_bank_name', config('payment.manual_bank', '')),
        ];
    } elseif ($mode === 'hosted') {
        $account = [
            'account_number' => '',
            'account_name'   => '',
            'bank_name'      => (string) config('payment.provider', 'PayVessel'),
        ];
    } else {
        $account = mock_account($reference);
    }

    // Record the attempt before contacting the gateway so a crashed request can
    // always be reconciled by support instead of vanishing without a trace.
    $data = [
        'amount'                 => $fee['amount'],
        'payment_type'           => $type,
        'installment_number'     => $slot,
        'plan_rate'              => $fee['plan_rate'],
        'provider'               => $provider,
        'status'                 => 'PENDING',
        'virtual_account_number' => $account['account_number'] ?: null,
        'account_name'           => $account['account_name'] ?: null,
        'bank_name'              => $account['bank_name'] ?: null,
        'checkout_url'           => null,
        'provider_reference'     => null,
        'access_code'            => null,
        'gateway_charge'         => (int) ($fee['gateway_charge'] ?? gateway_charge()),
        'failure_reason'         => null,
        'updated_at'             => now(),
    ];

    if ($existing) {
        db_update('payments', $data, 'id = ?', [$existing['id']]);
        $paymentId = (int) $existing['id'];
    } else {
        $data['participant_id'] = $participant['id'];
        $data['reference'] = $reference;
        $data['created_at'] = now();
        $paymentId = db_insert('payments', $data);
    }

    $payment = db_one('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    try {
        $authorization = payment_authorize($mode, [
            'email'     => (string) $participant['email'],
            'amount'    => (int) $fee['amount'],
            'reference' => $reference,
        ]);
    } catch (Throwable $e) {
        mark_payment_failed($payment, 'Payment service error.');
        log_error('Payment authorization failed: ' . $e->getMessage(), ['reference' => $reference]);
        throw new RuntimeException('The payment service is unavailable right now. Please try again shortly.');
    }

    if (empty($authorization['ok'])) {
        $reason = (string) ($authorization['message'] ?? 'Payment could not be started. Please try again.');
        mark_payment_failed($payment, $reason);
        log_error('Gateway refused authorization', [
            'reference' => $reference,
            'status'    => $authorization['status'] ?? 'unknown',
        ]);
        throw new RuntimeException($reason);
    }

    $update = ['updated_at' => now()];
    if (!empty($authorization['redirect_url'])) {
        $update['checkout_url'] = (string) $authorization['redirect_url'];
    }
    if (!empty($authorization['reference'])) {
        $safeReference = payment_safe_provider_reference((string) $authorization['reference'], (int) $paymentId);
        if ($safeReference !== null) {
            $update['provider_reference'] = $safeReference;
        }
    }
    if (!empty($authorization['access_code'])) {
        $update['access_code'] = (string) $authorization['access_code'];
    }
    db_update('payments', $update, 'id = ?', [$paymentId]);

    db_update('participants', [
        'amount_owed' => $fee['total_fee'],
        'updated_at'  => now(),
    ], 'id = ?', [$participant['id']]);

    audit('PAYMENT_INITIATED', 'payment', $reference, null, [
        'participant' => $participant['registration_id'],
        'type'        => $type,
        'amount'      => $fee['amount'],
        'mode'        => $mode,
    ]);

    $payment = db_one('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    telegram_notify_payment_started($payment, $mode);
    return [
        'payment' => $payment,
        'mode'    => $mode,
        'account' => $account,
        'label'   => $type === 'INSTALLMENT' ? 'Instalment ' . $slot . '/' . installment_count() : 'Full payment',
        'whatsapp' => $mode === 'manual' ? whatsapp_number() : null,
    ];
}

/**
 * Keeps an unpaid payment in sync with the current quote. If a general price
 * update changed the amount, mock/manual payment instructions are refreshed;
 * a hosted checkout is retired so the delegate starts a correct new session.
 */
function reconcile_pending_payments(array $participant, ?array $quote, int $balance): void
{
    $pending = db_all(
        "SELECT * FROM payments WHERE participant_id = ? AND status = 'PENDING'",
        [$participant['id']]
    );
    if ($balance <= 0) {
        foreach ($pending as $payment) {
            mark_payment_failed($payment, 'Registration is already fully paid.');
        }
        return;
    }
    foreach ($pending as $payment) {
        $expected = null;
        if ($payment['payment_type'] === 'INSTALLMENT' && $quote !== null
            && (int) $payment['installment_number'] === (int) $quote['slot']) {
            $expected = (int) $quote['amount'];
        } elseif ($payment['payment_type'] === 'FULL' && $balance > 0) {
            $expected = full_payment_amount($participant);
        }
        if ($expected === null || $expected === (int) $payment['amount']) {
            continue;
        }
        if ($payment['provider'] === 'payvessel') {
            mark_payment_failed($payment, 'Superseded by a price or plan update; start a new checkout.');
            audit('SUPERSEDE_PAYMENT', 'payment', (string) $payment['reference'], ['amount' => (int) $payment['amount']], ['amount' => $expected]);
            continue;
        }
        db_update('payments', ['amount' => $expected, 'updated_at' => now()], 'id = ? AND status = ?', [$payment['id'], 'PENDING']);
        audit('RECALCULATE_PAYMENT', 'payment', (string) $payment['reference'], ['amount' => (int) $payment['amount']], ['amount' => $expected]);
    }
}

/* ── Bulk orders ─────────────────────────────────────────────────────── */
function bulk_order_by_token(string $token): ?array
{
    return db_one('SELECT * FROM bulk_orders WHERE token = ?', [$token]);
}

function bulk_tickets(int $orderId): array
{
    return db_all(
        'SELECT bt.*, p.registration_id, p.surname, p.other_name
         FROM bulk_tickets bt LEFT JOIN participants p ON p.id = bt.participant_id
         WHERE bt.bulk_order_id = ? ORDER BY bt.id',
        [$orderId]
    );
}

function create_bulk_order(string $email, string $phone, array $cart): array
{
    $email = strtolower(trim($email));
    $phone = trim($phone);
    if (!valid_email($email)) {
        throw new RuntimeException('Enter a valid email address for the ticket buyer.');
    }
    if (!valid_phone($phone)) {
        throw new RuntimeException('Enter a valid phone number for the ticket buyer.');
    }

    $clean = [];
    foreach ($cart as $category => $qty) {
        $qty = (int) $qty;
        if ($qty <= 0) {
            continue;
        }
        if (!in_array($category, categories(), true)) {
            throw new RuntimeException('One of the selected categories is not valid.');
        }
        $clean[$category] = min(50, $qty);
    }
    $seats = array_sum($clean);
    if ($seats < 1) {
        throw new RuntimeException('Add at least one seat to your bulk order.');
    }
    if ($seats > 50) {
        throw new RuntimeException('A single bulk order is limited to 50 seats.');
    }

    $charge = gateway_charge();
    $price = current_fee() + $charge;
    $amount = $price * $seats;
    $token = random_token(24);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $orderId = db_insert('bulk_orders', [
            'token'       => $token,
            'reference'   => null,
            'buyer_email' => $email,
            'buyer_phone' => $phone,
            'seats'       => $seats,
            'amount'      => $amount,
            'status'      => 'PENDING',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        foreach ($clean as $category => $qty) {
            for ($i = 0; $i < $qty; $i++) {
                db_insert('bulk_tickets', [
                    'bulk_order_id' => $orderId,
                    'token'         => random_token(24),
                    'category'      => $category,
                    'price'         => $price,
                    'used'          => 0,
                    'created_at'    => now(),
                ]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    audit('CREATE_BULK_ORDER', 'bulk_order', (string) $orderId, null, [
        'seats' => $seats, 'amount' => $amount, 'email' => $email,
    ]);

    return db_one('SELECT * FROM bulk_orders WHERE id = ?', [$orderId]);
}

function start_bulk_payment(array $order): array
{
    if ($order['status'] !== 'PENDING') {
        throw new RuntimeException('This bulk order is already paid.');
    }

    $mode = payment_mode();
    if ($mode === 'mock' && !mock_payments_enabled()) {
        throw new RuntimeException('Sandbox payments are disabled on this deployment.');
    }
    if ($mode === 'hosted' && !payment_provider_configured()) {
        throw new RuntimeException('Hosted payments are not configured yet. Please contact support.');
    }

    $existing = db_one(
        "SELECT * FROM payments WHERE bulk_order_id = ? AND status IN ('PENDING','FAILED') ORDER BY id DESC LIMIT 1",
        [$order['id']]
    );
    $reference = $existing['reference'] ?? ('SBHIC-BULK-' . strtoupper(bin2hex(random_bytes(6))));
    $provider = $mode === 'manual' ? 'manual_transfer' : ($mode === 'hosted' ? 'payvessel' : 'mock');
    $account = null;

    if ($mode === 'manual') {
        $number = (string) setting('payment_account_number', config('payment.manual_account_no', ''));
        $name = (string) setting('payment_account_name', config('payment.manual_account_name', ''));
        if ($number === '' || $name === '') {
            throw new RuntimeException('Manual payment account is not configured. Add it in Admin → Settings or .env.');
        }
        $account = [
            'account_number' => $number,
            'account_name'   => $name,
            'bank_name'      => (string) setting('payment_bank_name', config('payment.manual_bank', '')),
        ];
    } elseif ($mode === 'hosted') {
        $account = [
            'account_number' => '',
            'account_name'   => '',
            'bank_name'      => (string) config('payment.provider', 'PayVessel'),
        ];
    } else {
        $account = mock_account($reference);
    }

    $data = [
        'amount'                 => (int) $order['amount'],
        'payment_type'           => 'BULK',
        'installment_number'     => null,
        'plan_rate'              => phase(),
        'provider'               => $provider,
        'status'                 => 'PENDING',
        'virtual_account_number' => $account['account_number'] ?: null,
        'account_name'           => $account['account_name'] ?: null,
        'bank_name'              => $account['bank_name'] ?: null,
        'checkout_url'           => null,
        'provider_reference'     => null,
        'access_code'            => null,
        'gateway_charge'         => gateway_charge() * (int) $order['seats'],
        'failure_reason'         => null,
        'bulk_order_id'          => (int) $order['id'],
        'updated_at'             => now(),
    ];
    if ($existing) {
        db_update('payments', $data, 'id = ?', [$existing['id']]);
        $paymentId = (int) $existing['id'];
    } else {
        $data['participant_id'] = 0;
        $data['reference'] = $reference;
        $data['created_at'] = now();
        $paymentId = db_insert('payments', $data);
    }

    db_update('bulk_orders', ['reference' => $reference, 'updated_at' => now()], 'id = ?', [$order['id']]);

    $payment = db_one('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    try {
        $authorization = payment_authorize($mode, [
            'email'     => (string) $order['buyer_email'],
            'amount'    => (int) $order['amount'],
            'reference' => $reference,
        ]);
    } catch (Throwable $e) {
        mark_payment_failed($payment, 'Payment service error.');
        log_error('Bulk payment authorization failed: ' . $e->getMessage(), ['reference' => $reference]);
        throw new RuntimeException('The payment service is unavailable right now. Please try again shortly.');
    }

    if (empty($authorization['ok'])) {
        $reason = (string) ($authorization['message'] ?? 'Payment could not be started. Please try again.');
        mark_payment_failed($payment, $reason);
        log_error('Gateway refused bulk authorization', [
            'reference' => $reference,
            'status'    => $authorization['status'] ?? 'unknown',
        ]);
        throw new RuntimeException($reason);
    }

    $update = ['updated_at' => now()];
    if (!empty($authorization['redirect_url'])) {
        $update['checkout_url'] = (string) $authorization['redirect_url'];
    }
    if (!empty($authorization['reference'])) {
        $safeReference = payment_safe_provider_reference((string) $authorization['reference'], (int) $paymentId);
        if ($safeReference !== null) {
            $update['provider_reference'] = $safeReference;
        }
    }
    if (!empty($authorization['access_code'])) {
        $update['access_code'] = (string) $authorization['access_code'];
    }
    db_update('payments', $update, 'id = ?', [$paymentId]);

    audit('BULK_PAYMENT_INITIATED', 'bulk_order', (string) $order['id'], null, [
        'amount' => (int) $order['amount'], 'mode' => $mode, 'reference' => $reference,
    ]);

    $payment = db_one('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    telegram_notify_payment_started($payment, $mode);

    return [
        'payment'  => $payment,
        'mode'     => $mode,
        'account'  => $account,
        'label'    => 'Bulk registration · ' . (int) $order['seats'] . ' seats',
        'whatsapp' => $mode === 'manual' ? whatsapp_number() : null,
    ];
}

function refresh_bulk_order(int $orderId): array
{
    $order = db_one('SELECT * FROM bulk_orders WHERE id = ?', [$orderId]);
    if (!$order) {
        return [];
    }
    $paid = (int) db_value(
        "SELECT COALESCE(SUM(amount),0) FROM payments WHERE bulk_order_id = ? AND status = 'VERIFIED'",
        [$orderId],
        0
    );
    $used = (int) db_value('SELECT COUNT(*) FROM bulk_tickets WHERE bulk_order_id = ? AND used = 1', [$orderId], 0);
    $seats = (int) $order['seats'];

    if ($paid >= (int) $order['amount']) {
        $status = $used >= $seats ? 'COMPLETED' : 'PAID';
        db_update('bulk_orders', [
            'status'     => $status,
            'paid_at'    => $order['paid_at'] ?: now(),
            'updated_at' => now(),
        ], 'id = ?', [$orderId]);
    } elseif ($paid > 0) {
        db_update('bulk_orders', ['status' => 'PENDING', 'updated_at' => now()], 'id = ?', [$orderId]);
    }
    return db_one('SELECT * FROM bulk_orders WHERE id = ?', [$orderId]);
}

function bulk_order_status_label(string $status): string
{
    return ucwords(strtolower(str_replace('_', ' ', $status)));
}

function mock_account(string $reference): array
{
    $digits = (string) (hexdec(substr(hash('sha1', $reference), 0, 7)) % 1000000000);
    return [
        'account_number' => '9' . str_pad($digits, 9, '0', STR_PAD_LEFT),
        'account_name'   => (string) setting('name', 'SB HIC') . ' (TEST)',
        'bank_name'      => 'Test Bank',
    ];
}

function whatsapp_number(): ?string
{
    $raw = preg_replace('/\D+/', '', (string) setting('payment_whatsapp', config('payment.whatsapp', '')));
    if (!$raw) {
        return null;
    }
    if (str_starts_with($raw, '234')) {
        return $raw;
    }
    if (str_starts_with($raw, '0')) {
        return '234' . substr($raw, 1);
    }
    return strlen($raw) === 10 ? '234' . $raw : $raw;
}

/* ── Marking verified ────────────────────────────────────────────────── */
function record_payment_event(string $providerReference, ?int $paymentId, int $amount, string $outcome, mixed $raw = null): void
{
    try {
        db_insert('payment_events', [
            'provider_reference' => $providerReference,
            'payment_id'         => $paymentId,
            'amount'             => $amount,
            'outcome'            => $outcome,
            'raw'                => $raw === null ? null : json_encode($raw, JSON_UNESCAPED_UNICODE),
            'created_at'         => now(),
        ]);
    } catch (Throwable) {
        // Duplicate delivery — already recorded.
    }
}

function mark_payment_verified(array $payment, string $providerReference, int $amount, string $source = 'gateway', ?string $accessCode = null): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Lock the row for the whole decision + transition. SQLite serialises
        // writers itself, so the helper returns an empty suffix there.
        $fresh = db_one('SELECT * FROM payments WHERE id = ?' . payment_row_lock_sql(), [$payment['id']]);
        if (!$fresh) {
            $pdo->rollBack();
            return ['ok' => false, 'result' => 'missing', 'message' => 'Payment not found.'];
        }
        if ($fresh['status'] === 'VERIFIED') {
            record_payment_event($providerReference, (int) $fresh['id'], $amount, 'DUPLICATE_PAYMENT', ['source' => $source]);
            $pdo->commit();
            return ['ok' => true, 'result' => 'already', 'message' => 'Payment was already verified.'];
        }
        if (!in_array($fresh['status'], ['PENDING', 'INITIATED'], true)) {
            record_payment_event($providerReference, (int) $fresh['id'], $amount, 'IGNORED_STATUS', ['source' => $source, 'local' => $fresh['status']]);
            $pdo->commit();
            return ['ok' => false, 'result' => 'not_pending', 'message' => 'This payment is no longer awaiting confirmation.'];
        }
        // A paid result for the wrong amount is still a failed payment. A
        // missing amount is rejected earlier by the caller.
        if ($amount !== (int) $fresh['amount']) {
            record_payment_event($providerReference, (int) $fresh['id'], $amount, 'AMOUNT_MISMATCH', [
                'source' => $source, 'expected' => (int) $fresh['amount'],
            ]);
            $pdo->commit();
            return ['ok' => false, 'result' => 'amount_mismatch', 'message' => 'Amount received does not match the amount due.'];
        }
        if ($accessCode !== null && $accessCode !== '' && !empty($fresh['access_code'])
            && !hash_equals((string) $fresh['access_code'], $accessCode)) {
            record_payment_event($providerReference, (int) $fresh['id'], $amount, 'ACCESS_CODE_MISMATCH', ['source' => $source]);
            $pdo->commit();
            return ['ok' => false, 'result' => 'access_code_mismatch', 'message' => 'The payment access code did not match.'];
        }

        // provider_reference is unique in the guide's schema. Never steal a
        // reference owned by another payment row.
        $newReference = trim($providerReference) !== ''
            ? trim($providerReference)
            : (string) ($fresh['provider_reference'] ?: $fresh['reference']);
        if ($newReference !== '' && $newReference !== (string) $fresh['provider_reference']) {
            $collision = db_one(
                'SELECT id FROM payments WHERE provider_reference = ? AND id <> ? LIMIT 1',
                [$newReference, $fresh['id']]
            );
            if ($collision) {
                $newReference = (string) $fresh['provider_reference'];
            }
        }

        $sql = "UPDATE payments SET status = 'VERIFIED', provider_reference = ?, paid_at = ?, verified_at = ?,
                failure_reason = NULL, updated_at = ?";
        $params = [$newReference, now(), now(), now()];
        if ($accessCode !== null && $accessCode !== '') {
            $sql .= ', access_code = ?';
            $params[] = $accessCode;
        }
        $sql .= " WHERE id = ? AND status IN ('PENDING','INITIATED')";
        $params[] = $fresh['id'];

        $updated = db_exec($sql, $params);
        if ($updated === 0) {
            record_payment_event($newReference, (int) $fresh['id'], $amount, 'DUPLICATE_PAYMENT', ['source' => $source]);
            $pdo->commit();
            return ['ok' => true, 'result' => 'already', 'message' => 'Payment was already verified.'];
        }

        record_payment_event($newReference, (int) $fresh['id'], $amount, 'MATCHED', ['source' => $source]);

        if (!empty($fresh['bulk_order_id'])) {
            $order = refresh_bulk_order((int) $fresh['bulk_order_id']);
            $pdo->commit();
            audit('VERIFY_BULK_PAYMENT', 'bulk_order', (string) $fresh['bulk_order_id'], ['status' => $fresh['status']], [
                'status' => 'VERIFIED', 'amount' => $amount, 'source' => $source,
            ]);
            telegram_notify_payment_verified(
                db_one('SELECT * FROM payments WHERE id = ?', [$fresh['id']]) ?? $fresh,
                $amount,
                $source
            );
            if (($order['status'] ?? '') === 'PAID') {
                send_bulk_confirmation($order);
            }
            return [
                'ok' => true,
                'result' => 'verified',
                'message' => 'Bulk payment verified.',
                'bulk_order' => $order,
            ];
        }

        $participant = refresh_participant_payment((int) $fresh['participant_id']);
        $pdo->commit();

        audit('VERIFY_PAYMENT', 'payment', (string) $fresh['reference'], ['status' => $fresh['status']], [
            'status'      => 'VERIFIED',
            'amount'      => $amount,
            'source'      => $source,
            'participant' => $participant['registration_id'] ?? '',
            'payment'     => $participant['payment_status'] ?? '',
        ]);
        telegram_notify_payment_verified(
            db_one('SELECT * FROM payments WHERE id = ?', [$fresh['id']]) ?? $fresh,
            $amount,
            $source
        );

        if (($participant['payment_status'] ?? '') === 'FULLY_PAID' && (int) ($participant['profile_completed'] ?? 0) === 1) {
            send_registration_confirmation($participant);
        }

        return ['ok' => true, 'result' => 'verified', 'message' => 'Payment verified.', 'participant' => $participant];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_error('Payment verification failed: ' . $e->getMessage(), ['payment' => $payment['reference'] ?? '']);
        return ['ok' => false, 'result' => 'error', 'message' => 'Could not verify this payment right now.'];
    }
}

function mark_payment_failed(array $payment, string $reason): void
{
    $updated = db_update('payments', [
        'status'         => 'FAILED',
        'failure_reason' => mb_substr($reason, 0, 250),
        'updated_at'     => now(),
    ], "id = ? AND status IN ('PENDING','INITIATED')", [$payment['id']]);
    if ($updated > 0) {
        audit('PAYMENT_FAILED', 'payment', (string) $payment['reference'], null, ['reason' => $reason]);
    }
}

/* ── Payment providers ───────────────────────────────────────────────── */
function gateway_configured(): bool
{
    return trim((string) config('payment.base_url', '')) !== ''
        && trim((string) config('payment.api_key', '')) !== ''
        && trim((string) config('payment.secret_key', '')) !== '';
}

function payment_provider_configured(?string $mode = null): bool
{
    $mode ??= payment_mode();
    if ($mode === 'mock') {
        return mock_payments_enabled();
    }
    if ($mode === 'manual') {
        return trim((string) setting('payment_account_number', config('payment.manual_account_no', ''))) !== ''
            && trim((string) setting('payment_account_name', config('payment.manual_account_name', ''))) !== '';
    }
    return gateway_configured();
}

/** The sandbox must never be able to mark a real order paid. */
function mock_payments_enabled(): bool
{
    return payment_mode() === 'mock' && config('app.env') !== 'production';
}

/** MySQL supports SELECT … FOR UPDATE; SQLite serialises writers itself. */
function payment_row_lock_sql(): string
{
    return ($GLOBALS['config']['db']['driver'] ?? 'sqlite') === 'mysql' ? ' FOR UPDATE' : '';
}

/** Never let a newly authorised reference steal one already owned by a row. */
function payment_safe_provider_reference(string $candidate, int $paymentId): ?string
{
    $candidate = trim($candidate);
    if ($candidate === '') {
        return null;
    }
    $collision = db_one(
        'SELECT id FROM payments WHERE provider_reference = ? AND id <> ? LIMIT 1',
        [$candidate, $paymentId]
    );
    return $collision ? null : $candidate;
}

function payment_amount_to_gateway(int $amount): int
{
    return strtolower((string) config('payment.amount_unit', 'major')) === 'minor' ? $amount * 100 : $amount;
}

/** Normalise a gateway amount to an integer in the major unit, or null when absent. */
function payment_parse_amount(mixed $value): ?int
{
    if ($value === null || is_bool($value)) {
        return null;
    }
    if (is_int($value)) {
        return $value;
    }
    if (is_float($value)) {
        return (int) round($value);
    }
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }
    $text = preg_replace('/[^0-9.\-]/', '', $text) ?? '';
    if ($text === '' || $text === '-' || $text === '.' || $text === '-.') {
        return null;
    }
    return (int) round((float) $text);
}

function payment_verify_amount(mixed $value): ?int
{
    $amount = payment_parse_amount($value);
    if ($amount === null) {
        return null;
    }
    return strtolower((string) config('payment.verify_amount_unit', 'major')) === 'minor'
        ? (int) round($amount / 100)
        : $amount;
}

/** Read the first present, non-empty value from a list of dot-notation paths. */
function payment_extract_by_paths(array $payload, array $paths): mixed
{
    foreach ($paths as $path) {
        $value = $payload;
        $found = true;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $found = false;
                break;
            }
            $value = $value[$segment];
        }
        if ($found && $value !== null && $value !== '' && !is_bool($value)) {
            return $value;
        }
    }
    return null;
}

function payment_status_kind(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === '') {
        return 'unknown';
    }
    $paid = array_map('strtolower', (array) config('payment.paid_statuses', []));
    $pending = array_map('strtolower', (array) config('payment.pending_statuses', []));
    $failed = array_map('strtolower', (array) config('payment.failed_statuses', []));
    if (in_array($status, $paid, true)) {
        return 'paid';
    }
    if (in_array($status, $pending, true)) {
        return 'pending';
    }
    if (in_array($status, $failed, true)) {
        return 'failed';
    }
    return 'unknown';
}

/** Only absolute http(s) URLs may ever leave this application. */
function payment_safe_redirect_url(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return null;
    }
    if (!in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
        return null;
    }
    return $url;
}

/** Where should the browser go after a payment is started? */
function payment_checkout_redirect(array $started): ?string
{
    $mode = (string) ($started['mode'] ?? '');
    if ($mode === 'mock') {
        return absolute_url('pay/mock/' . rawurlencode((string) ($started['payment']['reference'] ?? '')));
    }
    if ($mode === 'hosted') {
        return payment_safe_redirect_url((string) ($started['payment']['checkout_url'] ?? ''));
    }
    return null;
}

function http_json(string $method, string $url, array $headers, ?array $body = null): array
{
    $ch = curl_init($url);
    $headerLines = ['Accept: application/json'];
    foreach ($headers as $key => $value) {
        $headerLines[] = $key . ': ' . $value;
    }
    if ($body !== null) {
        $headerLines[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    }
    $timeout = max(5, (int) config('payment.http_timeout', 30));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headerLines,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Could not reach the payment service: ' . $error);
    }
    $json = json_decode((string) $raw, true);
    return ['status' => $status, 'json' => is_array($json) ? $json : null, 'raw' => (string) $raw];
}

function gateway_headers(): array
{
    $apiHeader = trim((string) config('payment.api_key_header', 'api-key')) ?: 'api-key';
    $authHeader = trim((string) config('payment.auth_header', 'Authorization')) ?: 'Authorization';
    $scheme = trim((string) config('payment.auth_scheme', 'Bearer'));
    return [
        $apiHeader  => (string) config('payment.api_key', ''),
        $authHeader => ($scheme !== '' ? $scheme . ' ' : '') . (string) config('payment.secret_key', ''),
    ];
}

/**
 * Create a hosted checkout session (or return the local sandbox URL).
 *
 * @return array{ok: bool, redirect_url?: string|null, reference?: string|null,
 *               access_code?: string|null, status?: string, message?: string}
 */
function payment_authorize(string $mode, array $p): array
{
    if ($mode === 'manual') {
        return ['ok' => true, 'redirect_url' => null, 'reference' => $p['reference'] ?? null, 'access_code' => null];
    }
    if ($mode === 'mock') {
        return [
            'ok'           => true,
            'redirect_url' => absolute_url('pay/mock/' . rawurlencode((string) ($p['reference'] ?? ''))),
            'reference'    => (string) ($p['reference'] ?? ''),
            'access_code'  => null,
            'status'       => 'created',
        ];
    }

    if (!gateway_configured()) {
        return ['ok' => false, 'status' => 'invalid_config', 'message' => 'Hosted payments are not configured yet. Please contact support.'];
    }

    $base = rtrim((string) config('payment.base_url'), '/');
    $path = '/' . ltrim((string) config('payment.authorize_path', '/api/authorize'), '/');
    $callback = trim((string) config('payment.callback_url', ''));
    if ($callback === '') {
        $callback = absolute_url('payment/callback');
    }

    $payload = [
        'email'    => (string) ($p['email'] ?? ''),
        'amount'   => payment_amount_to_gateway((int) ($p['amount'] ?? 0)),
        'callback' => $callback,
        'tickets'  => (string) ($p['reference'] ?? ''),
    ];

    try {
        $response = http_json('POST', $base . $path, gateway_headers(), $payload);
    } catch (Throwable $e) {
        log_error('Gateway authorize network failure', ['reference' => $p['reference'] ?? '']);
        return ['ok' => false, 'status' => 'network', 'message' => 'Could not reach the payment service. Please try again shortly.'];
    }

    $http = (int) $response['status'];
    if ($http >= 500) {
        log_error('Gateway authorize server error', ['status' => $http, 'reference' => $p['reference'] ?? '']);
        return ['ok' => false, 'status' => 'http_500', 'message' => 'The payment service is temporarily unavailable. Please try again shortly.'];
    }
    if ($http < 200 || $http >= 300) {
        log_error('Gateway authorize rejected request', ['status' => $http, 'reference' => $p['reference'] ?? '']);
        return ['ok' => false, 'status' => 'http_' . $http, 'message' => 'The payment service could not start this payment. Please try again.'];
    }

    $json = $response['json'];
    if (!is_array($json)) {
        return ['ok' => false, 'status' => 'invalid_response', 'message' => 'The payment service returned an unexpected response.'];
    }
    if (($json['status'] ?? null) === false) {
        return ['ok' => false, 'status' => 'rejected', 'message' => (string) ($json['message'] ?? 'The payment service could not start this payment.')];
    }

    $url = payment_extract_by_paths($json, [
        'data.authorization_url', 'data.authorizationUrl', 'data.checkout_url', 'data.checkoutUrl',
        'authorization_url', 'authorizationUrl', 'checkout_url', 'checkoutUrl',
        'payment_url', 'paymentUrl', 'url', 'redirect_url',
    ]);
    $safe = payment_safe_redirect_url((string) ($url ?? ''));
    if ($safe === null) {
        log_error('Gateway authorize returned no safe redirect', ['reference' => $p['reference'] ?? '']);
        return ['ok' => false, 'status' => 'invalid_response', 'message' => 'The payment service did not return a valid checkout link.'];
    }

    $reference = payment_extract_by_paths($json, [
        'data.reference', 'data.transactionReference', 'data.transaction_reference', 'data.txnReference',
        'reference', 'transactionReference', 'transaction_reference', 'txnReference',
    ]);
    $accessCode = payment_extract_by_paths($json, [
        'data.access_code', 'data.accessCode', 'access_code', 'accessCode',
        'data.uniquePaymentId', 'uniquePaymentId',
    ]);

    return [
        'ok'           => true,
        'redirect_url' => $safe,
        'reference'    => (string) ($reference ?? $p['reference'] ?? ''),
        'access_code'  => $accessCode === null ? null : (string) $accessCode,
        'status'       => 'created',
    ];
}

/**
 * Verify a payment server-to-server. Never throws for a gateway error.
 *
 * @return array{ok: bool, paid: bool, pending?: bool, failed?: bool,
 *               status: string, amount?: int|null, access_code?: string|null,
 *               reference?: string, message?: string}
 */
function hosted_verify(string $reference): array
{
    if (!gateway_configured()) {
        return ['ok' => false, 'paid' => false, 'status' => 'invalid_config', 'message' => 'Hosted payments are not configured.'];
    }

    $ref = trim($reference);
    if ($ref === '') {
        return ['ok' => false, 'paid' => false, 'status' => 'invalid_reference', 'message' => 'No payment reference was supplied.'];
    }
    $prefix = trim((string) config('payment.verify_reference_prefix', 'TXN'));
    if ($prefix !== '' && stripos($ref, $prefix) !== 0) {
        $ref = $prefix . $ref;
    }

    $path = '/' . ltrim(str_replace('{reference}', rawurlencode($ref), (string) config('payment.verify_path', '/api/verify/{reference}')), '/');
    $url = rtrim((string) config('payment.base_url'), '/') . $path;

    try {
        $response = http_json('GET', $url, gateway_headers());
    } catch (Throwable $e) {
        log_error('Gateway verify network failure', ['reference' => $reference]);
        return ['ok' => false, 'paid' => false, 'status' => 'network', 'message' => 'Could not verify with the payment service. Please try again shortly.'];
    }

    $http = (int) $response['status'];
    if ($http >= 500) {
        log_error('Gateway verify server error', ['status' => $http, 'reference' => $reference]);
        return ['ok' => false, 'paid' => false, 'status' => 'http_500', 'message' => 'The payment service is temporarily unavailable. Please try again shortly.'];
    }
    if ($http < 200 || $http >= 300) {
        log_error('Gateway verify rejected request', ['status' => $http, 'reference' => $reference]);
        return ['ok' => false, 'paid' => false, 'status' => 'http_' . $http, 'message' => 'The payment service could not verify this payment. Please try again.'];
    }

    $json = $response['json'];
    if (!is_array($json)) {
        return ['ok' => false, 'paid' => false, 'status' => 'invalid_response', 'message' => 'The payment service returned an unexpected response.'];
    }

    $statusRaw = payment_extract_by_paths($json, [
        'data.status', 'data.paymentStatus', 'data.transactionStatus',
        'status', 'paymentStatus', 'transactionStatus', 'message',
    ]);
    $status = strtolower(trim((string) ($statusRaw ?? 'unknown')));
    if ($status === '') {
        $status = 'unknown';
    }
    $kind = payment_status_kind($status);
    if (($json['status'] ?? null) === false && $kind === 'unknown') {
        $kind = 'failed';
        $status = 'failed';
    }
    if ($kind === 'unknown') {
        log_warning('Unknown gateway payment status', ['status' => $status, 'reference' => $reference]);
    }

    $amount = payment_verify_amount(payment_extract_by_paths($json, [
        'data.amount', 'data.transactionAmount', 'data.totalAmount',
        'amount', 'transactionAmount', 'totalAmount',
    ]));
    $access = payment_extract_by_paths($json, [
        'data.access_code', 'data.accessCode', 'access_code', 'accessCode',
        'data.uniquePaymentId', 'uniquePaymentId',
    ]);
    $refOut = payment_extract_by_paths($json, [
        'data.reference', 'data.transactionReference', 'reference', 'transactionReference',
    ]);
    $message = (string) (
        $json['message']
        ?? ($json['data']['status_message'] ?? '')
    );

    return [
        'ok'          => true,
        'paid'        => $kind === 'paid',
        'pending'     => $kind === 'pending',
        'failed'      => $kind === 'failed',
        'status'      => $status,
        'amount'      => $amount,
        'access_code' => ($access === null || $access === '') ? null : (string) $access,
        'reference'   => (string) ($refOut ?? $reference),
        'message'     => $message,
    ];
}

function mock_verify(array $payment): array
{
    $status = strtoupper((string) ($payment['status'] ?? 'PENDING'));
    if ($status === 'VERIFIED') {
        return ['ok' => true, 'paid' => true, 'pending' => false, 'status' => 'success', 'amount' => (int) $payment['amount'], 'access_code' => null];
    }
    if (in_array($status, ['FAILED', 'REFUNDED', 'CANCELLED'], true)) {
        return ['ok' => true, 'paid' => false, 'pending' => false, 'failed' => true, 'status' => 'failed', 'message' => 'Sandbox payment was not completed.'];
    }
    return ['ok' => true, 'paid' => false, 'pending' => true, 'status' => 'pending', 'message' => 'Sandbox payment is awaiting confirmation.'];
}

function manual_verify(array $payment): array
{
    return ['ok' => true, 'paid' => false, 'pending' => true, 'status' => 'pending', 'message' => 'Bank transfer is awaiting confirmation by an admin.'];
}

function payment_verify(string $reference, array $payment): array
{
    $provider = (string) ($payment['provider'] ?? '');
    if ($provider === 'mock') {
        return mock_verify($payment);
    }
    if ($provider === 'manual_transfer') {
        return manual_verify($payment);
    }
    return hosted_verify($reference !== '' ? $reference : (string) ($payment['provider_reference'] ?: $payment['reference']));
}

/** A concurrent callback may have verified while this one was still deciding. */
function payment_settle_failure(array $payment, string $message): array
{
    $fresh = db_one('SELECT * FROM payments WHERE id = ?', [$payment['id']]);
    if ($fresh && in_array($fresh['status'], ['VERIFIED', 'COMPLETED'], true)) {
        return ['ok' => true, 'state' => 'verified', 'message' => 'This payment is already confirmed.', 'payment' => $fresh];
    }
    return ['ok' => false, 'state' => 'failed', 'message' => $message, 'payment' => $fresh];
}

/**
 * Apply the verification decision table from the integration guide.
 * Used by the browser callback, the portal re-check and the admin action.
 */
function settle_payment_with_gateway(array $payment): array
{
    $reference = (string) ($payment['provider_reference'] ?: $payment['reference']);
    $result = payment_verify($reference, $payment);

    if (empty($result['ok'])) {
        log_error('Gateway verification failed', [
            'status'    => $result['status'] ?? 'unknown',
            'reference' => $payment['reference'] ?? '',
        ]);
        return [
            'ok'      => false,
            'state'   => 'pending',
            'message' => (string) ($result['message'] ?? 'We could not verify this payment right now. Please try again.'),
            'payment' => $payment,
        ];
    }

    $status = strtolower((string) ($result['status'] ?? 'unknown'));

    if (empty($result['paid'])) {
        if (!empty($result['pending']) && !(bool) config('payment.allow_pending', false)) {
            return [
                'ok'      => false,
                'state'   => 'pending',
                'message' => (string) ($result['message'] ?? 'This payment is still pending.'),
                'payment' => $payment,
            ];
        }
        if (empty($result['pending'])) {
            if ($status !== 'unknown' || !empty($result['failed'])) {
                mark_payment_failed($payment, 'Gateway status: ' . ($status ?: 'failed'));
                return payment_settle_failure($payment, (string) ($result['message'] ?? 'The payment was not successful.'));
            }
            return [
                'ok'      => false,
                'state'   => 'pending',
                'message' => 'We could not confirm this payment yet. Please try again shortly.',
                'payment' => $payment,
            ];
        }
    }

    if (!array_key_exists('amount', $result) || $result['amount'] === null) {
        log_warning('Gateway paid response missing amount', ['reference' => $payment['reference'] ?? '']);
        return [
            'ok'      => false,
            'state'   => 'pending',
            'message' => 'The payment service did not return an amount. Please try again shortly.',
            'payment' => $payment,
        ];
    }

    if ((int) $result['amount'] !== (int) $payment['amount']) {
        mark_payment_failed($payment, 'Amount mismatch.');
        log_error('Gateway amount mismatch', [
            'reference' => $payment['reference'] ?? '',
            'expected'  => (int) $payment['amount'],
            'received'  => (int) $result['amount'],
        ]);
        return payment_settle_failure($payment, 'The amount paid does not match this registration.');
    }

    $access = $result['access_code'] ?? null;
    if ($access !== null && $access !== '' && !empty($payment['access_code'])
        && !hash_equals((string) $payment['access_code'], (string) $access)) {
        mark_payment_failed($payment, 'Access code mismatch.');
        log_error('Gateway access code mismatch', ['reference' => $payment['reference'] ?? '']);
        return payment_settle_failure($payment, 'The payment could not be matched to this registration.');
    }

    $marked = mark_payment_verified(
        $payment,
        (string) ($result['reference'] ?? $reference),
        (int) $result['amount'],
        'callback',
        $access !== null ? (string) $access : null
    );
    if ($marked['ok']) {
        return [
            'ok'      => true,
            'state'   => 'verified',
            'message' => $marked['message'],
            'payment' => db_one('SELECT * FROM payments WHERE id = ?', [$payment['id']]),
        ];
    }

    if (in_array($marked['result'] ?? '', ['amount_mismatch', 'access_code_mismatch', 'not_pending'], true)) {
        return payment_settle_failure($payment, $marked['message']);
    }
    return [
        'ok'      => false,
        'state'   => 'pending',
        'message' => $marked['message'],
        'payment' => db_one('SELECT * FROM payments WHERE id = ?', [$payment['id']]),
    ];
}

/** Re-check pending hosted payments when the buyer reopens a completion page. */
function reverify_pending_payment(array $payment, int $minSeconds = 20): array
{
    $id = (int) ($payment['id'] ?? 0);
    if ($id <= 0) {
        return ['ok' => false, 'skipped' => true];
    }
    $last = (int) ($_SESSION['_payment_reverify'][$id] ?? 0);
    if (time() - $last < $minSeconds) {
        return ['ok' => false, 'skipped' => true];
    }
    $_SESSION['_payment_reverify'][$id] = time();
    return settle_payment_with_gateway($payment);
}

function reverify_participant_payments(int $participantId): void
{
    foreach (db_all(
        "SELECT * FROM payments WHERE participant_id = ? AND status = 'PENDING' AND provider = 'payvessel'",
        [$participantId]
    ) as $payment) {
        reverify_pending_payment($payment);
    }
}

function reverify_bulk_payments(int $orderId): void
{
    foreach (db_all(
        "SELECT * FROM payments WHERE bulk_order_id = ? AND status = 'PENDING' AND provider = 'payvessel'",
        [$orderId]
    ) as $payment) {
        reverify_pending_payment($payment);
    }
}

function find_payment_by_reference(string $reference): ?array
{
    $reference = trim($reference);
    if ($reference === '') {
        return null;
    }

    $forms = [$reference];
    $prefixes = array_values(array_filter([
        trim((string) config('payment.reference_prefix', '')),
        trim((string) config('payment.verify_reference_prefix', 'TXN')),
    ]));
    foreach ($prefixes as $prefix) {
        if ($prefix === '') {
            continue;
        }
        if (stripos($reference, $prefix) === 0) {
            $forms[] = substr($reference, strlen($prefix));
        } else {
            $forms[] = $prefix . $reference;
        }
    }
    $forms = array_values(array_unique(array_filter($forms, static fn (string $v): bool => $v !== '')));

    $where = [];
    $params = [];
    foreach ($forms as $form) {
        $where[] = '(reference = ? OR provider_reference = ?)';
        $params[] = $form;
        $params[] = $form;
    }

    return db_one(
        'SELECT * FROM payments WHERE ' . implode(' OR ', $where) . ' ORDER BY id DESC LIMIT 1',
        $params
    );
}

/* ── Webhook ─────────────────────────────────────────────────────────── */
function webhook_authorised(string $rawBody, array $headers): array
{
    $secret = (string) config('payment.webhook_secret', '');
    $allowedIps = (array) config('payment.webhook_ips', []);
    $ip = client_ip();

    if ($allowedIps !== [] && !in_array($ip, $allowedIps, true)) {
        return [false, 'Caller not allowed.'];
    }

    if ($secret === '') {
        if (config('app.env') === 'production') {
            return [false, 'Webhook secret is not configured.'];
        }
        return [true, null];
    }

    $headerName = strtolower((string) config('payment.webhook_header', 'Payvessel-Http-Signature'));
    $provided = '';
    foreach ($headers as $key => $value) {
        if (strtolower($key) === $headerName) {
            $provided = is_array($value) ? (string) $value[0] : (string) $value;
            break;
        }
    }
    if ($provided === '') {
        return [false, 'Missing signature header.'];
    }

    $expected = hash_hmac('sha512', $rawBody, $secret);
    if (!hash_equals($expected, $provided)) {
        return [false, 'Invalid signature.'];
    }
    return [true, null];
}

function handle_webhook_payload(array $payload): array
{
    $reference = (string) (payment_extract_by_paths($payload, [
        'data.reference', 'transaction.reference', 'transactionReference', 'transaction_reference', 'reference',
    ]) ?? '');
    $statusRaw = payment_extract_by_paths($payload, [
        'data.status', 'transaction.status', 'status', 'paymentStatus', 'transactionStatus', 'event',
    ]);
    $status = strtolower(trim((string) ($statusRaw ?? '')));
    $kind = $status === '' ? 'unknown' : payment_status_kind($status);
    $amount = payment_verify_amount(payment_extract_by_paths($payload, [
        'order.amount', 'transaction.amount', 'data.amount', 'amount', 'transactionAmount', 'totalAmount',
    ]));

    if ($reference === '') {
        return ['ok' => false, 'message' => 'reference is required.'];
    }

    $payment = find_payment_by_reference($reference);
    if (!$payment) {
        record_payment_event($reference, null, $amount ?? 0, 'UNMATCHED', $payload);
        log_warning('Unknown payment reference on webhook', ['reference' => $reference]);
        return ['ok' => true, 'status' => 'unmatched'];
    }

    if ($payment['status'] === 'VERIFIED') {
        record_payment_event($reference, (int) $payment['id'], $amount ?? 0, 'DUPLICATE_PAYMENT', ['source' => 'webhook']);
        return ['ok' => true, 'status' => 'duplicate_payment'];
    }

    if ($kind === 'failed') {
        mark_payment_failed($payment, 'Gateway webhook status: ' . $status);
        return ['ok' => true, 'status' => 'failed'];
    }
    if ($kind === 'pending') {
        return ['ok' => true, 'status' => 'pending'];
    }
    if ($kind !== 'paid') {
        // Unknown status: never guess. Reconcile against the gateway instead.
        $settled = settle_payment_with_gateway($payment);
        return ['ok' => $settled['ok'], 'status' => $settled['state'], 'message' => $settled['message']];
    }
    if ($amount === null) {
        return ['ok' => false, 'message' => 'amount is required for a paid event.'];
    }

    $result = mark_payment_verified($payment, $reference, $amount, 'webhook');
    return ['ok' => $result['ok'], 'status' => $result['result'], 'message' => $result['message']];
}

/* ── Callback ────────────────────────────────────────────────────────── */
function handle_callback(string $reference): array
{
    $payment = find_payment_by_reference($reference);
    if (!$payment) {
        log_warning('Unknown payment reference on callback', ['reference' => $reference]);
        return [
            'ok'      => false,
            'state'   => 'error',
            'message' => 'We could not find a payment with that reference. If you were charged, contact support with the reference.',
        ];
    }

    // Completed states are idempotent: never verify or issue anything twice.
    if (in_array($payment['status'], ['VERIFIED', 'COMPLETED'], true)) {
        return ['ok' => true, 'state' => 'verified', 'payment' => $payment, 'message' => 'This payment is already confirmed.'];
    }

    // Failed / cancelled / refunded are terminal. Do not re-verify or unlock.
    if (in_array($payment['status'], ['FAILED', 'CANCELLED', 'REFUNDED'], true)) {
        return [
            'ok'      => false,
            'state'   => 'failed',
            'message' => (string) ($payment['failure_reason'] ?: 'This payment was not completed.'),
            'payment' => $payment,
        ];
    }

    return settle_payment_with_gateway($payment);
}

/* ── Logging ─────────────────────────────────────────────────────────── */
function log_error(string $message, array $context = []): void
{
    log_line('ERROR', $message, $context);
}

function log_warning(string $message, array $context = []): void
{
    log_line('WARN', $message, $context);
}

function log_line(string $level, string $message, array $context = []): void
{
    $line = '[' . now() . '] ' . $level . ' ' . $message;
    if ($context !== []) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    @file_put_contents(__DIR__ . '/../storage/logs/app.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
