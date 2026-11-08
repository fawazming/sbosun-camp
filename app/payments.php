<?php
declare(strict_types=1);

const INSTALLMENT_COUNT = 10;

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

function participant_owed(array $participant): int
{
    $paid = participant_paid((int) $participant['id']);

    // A full payment locks in the price that was actually charged. Later
    // general-price changes must never create a new balance for a paid delegate.
    $full = db_one(
        "SELECT plan_rate, amount FROM payments
         WHERE participant_id = ? AND payment_type = 'FULL' AND status = 'VERIFIED'
         ORDER BY id LIMIT 1",
        [$participant['id']]
    );
    if ($full) {
        return max($paid, (int) $full['amount']);
    }

    // Instalment plans adapt to the current general price. Existing plans may
    // continue after the early-bird deadline at the flat rate; new plans cannot.
    if (has_installment_plan((int) $participant['id'])) {
        return phase() === 'FLAT'
            ? (int) setting('flat_fee', 12000)
            : (int) setting('early_bird_fee', 8500);
    }

    return current_fee();
}

/**
 * Quotes the next instalment from the real remaining balance, not by
 * repeatedly dividing the old price. This is what makes a plan adjust safely
 * when an admin changes the general camp fee or when early bird lapses.
 */
function installment_quote(array $participant): ?array
{
    $total = participant_owed($participant);
    $paid = participant_paid((int) $participant['id']);
    $balance = max(0, $total - $paid);
    if ($balance <= 0) {
        return null;
    }

    $verified = verified_installment_count((int) $participant['id']);
    $started = has_installment_plan((int) $participant['id']);
    if (!$started && phase() !== 'EARLY_BIRD') {
        return null;
    }
    if ($verified >= INSTALLMENT_COUNT) {
        return null;
    }

    $slotsLeft = max(1, INSTALLMENT_COUNT - $verified);
    $amount = $slotsLeft === 1 ? $balance : max(1, intdiv($balance, $slotsLeft));
    $amount = min($amount, $balance);

    return [
        'amount'      => $amount,
        'slot'        => min($verified + 1, INSTALLMENT_COUNT),
        'slots_left'  => $slotsLeft,
        'balance'     => $balance,
        'total'       => $total,
        'started'     => $started,
        'plan_rate'   => phase() === 'EARLY_BIRD' ? 'EARLY_BIRD' : 'FLAT',
    ];
}

function fee_for(string $type, ?int $installment = null, ?array $participant = null): array
{
    $isEarly = phase() === 'EARLY_BIRD';
    $planRate = $isEarly ? 'EARLY_BIRD' : 'FLAT';

    if ($participant !== null) {
        $total = participant_owed($participant);
        $paid = participant_paid((int) $participant['id']);
        $balance = max(0, $total - $paid);

        if ($type === 'FULL') {
            if ($balance <= 0) {
                throw new RuntimeException('This registration is already fully paid.');
            }
            return [
                'plan_rate' => $planRate,
                'amount'    => $balance,
                'total_fee' => $total,
                'balance'   => $balance,
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
            'plan_rate'   => $quote['plan_rate'],
            'amount'      => $quote['amount'],
            'total_fee'   => $quote['total'],
            'balance'     => $quote['balance'],
            'installment' => $quote['slot'],
            'slots_left'  => $quote['slots_left'],
        ];
    }

    // Generic quote (used for bulk seats).
    $total = current_fee();
    if ($type === 'FULL') {
        return ['plan_rate' => $planRate, 'amount' => $total, 'total_fee' => $total, 'balance' => $total];
    }

    if (!$isEarly) {
        throw new RuntimeException('The instalment plan is only available before the early-bird deadline.');
    }
    $n = (int) $installment;
    if ($n < 1 || $n > INSTALLMENT_COUNT) {
        throw new RuntimeException('Instalment number must be between 1 and ' . INSTALLMENT_COUNT . '.');
    }
    $base = (int) round($total / INSTALLMENT_COUNT);
    $amount = $n < INSTALLMENT_COUNT ? $base : $total - ($base * (INSTALLMENT_COUNT - 1));
    return [
        'plan_rate'   => $planRate,
        'amount'      => $amount,
        'total_fee'   => $total,
        'balance'     => $total,
        'installment' => $n,
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

    $mode = payment_mode();
    $reference = $existing['reference'] ?? ('SBHIC-' . strtoupper(bin2hex(random_bytes(8))));
    $provider = $mode === 'manual' ? 'manual_transfer' : ($mode === 'hosted' ? 'payvessel' : 'mock');
    $account = null;
    $checkoutUrl = null;
    $providerReference = $existing['provider_reference'] ?? null;

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
        $checkout = hosted_create_checkout([
            'email'     => (string) $participant['email'],
            'amount'    => $fee['amount'],
            'reference' => $reference,
            'name'      => trim($participant['other_name'] . ' ' . $participant['surname']),
            'phone'     => (string) $participant['phone'],
        ]);
        $checkoutUrl = $checkout['checkout_url'];
        $providerReference = $checkout['reference'];
        $account = [
            'account_number' => '',
            'account_name'   => '',
            'bank_name'      => (string) config('payment.provider', 'PayVessel'),
        ];
    } else {
        $account = mock_account($reference);
    }

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
        'checkout_url'           => $checkoutUrl,
        'provider_reference'     => $providerReference,
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
    return [
        'payment' => $payment,
        'mode'    => $mode,
        'account' => $account,
        'label'   => $type === 'INSTALLMENT' ? 'Instalment ' . $slot . '/' . INSTALLMENT_COUNT : 'Full payment',
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
            $expected = $balance;
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

    $price = current_fee();
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

    $existing = db_one(
        "SELECT * FROM payments WHERE bulk_order_id = ? AND status IN ('PENDING','FAILED') ORDER BY id DESC LIMIT 1",
        [$order['id']]
    );
    $mode = payment_mode();
    $reference = $existing['reference'] ?? ('SBHIC-BULK-' . strtoupper(bin2hex(random_bytes(6))));
    $provider = $mode === 'manual' ? 'manual_transfer' : ($mode === 'hosted' ? 'payvessel' : 'mock');
    $account = null;
    $checkoutUrl = null;
    $providerReference = $existing['provider_reference'] ?? null;

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
        $checkout = hosted_create_checkout([
            'email'     => (string) $order['buyer_email'],
            'amount'    => (int) $order['amount'],
            'reference' => $reference,
            'name'      => 'SB HIC Bulk Buyer',
            'phone'     => (string) $order['buyer_phone'],
        ]);
        $checkoutUrl = $checkout['checkout_url'];
        $providerReference = $checkout['reference'];
        $account = ['account_number' => '', 'account_name' => '', 'bank_name' => (string) config('payment.provider', 'PayVessel')];
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
        'checkout_url'           => $checkoutUrl,
        'provider_reference'     => $providerReference,
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

    audit('BULK_PAYMENT_INITIATED', 'bulk_order', (string) $order['id'], null, [
        'amount' => (int) $order['amount'], 'mode' => $mode, 'reference' => $reference,
    ]);

    return [
        'payment'  => db_one('SELECT * FROM payments WHERE id = ?', [$paymentId]),
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

function mark_payment_verified(array $payment, string $providerReference, int $amount, string $source = 'gateway'): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $fresh = db_one('SELECT * FROM payments WHERE id = ?', [$payment['id']]);
        if (!$fresh) {
            $pdo->rollBack();
            return ['ok' => false, 'result' => 'missing', 'message' => 'Payment not found.'];
        }
        if ($fresh['status'] === 'VERIFIED') {
            record_payment_event($providerReference, (int) $fresh['id'], $amount, 'DUPLICATE_PAYMENT', ['source' => $source]);
            $pdo->commit();
            return ['ok' => true, 'result' => 'already', 'message' => 'Payment was already verified.'];
        }
        if ($amount < (int) $fresh['amount']) {
            record_payment_event($providerReference, (int) $fresh['id'], $amount, 'UNDERPAID', ['source' => $source]);
            $pdo->commit();
            return ['ok' => false, 'result' => 'underpaid', 'message' => 'Amount received is less than the amount due.'];
        }

        $updated = db_exec(
            "UPDATE payments SET status = 'VERIFIED', provider_reference = ?, paid_at = ?, verified_at = ?,
             failure_reason = NULL, updated_at = ? WHERE id = ? AND status <> 'VERIFIED'",
            [$providerReference, now(), now(), now(), $fresh['id']]
        );
        if ($updated === 0) {
            record_payment_event($providerReference, (int) $fresh['id'], $amount, 'DUPLICATE_PAYMENT', ['source' => $source]);
            $pdo->commit();
            return ['ok' => true, 'result' => 'already', 'message' => 'Payment was already verified.'];
        }

        record_payment_event($providerReference, (int) $fresh['id'], $amount, 'MATCHED', ['source' => $source]);

        if (!empty($fresh['bulk_order_id'])) {
            $order = refresh_bulk_order((int) $fresh['bulk_order_id']);
            $pdo->commit();
            audit('VERIFY_BULK_PAYMENT', 'bulk_order', (string) $fresh['bulk_order_id'], ['status' => $fresh['status']], [
                'status' => 'VERIFIED', 'amount' => $amount, 'source' => $source,
            ]);
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
    db_update('payments', [
        'status'         => 'FAILED',
        'failure_reason' => mb_substr($reason, 0, 250),
        'updated_at'     => now(),
    ], 'id = ? AND status = ?', [$payment['id'], 'PENDING']);
    audit('PAYMENT_FAILED', 'payment', (string) $payment['reference'], null, ['reason' => $reason]);
}

/* ── Hosted gateway (PayVessel / PayFlow) ────────────────────────────── */
function gateway_configured(): bool
{
    return config('payment.api_key', '') !== '' && config('payment.secret_key', '') !== '';
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
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headerLines,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Could not reach the payment service: ' . $error);
    }
    $json = json_decode((string) $raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('The payment service returned an unexpected response.');
    }
    return ['status' => $status, 'json' => $json, 'raw' => $raw];
}

function gateway_headers(): array
{
    return [
        'api-key'       => (string) config('payment.api_key', ''),
        'Authorization' => 'Bearer ' . config('payment.secret_key', ''),
    ];
}

function hosted_create_checkout(array $p): array
{
    if (!gateway_configured()) {
        throw new RuntimeException('Hosted payments are not configured. Add the gateway keys in .env.');
    }
    $base = (string) config('payment.base_url');
    $path = (string) config('payment.authorize_path', '/api/authorize');
    $callback = (string) config('payment.callback_url', '');
    if ($callback === '') {
        $callback = absolute_url('payment/callback');
    }

    $payload = [
        'email'    => $p['email'],
        'amount'   => (int) $p['amount'],
        'callback' => $callback,
        'name'     => $p['name'],
        'phone'    => $p['phone'],
        'tickets'  => $p['reference'],
    ];
    $response = http_json('POST', $base . $path, gateway_headers(), $payload);
    $json = $response['json'];
    $data = $json['data'] ?? null;

    if (!$data || empty($data['reference'])) {
        log_error('Gateway authorize failed', ['status' => $response['status'], 'response' => mb_substr($response['raw'], 0, 800)]);
        throw new RuntimeException('The payment service could not create a checkout. Please try again.');
    }

    $url = $data['authorization_url'] ?? $data['checkout_url'] ?? null;
    if (!$url) {
        throw new RuntimeException('The payment service did not return a checkout URL.');
    }

    return [
        'reference'   => (string) $data['reference'],
        'access_code' => (string) ($data['access_code'] ?? ''),
        'checkout_url' => (string) $url,
    ];
}

function hosted_verify(string $reference): array
{
    if (!gateway_configured()) {
        return ['ok' => false, 'message' => 'Hosted payments are not configured.'];
    }
    $ref = preg_replace('/^TXN/i', '', $reference);
    $verifyRef = 'TXN' . $ref;
    $path = str_replace('{reference}', rawurlencode($verifyRef), (string) config('payment.verify_path', '/api/verify/{reference}'));
    $url = (string) config('payment.base_url') . $path;

    try {
        $response = http_json('GET', $url, gateway_headers());
    } catch (Throwable $e) {
        log_error('Gateway verify failed: ' . $e->getMessage(), ['reference' => $reference]);
        return ['ok' => false, 'message' => 'Could not verify with the payment service. Please try again shortly.'];
    }

    $json = $response['json'];
    $data = $json['data'] ?? [];
    $status = strtolower((string) ($data['status'] ?? ''));
    $amount = (int) round((float) ($data['amount'] ?? 0));

    if (!in_array($status, ['success', 'successful', 'paid', 'completed'], true)) {
        return [
            'ok'      => false,
            'pending' => in_array($status, ['pending', 'ongoing', 'processing', 'queued'], true) && (bool) config('payment.allow_pending', false),
            'failed'  => in_array($status, ['failed', 'abandoned', 'reversed', 'declined', 'invalid', 'expired', 'cancelled', 'canceled'], true),
            'message' => (string) ($data['status_message'] ?? 'Payment was not successful.'),
            'status'  => $status,
        ];
    }

    return [
        'ok'       => true,
        'amount'   => $amount,
        'reference' => (string) ($data['reference'] ?? $reference),
        'status'   => $status,
    ];
}

function find_payment_by_reference(string $reference): ?array
{
    $reference = trim($reference);
    if ($reference === '') {
        return null;
    }
    $clean = preg_replace('/^TXN/i', '', $reference);
    return db_one(
        'SELECT * FROM payments WHERE reference = ? OR provider_reference = ? OR provider_reference = ? ORDER BY id DESC LIMIT 1',
        [$reference, $reference, $clean]
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
    $reference = (string) (
        $payload['transaction']['reference']
        ?? $payload['transactionReference']
        ?? $payload['reference']
        ?? $payload['data']['reference']
        ?? ''
    );
    $amountRaw = $payload['order']['amount']
        ?? $payload['transaction']['amount']
        ?? $payload['amount']
        ?? $payload['data']['amount']
        ?? 0;
    $amount = (int) round((float) str_replace(',', '', (string) $amountRaw));

    if ($reference === '' || $amount <= 0) {
        return ['ok' => false, 'message' => 'reference and amount are required.'];
    }

    $payment = find_payment_by_reference($reference);
    if (!$payment) {
        record_payment_event($reference, null, $amount, 'UNMATCHED', $payload);
        return ['ok' => true, 'status' => 'unmatched'];
    }

    if ($payment['status'] === 'VERIFIED') {
        record_payment_event($reference, (int) $payment['id'], $amount, 'DUPLICATE_PAYMENT', $payload);
        return ['ok' => true, 'status' => 'duplicate_payment'];
    }

    $result = mark_payment_verified($payment, $reference, $amount, 'webhook');
    return ['ok' => $result['ok'], 'status' => $result['result'], 'message' => $result['message']];
}

/* ── Callback ────────────────────────────────────────────────────────── */
function handle_callback(string $reference): array
{
    $payment = find_payment_by_reference($reference);
    if (!$payment) {
        return ['ok' => false, 'state' => 'error', 'message' => 'We could not find a payment with that reference.'];
    }

    if ($payment['status'] === 'VERIFIED') {
        return ['ok' => true, 'state' => 'verified', 'payment' => $payment];
    }

    if (payment_mode() === 'hosted') {
        $verified = hosted_verify($payment['provider_reference'] ?: $reference);
        if ($verified['ok']) {
            if ($verified['amount'] > 0 && $verified['amount'] < (int) $payment['amount']) {
                mark_payment_failed($payment, 'Amount mismatch: gateway returned ' . $verified['amount']);
                return ['ok' => false, 'state' => 'failed', 'message' => 'The amount paid does not match this registration.'];
            }
            $result = mark_payment_verified($payment, (string) ($verified['reference'] ?? $reference), (int) $verified['amount'], 'callback');
            if ($result['ok']) {
                return ['ok' => true, 'state' => 'verified', 'payment' => db_one('SELECT * FROM payments WHERE id = ?', [$payment['id']])];
            }
            return ['ok' => false, 'state' => 'failed', 'message' => $result['message']];
        }
        if (!empty($verified['pending'])) {
            return ['ok' => false, 'state' => 'pending', 'message' => $verified['message'], 'payment' => $payment];
        }
        if (!empty($verified['failed'])) {
            mark_payment_failed($payment, (string) ($verified['message'] ?? 'Gateway reported failure.'));
            return ['ok' => false, 'state' => 'failed', 'message' => $verified['message'], 'payment' => $payment];
        }
        return ['ok' => false, 'state' => 'pending', 'message' => $verified['message'], 'payment' => $payment];
    }

    // Mock and manual payments are confirmed by the mock gateway or an admin.
    return ['ok' => true, 'state' => 'pending', 'payment' => $payment, 'message' => 'Payment is awaiting confirmation.'];
}

/* ── Logging ─────────────────────────────────────────────────────────── */
function log_error(string $message, array $context = []): void
{
    $line = '[' . now() . '] ' . $message;
    if ($context !== []) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    @file_put_contents(__DIR__ . '/../storage/logs/app.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
