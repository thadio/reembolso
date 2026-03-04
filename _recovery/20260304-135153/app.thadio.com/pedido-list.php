<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\OrderController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'orders.view');

$controller = new OrderController($pdo, $connectionError);
$controller->index();
