<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Items.php';
require_once __DIR__ . '/../src/Photos.php';
require_once __DIR__ . '/../src/Intake.php';
require_once __DIR__ . '/../src/Requests.php';
require_once __DIR__ . '/../src/Sales.php';
require_once __DIR__ . '/../src/Dashboard.php';
require_once __DIR__ . '/support/RequestFixtures.php';

/** Any existing staff user, for fixtures that need one (the seed has one). */
function test_staff_id(PDO $pdo): int
{
    $id = $pdo->query('SELECT min(id) FROM staff_users')->fetchColumn();
    if ($id === null || $id === false) {
        throw new RuntimeException('No staff user exists. Run sql/seed.sql first.');
    }
    return (int) $id;
}
