<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\BagController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'bags.edit' : 'bags.create');

$controller = new BagController($pdo, $connectionError);
$controller->form();
