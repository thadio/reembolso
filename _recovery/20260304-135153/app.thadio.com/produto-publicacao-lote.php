<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ProductController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'products.bulk_publish');

$controller = new ProductController($pdo, $connectionError);
$controller->bulkPublish();
