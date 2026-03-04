<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\PersonController;

[$pdo, $connectionError] = bootstrapPdo();

if (!userCan('people.view') && !userCan('customers.view') && !userCan('vendors.view')) {
    requirePermission($pdo, 'people.view');
}

$controller = new PersonController($pdo, $connectionError);
$controller->index();
