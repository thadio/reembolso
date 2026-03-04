<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ConsignmentModuleController;

[$pdo, $connectionError] = bootstrapPdo();

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

// Route sub-actions
if ($action === 'show') {
    requirePermission($pdo, 'consignment_module.view_payouts');
    $controller = new ConsignmentModuleController($pdo, $connectionError);
    $controller->payoutShow();
} elseif ($action === 'confirm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission($pdo, 'consignment_module.confirm_payout');
    $controller = new ConsignmentModuleController($pdo, $connectionError);
    $controller->payoutConfirm();
} elseif ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission($pdo, 'consignment_module.cancel_payout');
    $controller = new ConsignmentModuleController($pdo, $connectionError);
    $controller->payoutCancel();
} elseif ($action === 'receipt') {
    requirePermission($pdo, 'consignment_module.view_payouts');
    $controller = new ConsignmentModuleController($pdo, $connectionError);
    $controller->payoutReceipt();
} elseif ($action === 'preview') {
    $previewConfirmedRaw = strtolower(trim((string) ($_GET['edit_confirmed'] ?? $_POST['allow_confirmed_edit'] ?? '')));
    $previewConfirmed = in_array($previewConfirmedRaw, ['1', 'true', 'yes', 'on'], true);
    requirePermission($pdo, $previewConfirmed ? 'consignment_module.confirm_payout' : 'consignment_module.create_payout');
    $controller = new ConsignmentModuleController($pdo, $connectionError);
    $controller->payoutPreview();
} elseif ($action === 'preview_batch_export' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission($pdo, 'consignment_module.create_payout');
    $controller = new ConsignmentModuleController($pdo, $connectionError);
    $controller->payoutPreviewBatchExport();
} else {
    // Default: create/edit form
    $editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
    $allowConfirmedEditRaw = strtolower(trim((string) ($_GET['edit_confirmed'] ?? $_POST['allow_confirmed_edit'] ?? '')));
    $editingConfirmed = $editing && in_array($allowConfirmedEditRaw, ['1', 'true', 'yes', 'on'], true);
    $inlineConfirm = $_SERVER['REQUEST_METHOD'] === 'POST' && trim((string) ($_POST['submit_action'] ?? '')) === 'confirm';
    if ($inlineConfirm) {
        requirePermission($pdo, 'consignment_module.confirm_payout');
    }
    if ($editingConfirmed) {
        requirePermission($pdo, 'consignment_module.confirm_payout');
    } else {
        requirePermission($pdo, 'consignment_module.create_payout');
    }
    $controller = new ConsignmentModuleController($pdo, $connectionError);
    $controller->payoutForm('consignacao-pagamento-cadastro.php', false);
}
