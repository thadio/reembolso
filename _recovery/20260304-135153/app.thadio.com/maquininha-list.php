<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\PaymentTerminalController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'payment_terminals.view');

$controller = new PaymentTerminalController($pdo, $connectionError);
$controller->index();
