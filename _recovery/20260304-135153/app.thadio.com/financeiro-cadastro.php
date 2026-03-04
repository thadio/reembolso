<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\FinanceController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'finance_entries.edit' : 'finance_entries.create');

$controller = new FinanceController($pdo, $connectionError);
$controller->form();
