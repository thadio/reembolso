<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\UserController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'users.view');

$controller = new UserController($pdo, $connectionError);
$controller->index();
