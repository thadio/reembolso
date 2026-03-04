<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\TimeClockController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'timeclock.report');

$controller = new TimeClockController($pdo, $connectionError);
$controller->report();
