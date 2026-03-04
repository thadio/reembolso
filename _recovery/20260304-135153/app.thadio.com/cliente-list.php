<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\PersonController;

[$pdo, $connectionError] = bootstrapPdo();
$_GET['role'] = $_GET['role'] ?? 'cliente';
if (!userCan('people.view') && !userCan('customers.view')) {
    requirePermission($pdo, 'people.view');
}

$controller = new PersonController($pdo, $connectionError);
$controller->index();
