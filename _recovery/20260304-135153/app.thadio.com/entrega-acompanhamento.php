<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\DeliveryTrackingController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'orders.view');

$controller = new DeliveryTrackingController($pdo, $connectionError);
$controller->index();
