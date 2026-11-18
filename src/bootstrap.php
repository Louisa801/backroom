<?php
declare(strict_types=1);

// Loaded by every page and API file. Never show PHP errors to users.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Items.php';
require_once __DIR__ . '/Photos.php';
require_once __DIR__ . '/Intake.php';
require_once __DIR__ . '/Requests.php';
require_once __DIR__ . '/Sales.php';

// Turn warnings and notices into exceptions so they reach the handler below.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    error_log((string) $e);
    $message = 'Something went wrong on our side. Please try again in a moment.';

    if (str_starts_with($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['error' => $message]);
        return;
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="en"><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Something went wrong · Backroom</title>'
        . '<body style="font-family:system-ui,sans-serif;font-size:18px;background:#F5EFE4;color:#2B211C;padding:24px">'
        . '<h1>Sorry</h1><p>' . e($message) . '</p><p><a href="/" style="color:#A9472A">Back to the archive</a></p>';
});
