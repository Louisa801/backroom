<?php
declare(strict_types=1);

// Every button on the staff board posts here: one status change, then back to the board.

require_once __DIR__ . '/../../src/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /staff/', true, 303);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    $pageTitle = 'Expired';
    $headerNote = 'Staff';
    require __DIR__ . '/../../templates/header.php';
    echo '<h1 class="page__title">Nothing changed</h1>'
        . '<p class="page__intro">This page had expired. Go back to the board and press the button again.</p>'
        . '<a class="button button--large" href="/staff/">Back to the board</a>';
    require __DIR__ . '/../../templates/footer.php';
    exit;
}

$id = static function (string $key): int {
    $value = $_POST[$key] ?? '';
    return is_string($value) && ctype_digit($value) && strlen($value) <= 9 ? (int) $value : 0;
};
$requestId = $id('request_id');
$itemId = $id('item_id');
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

$pdo = db();
$anchor = $action === 'advance' ? '#queue' : '#rooms'; // also where a refused tap lands

try {
    switch ($action) {
        case 'advance':
            $result = (new Requests($pdo))->advance($requestId, is_string($_POST['from'] ?? null) ? $_POST['from'] : '');
            $text = $result['status'] === 'fetching'
                ? "Request #$requestId: fetching started."
                : "Request #$requestId is ready in fitting room {$result['fitting_room']}.";
            $anchor = $result['status'] === 'ready' ? '#room-' . $result['fitting_room'] : '#queue';
            break;

        case 'sell':
            $result = (new Sales($pdo))->sell($requestId, $itemId);
            $text = "Sold: {$result['title']} (" . format_price($result['price']) . ').';
            $anchor = '#room-' . $result['fitting_room'];
            break;

        case 'return':
            $result = (new Requests($pdo))->returnItem($requestId, $itemId);
            $text = "{$result['title']} is back in the archive. Put it on shelf {$result['shelf_code']}.";
            $anchor = '#room-' . $result['fitting_room'];
            break;

        default:
            throw new TransitionRefusedException('Nothing changed: unknown button.');
    }

    if (!empty($result['closed'])) {
        $text .= " Request #$requestId is closed.";
    }
    $_SESSION['staff_flash'] = ['type' => 'success', 'text' => $text];
} catch (TransitionRefusedException $e) {
    $_SESSION['staff_flash'] = ['type' => 'info', 'text' => $e->getMessage()];
}

header('Location: /staff/' . $anchor, true, 303);
