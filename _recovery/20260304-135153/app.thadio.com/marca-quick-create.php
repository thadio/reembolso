<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\BrandController;

[$pdo, $connectionError] = bootstrapPdo();
$controller = new BrandController($pdo, $connectionError);
$controller->quickCreate();
