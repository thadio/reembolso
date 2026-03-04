<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\VoucherAccountController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
$isPayout = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payout_action']);
if ($isPayout) {
    requirePermission($pdo, 'voucher_accounts.payout');
} elseif ($editing && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requirePermission($pdo, 'voucher_accounts.view');
} else {
    requirePermission($pdo, $editing ? 'voucher_accounts.edit' : 'voucher_accounts.create');
}

$controller = new VoucherAccountController($pdo, $connectionError);
$controller->form();
