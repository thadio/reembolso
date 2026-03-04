<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\OrderReturnController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'order_returns.view');

$controller = new OrderReturnController($pdo, $connectionError);
$controller->index();
