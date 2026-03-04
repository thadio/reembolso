<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\ProfileController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'profiles.view');

$controller = new ProfileController($pdo, $connectionError);
$controller->index();
