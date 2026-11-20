<?php
declare(strict_types=1);

// Log out: POST with CSRF only (a link or an image on another site can't log someone out).

require_once __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Use the "Log out" button.';
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'This page had expired. Go back and press "Log out" again.';
    exit;
}

logout();
start_session();
$_SESSION['login_notice'] = 'You are logged out.';
header('Location: ' . LOGIN_PATH, true, 303);
