<?php
declare(strict_types=1);

/* ── PDO connection ──────────────────────────────────────────────────── */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = $GLOBALS['config']['db'];
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if ($cfg['driver'] === 'sqlite') {
        $path = (string) $cfg['database'];
        if (!preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path)) {
            $path = __DIR__ . '/../' . ltrim($path, '/\\');
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        $cfg['port'],
        $cfg['database']
    );
    $pdo = new PDO($dsn, (string) $cfg['username'], (string) $cfg['password'], $options);
    return $pdo;
}

function db_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        db()->query('SELECT 1 FROM settings LIMIT 1');
        $ready = true;
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

/* ── Query helpers ───────────────────────────────────────────────────── */
function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_value(string $sql, array $params = [], mixed $default = null): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : $value;
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function db_insert(string $table, array $data): int
{
    $columns = array_keys($data);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES ('
        . implode(', ', array_fill(0, count($columns), '?')) . ')';
    db_exec($sql, array_values($data));
    return (int) db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $whereParams = []): int
{
    $sets = [];
    foreach (array_keys($data) as $column) {
        $sets[] = $column . ' = ?';
    }
    $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
    return db_exec($sql, array_merge(array_values($data), $whereParams));
}

/* ── Installer ───────────────────────────────────────────────────────── */
function install_database(string $adminEmail = '', string $adminPassword = '', string $adminName = 'Super Admin'): array
{
    $pdo = db();
    $driver = $GLOBALS['config']['db']['driver'];
    $pk = $driver === 'sqlite'
        ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
        : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';

    $tables = [
        "CREATE TABLE IF NOT EXISTS settings (
            skey VARCHAR(191) PRIMARY KEY,
            svalue TEXT NULL
        )",
        "CREATE TABLE IF NOT EXISTS classes (
            id {$pk},
            name VARCHAR(191) NOT NULL,
            eligible_categories TEXT NOT NULL,
            created_at DATETIME NULL
        )",
        "CREATE TABLE IF NOT EXISTS houses (
            id {$pk},
            name VARCHAR(191) NOT NULL,
            gender VARCHAR(16) NOT NULL,
            capacity INT NOT NULL DEFAULT 40,
            status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NULL
        )",
        "CREATE TABLE IF NOT EXISTS participants (
            id {$pk},
            registration_id VARCHAR(32) NOT NULL UNIQUE,
            token VARCHAR(64) NOT NULL UNIQUE,
            surname VARCHAR(191) NOT NULL,
            other_name VARCHAR(191) NOT NULL,
            gender VARCHAR(16) NOT NULL,
            age_group VARCHAR(32) NOT NULL,
            category VARCHAR(64) NOT NULL,
            class_id INT NULL,
            zone VARCHAR(64) NOT NULL,
            phone VARCHAR(32) NOT NULL,
            email VARCHAR(191) NOT NULL,
            health_challenges TEXT NULL,
            allergies TEXT NULL,
            nok_name VARCHAR(191) NULL,
            nok_phone VARCHAR(32) NULL,
            is_minor INT NOT NULL DEFAULT 0,
            guardian_name VARCHAR(191) NULL,
            guardian_contact VARCHAR(191) NULL,
            profile_completed INT NOT NULL DEFAULT 0,
            payment_status VARCHAR(32) NOT NULL DEFAULT 'NOT_PAID',
            attendance_status VARCHAR(32) NOT NULL DEFAULT 'NOT_ARRIVED',
            house_id INT NULL,
            amount_owed INT NOT NULL DEFAULT 0,
            amount_paid INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL
        )",
        "CREATE TABLE IF NOT EXISTS payments (
            id {$pk},
            participant_id INT NOT NULL DEFAULT 0,
            bulk_order_id INT NULL,
            reference VARCHAR(191) NOT NULL UNIQUE,
            provider_reference VARCHAR(191) NULL,
            amount INT NOT NULL,
            payment_type VARCHAR(16) NOT NULL,
            installment_number INT NULL,
            plan_rate VARCHAR(16) NOT NULL,
            provider VARCHAR(64) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
            virtual_account_number VARCHAR(32) NULL,
            account_name VARCHAR(191) NULL,
            bank_name VARCHAR(191) NULL,
            checkout_url VARCHAR(512) NULL,
            paid_at DATETIME NULL,
            verified_at DATETIME NULL,
            failure_reason VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL
        )",
        "CREATE TABLE IF NOT EXISTS bulk_orders (
            id {$pk},
            token VARCHAR(64) NOT NULL UNIQUE,
            reference VARCHAR(191) NULL,
            buyer_email VARCHAR(191) NOT NULL,
            buyer_phone VARCHAR(32) NOT NULL,
            seats INT NOT NULL,
            amount INT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
            created_at DATETIME NOT NULL,
            paid_at DATETIME NULL,
            updated_at DATETIME NULL
        )",
        "CREATE TABLE IF NOT EXISTS bulk_tickets (
            id {$pk},
            bulk_order_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            category VARCHAR(64) NOT NULL,
            price INT NOT NULL,
            used INT NOT NULL DEFAULT 0,
            participant_id INT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS payment_events (
            id {$pk},
            provider_reference VARCHAR(191) NOT NULL UNIQUE,
            payment_id INT NULL,
            amount INT NOT NULL DEFAULT 0,
            outcome VARCHAR(32) NOT NULL,
            raw TEXT NULL,
            created_at DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS attendance (
            id {$pk},
            participant_id INT NOT NULL UNIQUE,
            status VARCHAR(16) NOT NULL DEFAULT 'NOT_ARRIVED',
            check_in_time DATETIME NULL,
            checked_in_by VARCHAR(191) NULL,
            corrected INT NOT NULL DEFAULT 0,
            correction_reason VARCHAR(255) NULL
        )",
        "CREATE TABLE IF NOT EXISTS admin_users (
            id {$pk},
            name VARCHAR(191) NOT NULL,
            email VARCHAR(191) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(32) NOT NULL DEFAULT 'VIEWER',
            zone VARCHAR(64) NULL,
            is_protected INT NOT NULL DEFAULT 0,
            status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id {$pk},
            actor_id INT NULL,
            actor_name VARCHAR(191) NULL,
            action VARCHAR(64) NOT NULL,
            target_type VARCHAR(64) NULL,
            target_id VARCHAR(64) NULL,
            before_json TEXT NULL,
            after_json TEXT NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS newsletter (
            id {$pk},
            email VARCHAR(191) NOT NULL UNIQUE,
            name VARCHAR(191) NULL,
            source VARCHAR(64) NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'subscribed',
            token VARCHAR(64) NOT NULL UNIQUE,
            subscribed_at DATETIME NULL,
            unsubscribed_at DATETIME NULL,
            created_at DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id {$pk},
            email VARCHAR(191) NULL,
            ip VARCHAR(45) NULL,
            success INT NOT NULL DEFAULT 0,
            attempted_at DATETIME NOT NULL
        )",
        "CREATE TABLE IF NOT EXISTS throttle (
            id {$pk},
            action VARCHAR(32) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            created_at DATETIME NOT NULL
        )",
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    /* ── Lightweight in-place upgrades for existing databases ── */
    $requiredColumns = [
        'participants' => ['profile_completed' => 'INT NOT NULL DEFAULT 1'],
        'payments'     => ['bulk_order_id' => 'INT NULL'],
    ];
    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column => $definition) {
            if (!db_column_exists($table, $column)) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }
    }

    $indexes = [
        'CREATE INDEX idx_participants_reg ON participants (registration_id)',
        'CREATE INDEX idx_participants_email ON participants (email)',
        'CREATE INDEX idx_participants_zone ON participants (zone)',
        'CREATE INDEX idx_participants_payment ON participants (payment_status)',
        'CREATE INDEX idx_participants_created ON participants (created_at)',
        'CREATE INDEX idx_payments_participant ON payments (participant_id)',
        'CREATE INDEX idx_payments_status ON payments (status)',
        'CREATE INDEX idx_payments_account ON payments (virtual_account_number)',
        'CREATE INDEX idx_payments_bulk ON payments (bulk_order_id)',
        'CREATE INDEX idx_bulk_tickets_order ON bulk_tickets (bulk_order_id)',
        'CREATE INDEX idx_bulk_orders_ref ON bulk_orders (reference)',
        'CREATE INDEX idx_audit_created ON audit_logs (created_at)',
        'CREATE INDEX idx_newsletter_status ON newsletter (status)',
        'CREATE INDEX idx_login_attempt ON login_attempts (email, ip, attempted_at)',
        'CREATE INDEX idx_throttle ON throttle (action, ip, created_at)',
    ];
    foreach ($indexes as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable) {
            // Index already exists (MySQL does not support IF NOT EXISTS here).
        }
    }

    /* ── Seed settings ── */
    $defaults = $GLOBALS['config']['programme'];
    $defaults['maintenance_mode'] = '0';
    $defaults['payment_mode'] = (string) config('payment.mode', 'mock');
    foreach ($defaults as $key => $value) {
        if (!db_one('SELECT skey FROM settings WHERE skey = ?', [$key])) {
            db_insert('settings', ['skey' => $key, 'svalue' => (string) $value]);
        }
    }

    /* ── Seed classes ── */
    if ((int) db_value('SELECT COUNT(*) FROM classes', [], 0) === 0) {
        foreach ($GLOBALS['config']['classes'] as $class) {
            db_insert('classes', [
                'name'                => $class['name'],
                'eligible_categories' => json_encode($class['eligible_categories']),
                'created_at'          => now(),
            ]);
        }
    }

    /* ── Seed houses ── */
    if ((int) db_value('SELECT COUNT(*) FROM houses', [], 0) === 0) {
        foreach ($GLOBALS['config']['houses'] as $house) {
            db_insert('houses', [
                'name'       => $house['name'],
                'gender'     => $house['gender'],
                'capacity'   => $house['capacity'],
                'status'     => 'ACTIVE',
                'created_at' => now(),
            ]);
        }
    }

    /* ── Seed the first admin ── */
    $created = false;
    $generated = null;
    if ((int) db_value('SELECT COUNT(*) FROM admin_users', [], 0) === 0) {
        $email = trim($adminEmail) ?: trim((string) env_value('SEED_ADMIN_EMAIL', 'admin@sbosun.org'));
        $password = $adminPassword ?: trim((string) env_value('SEED_ADMIN_PASSWORD', ''));
        if ($password === '') {
            $password = 'SB-' . bin2hex(random_bytes(4)) . '!';
            $generated = $password;
        }
        $name = trim($adminName) ?: (string) env_value('SEED_ADMIN_NAME', 'Super Admin');
        db_insert('admin_users', [
            'name'          => $name,
            'email'         => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => 'SUPER_ADMIN',
            'zone'          => null,
            'is_protected'  => 1,
            'status'        => 'ACTIVE',
            'created_at'    => now(),
        ]);
        $created = true;
        audit('INSTALL', 'system', 'database', null, ['admin' => $email]);
    }

    return ['admin_created' => $created, 'temp_password' => $generated];
}

/** Count helper used across dashboards. */
function count_rows(string $table, string $where = '1=1', array $params = []): int
{
    return (int) db_value("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params, 0);
}

/* ── Schema introspection (used by the installer and the admin DB viewer) ── */
function db_column_exists(string $table, string $column): bool
{
    try {
        if (($GLOBALS['config']['db']['driver'] ?? 'sqlite') === 'sqlite') {
            foreach (db_all("PRAGMA table_info({$table})") as $row) {
                if (($row['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        }
        return db_one("SHOW COLUMNS FROM {$table} LIKE " . db()->quote($column)) !== null;
    } catch (Throwable) {
        return false;
    }
}

function db_tables(): array
{
    if (($GLOBALS['config']['db']['driver'] ?? 'sqlite') === 'sqlite') {
        return array_map(
            static fn(array $r): string => (string) $r['name'],
            db_all("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
        );
    }
    return array_map(static fn(array $r): string => (string) array_values($r)[0], db_all('SHOW TABLES'));
}

function db_table_columns(string $table): array
{
    if (($GLOBALS['config']['db']['driver'] ?? 'sqlite') === 'sqlite') {
        return db_all("PRAGMA table_info({$table})");
    }
    return db_all("SHOW COLUMNS FROM {$table}");
}

function db_table_row_count(string $table): int
{
    try {
        return (int) db_value("SELECT COUNT(*) FROM {$table}", [], 0);
    } catch (Throwable) {
        return 0;
    }
}
