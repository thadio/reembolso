<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\FinanceCategoryController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'finance_categories.view');

$controller = new FinanceCategoryController($pdo, $connectionError);
$controller->index();
