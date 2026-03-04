<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ConsignmentModuleController;

[$pdo, $connectionError] = bootstrapPdo();
requireLogin($pdo);

// Compatibilidade com perfis legados que ainda têm apenas consignments.view.
if (!userCan('consignment_module.view_products') && !userCan('consignments.view')) {
    requirePermission($pdo, 'consignment_module.view_products');
}

$controller = new ConsignmentModuleController($pdo, $connectionError);
$controller->products();
