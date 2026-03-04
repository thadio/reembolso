<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ProductController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'products.view');

$controller = new ProductController($pdo, $connectionError);
$controller->index();
