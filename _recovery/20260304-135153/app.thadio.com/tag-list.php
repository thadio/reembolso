<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\TagController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'products.view');

$controller = new TagController($pdo, $connectionError);
$controller->index();

