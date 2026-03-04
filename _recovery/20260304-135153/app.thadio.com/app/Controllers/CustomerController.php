<?php

namespace App\Controllers;

use App\Support\Auth;
use PDO;

class CustomerController
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        $statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
        $redirect = 'pessoa-list.php?role=cliente';
        if ($statusFilter !== '') {
            $redirect .= '&status=' . urlencode($statusFilter);
        }

        Auth::requirePermission('people.view', $this->pdo);
        header('Location: ' . $redirect);
        exit;
    }

    public function form(): void
    {
        $editingId = isset($_GET['id']) ? (int) $_GET['id'] : ((isset($_POST['id']) && $_POST['id'] !== '') ? (int) $_POST['id'] : 0);
        $redirect = 'pessoa-cadastro.php?role=cliente';
        if ($editingId > 0) {
            $redirect .= '&id=' . $editingId;
        }

        Auth::requirePermission($editingId > 0 ? 'people.edit' : 'people.create', $this->pdo);
        header('Location: ' . $redirect);
        exit;
    }
}
