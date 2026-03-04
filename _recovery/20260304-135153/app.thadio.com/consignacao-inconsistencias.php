<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ConsignmentModuleController;

[$pdo, $connectionError] = bootstrapPdo();

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

$controller = new ConsignmentModuleController($pdo, $connectionError);

if ($action === 'backfill_review') {
    requirePermission($pdo, 'consignment_module.admin_override');
    $controller->backfillReconciliationReview();
} elseif ($action === 'backfill_action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission($pdo, 'consignment_module.admin_override');
    $controller->backfillReconciliationAction();
} elseif ($action === 'legacy') {
    requirePermission($pdo, 'consignment_module.admin_override');
    $controller->legacyReconciliation();
} elseif ($action === 'legacy_reconciliation_confirm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission($pdo, 'consignment_module.admin_override');
    $controller->legacyReconciliationConfirm();
} elseif ($action === 'reindex' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission($pdo, 'consignment_module.admin_override');
    $controller->reindex();
} elseif ($action === 'period_lock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission($pdo, 'consignment_module.admin_override');
    $controller->periodLock();
} elseif ($action === 'period_unlock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission($pdo, 'consignment_module.admin_override');
    $controller->periodUnlock();
} else {
    requirePermission($pdo, 'consignment_module.view_inconsistencies');
    $controller->inconsistencies();
}
