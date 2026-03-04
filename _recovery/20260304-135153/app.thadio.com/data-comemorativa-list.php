<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\CommemorativeDateController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'holidays.view');

$controller = new CommemorativeDateController($pdo, $connectionError);
$controller->index();
