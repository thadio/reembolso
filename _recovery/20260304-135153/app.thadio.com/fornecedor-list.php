<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\PersonController;

[$pdo, $connectionError] = bootstrapPdo();
$_GET['role'] = $_GET['role'] ?? 'fornecedor';
if (!userCan('people.view') && !userCan('vendors.view')) {
    requirePermission($pdo, 'people.view');
}

$controller = new PersonController($pdo, $connectionError);
$controller->index();
