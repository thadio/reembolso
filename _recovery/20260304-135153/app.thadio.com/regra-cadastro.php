<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\RuleController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'rules.edit' : 'rules.create');

$controller = new RuleController($pdo, $connectionError);
$controller->form();
