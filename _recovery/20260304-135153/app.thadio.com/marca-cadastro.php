<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\BrandController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'brands.edit' : 'brands.create');

$controller = new BrandController($pdo, $connectionError);
$controller->form();
