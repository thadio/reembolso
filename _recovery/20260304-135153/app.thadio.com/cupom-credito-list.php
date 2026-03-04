<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\VoucherAccountController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'voucher_accounts.view');

$controller = new VoucherAccountController($pdo, $connectionError);
$controller->index();
