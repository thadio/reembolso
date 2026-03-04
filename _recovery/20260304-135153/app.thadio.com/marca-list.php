<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\BrandController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'brands.view');

$controller = new BrandController($pdo, $connectionError);
$controller->index();
