<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\CarrierController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'carriers.view');

$controller = new CarrierController($pdo, $connectionError);
$controller->index();
