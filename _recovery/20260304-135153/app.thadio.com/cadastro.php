<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\UserController;

[$pdo, $connectionError] = bootstrapPdo();

$controller = new UserController($pdo, $connectionError);
$controller->form(true);
