<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\BatchIntakeController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'products.batch_intake');

$controller = new BatchIntakeController($pdo, $connectionError);
$controller->form();
