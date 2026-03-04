<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ConsignmentIntakeController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'consignments.view');

$controller = new ConsignmentIntakeController($pdo, $connectionError);
$controller->receiptTerm();
