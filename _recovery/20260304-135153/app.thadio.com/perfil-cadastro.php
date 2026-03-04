<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ProfileController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'profiles.edit' : 'profiles.create');

$controller = new ProfileController($pdo, $connectionError);
$controller->form();
