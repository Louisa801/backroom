<?php
declare(strict_types=1);

// The staff board without the page around it, for the 10-second refresh in staff.js.

require_once __DIR__ . '/../../src/bootstrap.php';

$requests = new Requests(db());
$csrf = csrf_token();
$queue = $requests->queue();
$rooms = $requests->fittingRooms();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/../../templates/staff_board.php';
