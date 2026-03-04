<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\VendorController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'vendors.report');

$controller = new VendorController($pdo, $connectionError);
$controller->report();
