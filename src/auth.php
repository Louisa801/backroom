<?php
declare(strict_types=1);

/** Start the session with safe cookie settings (once per request). */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); // Render terminates HTTPS at its proxy

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('backroom_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** The CSRF token for this session; put it in every form that changes data. */
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Check the token sent with a POST form against the session. */
function csrf_verify(mixed $given): bool
{
    start_session();
    return csrf_matches($_SESSION['csrf_token'] ?? null, $given);
}

/** Constant-time comparison of the expected and the given token. */
function csrf_matches(mixed $expected, mixed $given): bool
{
    return is_string($expected) && $expected !== ''
        && is_string($given) && $given !== ''
        && hash_equals($expected, $given);
}

/**
 * The staff member doing the work.
 * TODO (login story): return the logged-in user instead of the first staff user.
 */
function current_staff_id(PDO $pdo): int
{
    $id = $pdo->query('SELECT id FROM staff_users ORDER BY id LIMIT 1')->fetchColumn();
    if ($id === false) {
        throw new RuntimeException('No staff user exists. Run sql/seed.sql first.');
    }
    return (int) $id;
}
