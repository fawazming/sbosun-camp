<?php
/**
 * SB Osun Camp Portal — central configuration.
 *
 * Non-secret business values live in the `programme` section and are seeded
 * into the `settings` table (editable from Admin → Settings). Secrets and
 * deployment values live in `.env`.
 */

declare(strict_types=1);

/** Minimal .env reader (no external dependencies). */
function env_value(string $key, mixed $default = null): mixed
{
    static $env = null;

    if ($env === null) {
        $env = [];
        $file = __DIR__ . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                if (strlen($v) >= 2 && (($v[0] === '"' && str_ends_with($v, '"')) || ($v[0] === "'" && str_ends_with($v, "'")))) {
                    $v = substr($v, 1, -1);
                }
                $env[$k] = $v;
            }
        }
    }

    return $env[$key] ?? ($_ENV[$key] ?? ($_SERVER[$key] ?? $default));
}

return [
    'app' => [
        'name'        => 'SB Osun Camp Portal',
        'short_name'  => 'SB HIC',
        'organisation' => 'Standard Bearers Islamic Organization',
        'state'       => 'Osun State',
        'env'         => env_value('APP_ENV', 'local'),
        'debug'       => filter_var(env_value('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
        'timezone'    => 'Africa/Lagos',
        'locale'      => 'en_NG',
        'currency'    => '₦',
        'motto'       => 'Upholding the standard…',
    ],

    'db' => [
        'driver'   => env_value('DB_DRIVER', 'sqlite'), // sqlite | mysql
        'database' => env_value('DB_DATABASE', __DIR__ . '/storage/camp.sqlite'),
        'host'     => env_value('DB_HOST', '127.0.0.1'),
        'port'     => (int) env_value('DB_PORT', 3306),
        'username' => env_value('DB_USERNAME', 'root'),
        'password' => env_value('DB_PASSWORD', ''),
    ],

    'security' => [
        'session_name'       => 'sbosun_camp_session',
        'idle_timeout'       => (int) env_value('ADMIN_IDLE_TIMEOUT_MINUTES', 60),
        'login_max_attempts' => (int) env_value('LOGIN_MAX_ATTEMPTS', 5),
        'login_window'       => (int) env_value('LOGIN_WINDOW_MINUTES', 15),
        'registration_max'   => (int) env_value('REGISTRATION_MAX_PER_HOUR', 10),
    ],

    'payment' => [
        // mock | manual | hosted
        'mode'                => env_value('PAYMENT_MODE', 'mock'),
        'provider'            => env_value('PAYMENT_PROVIDER', 'PayVessel'),
        'base_url'            => rtrim((string) env_value('PAYMENT_BASE_URL', 'https://payflow.sgm.ng'), '/'),
        'authorize_path'      => env_value('PAYMENT_AUTHORIZE_PATH', '/api/authorize'),
        'verify_path'         => env_value('PAYMENT_VERIFY_PATH', '/api/verify/{reference}'),
        'api_key'             => env_value('PAYMENT_API_KEY', ''),
        'secret_key'          => env_value('PAYMENT_SECRET_KEY', ''),
        'business_id'         => env_value('PAYMENT_BUSINESS_ID', ''),
        'callback_url'        => env_value('PAYMENT_CALLBACK_URL', ''),
        'api_key_header'      => env_value('PAYMENT_API_KEY_HEADER', 'api-key'),
        'auth_header'         => env_value('PAYMENT_AUTH_HEADER', 'Authorization'),
        'auth_scheme'         => env_value('PAYMENT_AUTH_SCHEME', 'Bearer'),
        // Amount unit sent to / expected from the gateway: major | minor.
        'amount_unit'         => strtolower((string) env_value('PAYMENT_AMOUNT_UNIT', 'major')),
        'verify_amount_unit'  => strtolower((string) env_value('PAYMENT_VERIFY_AMOUNT_UNIT', 'major')),
        'reference_prefix'    => env_value('PAYMENT_REFERENCE_PREFIX', ''),
        'verify_reference_prefix' => env_value('PAYMENT_VERIFY_REFERENCE_PREFIX', 'TXN'),
        'paid_statuses'       => ['success', 'successful', 'completed', 'complete', 'paid', 'approved', 'verified', '00'],
        'pending_statuses'    => ['pending', 'processing', 'initiated', 'awaiting_payment', 'awaiting-payment', 'ongoing', 'queued'],
        'failed_statuses'     => ['failed', 'failure', 'cancelled', 'canceled', 'expired', 'reversed', 'declined', 'invalid', 'amount_invalid', 'amt_invalid', 'error', 'abandoned'],
        'webhook_secret'      => env_value('PAYMENT_WEBHOOK_SECRET', ''),
        'webhook_header'      => env_value('PAYMENT_WEBHOOK_HEADER', 'Payvessel-Http-Signature'),
        'webhook_ips'         => array_filter(array_map('trim', explode(',', (string) env_value('PAYMENT_WEBHOOK_ALLOWED_IPS', '')))),
        'allow_pending'       => filter_var(env_value('PAYMENT_ALLOW_PENDING', 'false'), FILTER_VALIDATE_BOOLEAN),
        'http_timeout'        => (int) env_value('PAYMENT_HTTP_TIMEOUT', 30),
        'manual_bank'         => env_value('PAYMENT_BANK_NAME', ''),
        'manual_account_name' => env_value('PAYMENT_ACCOUNT_NAME', ''),
        'manual_account_no'   => env_value('PAYMENT_ACCOUNT_NUMBER', ''),
        'whatsapp'            => env_value('PAYMENT_WHATSAPP', ''),
    ],

    'mail' => [
        'enabled'    => filter_var(env_value('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
        'host'       => env_value('MAIL_HOST', ''),
        'port'       => (int) env_value('MAIL_PORT', 465),
        'username'   => env_value('MAIL_USERNAME', ''),
        'password'   => env_value('MAIL_PASSWORD', ''),
        'encryption' => env_value('MAIL_ENCRYPTION', 'ssl'),
        'from_email' => env_value('MAIL_FROM_EMAIL', 'no-reply@sbosun.org'),
        'from_name'  => env_value('MAIL_FROM_NAME', 'SB Osun'),
    ],

    'programme' => [
        'name'                 => 'SB HIC 2026',
        'year'                 => 2026,
        'camp_start'           => '2026-12-24',
        'camp_end'             => '2026-12-28',
        'venue_name'           => 'Oduduwa College',
        'venue_address'        => 'Ile-Ife, Osun State',
        'early_bird_fee'       => 8500,
        'flat_fee'             => 12000,
        'early_bird_deadline'  => '2026-11-30 23:59:59',
        // Added to every payment transaction: each instalment, each full
        // payment and each bulk seat. Base fees stay clean.
        'installment_count'    => 10,
        'gateway_charge'       => 150,
        'default_house_capacity' => 40,
        'registration_open'    => '1',
        'support_email'        => 'support@sbosun.org',
        'support_phone'        => '09053444178',
    ],

    'telegram' => [
        'enabled'   => filter_var(env_value('TELEGRAM_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
        'bot_token' => (string) env_value('TELEGRAM_BOT_TOKEN', ''),
        'chat_ids'  => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env_value('TELEGRAM_CHAT_ID', ''))
        ))),
        'timeout'   => (int) env_value('TELEGRAM_TIMEOUT', 10),
    ],

    'classes' => [
        ['name' => 'Lower Class',  'eligible_categories' => ['JSS 1-3']],
        ['name' => 'Upper Class',  'eligible_categories' => ['SS1- SSS 3']],
        ['name' => 'Dawah Class',  'eligible_categories' => ['School Leaver', 'Undergraduate', 'Graduate', 'Others']],
    ],

    'houses' => [
        ['name' => 'Ali House',        'gender' => 'MALE',   'capacity' => 40],
        ['name' => 'Abu Bakar House',  'gender' => 'MALE',   'capacity' => 40],
        ['name' => 'Khadijah House',   'gender' => 'FEMALE', 'capacity' => 40],
        ['name' => 'Aishah House',     'gender' => 'FEMALE', 'capacity' => 40],
    ],

    'categories' => [
        'JSS 1-3', 'SS1- SSS 3', 'School Leaver', 'Undergraduate', 'Graduate', 'Others',
    ],

    'zones' => [
        'Ile-Ife', 'Osogbo', 'Ede', 'Ilesha', 'Ikirun', 'Ila', 'Inisa', 'Okuku',
        'Owena', 'Akure', 'Ekiti', 'olode', 'Osu', 'Moro', 'Iwo', 'Ejigbo',
        'OAU', 'UNIOSUN', 'FOUNTAIN',
    ],

    'age_groups' => ['10 - 20', '21 - 30', '31 - 40', '40 above'],

    'enquiries' => [
        ['Osun (Osogbo)', '09053444178'],
        ['Ile-Ife', '08103692449'],
        ['Ilesa', '08134747377'],
        ['Abuja', '08067275275'],
        ['Kano', '08066942387'],
        ['Ondo', '08106764258'],
        ['Ekiti', '08128387390'],
        ['Kwara', '07010239060'],
    ],
];
