<?php
declare(strict_types=1);

// M4 + M6: fetch queue and fitting rooms. Staff only.

require_once __DIR__ . '/../../src/bootstrap.php';
require_login();

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

<?php require __DIR__ . '/../../templates/staff_board.php'; ?>

<?php if (is_array($flash)): ?>
    <?php
    // The shelf code to put an item back on, in bold.
    $flashHtml = e($flash['text']);
    if (!empty($flash['shelf'])) {
        $flashHtml = str_replace('shelf ' . e($flash['shelf']), 'shelf <strong>' . e($flash['shelf']) . '</strong>', $flashHtml);
    }
    $isInfo = $flash['type'] === 'info';
    ?>
    <!-- Result of the last tap, fixed to the bottom of the screen so it is always in view (staff.js). -->
    <div class="toast toast--<?= $isInfo ? 'info' : 'success' ?>" data-toast>
        <span class="toast__icon" aria-hidden="true"><?= $isInfo ? 'i' : '✓' ?></span>
        <p class="toast__text" id="staff-flash" role="status" aria-live="polite"><?= $flashHtml ?></p>
        <button type="button" class="toast__close" aria-label="Close message" data-toast-close>✕</button>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
