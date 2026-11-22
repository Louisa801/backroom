<?php
declare(strict_types=1);

// Login, session and CSRF for the staff and manager screens. The customer tablet needs no login.

const LOGIN_MAX_FAILURES = 5;          // failed attempts per email ...
const LOGIN_LOCK_SECONDS = 60;         // ... then wait this long
const SESSION_IDLE_SECONDS = 8 * 3600; // log out after 8 hours without activity
const LOGIN_PATH = '/staff/login.php';
const LOGIN_FAILED = 'Email or password is incorrect.';
const LOGIN_LOCKED = 'Too many attempts. Please wait a minute and try again.';

/** Hash of a random string: unknown emails still run password_verify, so they take as long as a wrong password. */
const DUMMY_PASSWORD_HASH = '$2y$10$wXWNx/3tlEyO.wNrd/sC.ugQxLHFJ.OrKqxG9hQuaPu7ECWvf6pVO';

/**
 * True when the browser talks HTTPS to us. On Render, HTTPS ends at Render's proxy, which
 * forwards the request over plain HTTP with "X-Forwarded-Proto: https" (possibly a list
 * like "https, http"; the first value is the browser's).
 */
function request_is_https(array $server): bool
{
    if (!empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off') {
        return true;
    }
    $forwarded = (string) ($server['HTTP_X_FORWARDED_PROTO'] ?? '');
    return strtolower(trim(explode(',', $forwarded)[0])) === 'https';
}

/** Start the session with safe cookie settings (once per request). */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = request_is_https($_SERVER); // then the cookie gets the Secure flag

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    // PHP's default (24 minutes) would end sessions long before the 8-hour idle limit.
    ini_set('session.gc_maxlifetime', (string) SESSION_IDLE_SECONDS);
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

// ----------------------------------------------------------------------
// Logging in
// ----------------------------------------------------------------------

/**
 * Check an email and password. No session involved, so it can be tested with a fixed "now".
 * Unknown email and wrong password give the same message. After 5 failures for an email,
 * every attempt for it is refused (even with the right password) until a minute has passed.
 *
 * @return array{ok: bool, staff?: array{id: int, name: string, email: string}, error?: string}
 */
function attempt_login(PDO $pdo, string $email, string $password, DateTimeImmutable $now): array
{
    $email = strtolower(trim($email));
    $at = $now->format(DATE_ATOM);

    $pdo->prepare("DELETE FROM login_failures WHERE failed_at < CAST(:at AS timestamptz) - interval '1 day'")
        ->execute(['at' => $at]);

    $stmt = $pdo->prepare('SELECT count(*) AS failures, max(failed_at) AS latest FROM login_failures WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $failures = $stmt->fetch(PDO::FETCH_ASSOC);
    if ((int) $failures['failures'] >= LOGIN_MAX_FAILURES && $failures['latest'] !== null) {
        $unlocksAt = (new DateTimeImmutable($failures['latest']))->modify('+' . LOGIN_LOCK_SECONDS . ' seconds');
        if ($now < $unlocksAt) {
            return ['ok' => false, 'error' => LOGIN_LOCKED];
        }
        // The minute has passed: start counting again.
        $pdo->prepare('DELETE FROM login_failures WHERE email = :email')->execute(['email' => $email]);
    }

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM staff_users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    $valid = password_verify($password, $staff === false ? DUMMY_PASSWORD_HASH : $staff['password_hash']);
    if ($staff === false || !$valid) {
        $pdo->prepare('INSERT INTO login_failures (email, failed_at) VALUES (:email, CAST(:at AS timestamptz))')
            ->execute(['email' => $email, 'at' => $at]);
        return ['ok' => false, 'error' => LOGIN_FAILED];
    }

    $pdo->prepare('DELETE FROM login_failures WHERE email = :email')->execute(['email' => $email]);
    return ['ok' => true, 'staff' => ['id' => (int) $staff['id'], 'name' => $staff['name'], 'email' => $staff['email']]];
}

/** Start the logged-in session: new session id (against session fixation), new CSRF token. */
function login_session(array $staff): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION = [
        'staff'      => ['id' => (int) $staff['id'], 'name' => (string) $staff['name'], 'email' => (string) $staff['email']],
        'last_seen'  => time(),
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
}

/** End the session completely. */
function logout(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $params['path'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    }
    session_destroy();
}

/** True when the last activity is more than 8 hours ago (or unknown). */
function session_expired(?int $lastSeen, int $now): bool
{
    return $lastSeen === null || $now - $lastSeen > SESSION_IDLE_SECONDS;
}

/**
 * Where to go after logging in. Only local paths under /staff/ or /manager/ are allowed,
 * so the login page can't be used to send someone to another site (no open redirect).
 */
function safe_return_path(mixed $path): string
{
    $default = '/staff/';
    if (!is_string($path) || $path === '' || strlen($path) > 500) {
        return $default;
    }
    if (preg_match('/[\x00-\x1F\x7F\\\\]/', $path)) {
        return $default; // control characters or backslashes (browsers read "/\" as "//")
    }
    if (!preg_match('#^/(staff|manager)(/|$|\?)#', $path)) {
        return $default; // also refuses "//evil.com", "https://...", "javascript:..."
    }
    if (str_contains($path, '/..') || str_contains($path, '//')) {
        return $default;
    }
    return $path;
}

// ----------------------------------------------------------------------
// Protecting pages
// ----------------------------------------------------------------------

/** The logged-in staff member, or null. Also ends a session that was idle for 8 hours. */
function current_staff(): ?array
{
    start_session();
    $staff = $_SESSION['staff'] ?? null;
    if (!is_array($staff)) {
        return null;
    }
    if (session_expired(isset($_SESSION['last_seen']) ? (int) $_SESSION['last_seen'] : null, time())) {
        logout();
        start_session();
        $_SESSION['login_notice'] = 'You were logged out after 8 hours without activity.';
        return null;
    }
    $_SESSION['last_seen'] = time();
    return $staff;
}

/** The id of the logged-in staff member (buy-ins record it). Only call on a protected page. */
function current_staff_id(): int
{
    $staff = current_staff();
    if ($staff === null) {
        throw new RuntimeException('Nobody is logged in.');
    }
    return (int) $staff['id'];
}

/** Top of every staff and manager page: not logged in -> to the login page, and back afterwards. */
function require_login(): array
{
    $staff = current_staff();
    if ($staff !== null) {
        return $staff;
    }
    // After a form POST, going "back" to the form target makes no sense: return to the board.
    $return = $_SERVER['REQUEST_METHOD'] === 'GET' ? safe_return_path($_SERVER['REQUEST_URI'] ?? '') : '/staff/';
    header('Location: ' . LOGIN_PATH . '?return=' . rawurlencode($return), true, 303);
    header('Cache-Control: no-store');
    exit;
}

/** Top of every staff endpoint that JavaScript fetches: not logged in -> 401. */
function require_login_api(): array
{
    $staff = current_staff();
    if ($staff !== null) {
        return $staff;
    }
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Please log in again.';
    exit;
}
