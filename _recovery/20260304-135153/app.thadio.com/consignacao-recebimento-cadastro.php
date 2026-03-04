<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ConsignmentIntakeController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'consignments.edit' : 'consignments.create');

$controller = new ConsignmentIntakeController($pdo, $connectionError);
$controller->form();
