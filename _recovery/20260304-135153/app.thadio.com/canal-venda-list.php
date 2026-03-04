<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\SalesChannelController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'sales_channels.view');

$controller = new SalesChannelController($pdo, $connectionError);
$controller->index();
