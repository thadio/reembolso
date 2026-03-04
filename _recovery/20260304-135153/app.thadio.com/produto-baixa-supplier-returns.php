<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ProductWriteOffController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'products.writeoff');

$controller = new ProductWriteOffController($pdo, $connectionError);
$controller->supplierReturns();
