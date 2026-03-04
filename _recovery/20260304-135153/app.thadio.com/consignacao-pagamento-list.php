<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ConsignmentModuleController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'consignment_module.view_payouts');

$action = trim((string) ($_GET['action'] ?? ''));

$controller = new ConsignmentModuleController($pdo, $connectionError);

if ($action === 'show' && isset($_GET['id'])) {
    $controller->payoutShow();
} else {
    $controller->payoutList();
}
