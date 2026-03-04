<?php

use App\Repositories\ProductSupplyRepository;
use App\Support\Input;

require __DIR__ . '/bootstrap.php';

function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

[$pdo, $connectionError] = bootstrapPdo();
if (!$pdo) {
    respond(['status' => 'error', 'message' => 'Sem conexão com o banco.'], 500);
}
if ($connectionError) {
    respond(['status' => 'error', 'message' => 'Erro ao conectar ao banco: ' . $connectionError], 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['status' => 'error', 'message' => 'Método inválido.'], 405);
}

requirePermission($pdo, 'products.edit');

$body = file_get_contents('php://input');
$input = json_decode($body, true);
if (!is_array($input)) {
    $input = $_POST;
}

$productId = isset($input['product_id']) ? (int) $input['product_id'] : 0;
$cost = Input::parseNumber($input['cost'] ?? null);

if ($productId <= 0) {
    respond(['status' => 'error', 'message' => 'Produto inválido.'], 422);
}
if ($cost === null || $cost < 0) {
    respond(['status' => 'error', 'message' => 'Informe um custo válido (maior ou igual a zero).'], 422);
}

$supplyRepo = new ProductSupplyRepository($pdo);
$supply = $supplyRepo->findByProductId($productId);
if (!$supply) {
    respond(['status' => 'error', 'message' => 'Dados de fornecimento não encontrados.'], 404);
}
$source = strtolower(trim((string) ($supply['source'] ?? '')));
if ($source !== 'compra') {
    respond(['status' => 'error', 'message' => 'Somente produtos de compra podem ser atualizados aqui.'], 403);
}

try {
    $supplyRepo->upsert([
        'product_id' => $productId,
        'sku' => $supply['sku'] ?? null,
        'supplier_id_vendor' => isset($supply['supplier_id_vendor']) ? (int) $supply['supplier_id_vendor'] : null,
        'source' => $supply['source'] ?? null,
        'cost' => $cost,
        'percentual_consignacao' => $supply['percentual_consignacao'] ?? null,
        'lot_id' => isset($supply['lot_id']) ? (int) $supply['lot_id'] : null,
    ]);
} catch (\Throwable $e) {
    respond(['status' => 'error', 'message' => 'Falha ao atualizar o custo: ' . $e->getMessage()], 500);
}

respond(['status' => 'ok']);
