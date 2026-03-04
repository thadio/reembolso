<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\BankAccountController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'bank_accounts.view');

$controller = new BankAccountController($pdo, $connectionError);
$controller->index();
