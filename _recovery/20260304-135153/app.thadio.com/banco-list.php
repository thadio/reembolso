<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\BankController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'banks.view');

$controller = new BankController($pdo, $connectionError);
$controller->index();
