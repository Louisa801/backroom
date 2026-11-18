<?php
declare(strict_types=1);

// Helper for RequestsConcurrencyTest: creates a try-on request in its own process
// (its own database connection) and prints the result as JSON.
// Usage: php tests/support/request_worker.php <room> <item id> [<item id> ...]

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/Items.php';
require_once __DIR__ . '/../../src/Requests.php';

$room = (int) ($argv[1] ?? 0);
$ids = array_map('intval', array_slice($argv, 2));

try {
    echo json_encode((new Requests(db()))->create($ids, $room), JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}
