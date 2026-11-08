<?php
declare(strict_types=1);

/* ── Configuration ───────────────────────────────────────────────────── */
function config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['config'] ?? [];
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function base_path(): string
{
    return $GLOBALS['base_path'] ?? '';
}

function base_url(string $path = ''): string
{
    $base = rtrim($GLOBALS['base_url'] ?? '', '/');
    return $base . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

/** Query-string URL — no mod_rewrite required. */
function url(string $path = '', array $query = []): string
{
    $path = trim($path, '/');
    $params = $path === '' ? [] : ['r' => $path];
    foreach ($query as $k => $v) {
        if ($v !== null && $v !== '') {
            $params[$k] = $v;
        }
    }
    $qs = http_build_query($params);
    return base_url('index.php') . ($qs ? '?' . $qs : '');
}

function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function absolute_url(string $path = '', array $query = []): string
{
    return url($path, $query);
}

/* ── Escaping / output ───────────────────────────────────────────────── */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(mixed $amount, bool $decimals = false): string
{
    $symbol = config('app.currency', '₦');
    return $symbol . number_format((float) $amount, $decimals ? 2 : 0);
}

function nl2p(?string $text): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    return '<p>' . implode('</p><p>', array_map('e', preg_split('/\R{2,}/', $text))) . '</p>';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function nice_date(?string $date, string $format = 'j M Y'): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function nice_datetime(?string $date): string
{
    return nice_date($date, 'j M Y, g:ia');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach (array_slice($parts ?: [], 0, 2) as $part) {
        $out .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $out ?: 'SB';
}

/* ── Request / redirect / response ───────────────────────────────────── */
function request(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function post(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $default;
}

function redirect(string $path, int $status = 302): never
{
    header('Location: ' . $path, true, $status);
    exit;
}

function redirect_route(string $route, array $query = []): never
{
    redirect(url($route, $query));
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return trim(explode(',', (string) $_SERVER[$key])[0]);
        }
    }
    return '0.0.0.0';
}

/* ── Flash messages ──────────────────────────────────────────────────── */
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function set_flash(string $type, string $message): void
{
    flash($type, $message);
}

/* ── CSRF ────────────────────────────────────────────────────────────── */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $provided = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($provided) || !hash_equals($_SESSION['_csrf'] ?? '', $provided)) {
        http_response_code(419);
        view('errors/419', ['title' => 'Session expired'], 'layout');
        exit;
    }
}

/* ── Validation helpers ──────────────────────────────────────────────── */
function valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_phone(string $phone): bool
{
    $digits = preg_replace('/\D+/', '', $phone);
    return strlen($digits) >= 10 && strlen($digits) <= 15;
}

function slug_id(string $prefix = 'SB'): string
{
    return strtoupper($prefix) . '-' . date('y') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function random_token(int $bytes = 24): string
{
    return bin2hex(random_bytes($bytes));
}

function old(string $key, mixed $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}

function remember_old(array $input): void
{
    unset($input['_csrf'], $input['password']);
    $_SESSION['_old'] = $input;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

/* ── Settings (database-backed, admin editable) ──────────────────────── */
function settings(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    $cache = [];
    if (db_ready()) {
        foreach (db_all('SELECT skey, svalue FROM settings') as $row) {
            $cache[$row['skey']] = $row['svalue'];
        }
    }
    return $cache;
}

function setting(string $key, mixed $default = null): mixed
{
    $all = settings();
    return $all[$key] ?? ($GLOBALS['config']['programme'][$key] ?? $default);
}

function save_setting(string $key, mixed $value): void
{
    if (db_one('SELECT skey FROM settings WHERE skey = ?', [$key])) {
        db_exec('UPDATE settings SET svalue = ? WHERE skey = ?', [(string) $value, $key]);
    } else {
        db_exec('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$key, (string) $value]);
    }
    settings(true);
}

function programme(): array
{
    $base = $GLOBALS['config']['programme'];
    foreach ($base as $key => $default) {
        $base[$key] = setting($key, $default);
    }
    return $base;
}

function phase(): string
{
    $deadline = (string) setting('early_bird_deadline', '');
    return (time() <= strtotime($deadline)) ? 'EARLY_BIRD' : 'FLAT';
}

function phase_label(): string
{
    return phase() === 'EARLY_BIRD' ? 'Early bird' : 'Flat rate';
}

function current_fee(): int
{
    return phase() === 'EARLY_BIRD'
        ? (int) setting('early_bird_fee', 8500)
        : (int) setting('flat_fee', 12000);
}

function camp_dates(): string
{
    $start = strtotime((string) setting('camp_start'));
    $end = strtotime((string) setting('camp_end'));
    if (!$start || !$end) {
        return 'Dates to be announced';
    }
    if (date('Y', $start) === date('Y', $end)) {
        if (date('m', $start) === date('m', $end)) {
            return date('j', $start) . '–' . date('j F Y', $end);
        }
        return date('j F', $start) . ' – ' . date('j F Y', $end);
    }
    return date('j M Y', $start) . ' – ' . date('j M Y', $end);
}

/* ── Domain helpers ──────────────────────────────────────────────────── */
function categories(): array
{
    return $GLOBALS['config']['categories'];
}

function zones(): array
{
    return $GLOBALS['config']['zones'];
}

function age_groups(): array
{
    return $GLOBALS['config']['age_groups'];
}

function class_for_category(string $category): ?array
{
    foreach (db_all('SELECT * FROM classes ORDER BY id') as $class) {
        $eligible = json_decode((string) $class['eligible_categories'], true) ?: [];
        if (in_array($category, $eligible, true)) {
            return $class;
        }
    }
    return null;
}

function houses_for_gender(string $gender): array
{
    return db_all("SELECT * FROM houses WHERE gender = ? AND status = 'ACTIVE' ORDER BY name", [$gender]);
}

function participant_by_token(string $token): ?array
{
    return db_one('SELECT * FROM participants WHERE token = ? AND deleted_at IS NULL', [$token]);
}

function delegate_name(array $participant, bool $fallbackEmail = true): string
{
    $name = trim(($participant['other_name'] ?? '') . ' ' . ($participant['surname'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    return $fallbackEmail ? (string) ($participant['email'] ?? 'New delegate') : 'New delegate';
}

function payment_status_label(string $status): string
{
    return ucwords(strtolower(str_replace('_', ' ', $status)));
}

function badge_class(string $status): string
{
    return match ($status) {
        'FULLY_PAID', 'VERIFIED', 'PRESENT', 'ACTIVE', 'MATCHED' => 'badge badge-green',
        'PARTIALLY_PAID', 'PENDING', 'NOT_ARRIVED', 'UNDERPAID' => 'badge badge-amber',
        'NOT_PAID', 'FAILED', 'DEACTIVATED', 'DUPLICATE_PAYMENT' => 'badge badge-red',
        default => 'badge badge-slate',
    };
}

function throttle_limited(string $action, int $max, int $minutes = 60): bool
{
    if (!db_ready()) {
        return false;
    }
    $count = (int) (db_one(
        'SELECT COUNT(*) AS c FROM throttle WHERE action = ? AND ip = ? AND created_at >= ?',
        [$action, client_ip(), date('Y-m-d H:i:s', time() - ($minutes * 60))]
    )['c'] ?? 0);
    return $count >= $max;
}

function throttle_hit(string $action): void
{
    if (db_ready()) {
        db_exec('INSERT INTO throttle (action, ip, created_at) VALUES (?, ?, ?)', [$action, client_ip(), now()]);
    }
}

function registration_rate_limited(): bool
{
    $max = (int) config('security.registration_max', 10);
    $ip = client_ip();
    if (!db_ready()) {
        return false;
    }
    $count = (int) (db_one(
        'SELECT COUNT(*) AS c FROM throttle WHERE action = ? AND ip = ? AND created_at >= ?',
        ['register', $ip, date('Y-m-d H:i:s', time() - 3600)]
    )['c'] ?? 0);
    return $count >= $max;
}

function registration_rate_hit(): void
{
    if (db_ready()) {
        db_exec('INSERT INTO throttle (action, ip, created_at) VALUES (?, ?, ?)', ['register', client_ip(), now()]);
    }
}

function audit(string $action, string $targetType = '', string $targetId = '', mixed $before = null, mixed $after = null): void
{
    if (!db_ready()) {
        return;
    }
    $admin = current_admin();
    db_exec(
        'INSERT INTO audit_logs (actor_id, actor_name, action, target_type, target_id, before_json, after_json, ip, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $admin['id'] ?? null,
            $admin['name'] ?? 'system',
            $action,
            $targetType,
            $targetId,
            $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
            $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
            client_ip(),
            now(),
        ]
    );
}

/* ── View rendering ──────────────────────────────────────────────────── */
function view(string $template, array $data = [], ?string $layout = null): void
{
    $render = static function (string $file, array $vars): string {
        extract($vars, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    };

    $file = __DIR__ . '/../views/' . $template . '.php';
    if (!is_file($file)) {
        http_response_code(500);
        echo 'View not found: ' . e($template);
        return;
    }
    $content = $render($file, $data);

    if ($layout === null) {
        echo $content;
        return;
    }
    $layoutFile = __DIR__ . '/../views/' . $layout . '.php';
    if (!is_file($layoutFile)) {
        echo $content;
        return;
    }
    echo $render($layoutFile, array_merge($data, ['content' => $content]));
}

function component(string $name, array $data = []): void
{
    $file = __DIR__ . '/../views/components/' . $name . '.php';
    if (is_file($file)) {
        extract($data, EXTR_SKIP);
        require $file;
    }
}

/* ── Tiny router ─────────────────────────────────────────────────────── */
function dispatch(string $method, string $path): void
{
    $path = trim($path, '/');
    if ($path === '') {
        $path = 'home';
    }
    $routes = require __DIR__ . '/../routes.php';
    foreach ($routes as $route) {
        [$routeMethod, $pattern, $handler] = $route;
        if ($routeMethod !== '*' && $routeMethod !== $method) {
            continue;
        }
        $regex = '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        if (preg_match($regex, $path, $matches)) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler($params);
            return;
        }
    }
    http_response_code(404);
    view('errors/404', ['title' => 'Page not found'], 'layout');
}
