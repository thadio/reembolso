<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\FinanceController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'finance_entries.view');

$controller = new FinanceController($pdo, $connectionError);
$controller->index();
