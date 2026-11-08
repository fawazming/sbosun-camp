<?php
declare(strict_types=1);

/**
 * SB Osun Camp Portal — front controller.
 *
 * Works with clean URLs (Apache .htaccess) and without mod_rewrite
 * (?r=admin/dashboard). No framework or Composer dependency.
 */

require __DIR__ . '/app/bootstrap.php';

/* Friendly first-run screen when the database has not been installed yet. */
if (!db_ready()) {
    http_response_code(503);
    $installUrl = base_url('install.php');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>SB Osun Camp Portal — Setup required</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F7F3E8;font-family:Arial,Helvetica,sans-serif;color:#14170F;padding:24px}'
        . '.card{max-width:520px;background:#fff;border:1px solid rgba(15,61,46,.12);border-radius:24px;padding:32px;box-shadow:0 24px 60px rgba(10,42,32,.12)}'
        . 'h1{margin:0 0 8px;color:#0F3D2E;font-size:24px}p{line-height:1.6;color:#4B5347}a{display:inline-block;margin-top:14px;background:#E8B923;color:#0A2A20;text-decoration:none;font-weight:800;padding:12px 22px;border-radius:999px}</style>'
        . '</head><body><div class="card"><div style="width:44px;height:44px;border-radius:50%;background:#E8B923;color:#0A2A20;display:grid;place-items:center;font-weight:900;margin-bottom:16px">SB</div>'
        . '<h1>Camp portal setup required</h1><p>The database tables are not installed yet. Run the one-time installer to create the schema, seed classes, houses and your first Super Admin account.</p>'
        . '<a href="' . htmlspecialchars($installUrl, ENT_QUOTES) . '">Open the installer</a></div></body></html>';
    exit;
}

$path = (string) ($_GET['r'] ?? '');
if ($path === '') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    $uri = preg_replace('#^/index\.php#', '', $uri) ?: '/';
    $path = trim($uri, '/');
    if ($path === '' || $path === 'index.php') {
        $path = 'home';
    }
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
try {
    dispatch($method, $path);
} catch (Throwable $e) {
    log_error('Unhandled exception: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
    http_response_code(500);
    if (config('app.debug')) {
        echo '<pre style="padding:24px;white-space:pre-wrap">' . e($e->getMessage()) . "\n\n" . e($e->getTraceAsString()) . '</pre>';
    } else {
        view('errors/500', ['title' => 'Something went wrong'], 'layout');
    }
}
