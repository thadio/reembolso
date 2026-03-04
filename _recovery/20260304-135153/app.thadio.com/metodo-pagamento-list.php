<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\PaymentMethodController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'payment_methods.view');

$controller = new PaymentMethodController($pdo, $connectionError);
$controller->index();
