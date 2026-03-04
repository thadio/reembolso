<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\PieceLotController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'products.batch_intake');

$controller = new PieceLotController($pdo, $connectionError);
$controller->index();
