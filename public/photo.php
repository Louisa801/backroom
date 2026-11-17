<?php
declare(strict_types=1);

// Serves an uploaded item photo from the database: /photo.php?id=12
// A photo never changes (a new upload gets a new id), so browsers may cache it for a year.

require_once __DIR__ . '/../src/bootstrap.php';

function photo_not_found(): never
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Photo not found.';
    exit;
}

$id = $_GET['id'] ?? '';
if (!is_string($id) || !ctype_digit($id) || strlen($id) > 9 || (int) $id < 1) {
    photo_not_found();
}

$etag = '"photo-' . (int) $id . '"';
$cacheHeaders = static function () use ($etag): void {
    header('Cache-Control: public, max-age=31536000, immutable');
    header('ETag: ' . $etag);
};

if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    $cacheHeaders();
    exit;
}

$photo = (new Photos(db()))->find((int) $id);
if ($photo === null) {
    photo_not_found();
}

header('Content-Type: ' . $photo['mime_type']);
header('Content-Length: ' . strlen($photo['data']));
header('X-Content-Type-Options: nosniff');
$cacheHeaders();
echo $photo['data'];
