<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_response(['error' => 'Only GET is allowed here.'], 405);
}

try {
    $filters = Items::normaliseFilters($_GET);
} catch (InvalidArgumentException $e) {
    json_response(['error' => $e->getMessage()], 400);
}

$items = (new Items(db()))->filterArchive($filters);

json_response(['count' => count($items), 'items' => $items]);
