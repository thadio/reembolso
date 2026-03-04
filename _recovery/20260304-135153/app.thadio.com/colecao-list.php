<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\CollectionController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'collections.view');

$controller = new CollectionController($pdo, $connectionError);
$controller->index();
