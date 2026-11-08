<?php
declare(strict_types=1);

/**
 * SB Osun Camp Portal — application bootstrap.
 * Raw PHP 8.1+, PDO, no framework and no Composer dependencies.
 */

$GLOBALS['config'] = require __DIR__ . '/../config.php';

$app = $GLOBALS['config']['app'];
date_default_timezone_set($app['timezone']);
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

if (!is_dir(__DIR__ . '/../storage/logs')) {
    @mkdir(__DIR__ . '/../storage/logs', 0775, true);
}

if ($app['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php-error.log');

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/payments.php';
require __DIR__ . '/mailer.php';

ensure_payment_schema();

/* ── Session ─────────────────────────────────────────────────────────── */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($GLOBALS['config']['security']['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

/* ── Runtime URL detection (works in /sbosun-camp/ or at a domain root) ─ */
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$basePath   = rtrim(str_replace('/index.php', '', $scriptName), '/');
$GLOBALS['base_path'] = $basePath;
$GLOBALS['base_url']  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath;
