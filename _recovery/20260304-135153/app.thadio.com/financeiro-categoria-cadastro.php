<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\FinanceCategoryController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'finance_categories.edit' : 'finance_categories.create');

$controller = new FinanceCategoryController($pdo, $connectionError);
$controller->form();
