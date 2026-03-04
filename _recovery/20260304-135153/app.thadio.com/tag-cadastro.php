<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\TagController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'products.edit');

$controller = new TagController($pdo, $connectionError);
$controller->form();

