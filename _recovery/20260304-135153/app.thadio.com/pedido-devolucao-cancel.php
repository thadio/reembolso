<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\OrderReturnController;

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'order_returns.edit');

$returnId = isset($_POST['return_id']) ? (int) $_POST['return_id'] : 0;
if ($returnId <= 0) {
    $err = rawurlencode('ID da devolução inválido.');
    header('Location: pedido-devolucao-cadastro.php?id=' . $returnId . '&error=' . $err);
    exit;
}

$controller = new OrderReturnController($pdo, $connectionError);
list($messages, $errors) = $controller->cancel($returnId);

if (!empty($errors)) {
    $err = rawurlencode(implode(' ', $errors));
    header('Location: pedido-devolucao-cadastro.php?id=' . $returnId . '&error=' . $err);
    exit;
}
$succ = '';
if (!empty($messages)) {
    $succ = rawurlencode(implode(' ', $messages));
}
header('Location: pedido-devolucao-cadastro.php?id=' . $returnId . '&success=' . $succ);
exit;
