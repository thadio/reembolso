<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\TimeClockController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'timeclock.view');

$controller = new TimeClockController($pdo, $connectionError);
$controller->index();
