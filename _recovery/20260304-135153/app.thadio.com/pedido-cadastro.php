<?php

require __DIR__ . '/bootstrap.php';

use App\Controllers\OrderController;

[$pdo, $connectionError] = bootstrapPdo();

// ------------------------------------------------------------------
// FIX: Ações AJAX de sub-recursos (criar cliente, atualizar endereço,
// abrir sacola) possuem verificação de permissão própria dentro do
// controller.  Antes, o guard genérico exigia `orders.create` porque
// o POST não contém `id`, fazendo $editing=false.  Isso bloqueava
// usuários que estão apenas editando/visualizando um pedido e tentam
// criar um novo cliente via modal.
// ------------------------------------------------------------------
$inlineActions = ['create_customer', 'update_customer_address', 'ensure_open_bag'];
$postAction    = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : '';

if (in_array($postAction, $inlineActions, true)) {
    // Para ações inline, basta que o usuário tenha QUALQUER permissão
    // de pedidos (view, edit ou create).  A autorização granular do
    // sub-recurso é feita dentro de handleCustomerCreate() etc.
    if (!userCan('orders.create') && !userCan('orders.edit') && !userCan('orders.view')) {
        // Responde JSON para que o fetch() do frontend consiga parsear.
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => false,
            'message' => 'Sem permissão para acessar pedidos.',
        ]);
        exit;
    }
} else {
    $editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
    requirePermission($pdo, $editing ? 'orders.view' : 'orders.create');
}

$controller = new OrderController($pdo, $connectionError);
$controller->form();
