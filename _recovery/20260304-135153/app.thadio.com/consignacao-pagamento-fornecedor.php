<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ConsignmentModuleController;

[$pdo, $connectionError] = bootstrapPdo();

$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
$inlineConfirm = $_SERVER['REQUEST_METHOD'] === 'POST' && trim((string) ($_POST['submit_action'] ?? '')) === 'confirm';

if ($inlineConfirm) {
    requirePermission($pdo, 'consignment_module.confirm_payout');
}

requirePermission($pdo, $editing ? 'consignment_module.create_payout' : 'consignment_module.create_payout');

$controller = new ConsignmentModuleController($pdo, $connectionError);
$controller->payoutForm('consignacao-pagamento-fornecedor.php', true);
