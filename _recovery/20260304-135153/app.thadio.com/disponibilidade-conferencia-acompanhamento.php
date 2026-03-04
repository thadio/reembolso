<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\InventoryController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'inventory.monitor');

$controller = new InventoryController($pdo, $connectionError);
$controller->monitor();
