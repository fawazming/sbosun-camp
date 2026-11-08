<?php
declare(strict_types=1);

/* ── Auth ────────────────────────────────────────────────────────────── */
function admin_login_page(): void
{
    if (current_admin()) {
        redirect_route('admin/dashboard');
    }
    view('admin/login', ['title' => 'Admin sign in'], 'admin/blank');
}

function admin_login_submit(): void
{
    verify_csrf();
    $email = (string) post('email');
    $password = (string) post('password');
    [$ok, $error] = attempt_login($email, $password);
    if (!$ok) {
        flash('error', (string) $error);
        redirect_route('admin/login');
    }
    $next = (string) post('next', '');
    if ($next !== '' && str_starts_with($next, '/') && !str_contains($next, '//')) {
        header('Location: ' . $next);
        exit;
    }
    redirect_route('admin/dashboard');
}

function admin_logout(): void
{
    verify_csrf();
    logout_admin();
    flash('success', 'You have been signed out.');
    redirect_route('admin/login');
}

/* ── Dashboard ───────────────────────────────────────────────────────── */
function admin_dashboard(): void
{
    $admin = require_admin();
    [$scope, $scopeParams] = admin_scope($admin);

    $stats = [
        'total'     => (int) db_value("SELECT COUNT(*) FROM participants p WHERE p.deleted_at IS NULL{$scope}", $scopeParams, 0),
        'fully'     => (int) db_value("SELECT COUNT(*) FROM participants p WHERE p.deleted_at IS NULL AND p.payment_status = 'FULLY_PAID'{$scope}", $scopeParams, 0),
        'partial'   => (int) db_value("SELECT COUNT(*) FROM participants p WHERE p.deleted_at IS NULL AND p.payment_status = 'PARTIALLY_PAID'{$scope}", $scopeParams, 0),
        'not_paid'  => (int) db_value("SELECT COUNT(*) FROM participants p WHERE p.deleted_at IS NULL AND p.payment_status = 'NOT_PAID'{$scope}", $scopeParams, 0),
        'present'   => (int) db_value("SELECT COUNT(*) FROM participants p WHERE p.deleted_at IS NULL AND p.attendance_status = 'PRESENT'{$scope}", $scopeParams, 0),
        'collected' => (int) db_value("SELECT COALESCE(SUM(pay.amount),0) FROM payments pay JOIN participants p ON p.id = pay.participant_id WHERE pay.status = 'VERIFIED' AND p.deleted_at IS NULL{$scope}", $scopeParams, 0),
        'pending_tx' => (int) db_value("SELECT COUNT(*) FROM payments pay JOIN participants p ON p.id = pay.participant_id WHERE pay.status = 'PENDING' AND p.deleted_at IS NULL{$scope}", $scopeParams, 0),
        'today'     => (int) db_value("SELECT COUNT(*) FROM participants p WHERE p.deleted_at IS NULL AND substr(p.created_at,1,10) = ?{$scope}", array_merge([date('Y-m-d')], $scopeParams), 0),
    ];
    $stats['expected'] = $stats['total'];
    $stats['not_arrived'] = max(0, $stats['expected'] - $stats['present']);
    $stats['attendance_pct'] = $stats['expected'] > 0 ? round(($stats['present'] / $stats['expected']) * 100, 1) : 0;

    $byZone = db_all("SELECT p.zone, COUNT(*) AS total FROM participants p WHERE p.deleted_at IS NULL{$scope} GROUP BY p.zone ORDER BY total DESC", $scopeParams);
    $byCategory = db_all("SELECT p.category, COUNT(*) AS total FROM participants p WHERE p.deleted_at IS NULL{$scope} GROUP BY p.category ORDER BY total DESC", $scopeParams);
    $byGender = db_all("SELECT p.gender, COUNT(*) AS total FROM participants p WHERE p.deleted_at IS NULL{$scope} GROUP BY p.gender", $scopeParams);
    $recent = db_all("SELECT p.*, c.name AS class_name, h.name AS house_name FROM participants p LEFT JOIN classes c ON c.id = p.class_id LEFT JOIN houses h ON h.id = p.house_id WHERE p.deleted_at IS NULL{$scope} ORDER BY p.id DESC LIMIT 8", $scopeParams);
    $pending = db_all("SELECT pay.*, p.registration_id, p.other_name, p.surname FROM payments pay JOIN participants p ON p.id = pay.participant_id WHERE pay.status = 'PENDING' AND p.deleted_at IS NULL{$scope} ORDER BY pay.id DESC LIMIT 6", $scopeParams);
    $houses = db_all("SELECT h.*, (SELECT COUNT(*) FROM participants p WHERE p.house_id = h.id AND p.deleted_at IS NULL) AS occupants FROM houses h ORDER BY h.gender, h.name");

    view('admin/dashboard', [
        'title'     => 'Dashboard',
        'admin'     => $admin,
        'stats'     => $stats,
        'byZone'    => $byZone,
        'byCategory' => $byCategory,
        'byGender'  => $byGender,
        'recent'    => $recent,
        'pending'   => $pending,
        'houses'    => $houses,
    ], 'admin/layout');
}

/* ── Participants ────────────────────────────────────────────────────── */
function participant_filters(?array $admin = null): array
{
    $admin ??= current_admin();
    [$scope, $scopeParams] = admin_scope($admin);
    $where = 'p.deleted_at IS NULL' . $scope;
    $params = $scopeParams;

    $search = trim((string) request('q', ''));
    if ($search !== '') {
        $where .= ' AND (p.registration_id LIKE ? OR p.surname LIKE ? OR p.other_name LIKE ? OR p.email LIKE ? OR p.phone LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    foreach (['zone' => 'p.zone', 'gender' => 'p.gender', 'category' => 'p.category', 'payment_status' => 'p.payment_status', 'attendance_status' => 'p.attendance_status'] as $key => $column) {
        $value = trim((string) request($key, ''));
        if ($value !== '') {
            $where .= " AND {$column} = ?";
            $params[] = $value;
        }
    }
    $houseId = (int) request('house_id', 0);
    if ($houseId > 0) {
        $where .= ' AND p.house_id = ?';
        $params[] = $houseId;
    }
    $from = trim((string) request('from', ''));
    $to = trim((string) request('to', ''));
    if ($from !== '') {
        $where .= ' AND p.created_at >= ?';
        $params[] = $from . ' 00:00:00';
    }
    if ($to !== '') {
        $where .= ' AND p.created_at <= ?';
        $params[] = $to . ' 23:59:59';
    }
    return [$where, $params];
}

function admin_participants(): void
{
    $admin = require_admin(['REGISTRATION_ADMIN', 'FINANCE_ADMIN', 'VIEWER', 'ZONE_ADMIN', 'ATTENDANCE_ADMIN']);
    [$where, $params] = participant_filters($admin);
    $page = max(1, (int) request('page', 1));
    $perPage = 50;
    $total = (int) db_value("SELECT COUNT(*) FROM participants p WHERE {$where}", $params, 0);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $rows = db_all(
        "SELECT p.*, c.name AS class_name, h.name AS house_name
         FROM participants p
         LEFT JOIN classes c ON c.id = p.class_id
         LEFT JOIN houses h ON h.id = p.house_id
         WHERE {$where}
         ORDER BY p.id DESC LIMIT {$perPage} OFFSET {$offset}",
        $params
    );
    $houses = db_all('SELECT * FROM houses ORDER BY gender, name');

    view('admin/participants', [
        'title'   => 'Participants',
        'admin'   => $admin,
        'rows'    => $rows,
        'total'   => $total,
        'page'    => $page,
        'pages'   => $pages,
        'houses'  => $houses,
    ], 'admin/layout');
}

function admin_participant_detail(array $params): void
{
    $admin = require_admin(['REGISTRATION_ADMIN', 'FINANCE_ADMIN', 'VIEWER', 'ZONE_ADMIN', 'ATTENDANCE_ADMIN']);
    $participant = db_one('SELECT * FROM participants WHERE id = ? AND deleted_at IS NULL', [(int) $params['id']]);
    if (!$participant) {
        http_response_code(404);
        view('errors/404', ['title' => 'Participant not found'], 'admin/layout');
        return;
    }
    if ($admin['role'] === 'ZONE_ADMIN' && $admin['zone'] && $participant['zone'] !== $admin['zone']) {
        http_response_code(403);
        view('errors/403', ['title' => 'Access denied'], 'admin/layout');
        return;
    }
    $class = $participant['class_id'] ? db_one('SELECT * FROM classes WHERE id = ?', [$participant['class_id']]) : null;
    $house = $participant['house_id'] ? db_one('SELECT * FROM houses WHERE id = ?', [$participant['house_id']]) : null;
    $payments = db_all('SELECT * FROM payments WHERE participant_id = ? ORDER BY id DESC', [$participant['id']]);
    $attendance = db_one('SELECT * FROM attendance WHERE participant_id = ?', [$participant['id']]);
    $houses = houses_for_gender((string) $participant['gender']);

    view('admin/participant-detail', [
        'title'       => $participant['other_name'] . ' ' . $participant['surname'],
        'admin'       => $admin,
        'participant' => $participant,
        'class'       => $class,
        'house'       => $house,
        'payments'    => $payments,
        'attendance'  => $attendance,
        'houses'      => $houses,
    ], 'admin/layout');
}

function admin_participant_action(array $params): void
{
    $admin = require_admin(['REGISTRATION_ADMIN', 'ATTENDANCE_ADMIN', 'HOUSE_ADMIN', 'SUPER_ADMIN']);
    verify_csrf();
    $participant = db_one('SELECT * FROM participants WHERE id = ? AND deleted_at IS NULL', [(int) $params['id']]);
    if (!$participant) {
        flash('error', 'Participant not found.');
        redirect_route('admin/participants');
    }
    $action = (string) post('action');
    $back = 'admin/participants/' . $participant['id'];

    if ($action === 'check_in') {
        [$ok, $message] = check_in_participant((int) $participant['id'], (string) $admin['name']);
        flash($ok ? 'success' : 'error', $message);
    } elseif ($action === 'undo_check_in') {
        [$ok, $message] = undo_check_in((int) $participant['id'], (string) $admin['name'], trim((string) post('reason')));
        flash($ok ? 'success' : 'error', $message);
    } elseif ($action === 'assign_house') {
        $houseId = (int) post('house_id');
        $house = db_one("SELECT * FROM houses WHERE id = ? AND gender = ? AND status = 'ACTIVE'", [$houseId, $participant['gender']]);
        if (!$house) {
            flash('error', 'Choose an active house matching the delegate’s gender.');
        } else {
            db_update('participants', ['house_id' => $houseId, 'updated_at' => now()], 'id = ?', [$participant['id']]);
            audit('ASSIGN_HOUSE', 'participant', (string) $participant['id'], null, ['house' => $house['name'], 'mode' => 'manual']);
            flash('success', 'House updated to ' . $house['name'] . '.');
        }
    } elseif ($action === 'delete') {
        db_update('participants', ['deleted_at' => now(), 'updated_at' => now()], 'id = ?', [$participant['id']]);
        audit('DELETE_PARTICIPANT', 'participant', (string) $participant['id'], $participant, null);
        flash('success', 'Participant archived (soft delete).');
        redirect_route('admin/participants');
    }
    redirect_route($back);
}

function admin_participants_export(): void
{
    $admin = require_admin(['REGISTRATION_ADMIN', 'FINANCE_ADMIN', 'VIEWER', 'ZONE_ADMIN']);
    [$where, $params] = participant_filters($admin);
    $rows = db_all(
        "SELECT p.*, c.name AS class_name, h.name AS house_name
         FROM participants p LEFT JOIN classes c ON c.id = p.class_id LEFT JOIN houses h ON h.id = p.house_id
         WHERE {$where} ORDER BY p.id DESC",
        $params
    );
    audit('EXPORT_PARTICIPANTS', 'report', 'registrations');
    csv_download('sb-osun-registrations-' . date('Y-m-d') . '.csv', [
        'Registration ID', 'Surname', 'Other Name', 'Gender', 'Age Group', 'Category', 'Class', 'Zone',
        'Phone', 'Email', 'Next of Kin', 'Next of Kin Phone', 'Allergies/Ailment', 'Minor', 'Guardian',
        'Guardian Contact', 'Payment Status', 'Amount Owed', 'Amount Paid', 'Attendance', 'House', 'Registered',
    ], array_map(static fn(array $p): array => [
        $p['registration_id'], $p['surname'], $p['other_name'], $p['gender'], $p['age_group'], $p['category'],
        $p['class_name'], $p['zone'], $p['phone'], $p['email'], $p['nok_name'], $p['nok_phone'], $p['allergies'],
        $p['is_minor'] ? 'Yes' : 'No', $p['guardian_name'], $p['guardian_contact'], $p['payment_status'],
        $p['amount_owed'], $p['amount_paid'], $p['attendance_status'], $p['house_name'], $p['created_at'],
    ], $rows));
}

/* ── Payments ────────────────────────────────────────────────────────── */
function admin_payments(): void
{
    $admin = require_admin(['FINANCE_ADMIN', 'VIEWER', 'REGISTRATION_ADMIN']);
    $status = trim((string) request('status', ''));
    $search = trim((string) request('q', ''));
    $where = '1=1';
    $params = [];
    if ($status !== '') {
        $where .= ' AND pay.status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $where .= ' AND (pay.reference LIKE ? OR pay.provider_reference LIKE ? OR p.registration_id LIKE ? OR p.surname LIKE ? OR p.other_name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }

    $rows = db_all(
        "SELECT pay.*, p.registration_id, p.surname, p.other_name, p.zone
         FROM payments pay JOIN participants p ON p.id = pay.participant_id
         WHERE {$where} ORDER BY pay.id DESC LIMIT 300",
        $params
    );
    $collected = (int) db_value("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'VERIFIED'", [], 0);
    $counts = [
        'PENDING' => count_rows('payments', "status = 'PENDING'"),
        'VERIFIED' => count_rows('payments', "status = 'VERIFIED'"),
        'FAILED' => count_rows('payments', "status = 'FAILED'"),
        'REFUNDED' => count_rows('payments', "status = 'REFUNDED'"),
    ];

    view('admin/payments', [
        'title'     => 'Payments',
        'admin'     => $admin,
        'rows'      => $rows,
        'collected' => $collected,
        'counts'    => $counts,
    ], 'admin/layout');
}

function admin_payment_action(array $params): void
{
    $admin = require_admin(['FINANCE_ADMIN', 'SUPER_ADMIN']);
    verify_csrf();
    $payment = db_one('SELECT * FROM payments WHERE id = ?', [(int) $params['id']]);
    if (!$payment) {
        flash('error', 'Payment not found.');
        redirect_route('admin/payments');
    }
    $action = (string) post('action');

    if ($action === 'approve') {
        if ($payment['status'] !== 'PENDING') {
            flash('error', 'Only pending payments can be approved.');
        } else {
            $result = mark_payment_verified($payment, 'MANUAL-' . strtoupper(bin2hex(random_bytes(4))), (int) $payment['amount'], 'manual-admin');
            audit('APPROVE_PAYMENT', 'payment', (string) $payment['reference'], null, ['admin' => $admin['name']]);
            flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Payment approved and participant updated.' : $result['message']);
        }
    } elseif ($action === 'refund') {
        $reason = trim((string) post('reason'));
        if ($reason === '') {
            flash('error', 'A refund reason is required.');
        } elseif ($payment['status'] !== 'VERIFIED') {
            flash('error', 'Only verified payments can be refunded.');
        } else {
            db_update('payments', ['status' => 'REFUNDED', 'failure_reason' => 'Refund: ' . $reason, 'updated_at' => now()], 'id = ?', [$payment['id']]);
            $updatedParticipant = refresh_participant_payment((int) $payment['participant_id']);
            if (($updatedParticipant['payment_status'] ?? '') !== 'FULLY_PAID' && !empty($updatedParticipant['house_id'])) {
                db_update('participants', ['house_id' => null], 'id = ?', [$updatedParticipant['id']]);
                audit('RELEASE_HOUSE', 'participant', (string) $updatedParticipant['id'], ['house_id' => $updatedParticipant['house_id']], ['reason' => 'refund']);
            }
            audit('REFUND_PAYMENT', 'payment', (string) $payment['reference'], ['status' => 'VERIFIED'], ['status' => 'REFUNDED', 'reason' => $reason, 'admin' => $admin['name']]);
            flash('success', 'Refund recorded and participant balance recalculated.');
        }
    }
    redirect_route('admin/payments');
}

function admin_payments_export(): void
{
    $admin = require_admin(['FINANCE_ADMIN', 'VIEWER', 'SUPER_ADMIN']);
    $rows = db_all(
        "SELECT pay.*, p.registration_id, p.surname, p.other_name, p.zone
         FROM payments pay JOIN participants p ON p.id = pay.participant_id
         WHERE p.deleted_at IS NULL ORDER BY pay.id DESC"
    );
    audit('EXPORT_PAYMENTS', 'report', 'payments');
    csv_download('sb-osun-payments-' . date('Y-m-d') . '.csv', [
        'Reference', 'Provider Reference', 'Registration ID', 'Name', 'Zone', 'Type', 'Instalment',
        'Plan', 'Amount', 'Status', 'Provider', 'Paid At', 'Created At',
    ], array_map(static fn(array $p): array => [
        $p['reference'], $p['provider_reference'], $p['registration_id'], $p['other_name'] . ' ' . $p['surname'],
        $p['zone'], $p['payment_type'], $p['installment_number'], $p['plan_rate'], $p['amount'], $p['status'],
        $p['provider'], $p['paid_at'], $p['created_at'],
    ], $rows));
}

/* ── Check-in ────────────────────────────────────────────────────────── */
function admin_checkin(): void
{
    $admin = require_admin(['ATTENDANCE_ADMIN', 'REGISTRATION_ADMIN', 'VIEWER']);
    $query = trim((string) request('q', ''));
    $results = [];
    if ($query !== '') {
        $like = '%' . $query . '%';
        $results = db_all(
            "SELECT p.*, c.name AS class_name, h.name AS house_name, a.status AS attendance_state, a.check_in_time
             FROM participants p
             LEFT JOIN classes c ON c.id = p.class_id
             LEFT JOIN houses h ON h.id = p.house_id
             LEFT JOIN attendance a ON a.participant_id = p.id
             WHERE p.deleted_at IS NULL AND (p.registration_id LIKE ? OR p.surname LIKE ? OR p.other_name LIKE ? OR p.phone LIKE ?)
             ORDER BY p.surname LIMIT 20",
            [$like, $like, $like, $like]
        );
    }
    $expected = count_rows('participants', 'deleted_at IS NULL');
    $present = count_rows('participants', "deleted_at IS NULL AND attendance_status = 'PRESENT'");
    $recent = db_all(
        "SELECT p.registration_id, p.other_name, p.surname, a.check_in_time, a.checked_in_by
         FROM attendance a JOIN participants p ON p.id = a.participant_id
         WHERE a.status = 'PRESENT' ORDER BY a.check_in_time DESC LIMIT 8"
    );

    view('admin/checkin', [
        'title'   => 'Check-in mode',
        'admin'   => $admin,
        'query'   => $query,
        'results' => $results,
        'expected' => $expected,
        'present' => $present,
        'recent'  => $recent,
    ], 'admin/layout');
}

function admin_checkin_action(): void
{
    $admin = require_admin(['ATTENDANCE_ADMIN', 'SUPER_ADMIN']);
    verify_csrf();
    $participantId = (int) post('participant_id');
    if (post('action') === 'undo') {
        [$ok, $message] = undo_check_in($participantId, (string) $admin['name'], trim((string) post('reason')));
    } else {
        [$ok, $message] = check_in_participant($participantId, (string) $admin['name']);
    }
    flash($ok ? 'success' : 'error', $message);
    redirect_route('admin/check-in', ['q' => post('q', '')]);
}

/* ── Houses ──────────────────────────────────────────────────────────── */
function admin_houses(): void
{
    $admin = require_admin(['HOUSE_ADMIN', 'REGISTRATION_ADMIN', 'VIEWER']);
    $houses = db_all(
        "SELECT h.*, (SELECT COUNT(*) FROM participants p WHERE p.house_id = h.id AND p.deleted_at IS NULL) AS occupants
         FROM houses h ORDER BY h.gender, h.name"
    );
    $unassigned = db_all(
        "SELECT p.* FROM participants p
         WHERE p.deleted_at IS NULL AND p.payment_status = 'FULLY_PAID' AND p.house_id IS NULL
         ORDER BY p.id"
    );
    view('admin/houses', [
        'title'      => 'Houses',
        'admin'      => $admin,
        'houses'     => $houses,
        'unassigned' => $unassigned,
    ], 'admin/layout');
}

function admin_house_action(): void
{
    $admin = require_admin(['HOUSE_ADMIN', 'SUPER_ADMIN']);
    verify_csrf();
    $action = (string) post('action');

    if ($action === 'create') {
        $name = trim((string) post('name'));
        $gender = strtoupper(trim((string) post('gender')));
        $capacity = max(1, (int) post('capacity', 40));
        if ($name === '' || !in_array($gender, ['MALE', 'FEMALE'], true)) {
            flash('error', 'Enter a house name and choose a valid gender.');
        } else {
            db_insert('houses', ['name' => $name, 'gender' => $gender, 'capacity' => $capacity, 'status' => 'ACTIVE', 'created_at' => now()]);
            audit('CREATE_HOUSE', 'house', $name);
            flash('success', 'House created.');
        }
    } elseif ($action === 'toggle') {
        $house = db_one('SELECT * FROM houses WHERE id = ?', [(int) post('house_id')]);
        if ($house) {
            $status = $house['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
            db_update('houses', ['status' => $status], 'id = ?', [$house['id']]);
            audit('TOGGLE_HOUSE', 'house', (string) $house['id'], ['status' => $house['status']], ['status' => $status]);
            flash('success', 'House ' . strtolower($status) . '.');
        }
    } elseif ($action === 'auto_assign') {
        $rows = db_all("SELECT * FROM participants WHERE deleted_at IS NULL AND payment_status = 'FULLY_PAID' AND house_id IS NULL");
        $assigned = 0;
        $failed = 0;
        foreach ($rows as $participant) {
            auto_assign_house((int) $participant['id'], (string) $participant['gender']) ? $assigned++ : $failed++;
        }
        audit('AUTO_ASSIGN_HOUSES', 'house', 'all', null, ['assigned' => $assigned, 'failed' => $failed]);
        flash($assigned > 0 ? 'success' : 'error', "Assigned {$assigned} delegate(s)." . ($failed ? " {$failed} could not be placed because houses are full." : ''));
    } elseif ($action === 'assign') {
        $participantId = (int) post('participant_id');
        $houseId = (int) post('house_id');
        $participant = db_one('SELECT * FROM participants WHERE id = ? AND deleted_at IS NULL', [$participantId]);
        $house = db_one("SELECT * FROM houses WHERE id = ? AND status = 'ACTIVE'", [$houseId]);
        if (!$participant || !$house || $house['gender'] !== $participant['gender']) {
            flash('error', 'Choose an active house that matches the delegate’s gender.');
        } else {
            db_update('participants', ['house_id' => $houseId, 'updated_at' => now()], 'id = ?', [$participantId]);
            audit('ASSIGN_HOUSE', 'participant', (string) $participantId, null, ['house' => $house['name'], 'mode' => 'manual']);
            flash('success', 'Delegate assigned to ' . $house['name'] . '.');
        }
    }
    redirect_route('admin/houses');
}

/* ── Reports ─────────────────────────────────────────────────────────── */
function admin_reports(): void
{
    $admin = require_admin();
    view('admin/reports', ['title' => 'Reports', 'admin' => $admin], 'admin/layout');
}

function admin_report_export(): void
{
    $admin = require_admin();
    $type = (string) request('type', 'registrations');
    $date = date('Y-m-d');

    if ($type === 'payments') {
        $rows = db_all("SELECT pay.*, p.registration_id, p.surname, p.other_name FROM payments pay JOIN participants p ON p.id = pay.participant_id ORDER BY pay.id DESC");
        csv_download("sb-osun-payments-{$date}.csv", ['Reference', 'Registration', 'Name', 'Amount', 'Type', 'Instalment', 'Status', 'Paid At'], array_map(
            static fn(array $r): array => [$r['reference'], $r['registration_id'], $r['other_name'] . ' ' . $r['surname'], $r['amount'], $r['payment_type'], $r['installment_number'], $r['status'], $r['paid_at']],
            $rows
        ));
    }

    if ($type === 'attendance') {
        $rows = db_all("SELECT p.registration_id, p.surname, p.other_name, c.name AS class_name, h.name AS house_name, a.status, a.check_in_time, a.checked_in_by
                        FROM participants p LEFT JOIN classes c ON c.id = p.class_id LEFT JOIN houses h ON h.id = p.house_id
                        LEFT JOIN attendance a ON a.participant_id = p.id WHERE p.deleted_at IS NULL ORDER BY p.surname");
        csv_download("sb-osun-attendance-{$date}.csv", ['Registration', 'Surname', 'Other Name', 'Class', 'House', 'Status', 'Check-in Time', 'Checked In By'], array_map(
            static fn(array $r): array => [$r['registration_id'], $r['surname'], $r['other_name'], $r['class_name'], $r['house_name'], $r['status'] ?? 'NOT_ARRIVED', $r['check_in_time'], $r['checked_in_by']],
            $rows
        ));
    }

    if ($type === 'houses') {
        $rows = db_all("SELECT h.*, (SELECT COUNT(*) FROM participants p WHERE p.house_id = h.id AND p.deleted_at IS NULL) AS occupants FROM houses h ORDER BY h.gender, h.name");
        csv_download("sb-osun-houses-{$date}.csv", ['House', 'Gender', 'Capacity', 'Occupants', 'Available', 'Status'], array_map(
            static fn(array $r): array => [$r['name'], $r['gender'], $r['capacity'], $r['occupants'], max(0, (int) $r['capacity'] - (int) $r['occupants']), $r['status']],
            $rows
        ));
    }

    if ($type === 'newsletter') {
        $rows = db_all('SELECT * FROM newsletter ORDER BY id DESC');
        csv_download("sb-osun-newsletter-{$date}.csv", ['Email', 'Name', 'Status', 'Source', 'Subscribed', 'Unsubscribed'], array_map(
            static fn(array $r): array => [$r['email'], $r['name'], $r['status'], $r['source'], $r['subscribed_at'], $r['unsubscribed_at']],
            $rows
        ));
    }

    $rows = db_all("SELECT p.*, c.name AS class_name, h.name AS house_name FROM participants p LEFT JOIN classes c ON c.id = p.class_id LEFT JOIN houses h ON h.id = p.house_id WHERE p.deleted_at IS NULL ORDER BY p.id DESC");
    audit('EXPORT_REPORT', 'report', 'registrations');
    csv_download("sb-osun-registrations-{$date}.csv", ['Registration', 'Surname', 'Other Name', 'Gender', 'Age Group', 'Category', 'Class', 'Zone', 'Phone', 'Email', 'Payment', 'House', 'Attendance', 'Registered'], array_map(
        static fn(array $r): array => [$r['registration_id'], $r['surname'], $r['other_name'], $r['gender'], $r['age_group'], $r['category'], $r['class_name'], $r['zone'], $r['phone'], $r['email'], $r['payment_status'], $r['house_name'], $r['attendance_status'], $r['created_at']],
        $rows
    ));
}

/* ── Admin users ─────────────────────────────────────────────────────── */
function admin_users(): void
{
    $admin = require_admin(['SUPER_ADMIN']);
    $users = db_all('SELECT * FROM admin_users ORDER BY id');
    view('admin/users', ['title' => 'Admin users', 'admin' => $admin, 'users' => $users, 'roles' => ADMIN_ROLES], 'admin/layout');
}

function admin_users_action(): void
{
    $admin = require_admin(['SUPER_ADMIN']);
    verify_csrf();
    $action = (string) post('action');

    if ($action === 'create') {
        $name = trim((string) post('name'));
        $email = strtolower(trim((string) post('email')));
        $password = (string) post('password');
        $role = (string) post('role');
        $zone = trim((string) post('zone')) ?: null;
        if ($name === '' || !valid_email($email) || strlen($password) < 8 || !isset(ADMIN_ROLES[$role])) {
            flash('error', 'Provide a name, valid email, role and a password of at least 8 characters.');
        } elseif (db_one('SELECT id FROM admin_users WHERE email = ?', [$email])) {
            flash('error', 'That email already has an admin account.');
        } else {
            db_insert('admin_users', [
                'name'          => $name,
                'email'         => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role'          => $role,
                'zone'          => $zone,
                'is_protected'  => 0,
                'status'        => 'ACTIVE',
                'created_at'    => now(),
            ]);
            audit('CREATE_ADMIN', 'admin', $email, null, ['role' => $role, 'zone' => $zone]);
            flash('success', 'Admin account created.');
        }
    } elseif ($action === 'toggle') {
        $user = db_one('SELECT * FROM admin_users WHERE id = ?', [(int) post('user_id')]);
        if (!$user) {
            flash('error', 'Admin not found.');
        } elseif ((int) $user['is_protected'] === 1 || (int) $user['id'] === (int) $admin['id']) {
            flash('error', 'This protected account cannot be deactivated.');
        } else {
            $status = $user['status'] === 'ACTIVE' ? 'DEACTIVATED' : 'ACTIVE';
            db_update('admin_users', ['status' => $status], 'id = ?', [$user['id']]);
            audit('TOGGLE_ADMIN', 'admin', (string) $user['id'], ['status' => $user['status']], ['status' => $status]);
            flash('success', 'Admin ' . strtolower($status) . '.');
        }
    } elseif ($action === 'reset_password') {
        $user = db_one('SELECT * FROM admin_users WHERE id = ?', [(int) post('user_id')]);
        $password = (string) post('password');
        if (!$user || strlen($password) < 8) {
            flash('error', 'Password must be at least 8 characters.');
        } else {
            db_update('admin_users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
            audit('RESET_ADMIN_PASSWORD', 'admin', (string) $user['id']);
            flash('success', 'Password updated.');
        }
    }
    redirect_route('admin/users');
}

/* ── Settings ────────────────────────────────────────────────────────── */
function admin_settings(): void
{
    $admin = require_admin(['SUPER_ADMIN']);
    $classes = db_all('SELECT * FROM classes ORDER BY id');
    view('admin/settings', [
        'title'          => 'Settings',
        'admin'          => $admin,
        'classes'        => $classes,
        'gateway_ready'  => gateway_configured(),
        'mail_ready'     => mail_configured(),
    ], 'admin/layout');
}

function admin_settings_save(): void
{
    $admin = require_admin(['SUPER_ADMIN']);
    verify_csrf();

    $early = (int) post('early_bird_fee');
    $flat = (int) post('flat_fee');
    if ($early <= 0 || $flat <= 0 || $flat < $early) {
        flash('error', 'Fees must be positive and the flat rate cannot be lower than the early-bird rate.');
        redirect_route('admin/settings');
    }
    if (!valid_email((string) post('support_email'))) {
        flash('error', 'Enter a valid support email address.');
        redirect_route('admin/settings');
    }

    $fields = [
        'name', 'year', 'camp_start', 'camp_end', 'venue_name', 'venue_address',
        'early_bird_fee', 'flat_fee', 'early_bird_deadline', 'default_house_capacity',
        'support_email', 'support_phone', 'payment_mode', 'payment_bank_name',
        'payment_account_name', 'payment_account_number', 'payment_whatsapp',
    ];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            save_setting($field, trim((string) $_POST[$field]));
        }
    }
    save_setting('registration_open', post('registration_open') ? '1' : '0');
    save_setting('maintenance_mode', post('maintenance_mode') ? '1' : '0');

    audit('UPDATE_SETTINGS', 'settings', 'programme', null, ['admin' => $admin['name']]);
    flash('success', 'Settings saved.');
    redirect_route('admin/settings');
}

/* ── Audit log ───────────────────────────────────────────────────────── */
function admin_audit(): void
{
    $admin = require_admin(['SUPER_ADMIN']);
    $page = max(1, (int) request('page', 1));
    $perPage = 60;
    $total = count_rows('audit_logs');
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;
    $logs = db_all("SELECT * FROM audit_logs ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}");
    view('admin/audit', ['title' => 'Audit log', 'admin' => $admin, 'logs' => $logs, 'page' => $page, 'pages' => $pages, 'total' => $total], 'admin/layout');
}

/* ── Newsletter ──────────────────────────────────────────────────────── */
function admin_newsletter(): void
{
    $admin = require_admin(['REGISTRATION_ADMIN', 'VIEWER', 'SUPER_ADMIN']);
    $rows = db_all('SELECT * FROM newsletter ORDER BY id DESC LIMIT 500');
    view('admin/newsletter', [
        'title'   => 'Newsletter',
        'admin'   => $admin,
        'rows'    => $rows,
        'active'  => count_rows('newsletter', "status = 'subscribed'"),
        'total'   => count_rows('newsletter'),
        'mail_ready' => mail_configured(),
    ], 'admin/layout');
}

function admin_newsletter_action(): void
{
    $admin = require_admin(['REGISTRATION_ADMIN', 'SUPER_ADMIN']);
    verify_csrf();
    $action = (string) post('action');

    if ($action === 'delete') {
        $id = (int) post('id');
        $row = db_one('SELECT * FROM newsletter WHERE id = ?', [$id]);
        if ($row) {
            db_exec('DELETE FROM newsletter WHERE id = ?', [$id]);
            audit('DELETE_SUBSCRIBER', 'newsletter', $row['email']);
            flash('success', 'Subscriber removed.');
        }
    } elseif ($action === 'broadcast') {
        $subject = trim((string) post('subject'));
        $title = trim((string) post('title')) ?: $subject;
        $message = trim((string) post('message'));
        if ($subject === '' || $message === '') {
            flash('error', 'Subject and message are required.');
        } else {
            $subscribers = db_all("SELECT * FROM newsletter WHERE status = 'subscribed' ORDER BY id LIMIT 200");
            $sent = 0;
            foreach ($subscribers as $subscriber) {
                if (send_broadcast($subscriber, $subject, $title, $message)) {
                    $sent++;
                }
            }
            audit('NEWSLETTER_BROADCAST', 'newsletter', $subject, null, ['recipients' => count($subscribers), 'sent' => $sent, 'admin' => $admin['name']]);
            flash($sent > 0 ? 'success' : 'error', "Broadcast processed: {$sent} sent, " . (count($subscribers) - $sent) . ' failed. ' . (mail_configured() ? '' : 'Mail is not configured; configure SMTP to deliver.'));
        }
    }
    redirect_route('admin/newsletter');
}

/* ── CSV helper ──────────────────────────────────────────────────────── */
function csv_download(string $filename, array $headers, array $rows): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, array_map(static function ($value): string {
            $value = is_scalar($value) || $value === null ? (string) $value : (string) json_encode($value);
            // Guard against spreadsheet formula injection.
            if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
                $value = "'" . $value;
            }
            return $value;
        }, $row));
    }
    fclose($out);
    exit;
}
