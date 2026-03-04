<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\VoucherIdentificationPatternController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'voucher_identification_patterns.view');

$controller = new VoucherIdentificationPatternController($pdo, $connectionError);
$controller->index();
