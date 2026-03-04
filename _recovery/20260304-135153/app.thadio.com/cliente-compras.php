<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\CustomerPurchaseController;

[$pdo, $connectionError] = bootstrapPdo();

if (!userCan('customers.view') && !userCan('orders.view')) {
    requirePermission($pdo, 'customers.view');
}

$controller = new CustomerPurchaseController($pdo, $connectionError);
$controller->index();
