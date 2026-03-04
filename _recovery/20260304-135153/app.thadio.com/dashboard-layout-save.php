<?php

require __DIR__ . '/bootstrap.php';

use App\Repositories\DashboardLayoutRepository;

[$pdo] = bootstrapPdo();
requirePermission($pdo, 'dashboard.layout_customize');
$currentUser = currentUser();
$userId = $currentUser['id'] ?? null;

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
    return;
}

$body = file_get_contents('php://input');
$payload = json_decode($body ?: '', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Corpo inválido.']);
    return;
}

$columns = isset($payload['columns']) ? (int) $payload['columns'] : 3;
$columns = max(1, min(6, $columns));
$itemsRaw = is_array($payload['items']) ? $payload['items'] : [];
$validatedItems = [];
$maxRows = 6;
foreach ($itemsRaw as $item) {
    if (!is_array($item)) {
        continue;
    }
    $id = trim((string) ($item['id'] ?? ''));
    if ($id === '') {
        continue;
    }
    $span = isset($item['span']) ? (int) $item['span'] : 1;
    $span = max(1, min($columns, $span));
    $rows = isset($item['rows']) ? (int) $item['rows'] : 1;
    $rows = max(1, min($maxRows, $rows));
    $validatedItems[] = ['id' => $id, 'span' => $span, 'rows' => $rows];
}

if (!$pdo || !$userId) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Usuário inválido ou sem conexão.']);
    return;
}

$repo = new DashboardLayoutRepository($pdo);
$repo->saveForUser((int) $userId, ['columns' => $columns, 'items' => $validatedItems]);

echo json_encode(['status' => 'ok']);
