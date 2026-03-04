<?php

/**
 * Entry point - Cadastro/Edição de consignações de produtos.
 */

require __DIR__ . '/bootstrap.php';

use App\Controllers\ConsignmentController;

[$pdo, $connectionError] = bootstrapPdo();

$controller = new ConsignmentController($pdo);
$action = $_GET['action'] ?? ($_POST['action'] ?? 'create');
$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'delete') {
        requirePermission($pdo, 'consignments.delete');
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID não fornecido.';
            header('Location: /consignacao-produto-list.php');
            exit;
        }
        $controller->destroy($id);
        exit;
    }

    if ($action === 'close') {
        requirePermission($pdo, 'consignments.close');
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID não fornecido.';
            header('Location: /consignacao-produto-list.php');
            exit;
        }
        $controller->close($id);
        exit;
    }

    if ($id > 0) {
        requirePermission($pdo, 'consignments.edit');
        $controller->update($id);
        exit;
    }

    requirePermission($pdo, 'consignments.create');
    $controller->store();
    exit;
}

match ($action) {
    'show' => (function () use ($controller, $pdo, $id): void {
        requirePermission($pdo, 'consignments.view');
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID não fornecido.';
            header('Location: /consignacao-produto-list.php');
            exit;
        }
        $controller->show($id);
    })(),
    'edit' => (function () use ($controller, $pdo, $id): void {
        requirePermission($pdo, 'consignments.edit');
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID não fornecido.';
            header('Location: /consignacao-produto-list.php');
            exit;
        }
        $controller->edit($id);
    })(),
    'create' => (function () use ($controller, $pdo): void {
        requirePermission($pdo, 'consignments.create');
        $controller->create();
    })(),
    'store' => (function () use ($controller, $pdo): void {
        requirePermission($pdo, 'consignments.create');
        $controller->store();
    })(),
    'update' => (function () use ($controller, $pdo, $id): void {
        requirePermission($pdo, 'consignments.edit');
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID não fornecido.';
            header('Location: /consignacao-produto-list.php');
            exit;
        }
        $controller->update($id);
    })(),
    default => (function (): void {
        $_SESSION['flash_error'] = 'Ação inválida.';
        header('Location: /consignacao-produto-list.php');
        exit;
    })(),
};
