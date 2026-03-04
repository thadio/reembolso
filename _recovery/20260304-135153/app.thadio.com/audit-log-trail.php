<?php
/**
 * Histórico de Alterações de um Registro Específico
 */

require_once __DIR__ . '/bootstrap.php';

use App\Controllers\AuditController;
use App\Support\Auth;

// Autenticação obrigatória
Auth::requireLogin();

[$pdo] = bootstrapPdo();

// Verificar permissão
requirePermission($pdo, 'auditoria.view_trail');

$controller = new AuditController($pdo);
$controller->trail();
