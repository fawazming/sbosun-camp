<?php
declare(strict_types=1);

/**
 * Portable database dump (SQLite or MySQL):
 *
 *   php tools/dump-db.php                         # write SQL to stdout
 *   php tools/dump-db.php storage/backups/camp.sql
 *
 * Only file paths are accepted; the dump is never served by the web server
 * because storage/ is protected/ignored. Use the admin viewer for a download.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

if (!db_ready()) {
    fwrite(STDERR, "Database is not installed or not reachable.\n");
    exit(1);
}

$path = $argv[1] ?? '';
if ($path === '') {
    $out = fopen('php://output', 'w');
} else {
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, "Could not create directory: {$dir}\n");
        exit(1);
    }
    $out = fopen($path, 'w');
}

if ($out === false) {
    fwrite(STDERR, "Could not open the dump output.\n");
    exit(1);
}

db_dump_write($out);

if ($path === '') {
    exit(0);
}

fclose($out);
fwrite(STDOUT, "Database dump written to {$path}\n");
