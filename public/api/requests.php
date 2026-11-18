<?php
declare(strict_types=1);

// Try-on requests from the customer tablet.
//   POST /api/requests.php   body {"items": [ids], "fitting_room": 1-3}, header X-CSRF-Token
//   GET  /api/requests.php?id=12   status for polling

require_once __DIR__ . '/../../src/bootstrap.php';

$requests = new Requests(db());

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['id'] ?? '';
    if (!is_string($id) || !ctype_digit($id) || strlen($id) > 9) {
        json_response(['error' => 'We couldn\'t find this request.'], 404);
    }
    $status = $requests->status((int) $id);
    if ($status === null) {
        json_response(['error' => 'We couldn\'t find this request.'], 404);
    }
    json_response($status);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    json_response(['error' => 'Only GET and POST are allowed here.'], 405);
}

if (!csrf_verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    json_response(['error' => 'This page has expired. Please reload the page and try again.'], 403);
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    json_response(['error' => 'We couldn\'t read your request. Please try again.'], 400);
}

try {
    $result = $requests->create($body['items'] ?? null, $body['fitting_room'] ?? null);
} catch (InvalidArgumentException $e) {
    json_response(['error' => $e->getMessage()], 400);
}

if ($result['request_id'] === null) {
    json_response([
        'error' => 'Sorry, ' . (count($result['taken']) === 1 ? 'this item was' : 'these items were')
            . ' just taken by someone else: ' . Requests::describeTaken($result['taken']) . '.',
        'taken' => $result['taken'],
    ], 409);
}

// The status page shows once which items were taken in the meantime.
if ($result['taken'] !== []) {
    $_SESSION['request_taken'][$result['request_id']] = Requests::describeTaken($result['taken']);
}

json_response($result, 201);
