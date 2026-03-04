<?php

/**
 * Entry point - Lista de consignações de produtos.
 */

require __DIR__ . '/bootstrap.php';

use App\Controllers\ConsignmentController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'consignments.view');

$controller = new ConsignmentController($pdo);
$controller->index();
