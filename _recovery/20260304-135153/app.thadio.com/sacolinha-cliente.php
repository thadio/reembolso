<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\BagController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'bags.view');

$controller = new BagController($pdo, $connectionError);
$controller->customerHistory();
