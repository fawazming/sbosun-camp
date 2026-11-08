<?php
declare(strict_types=1);

const ADMIN_ROLES = [
    'SUPER_ADMIN'        => 'Super Admin',
    'REGISTRATION_ADMIN' => 'Registration Admin',
    'FINANCE_ADMIN'      => 'Finance Admin',
    'ATTENDANCE_ADMIN'   => 'Attendance Admin',
    'HOUSE_ADMIN'        => 'House Admin',
    'VIEWER'             => 'Viewer',
    'ZONE_ADMIN'         => 'Zone Admin',
];

function current_admin(): ?array
{
    static $admin = null;
    static $loaded = false;

    if ($loaded) {
        return $admin;
    }
    $loaded = true;

    $id = $_SESSION['admin_id'] ?? null;
    if (!$id) {
        return null;
    }

    $lastSeen = (int) ($_SESSION['admin_last_seen'] ?? 0);
    $timeout = (int) config('security.idle_timeout', 60) * 60;
    if ($lastSeen > 0 && time() - $lastSeen > $timeout) {
        logout_admin(false);
        flash('error', 'Your session expired. Please sign in again.');
        return null;
    }

    $row = db_one("SELECT * FROM admin_users WHERE id = ? AND status = 'ACTIVE'", [$id]);
    if (!$row) {
        unset($_SESSION['admin_id'], $_SESSION['admin_last_seen']);
        return null;
    }

    $_SESSION['admin_last_seen'] = time();
    $admin = $row;
    return $admin;
}

function admin_can(array $roles): bool
{
    $admin = current_admin();
    if (!$admin) {
        return false;
    }
    return $admin['role'] === 'SUPER_ADMIN' || in_array($admin['role'], $roles, true);
}

function require_admin(array $roles = []): array
{
    $admin = current_admin();
    if (!$admin) {
        if (is_post() || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api')) {
            json_response(['error' => 'Unauthorized'], 401);
        }
        flash('error', 'Please sign in to continue.');
        redirect_route('admin/login', ['next' => $_SERVER['REQUEST_URI'] ?? '']);
    }
    if ($roles !== [] && !admin_can($roles)) {
        http_response_code(403);
        view('errors/403', ['title' => 'Access denied'], 'admin/layout');
        exit;
    }
    return $admin;
}

function login_rate_limited(string $email): bool
{
    $max = (int) config('security.login_max_attempts', 5);
    $window = (int) config('security.login_window', 15);
    $count = (int) (db_one(
        'SELECT COUNT(*) AS c FROM login_attempts
         WHERE success = 0 AND (email = ? OR ip = ?) AND attempted_at >= ?',
        [strtolower($email), client_ip(), date('Y-m-d H:i:s', time() - ($window * 60))]
    )['c'] ?? 0);
    return $count >= $max;
}

function attempt_login(string $email, string $password): array
{
    $email = strtolower(trim($email));

    if (login_rate_limited($email)) {
        audit('LOGIN_BLOCKED', 'admin', $email);
        return [false, 'Too many failed attempts. Please wait ' . config('security.login_window', 15) . ' minutes and try again.'];
    }

    $admin = db_one('SELECT * FROM admin_users WHERE email = ?', [$email]);
    $ok = $admin && $admin['status'] === 'ACTIVE' && password_verify($password, $admin['password_hash']);

    db_insert('login_attempts', [
        'email'        => $email,
        'ip'           => client_ip(),
        'success'      => $ok ? 1 : 0,
        'attempted_at' => now(),
    ]);

    if (!$ok) {
        audit('LOGIN_FAILED', 'admin', $email);
        return [false, 'Invalid email or password.'];
    }

    // Opportunistic hash upgrade.
    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        db_update('admin_users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$admin['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_last_seen'] = time();
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));

    db_update('admin_users', ['last_login_at' => now()], 'id = ?', [$admin['id']]);
    audit('LOGIN', 'admin', (string) $admin['id'], null, ['email' => $admin['email'], 'role' => $admin['role']]);
    return [true, null];
}

function logout_admin(bool $audit = true): void
{
    $admin = current_admin();
    if ($audit && $admin) {
        audit('LOGOUT', 'admin', (string) $admin['id']);
    }
    unset($_SESSION['admin_id'], $_SESSION['admin_last_seen']);
    session_regenerate_id(true);
}

function admin_scope(?array $admin = null): array
{
    $admin ??= current_admin();
    if (!$admin) {
        return ['', []];
    }
    if ($admin['role'] === 'SUPER_ADMIN' || empty($admin['zone'])) {
        return ['', []];
    }
    return [' AND zone = ?', [$admin['zone']]];
}

function admin_initials(?array $admin = null): string
{
    $admin ??= current_admin();
    return initials($admin['name'] ?? 'SB');
}
