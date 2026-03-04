<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\CollectionController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'collections.edit' : 'collections.create');

$controller = new CollectionController($pdo, $connectionError);
$controller->form();
