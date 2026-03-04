<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\VoucherIdentificationPatternController;

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'voucher_identification_patterns.edit' : 'voucher_identification_patterns.create');

$controller = new VoucherIdentificationPatternController($pdo, $connectionError);
$controller->form();
