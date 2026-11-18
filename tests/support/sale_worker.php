<?php
declare(strict_types=1);

// Helper for SalesConcurrencyTest: presses "Sold" in its own process (its own database
// connection) and prints the result as JSON.
// Usage: php tests/support/sale_worker.php <request id> <item id>

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/Items.php';
require_once __DIR__ . '/../../src/Requests.php';
require_once __DIR__ . '/../../src/Sales.php';

try {
    $result = (new Sales(db()))->sell((int) ($argv[1] ?? 0), (int) ($argv[2] ?? 0));
    echo json_encode(['sold' => true] + $result, JSON_THROW_ON_ERROR);
} catch (TransitionRefusedException $e) {
    echo json_encode(['sold' => false, 'refused' => $e->getMessage()]);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}
