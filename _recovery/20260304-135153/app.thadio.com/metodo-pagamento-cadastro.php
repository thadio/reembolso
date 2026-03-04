<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\PaymentMethodController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'payment_methods.edit' : 'payment_methods.create');

$controller = new PaymentMethodController($pdo, $connectionError);
$controller->form();
