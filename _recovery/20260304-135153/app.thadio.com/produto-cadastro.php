<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ProductController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'products.edit' : 'products.create');

$controller = new ProductController($pdo, $connectionError);
$controller->form();
