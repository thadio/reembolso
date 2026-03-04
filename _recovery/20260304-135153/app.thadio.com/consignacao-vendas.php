<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ConsignmentModuleController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'consignment_module.view_sales');

$controller = new ConsignmentModuleController($pdo, $connectionError);
$controller->sales();
