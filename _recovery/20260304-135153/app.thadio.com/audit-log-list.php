<?php
/**
 * Listagem de Audit Logs
 */

require_once __DIR__ . '/bootstrap.php';

use App\Controllers\AuditController;
use App\Support\Auth;

// Autenticação obrigatória
Auth::requireLogin();

[$pdo] = bootstrapPdo();

// Verificar permissão
requirePermission($pdo, 'auditoria.view');

$controller = new AuditController($pdo);
$controller->list();
