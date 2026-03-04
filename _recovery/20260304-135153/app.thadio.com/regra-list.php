<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\RuleController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'rules.view');

$controller = new RuleController($pdo, $connectionError);
$controller->index();
