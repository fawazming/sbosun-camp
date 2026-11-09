<?php
declare(strict_types=1);

/* ── Public pages ────────────────────────────────────────────────────── */

function public_open(): bool
{
    if ((string) setting('maintenance_mode', '0') === '1') {
        return false;
    }
    if ((string) setting('registration_open', '1') !== '1') {
        return false;
    }
    return true;
}

function require_public_open(): void
{
    if (public_open()) {
        return;
    }
    http_response_code(503);
    view('public/closed', [
        'title'       => 'Registration paused',
        'maintenance' => (string) setting('maintenance_mode', '0') === '1',
    ], 'layout');
    exit;
}

function page_home(): void
{
    $counts = ['participants' => 0, 'zones' => 0];
    if (db_ready()) {
        $counts['participants'] = count_rows('participants', 'deleted_at IS NULL');
        $counts['zones'] = (int) db_value('SELECT COUNT(DISTINCT zone) FROM participants WHERE deleted_at IS NULL', [], 0);
    }
    view('public/home', [
        'title'  => setting('name', 'SB HIC 2026') . ' — Registration & Camp Portal',
        'counts' => $counts,
    ], 'layout');
}

function page_register(): void
{
    require_public_open();
    view('public/register', ['title' => 'Register — ' . setting('name', 'SB HIC 2026')], 'layout');
}

/**
 * Validate the full delegate profile. Used by both single and bulk completion.
 * Returns [clean input, errors].
 */
function validate_delegate_profile(array $input): array
{
    $clean = [
        'surname'           => trim((string) ($input['surname'] ?? '')),
        'other_name'        => trim((string) ($input['other_name'] ?? '')),
        'gender'            => strtoupper(trim((string) ($input['gender'] ?? ''))),
        'age_group'         => trim((string) ($input['age_group'] ?? '')),
        'zone'              => trim((string) ($input['zone'] ?? '')),
        'phone'             => trim((string) ($input['phone'] ?? '')),
        'email'             => strtolower(trim((string) ($input['email'] ?? ''))),
        'nok_name'          => trim((string) ($input['nok_name'] ?? '')),
        'nok_phone'         => trim((string) ($input['nok_phone'] ?? '')),
        'allergies'         => trim((string) ($input['allergies'] ?? '')),
        'health_challenges' => trim((string) ($input['health_challenges'] ?? '')),
        'is_minor'          => !empty($input['is_minor']) ? 1 : 0,
        'guardian_name'     => trim((string) ($input['guardian_name'] ?? '')),
        'guardian_contact'  => trim((string) ($input['guardian_contact'] ?? '')),
    ];

    $errors = [];
    if ($clean['surname'] === '') {
        $errors[] = 'Surname is required.';
    }
    if ($clean['other_name'] === '') {
        $errors[] = 'Other name is required.';
    }
    if (!in_array($clean['gender'], ['MALE', 'FEMALE'], true)) {
        $errors[] = 'Select a valid gender.';
    }
    if (!in_array($clean['age_group'], age_groups(), true)) {
        $errors[] = 'Select a valid age bracket.';
    }
    if (!in_array($clean['zone'], zones(), true)) {
        $errors[] = 'Select a valid zone.';
    }
    if (!valid_email($clean['email'])) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!valid_phone($clean['phone'])) {
        $errors[] = 'Enter a valid phone / WhatsApp number.';
    }
    if (!valid_phone($clean['nok_phone'])) {
        $errors[] = 'Enter a valid next-of-kin phone number.';
    }
    if ($clean['allergies'] === '') {
        $errors[] = 'State any allergies/existing ailment, or type “None”.';
    }
    if ($clean['is_minor'] && ($clean['guardian_name'] === '' || $clean['guardian_contact'] === '')) {
        $errors[] = 'Guardian name and contact are required for delegates under 18.';
    }

    return [$clean, $errors];
}

function has_verified_payment(int $participantId): bool
{
    return (int) db_value(
        "SELECT COUNT(*) FROM payments WHERE participant_id = ? AND status = 'VERIFIED'",
        [$participantId],
        0
    ) > 0;
}

/**
 * Pay-first registration: create a lightweight participant shell, start the
 * payment, and collect the delegate profile only after money is confirmed.
 */
function submit_register(): void
{
    verify_csrf();
    require_public_open();

    if (registration_rate_limited()) {
        flash('error', 'Too many registration attempts from this network. Please try again later.');
        redirect_route('register');
    }

    $input = [
        'email'    => strtolower(trim((string) post('email'))),
        'phone'    => trim((string) post('phone')),
        'category' => trim((string) post('category')),
        'plan'     => strtoupper((string) post('preferred_plan')) === 'INSTALLMENT' ? 'INSTALLMENT' : 'FULL',
    ];
    remember_old($input);

    $errors = [];
    if (!valid_email($input['email'])) {
        $errors[] = 'Enter a valid email address for your receipt and portal link.';
    }
    if (!valid_phone($input['phone'])) {
        $errors[] = 'Enter a valid phone / WhatsApp number.';
    }
    if (!in_array($input['category'], categories(), true)) {
        $errors[] = 'Select a valid category.';
    }
    if ($input['plan'] === 'INSTALLMENT' && phase() !== 'EARLY_BIRD') {
        $errors[] = 'The instalment plan is only available before the early-bird deadline.';
    }
    $class = class_for_category($input['category']);
    if (!$class) {
        $errors[] = 'No class is configured for that category.';
    }
    if ($errors !== []) {
        flash('error', implode(' ', $errors));
        redirect_route('register');
    }

    do {
        $registrationId = slug_id('SB');
    } while (db_one('SELECT id FROM participants WHERE registration_id = ?', [$registrationId]));

    $participantId = db_insert('participants', [
        'registration_id'   => $registrationId,
        'token'             => random_token(24),
        'surname'           => '',
        'other_name'        => '',
        'gender'            => '',
        'age_group'         => '',
        'category'          => $input['category'],
        'class_id'          => (int) $class['id'],
        'zone'              => '',
        'phone'             => $input['phone'],
        'email'             => $input['email'],
        'allergies'         => null,
        'nok_phone'         => null,
        'profile_completed' => 0,
        'payment_status'    => 'NOT_PAID',
        'attendance_status' => 'NOT_ARRIVED',
        'amount_owed'       => current_fee() + gateway_charge(),
        'amount_paid'       => 0,
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    ensure_attendance($participantId);
    registration_rate_hit();
    clear_old();
    audit('START_REGISTRATION', 'participant', $registrationId, null, [
        'email' => $input['email'], 'category' => $input['category'], 'plan' => $input['plan'],
    ]);

    $participant = db_one('SELECT * FROM participants WHERE id = ?', [$participantId]);
    try {
        $started = start_payment($participant, $input['plan'], $input['plan'] === 'INSTALLMENT' ? 1 : null);
    } catch (Throwable $e) {
        flash('error', 'Payment could not be started: ' . $e->getMessage() . ' You can retry from your portal.');
        redirect_route('portal/' . $participant['token']);
    }

    $checkout = payment_checkout_redirect($started);
    if ($checkout !== null) {
        redirect($checkout);
    }
    if ($started['mode'] === 'hosted') {
        flash('error', 'The payment service returned an invalid checkout link. Please try again from your portal.');
        redirect_route('status');
    }
    flash('success', 'Almost there — complete the transfer below. Your delegate form unlocks after payment is confirmed.');
    redirect_route('portal/' . $participant['token'], ['pay' => $started['payment']['reference']]);
}

function page_complete(array $params): void
{
    $participant = participant_by_token((string) $params['token']);
    if (!$participant) {
        http_response_code(404);
        view('errors/404', ['title' => 'Registration not found'], 'layout');
        return;
    }
    if ((int) ($participant['profile_completed'] ?? 0) === 1) {
        redirect_route('portal/' . $participant['token']);
    }
    if (!has_verified_payment((int) $participant['id'])) {
        // The buyer may have closed the gateway tab. Re-run verification on
        // demand instead of assuming the payment failed.
        reverify_participant_payments((int) $participant['id']);
        $participant = db_one('SELECT * FROM participants WHERE id = ?', [$participant['id']]) ?? $participant;
    }
    if (!has_verified_payment((int) $participant['id'])) {
        flash('error', 'Complete payment first. Your delegate form unlocks as soon as payment is confirmed.');
        redirect_route('portal/' . $participant['token']);
    }
    $class = $participant['class_id'] ? db_one('SELECT * FROM classes WHERE id = ?', [$participant['class_id']]) : null;
    $quotes = installment_quote($participant);
    view('public/complete', [
        'title'       => 'Complete registration — ' . setting('name', 'SB HIC 2026'),
        'participant' => $participant,
        'class'       => $class,
        'quote'       => $quotes,
        'owed'        => participant_owed($participant),
        'paid'        => participant_paid((int) $participant['id']),
    ], 'layout');
}

function submit_complete(array $params): void
{
    verify_csrf();
    $participant = participant_by_token((string) $params['token']);
    if (!$participant) {
        http_response_code(404);
        view('errors/404', ['title' => 'Registration not found'], 'layout');
        return;
    }
    if (!has_verified_payment((int) $participant['id'])) {
        flash('error', 'Payment has not been confirmed yet.');
        redirect_route('portal/' . $participant['token']);
    }
    if ((int) ($participant['profile_completed'] ?? 0) === 1) {
        redirect_route('portal/' . $participant['token']);
    }

    $input = [
        'surname'           => post('surname'),
        'other_name'        => post('other_name'),
        'gender'            => post('gender'),
        'age_group'         => post('age_group'),
        'zone'              => post('zone'),
        'phone'             => post('phone'),
        'email'             => post('email'),
        'nok_name'          => post('nok_name'),
        'nok_phone'         => post('nok_phone'),
        'allergies'         => post('allergies'),
        'health_challenges' => post('health_challenges'),
        'is_minor'          => post('is_minor'),
        'guardian_name'     => post('guardian_name'),
        'guardian_contact'  => post('guardian_contact'),
    ];
    remember_old($input);
    [$clean, $errors] = validate_delegate_profile($input);

    if ($errors !== []) {
        flash('error', implode(' ', $errors));
        redirect_route('complete/' . $participant['token']);
    }

    db_update('participants', [
        'surname'           => $clean['surname'],
        'other_name'        => $clean['other_name'],
        'gender'            => $clean['gender'],
        'age_group'         => $clean['age_group'],
        'zone'              => $clean['zone'],
        'phone'             => $clean['phone'],
        'email'             => $clean['email'],
        'nok_name'          => $clean['nok_name'] ?: null,
        'nok_phone'         => $clean['nok_phone'],
        'allergies'         => $clean['allergies'],
        'health_challenges' => $clean['health_challenges'] ?: null,
        'is_minor'          => $clean['is_minor'],
        'guardian_name'     => $clean['is_minor'] ? $clean['guardian_name'] : null,
        'guardian_contact'  => $clean['is_minor'] ? $clean['guardian_contact'] : null,
        'profile_completed' => 1,
        'updated_at'        => now(),
    ], 'id = ?', [$participant['id']]);

    $participant = refresh_participant_payment((int) $participant['id']);
    if (($participant['payment_status'] ?? '') === 'FULLY_PAID' && empty($participant['house_id'])) {
        auto_assign_house((int) $participant['id'], (string) $participant['gender']);
        $participant = db_one('SELECT * FROM participants WHERE id = ?', [$participant['id']]);
        send_registration_confirmation($participant);
    }

    clear_old();
    audit('COMPLETE_REGISTRATION', 'participant', $participant['registration_id'], null, ['name' => $clean['other_name'] . ' ' . $clean['surname']]);
    flash('success', 'Registration complete. Your delegate portal and ID card are ready.');
    redirect_route('portal/' . $participant['token']);
}

/* ── Delegate portal ─────────────────────────────────────────────────── */
function page_portal(array $params): void
{
    $participant = participant_by_token((string) $params['token']);
    if (!$participant) {
        http_response_code(404);
        view('errors/404', ['title' => 'Registration not found'], 'layout');
        return;
    }
    // Recover payments whose browser callback never arrived.
    reverify_participant_payments((int) $participant['id']);
    $participant = db_one('SELECT * FROM participants WHERE id = ?', [$participant['id']]) ?? $participant;
    ensure_attendance((int) $participant['id']);
    $class = $participant['class_id'] ? db_one('SELECT * FROM classes WHERE id = ?', [$participant['class_id']]) : null;
    $house = $participant['house_id'] ? db_one('SELECT * FROM houses WHERE id = ?', [$participant['house_id']]) : null;
    $attendance = db_one('SELECT * FROM attendance WHERE participant_id = ?', [$participant['id']]);
    $owed = participant_owed($participant);
    $paid = participant_paid((int) $participant['id']);
    $balance = max(0, $owed - $paid);
    $quote = installment_quote($participant);

    // A general price update must never leave a stale amount on an unpaid checkout.
    reconcile_pending_payments($participant, $quote, $balance);

    $payments = db_all('SELECT * FROM payments WHERE participant_id = ? ORDER BY id DESC', [$participant['id']]);
    $hasPayment = has_verified_payment((int) $participant['id']);
    $profileCompleted = (int) ($participant['profile_completed'] ?? 0) === 1;

    $activePayment = null;
    if (!empty($_GET['pay'])) {
        $activePayment = db_one('SELECT * FROM payments WHERE reference = ? AND participant_id = ?', [$_GET['pay'], $participant['id']]);
    }

    $displayName = trim($participant['other_name'] . ' ' . $participant['surname']);
    if ($displayName === '') {
        $displayName = $participant['email'];
    }

    view('public/portal', [
        'title'            => $displayName . ' — Registration',
        'participant'      => $participant,
        'class'            => $class,
        'house'            => $house,
        'attendance'       => $attendance,
        'payments'         => $payments,
        'owed'             => $owed,
        'paid'             => $paid,
        'balance'          => $balance,
        'activePayment'    => $activePayment,
        'quote'            => $quote,
        'hasPayment'       => $hasPayment,
        'profileCompleted' => $profileCompleted,
        'mode'             => payment_mode(),
        'preferredPlan'    => strtoupper((string) ($_GET['plan'] ?? '')),
    ], 'layout');
}

/* ── Bulk registration ───────────────────────────────────────────────── */
function page_bulk_register(): void
{
    require_public_open();
    view('public/bulk', [
        'title'      => 'Bulk registration — ' . setting('name', 'SB HIC 2026'),
        'fee'        => current_fee() + gateway_charge(),
        'categories' => categories(),
        'phase'      => phase_label(),
    ], 'layout');
}

function submit_bulk_register(): void
{
    verify_csrf();
    require_public_open();

    if (registration_rate_limited()) {
        flash('error', 'Too many registration attempts from this network. Please try again later.');
        redirect_route('bulk');
    }

    $email = strtolower(trim((string) post('email')));
    $phone = trim((string) post('phone'));
    $quantities = (array) post('qty', []);
    remember_old(['email' => $email, 'phone' => $phone, 'qty' => $quantities]);

    $order = null;
    try {
        $order = create_bulk_order($email, $phone, $quantities);
        registration_rate_hit();
        clear_old();
        $started = start_bulk_payment($order);
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect_route($order ? 'bulk/' . $order['token'] : 'bulk');
    }

    $checkout = payment_checkout_redirect($started);
    if ($checkout !== null) {
        redirect($checkout);
    }
    if ($started['mode'] === 'hosted') {
        flash('error', 'The payment service returned an invalid checkout link. Please try again from your portal.');
        redirect_route('status');
    }
    flash('success', 'Bulk order created. Complete the transfer below — your seat links unlock after payment.');
    redirect_route('bulk/' . $order['token'], ['pay' => $started['payment']['reference']]);
}

function page_bulk_portal(array $params): void
{
    $order = bulk_order_by_token((string) $params['token']);
    if (!$order) {
        http_response_code(404);
        view('errors/404', ['title' => 'Bulk order not found'], 'layout');
        return;
    }
    // Recover bulk payments whose browser callback never arrived.
    reverify_bulk_payments((int) $order['id']);
    $order = db_one('SELECT * FROM bulk_orders WHERE id = ?', [$order['id']]) ?? $order;
    $tickets = bulk_tickets((int) $order['id']);
    $payments = db_all('SELECT * FROM payments WHERE bulk_order_id = ? ORDER BY id DESC', [$order['id']]);
    $pending = null;
    foreach ($payments as $payment) {
        if ($payment['status'] === 'PENDING') {
            $pending = $payment;
            break;
        }
    }
    $activePayment = null;
    if (!empty($_GET['pay'])) {
        $activePayment = db_one(
            'SELECT * FROM payments WHERE reference = ? AND bulk_order_id = ?',
            [$_GET['pay'], $order['id']]
        );
    }
    $used = 0;
    foreach ($tickets as $ticket) {
        if ((int) $ticket['used'] === 1) {
            $used++;
        }
    }
    view('public/bulk-portal', [
        'title'         => 'Bulk order — ' . $order['buyer_email'],
        'order'         => $order,
        'tickets'       => $tickets,
        'payments'      => $payments,
        'pending'       => $pending,
        'activePayment' => $activePayment,
        'used'          => $used,
        'mode'          => payment_mode(),
        'whatsapp'      => whatsapp_number(),
    ], 'layout');
}

function retry_bulk_payment(array $params): void
{
    verify_csrf();
    require_public_open();
    $order = bulk_order_by_token((string) $params['token']);
    if (!$order) {
        flash('error', 'Bulk order not found.');
        redirect_route('bulk');
    }
    try {
        $started = start_bulk_payment($order);
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect_route('bulk/' . $order['token']);
    }
    $checkout = payment_checkout_redirect($started);
    if ($checkout !== null) {
        redirect($checkout);
    }
    if ($started['mode'] === 'hosted') {
        flash('error', 'The payment service returned an invalid checkout link. Please try again from your portal.');
        redirect_route('status');
    }
    flash('success', 'Transfer instructions refreshed below.');
    redirect_route('bulk/' . $order['token'], ['pay' => $started['payment']['reference']]);
}

function page_complete_ticket(array $params): void
{
    $ticket = db_one('SELECT * FROM bulk_tickets WHERE token = ?', [(string) $params['token']]);
    if (!$ticket) {
        http_response_code(404);
        view('errors/404', ['title' => 'Ticket not found'], 'layout');
        return;
    }
    $order = db_one('SELECT * FROM bulk_orders WHERE id = ?', [$ticket['bulk_order_id']]);
    if (!$order) {
        http_response_code(404);
        view('errors/404', ['title' => 'Bulk order not found'], 'layout');
        return;
    }
    if ((int) $ticket['used'] === 1) {
        flash('error', 'This ticket link has already been completed. Each link works once.');
        redirect_route('bulk/' . $order['token']);
    }
    if (!in_array($order['status'], ['PAID', 'COMPLETED'], true)) {
        flash('error', 'The bulk payment has not been confirmed yet.');
        redirect_route('bulk/' . $order['token']);
    }
    $class = class_for_category((string) $ticket['category']);
    view('public/complete', [
        'title'       => 'Complete delegate details — ' . setting('name', 'SB HIC 2026'),
        'mode'        => 'bulk',
        'formAction'  => url('complete-ticket/' . $ticket['token']),
        'participant' => null,
        'ticket'      => $ticket,
        'order'       => $order,
        'class'       => $class,
        'category'    => $ticket['category'],
        'owed'        => (int) $ticket['price'],
        'paid'        => (int) $ticket['price'],
        'quote'       => null,
        'prefill'     => [
            'email' => $order['buyer_email'],
            'phone' => $order['buyer_phone'],
        ],
    ], 'layout');
}

function submit_complete_ticket(array $params): void
{
    verify_csrf();
    $ticket = db_one('SELECT * FROM bulk_tickets WHERE token = ?', [(string) $params['token']]);
    if (!$ticket) {
        http_response_code(404);
        view('errors/404', ['title' => 'Ticket not found'], 'layout');
        return;
    }
    $order = db_one('SELECT * FROM bulk_orders WHERE id = ?', [$ticket['bulk_order_id']]);
    if (!$order) {
        http_response_code(404);
        view('errors/404', ['title' => 'Bulk order not found'], 'layout');
        return;
    }
    if ((int) $ticket['used'] === 1) {
        flash('error', 'This ticket link has already been used.');
        redirect_route('bulk/' . $order['token']);
    }
    if (!in_array($order['status'], ['PAID', 'COMPLETED'], true)) {
        flash('error', 'The bulk payment has not been confirmed yet.');
        redirect_route('bulk/' . $order['token']);
    }

    $input = [
        'surname'           => post('surname'),
        'other_name'        => post('other_name'),
        'gender'            => post('gender'),
        'age_group'         => post('age_group'),
        'zone'              => post('zone'),
        'phone'             => post('phone'),
        'email'             => post('email'),
        'nok_name'          => post('nok_name'),
        'nok_phone'         => post('nok_phone'),
        'allergies'         => post('allergies'),
        'health_challenges' => post('health_challenges'),
        'is_minor'          => post('is_minor'),
        'guardian_name'     => post('guardian_name'),
        'guardian_contact'  => post('guardian_contact'),
    ];
    remember_old($input);
    [$clean, $errors] = validate_delegate_profile($input);
    if ($errors !== []) {
        flash('error', implode(' ', $errors));
        redirect_route('complete-ticket/' . $ticket['token']);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $claimed = db_exec(
            'UPDATE bulk_tickets SET used = 1, used_at = ? WHERE id = ? AND used = 0',
            [now(), $ticket['id']]
        );
        if ($claimed === 0) {
            $pdo->rollBack();
            flash('error', 'This ticket link has already been used on another device.');
            redirect_route('bulk/' . $order['token']);
        }

        $class = class_for_category((string) $ticket['category']);
        do {
            $registrationId = slug_id('SB');
        } while (db_one('SELECT id FROM participants WHERE registration_id = ?', [$registrationId]));

        $participantId = db_insert('participants', [
            'registration_id'   => $registrationId,
            'token'             => random_token(24),
            'surname'           => $clean['surname'],
            'other_name'        => $clean['other_name'],
            'gender'            => $clean['gender'],
            'age_group'         => $clean['age_group'],
            'category'          => $ticket['category'],
            'class_id'          => $class ? (int) $class['id'] : null,
            'zone'              => $clean['zone'],
            'phone'             => $clean['phone'],
            'email'             => $clean['email'],
            'health_challenges' => $clean['health_challenges'] ?: null,
            'allergies'         => $clean['allergies'],
            'nok_name'          => $clean['nok_name'] ?: null,
            'nok_phone'         => $clean['nok_phone'],
            'is_minor'          => $clean['is_minor'],
            'guardian_name'     => $clean['is_minor'] ? $clean['guardian_name'] : null,
            'guardian_contact'  => $clean['is_minor'] ? $clean['guardian_contact'] : null,
            'profile_completed' => 1,
            'payment_status'    => 'FULLY_PAID',
            'attendance_status' => 'NOT_ARRIVED',
            'amount_owed'       => (int) $ticket['price'],
            'amount_paid'       => (int) $ticket['price'],
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
        ensure_attendance($participantId);

        db_insert('payments', [
            'participant_id'         => $participantId,
            'bulk_order_id'          => (int) $order['id'],
            'reference'              => 'BULK-' . (int) $order['id'] . '-' . (int) $ticket['id'] . '-' . strtoupper(bin2hex(random_bytes(3))),
            'provider_reference'     => (string) ($order['reference'] ?: ''),
            'amount'                 => (int) $ticket['price'],
            'payment_type'           => 'FULL',
            'installment_number'     => null,
            'plan_rate'              => phase(),
            'provider'               => 'bulk',
            'status'                 => 'VERIFIED',
            'virtual_account_number' => null,
            'account_name'           => null,
            'bank_name'              => null,
            'checkout_url'           => null,
            'gateway_charge'         => 0,
            'paid_at'                => $order['paid_at'] ?: now(),
            'verified_at'            => now(),
            'created_at'             => now(),
        ]);

        db_update('bulk_tickets', ['participant_id' => $participantId], 'id = ?', [$ticket['id']]);
        refresh_bulk_order((int) $order['id']);
        auto_assign_house($participantId, (string) $clean['gender']);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_error('Bulk ticket completion failed: ' . $e->getMessage(), ['ticket' => $ticket['token']]);
        flash('error', 'Could not complete this ticket right now. Please try again.');
        redirect_route('complete-ticket/' . $ticket['token']);
    }

    clear_old();
    audit('COMPLETE_BULK_TICKET', 'bulk_ticket', (string) $ticket['id'], null, [
        'participant' => $registrationId, 'order' => $order['id'],
    ]);
    $participant = db_one('SELECT * FROM participants WHERE id = ?', [$participantId]);
    if ($participant) {
        send_registration_confirmation($participant);
    }
    flash('success', 'Delegate details saved. Your registration and ID card are ready.');
    redirect_route('portal/' . $participant['token']);
}

function start_payment_controller(): void
{
    verify_csrf();
    require_public_open();

    $token = (string) post('token');
    $participant = participant_by_token($token);
    if (!$participant) {
        flash('error', 'Registration not found.');
        redirect_route('status');
    }

    $type = (string) post('payment_type', 'FULL');
    $installment = post('installment_number');
    $installment = $installment !== null && $installment !== '' ? (int) $installment : null;

    try {
        $started = start_payment($participant, $type, $installment);
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect_route('portal/' . $token);
    }

    $checkout = payment_checkout_redirect($started);
    if ($checkout !== null) {
        redirect($checkout);
    }
    if ($started['mode'] === 'hosted') {
        flash('error', 'The payment service returned an invalid checkout link. Please try again from your portal.');
        redirect_route('status');
    }

    flash('success', 'Transfer instructions are ready below.');
    redirect_route('portal/' . $token, ['pay' => $started['payment']['reference']]);
}

/* ── Mock gateway ────────────────────────────────────────────────────── */
function page_mock_gateway(array $params): void
{
    if (!mock_payments_enabled()) {
        http_response_code(404);
        view('errors/404', ['title' => 'Not found'], 'layout');
        return;
    }
    $payment = find_payment_by_reference((string) $params['reference']);
    if (!$payment) {
        http_response_code(404);
        view('errors/404', ['title' => 'Payment not found'], 'layout');
        return;
    }
    view('public/mock-pay', ['title' => 'Sandbox checkout', 'payment' => $payment], 'layout');
}

function mock_gateway_action(array $params): void
{
    verify_csrf();
    if (!mock_payments_enabled()) {
        http_response_code(404);
        exit;
    }
    $payment = find_payment_by_reference((string) $params['reference']);
    if (!$payment) {
        redirect_route('home');
    }

    if (post('action') === 'pay') {
        $result = mark_payment_verified($payment, 'MOCK-' . strtoupper(bin2hex(random_bytes(4))), (int) $payment['amount'], 'mock');
        flash($result['ok'] ? 'success' : 'error', $result['message']);
    } else {
        mark_payment_failed($payment, 'Cancelled in sandbox checkout.');
        flash('error', 'Payment cancelled in the sandbox. No delegate was created.');
    }
    redirect_route('payment/callback', ['reference' => $payment['reference']]);
}

/* ── Callback / webhook ──────────────────────────────────────────────── */
function page_callback(array $params = []): void
{
    if (throttle_limited('payment_callback', 60, 1)) {
        http_response_code(429);
        view('public/callback', [
            'title'       => 'Payment status',
            'result'      => [
                'ok'      => false,
                'state'   => 'pending',
                'message' => 'Too many payment checks from this network. Please wait a moment and refresh this page.',
            ],
            'payment'     => null,
            'participant' => null,
            'house'       => null,
            'bulkOrder'   => null,
            'bulkTickets' => [],
            'reference'   => '',
        ], 'layout');
        return;
    }
    throttle_hit('payment_callback');

    // The gateway may use the path form or any of its common query aliases.
    $reference = trim((string) ($params['reference'] ?? ''));
    if ($reference === '') {
        foreach (['reference', 'trxref', 'paymentReference', 'transaction_ref', 'transactionRef', 'ref'] as $key) {
            $value = request($key);
            if (is_string($value) && trim($value) !== '') {
                $reference = trim($value);
                break;
            }
        }
    }

    $result = $reference === ''
        ? ['ok' => false, 'state' => 'error', 'message' => 'No payment reference was supplied. If you were charged, contact support.']
        : handle_callback($reference);

    $payment = $result['payment'] ?? ($reference !== '' ? find_payment_by_reference($reference) : null);
    $bulkOrder = null;
    $bulkTickets = [];
    $participant = null;
    $house = null;

    if ($payment && !empty($payment['bulk_order_id'])) {
        $bulkOrder = db_one('SELECT * FROM bulk_orders WHERE id = ?', [$payment['bulk_order_id']]);
        if ($bulkOrder) {
            $bulkTickets = bulk_tickets((int) $bulkOrder['id']);
        }
    } elseif ($payment && (int) $payment['participant_id'] > 0) {
        $participant = db_one('SELECT * FROM participants WHERE id = ?', [$payment['participant_id']]);
        $house = $participant && $participant['house_id'] ? db_one('SELECT * FROM houses WHERE id = ?', [$participant['house_id']]) : null;
    }

    view('public/callback', [
        'title'        => 'Payment status',
        'result'       => $result,
        'payment'      => $payment,
        'participant'  => $participant,
        'house'        => $house,
        'bulkOrder'    => $bulkOrder,
        'bulkTickets'  => $bulkTickets,
        'reference'    => $reference,
    ], 'layout');
}

function payment_webhook(): void
{
    $raw = file_get_contents('php://input') ?: '';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    [$authorised, $reason] = webhook_authorised($raw, $headers);
    if (!$authorised) {
        json_response(['ok' => false, 'error' => $reason], 401);
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        json_response(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }

    $result = handle_webhook_payload($payload);
    json_response($result, $result['ok'] ? 200 : 422);
}

/* ── Status lookup / ID card / calendar ──────────────────────────────── */
function page_status(): void
{
    view('public/status', ['title' => 'Find my registration'], 'layout');
}

function lookup_status(): void
{
    verify_csrf();
    if (throttle_limited('status_lookup', 20, 60)) {
        flash('error', 'Too many lookup attempts. Please wait a little while and try again.');
        redirect_route('status');
    }
    throttle_hit('status_lookup');

    $reference = trim((string) post('reference'));
    $payerEmail = strtolower(trim((string) post('payer_email')));

    /* Payment-reference lookup works before the delegate form is filled. */
    if ($reference !== '' || $payerEmail !== '') {
        if ($reference === '' || !valid_email($payerEmail)) {
            flash('error', 'Enter both your payment reference and the email used to pay.');
            redirect_route('status');
        }

        $payment = find_payment_by_reference($reference);
        if ($payment) {
            if (!empty($payment['bulk_order_id'])) {
                $order = db_one('SELECT * FROM bulk_orders WHERE id = ?', [$payment['bulk_order_id']]);
                if ($order && strtolower($order['buyer_email']) === $payerEmail) {
                    redirect_route('bulk/' . $order['token']);
                }
            } else {
                $participant = db_one('SELECT * FROM participants WHERE id = ? AND deleted_at IS NULL', [$payment['participant_id']]);
                if ($participant && strtolower($participant['email']) === $payerEmail) {
                    redirect_route('portal/' . $participant['token']);
                }
            }
        }

        $order = db_one('SELECT * FROM bulk_orders WHERE reference = ?', [$reference]);
        if ($order && strtolower($order['buyer_email']) === $payerEmail) {
            redirect_route('bulk/' . $order['token']);
        }

        flash('error', 'No payment matches that reference and email. Check the reference on your receipt.');
        redirect_route('status');
    }

    $registrationId = strtoupper(trim((string) post('registration_id')));
    $surname = trim((string) post('surname'));
    $participant = db_one(
        'SELECT * FROM participants WHERE registration_id = ? AND LOWER(surname) = LOWER(?) AND deleted_at IS NULL',
        [$registrationId, $surname]
    );

    if (!$participant) {
        flash('error', 'No registration matches that ID and surname.');
        redirect_route('status');
    }

    $class = $participant['class_id'] ? db_one('SELECT * FROM classes WHERE id = ?', [$participant['class_id']]) : null;
    $house = $participant['house_id'] ? db_one('SELECT * FROM houses WHERE id = ?', [$participant['house_id']]) : null;
    $attendance = db_one('SELECT * FROM attendance WHERE participant_id = ?', [$participant['id']]);
    $owed = participant_owed($participant);
    $paid = participant_paid((int) $participant['id']);

    view('public/status-result', [
        'title'       => 'Registration status',
        'participant' => $participant,
        'class'       => $class,
        'house'       => $house,
        'attendance'  => $attendance,
        'owed'        => $owed,
        'paid'        => $paid,
    ], 'layout');
}

function page_id_card(array $params): void
{
    $participant = participant_by_token((string) $params['token']);
    if (!$participant) {
        http_response_code(404);
        view('errors/404', ['title' => 'ID card not found'], 'layout');
        return;
    }
    if ((int) ($participant['profile_completed'] ?? 0) !== 1) {
        flash('error', 'Complete your delegate details before opening your ID card.');
        redirect_route('complete/' . $participant['token']);
    }
    $class = $participant['class_id'] ? db_one('SELECT * FROM classes WHERE id = ?', [$participant['class_id']]) : null;
    $house = $participant['house_id'] ? db_one('SELECT * FROM houses WHERE id = ?', [$participant['house_id']]) : null;
    view('public/id-card', [
        'title'       => 'ID card — ' . $participant['registration_id'],
        'participant' => $participant,
        'class'       => $class,
        'house'       => $house,
    ], 'layout');
}

function event_ics(): void
{
    $start = date('Ymd', strtotime((string) setting('camp_start'))) . 'T080000';
    $end = date('Ymd', strtotime((string) setting('camp_end'))) . 'T180000';
    $name = (string) setting('name', 'SB HIC 2026');
    $venue = (string) setting('venue_name') . ', ' . (string) setting('venue_address');
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//SB Osun//Camp Portal//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . md5($name . $start) . '@sbosun.org',
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART;VALUE=DATE:' . date('Ymd', strtotime((string) setting('camp_start'))),
        'DTEND;VALUE=DATE:' . date('Ymd', strtotime((string) setting('camp_end') . ' +1 day')),
        'SUMMARY:' . addcslashes($name, ',;\\'),
        'LOCATION:' . addcslashes($venue, ',;\\'),
        'DESCRIPTION:' . addcslashes((string) setting('motto', 'Upholding the standard…'), ',;\\'),
        'END:VEVENT',
        'END:VCALENDAR',
    ];
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="sb-hic-2026.ics"');
    echo implode("\r\n", $lines) . "\r\n";
    exit;
}

/* ── Newsletter ──────────────────────────────────────────────────────── */
function newsletter_subscribe(): void
{
    verify_csrf();
    if (throttle_limited('newsletter', 10, 60)) {
        flash('error', 'Too many subscribe attempts. Please try again later.');
        redirect_route('home');
    }
    throttle_hit('newsletter');
    $email = strtolower(trim((string) post('email')));
    $name = trim((string) post('name'));
    $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

    if (!valid_email($email)) {
        if ($isAjax) {
            json_response(['ok' => false, 'error' => 'Enter a valid email address.'], 422);
        }
        flash('error', 'Enter a valid email address.');
        redirect(url('home') . '#newsletter');
    }

    $existing = db_one('SELECT * FROM newsletter WHERE email = ?', [$email]);
    if ($existing) {
        if ($existing['status'] === 'unsubscribed') {
            db_update('newsletter', ['status' => 'subscribed', 'unsubscribed_at' => null, 'name' => $name ?: $existing['name']], 'id = ?', [$existing['id']]);
        }
        if ($isAjax) {
            json_response(['ok' => true, 'message' => 'You are already subscribed.']);
        }
        flash('success', 'You are already subscribed to camp updates.');
        redirect_route('home', ['newsletter' => 'ok']);
    }

    $token = random_token(24);
    db_insert('newsletter', [
        'email'           => $email,
        'name'            => $name ?: null,
        'source'          => 'website',
        'status'          => 'subscribed',
        'token'           => $token,
        'subscribed_at'   => now(),
        'created_at'      => now(),
    ]);
    send_newsletter_welcome($email, $token);
    audit('NEWSLETTER_SUBSCRIBE', 'newsletter', $email);

    if ($isAjax) {
        json_response(['ok' => true, 'message' => 'Subscribed. Welcome aboard!']);
    }
    flash('success', 'You are subscribed to camp updates.');
    redirect_route('home', ['newsletter' => 'ok']);
}

function newsletter_unsubscribe(array $params): void
{
    $token = (string) $params['token'];
    $subscriber = db_one('SELECT * FROM newsletter WHERE token = ?', [$token]);
    if ($subscriber && $subscriber['status'] !== 'unsubscribed') {
        db_update('newsletter', ['status' => 'unsubscribed', 'unsubscribed_at' => now()], 'id = ?', [$subscriber['id']]);
        audit('NEWSLETTER_UNSUBSCRIBE', 'newsletter', $subscriber['email']);
    }
    view('public/unsubscribed', [
        'title'      => 'Unsubscribed',
        'subscriber' => $subscriber,
    ], 'layout');
}
