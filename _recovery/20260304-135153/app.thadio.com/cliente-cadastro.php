<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\PersonController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
$_GET['role'] = $_GET['role'] ?? 'cliente';
if ($editing) {
    if (!userCan('people.edit') && !userCan('customers.edit')) {
        requirePermission($pdo, 'people.edit');
    }
} else {
    if (!userCan('people.create') && !userCan('customers.create')) {
        requirePermission($pdo, 'people.create');
    }
}

$controller = new PersonController($pdo, $connectionError);
$controller->form();
