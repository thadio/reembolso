<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\InventoryController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'inventory.view');

$controller = new InventoryController($pdo, $connectionError);
$controller->index();
