<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\PaymentTerminalController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'payment_terminals.edit' : 'payment_terminals.create');

$controller = new PaymentTerminalController($pdo, $connectionError);
$controller->form();
