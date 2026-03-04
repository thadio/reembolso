<?php
/**
 * Visualização de Detalhes de um Audit Log
 */

require_once __DIR__ . '/bootstrap.php';

use App\Controllers\AuditController;
use App\Support\Auth;

// Autenticação obrigatória
Auth::requireLogin();

[$pdo] = bootstrapPdo();

// Verificar permissão
requirePermission($pdo, 'auditoria.view_details');

$controller = new AuditController($pdo);
$controller->view();
