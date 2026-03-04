<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ConsignmentModuleController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'consignment_module.export_reports');

$controller = new ConsignmentModuleController($pdo, $connectionError);
$controller->reportViews();
