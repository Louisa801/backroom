<?php
declare(strict_types=1);

// M4 + M6: fetch queue and fitting rooms. (Login is added in the login story.)

require_once __DIR__ . '/../../src/bootstrap.php';

$requests = new Requests(db());
$csrf = csrf_token();
$queue = $requests->queue();
$rooms = $requests->fittingRooms();

// Message from the last button press (set by action.php), shown once.
$flash = $_SESSION['staff_flash'] ?? null;
unset($_SESSION['staff_flash']);

$pageTitle = 'Fetch queue & fitting rooms';
$headerNote = 'Staff';
$staffNav = 'board';
$scripts = ['staff.js'];
require __DIR__ . '/../../templates/header.php';
?>
<h1 class="visually-hidden">Fetch queue and fitting rooms</h1>

<?php if (is_array($flash)): ?>
    <p class="notice notice--<?= $flash['type'] === 'info' ? 'info' : 'success' ?>" role="status" id="staff-flash"><?= e($flash['text']) ?></p>
<?php endif; ?>

<?php require __DIR__ . '/../../templates/staff_board.php'; ?>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
