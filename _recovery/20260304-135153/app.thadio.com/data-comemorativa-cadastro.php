<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\CommemorativeDateController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'holidays.edit' : 'holidays.create');

$controller = new CommemorativeDateController($pdo, $connectionError);
$controller->form();
