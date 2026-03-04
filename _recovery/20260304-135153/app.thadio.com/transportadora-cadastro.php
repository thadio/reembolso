<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\CarrierController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'carriers.edit' : 'carriers.create');

$controller = new CarrierController($pdo, $connectionError);
$controller->form();
