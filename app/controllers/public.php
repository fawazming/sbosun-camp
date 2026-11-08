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

function submit_register(): void
{
    verify_csrf();
    require_public_open();

    if (registration_rate_limited()) {
        flash('error', 'Too many registration attempts from this network. Please try again later.');
        redirect_route('register');
    }

    $input = [
        'surname'          => trim((string) post('surname')),
        'other_name'       => trim((string) post('other_name')),
        'gender'           => strtoupper(trim((string) post('gender'))),
        'age_group'        => trim((string) post('age_group')),
        'category'         => trim((string) post('category')),
        'zone'             => trim((string) post('zone')),
        'phone'            => trim((string) post('phone')),
        'email'            => strtolower(trim((string) post('email'))),
        'nok_name'         => trim((string) post('nok_name')),
        'nok_phone'        => trim((string) post('nok_phone')),
        'allergies'        => trim((string) post('allergies')),
        'health_challenges' => trim((string) post('health_challenges')),
        'is_minor'         => post('is_minor') ? 1 : 0,
        'guardian_name'    => trim((string) post('guardian_name')),
        'guardian_contact' => trim((string) post('guardian_contact')),
    ];
    remember_old($input);

    $errors = [];
    if ($input['surname'] === '') {
        $errors[] = 'Surname is required.';
    }
    if ($input['other_name'] === '') {
        $errors[] = 'Other name is required.';
    }
    if (!in_array($input['gender'], ['MALE', 'FEMALE'], true)) {
        $errors[] = 'Select a valid gender.';
    }
    if (!in_array($input['age_group'], age_groups(), true)) {
        $errors[] = 'Select a valid age bracket.';
    }
    if (!in_array($input['category'], categories(), true)) {
        $errors[] = 'Select a valid category.';
    }
    if (!in_array($input['zone'], zones(), true)) {
        $errors[] = 'Select a valid zone.';
    }
    if (!valid_email($input['email'])) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!valid_phone($input['phone'])) {
        $errors[] = 'Enter a valid phone / WhatsApp number.';
    }
    if (!valid_phone($input['nok_phone'])) {
        $errors[] = 'Enter a valid next-of-kin phone number.';
    }
    if ($input['allergies'] === '') {
        $errors[] = 'State any allergies/existing ailment, or type “None”.';
    }
    if ($input['is_minor'] && ($input['guardian_name'] === '' || $input['guardian_contact'] === '')) {
        $errors[] = 'Guardian name and contact are required for delegates under 18.';
    }

    $class = class_for_category($input['category']);
    if (!$class) {
        $errors[] = 'No class is configured for that category.';
    }

    if ($errors !== []) {
        flash('error', implode(' ', $errors));
        redirect_route('register');
    }

    // Unique, human-readable registration number.
    do {
        $registrationId = slug_id('SB');
    } while (db_one('SELECT id FROM participants WHERE registration_id = ?', [$registrationId]));

    $participantId = db_insert('participants', [
        'registration_id'   => $registrationId,
        'token'             => random_token(24),
        'surname'           => $input['surname'],
        'other_name'        => $input['other_name'],
        'gender'            => $input['gender'],
        'age_group'         => $input['age_group'],
        'category'          => $input['category'],
        'class_id'          => (int) $class['id'],
        'zone'              => $input['zone'],
        'phone'             => $input['phone'],
        'email'             => $input['email'],
        'health_challenges' => $input['health_challenges'] ?: null,
        'allergies'         => $input['allergies'],
        'nok_name'          => $input['nok_name'] ?: null,
        'nok_phone'         => $input['nok_phone'],
        'is_minor'          => $input['is_minor'],
        'guardian_name'     => $input['is_minor'] ? $input['guardian_name'] : null,
        'guardian_contact'  => $input['is_minor'] ? $input['guardian_contact'] : null,
        'payment_status'    => 'NOT_PAID',
        'attendance_status' => 'NOT_ARRIVED',
        'amount_owed'       => current_fee(),
        'amount_paid'       => 0,
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    ensure_attendance($participantId);
    registration_rate_hit();
    clear_old();
    audit('REGISTER', 'participant', $registrationId, null, ['name' => $input['other_name'] . ' ' . $input['surname'], 'category' => $input['category']]);

    $participant = db_one('SELECT * FROM participants WHERE id = ?', [$participantId]);
    $preferredPlan = strtoupper((string) post('preferred_plan')) === 'INSTALLMENT' ? 'INSTALLMENT' : 'FULL';
    flash('success', 'Registration received! Complete payment below to secure your place.');
    redirect_route('portal/' . $participant['token'], ['plan' => $preferredPlan]);
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
    ensure_attendance((int) $participant['id']);
    $class = $participant['class_id'] ? db_one('SELECT * FROM classes WHERE id = ?', [$participant['class_id']]) : null;
    $house = $participant['house_id'] ? db_one('SELECT * FROM houses WHERE id = ?', [$participant['house_id']]) : null;
    $attendance = db_one('SELECT * FROM attendance WHERE participant_id = ?', [$participant['id']]);
    $payments = db_all('SELECT * FROM payments WHERE participant_id = ? ORDER BY id DESC', [$participant['id']]);
    $owed = participant_owed($participant);
    $paid = participant_paid((int) $participant['id']);

    $activePayment = null;
    if (!empty($_GET['pay'])) {
        $activePayment = db_one('SELECT * FROM payments WHERE reference = ? AND participant_id = ?', [$_GET['pay'], $participant['id']]);
    }

    $nextInstallment = 1;
    foreach ($payments as $p) {
        if ($p['payment_type'] === 'INSTALLMENT' && $p['status'] === 'VERIFIED') {
            $nextInstallment = max($nextInstallment, ((int) $p['installment_number']) + 1);
        }
    }

    view('public/portal', [
        'title'           => $participant['other_name'] . ' — Registration',
        'participant'     => $participant,
        'class'           => $class,
        'house'           => $house,
        'attendance'      => $attendance,
        'payments'        => $payments,
        'owed'            => $owed,
        'paid'            => $paid,
        'balance'         => max(0, $owed - $paid),
        'activePayment'   => $activePayment,
        'nextInstallment' => min($nextInstallment, INSTALLMENT_COUNT),
        'mode'            => payment_mode(),
        'preferredPlan'   => strtoupper((string) ($_GET['plan'] ?? '')),
    ], 'layout');
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

    if ($started['mode'] === 'mock') {
        redirect_route('pay/mock/' . $started['payment']['reference']);
    }
    if ($started['mode'] === 'hosted' && $started['payment']['checkout_url']) {
        header('Location: ' . $started['payment']['checkout_url']);
        exit;
    }

    flash('success', 'Transfer instructions are ready below.');
    redirect_route('portal/' . $token, ['pay' => $started['payment']['reference']]);
}

/* ── Mock gateway ────────────────────────────────────────────────────── */
function page_mock_gateway(array $params): void
{
    if (payment_mode() !== 'mock') {
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
    if (payment_mode() !== 'mock') {
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
function page_callback(): void
{
    $reference = (string) request('reference', request('trxref', request('ref', '')));
    $result = $reference === ''
        ? ['ok' => false, 'state' => 'error', 'message' => 'No payment reference was supplied.']
        : handle_callback($reference);

    $payment = $result['payment'] ?? ($reference !== '' ? find_payment_by_reference($reference) : null);
    $participant = $payment ? db_one('SELECT * FROM participants WHERE id = ?', [$payment['participant_id']]) : null;
    $house = $participant && $participant['house_id'] ? db_one('SELECT * FROM houses WHERE id = ?', [$participant['house_id']]) : null;

    view('public/callback', [
        'title'       => 'Payment status',
        'result'      => $result,
        'payment'     => $payment,
        'participant' => $participant,
        'house'       => $house,
        'reference'   => $reference,
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
