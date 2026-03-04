<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\SalesChannelController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'sales_channels.edit' : 'sales_channels.create');

$controller = new SalesChannelController($pdo, $connectionError);
$controller->form();
