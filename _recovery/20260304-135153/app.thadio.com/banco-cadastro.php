<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\BankController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'banks.edit' : 'banks.create');

$controller = new BankController($pdo, $connectionError);
$controller->form();
