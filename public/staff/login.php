<?php
declare(strict_types=1);

// Staff login (email + password). After logging in, back to the page you came from.

require_once __DIR__ . '/../../src/bootstrap.php';

start_session();

$return = safe_return_path($_POST['return'] ?? $_GET['return'] ?? null);

if (current_staff() !== null) {
    header('Location: ' . $return, true, 303);
    exit;
}

$notice = is_string($_SESSION['login_notice'] ?? null) ? $_SESSION['login_notice'] : null;
unset($_SESSION['login_notice']);
$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'This page had expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        http_response_code(400);
        $error = 'Please enter your email and password.';
    } else {
        $result = attempt_login(db(), $email, $password, new DateTimeImmutable());
        if ($result['ok']) {
            login_session($result['staff']);
            header('Location: ' . $return, true, 303);
            exit;
        }
        // Same status and message for an unknown email and a wrong password.
        http_response_code($result['error'] === LOGIN_LOCKED ? 429 : 401);
        $error = $result['error'];
    }
}

$pageTitle = 'Staff login';
$headerNote = 'Staff';
require __DIR__ . '/../../templates/header.php';
?>
<section class="login" aria-labelledby="login-title">
    <h1 class="page__title" id="login-title">Staff login</h1>
    <p class="page__intro">For the fetch queue, fitting rooms, buy-in and dashboard.</p>

    <?php if ($notice !== null): ?>
        <p class="notice notice--info" role="status"><?= e($notice) ?></p>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <p class="notice notice--error" role="alert" id="login-error"><?= e($error) ?></p>
    <?php endif; ?>

    <form class="login__form" method="post" action="/staff/login.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="return" value="<?= e($return) ?>">
        <div class="field">
            <label for="login-email">Email</label>
            <input type="email" id="login-email" name="email" value="<?= e($email) ?>" autocomplete="username" required
                   <?= $error !== null ? 'aria-describedby="login-error"' : '' ?>>
        </div>
        <div class="field">
            <label for="login-password">Password</label>
            <input type="password" id="login-password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="button button--large">Log in</button>
    </form>
    <p class="login__public">Customers don't need to log in: <a href="/">go to the archive</a>.</p>
</section>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
