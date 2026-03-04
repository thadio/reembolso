<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\BankAccountController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'bank_accounts.edit' : 'bank_accounts.create');

$controller = new BankAccountController($pdo, $connectionError);
$controller->form();
