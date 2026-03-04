<?php

require __DIR__ . '/bootstrap.php';

use App\Repositories\PieceLotRepository;
use App\Repositories\VendorRepository;

requireLogin();

header('Content-Type: application/json; charset=UTF-8');

if (!userCan('products.create') && !userCan('products.edit')) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'message' => 'Sem permissão para consultar lotes.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$supplierRaw = $_GET['supplier_pessoa_id'] ?? $_POST['supplier_pessoa_id'] ?? $_GET['supplier'] ?? $_POST['supplier'] ?? '';
$supplierPessoaId = (int) $supplierRaw;
$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
$lotId = isset($_POST['lot_id']) ? (int) $_POST['lot_id'] : 0;
$currentLotId = isset($_GET['current_lot_id']) ? (int) $_GET['current_lot_id'] : 0;

if ($supplierPessoaId <= 0 && $lotId <= 0) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'Fornecedor inválido.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

[$pdo, $connectionError] = bootstrapPdo();
if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $connectionError ?: 'Falha ao conectar ao banco.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$lots = new PieceLotRepository($pdo);

if ($lotId > 0 && $supplierPessoaId <= 0) {
    $existingLot = $lots->find($lotId);
    if ($existingLot) {
        $supplierPessoaId = (int) ($existingLot['supplier_pessoa_id'] ?? 0);
    }
}

$vendors = new VendorRepository($pdo);
$vendor = $supplierPessoaId > 0 ? $vendors->find($supplierPessoaId) : null;
if (!$vendor) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'message' => 'Fornecedor não encontrado.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$created = false;

if ($action !== '') {
    if ($action === 'close' || $action === 'close_and_create') {
        if ($lotId <= 0) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Selecione um lote para encerrar.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $targetLot = $lots->find($lotId);
        if (!$targetLot) {
            http_response_code(404);
            echo json_encode([
                'ok' => false,
                'message' => 'Lote não encontrado.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ((string) ($targetLot['status'] ?? '') !== 'fechado') {
            $lots->close($lotId);
        }
        $supplierPessoaId = (int) ($targetLot['supplier_pessoa_id'] ?? $supplierPessoaId);
    }

    if ($action === 'create' || $action === 'close_and_create') {
        $newLot = $lots->create($supplierPessoaId);
        $lotId = (int) ($newLot['id'] ?? 0);
        $created = true;
    }
}

$lotList = $lots->list(['supplier_pessoa_id' => $supplierPessoaId], 300);
$selectedLot = null;

if ($lotId > 0) {
    $selectedLot = $lots->find($lotId);
}

if (!$selectedLot && $currentLotId > 0) {
    foreach ($lotList as $row) {
        if ((int) ($row['id'] ?? 0) === $currentLotId) {
            $selectedLot = $row;
            break;
        }
    }
}

if (!$selectedLot) {
    $selectedLot = $lots->latestOpenBySupplier($supplierPessoaId);
}

if (!$selectedLot) {
    $selectedLot = $lots->create($supplierPessoaId);
    $created = true;
    $lotList = $lots->list(['supplier_pessoa_id' => $supplierPessoaId], 300);
}

echo json_encode([
    'ok' => true,
    'data' => [
        'id' => (int) ($selectedLot['id'] ?? 0),
        'supplier_pessoa_id' => (int) ($selectedLot['supplier_pessoa_id'] ?? $supplierPessoaId),
        'name' => (string) ($selectedLot['name'] ?? ''),
        'status' => (string) ($selectedLot['status'] ?? 'aberto'),
        'opened_at' => (string) ($selectedLot['opened_at'] ?? ''),
        'created' => $created,
    ],
    'lots' => array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'supplier_pessoa_id' => (int) ($row['supplier_pessoa_id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'opened_at' => (string) ($row['opened_at'] ?? ''),
            'closed_at' => (string) ($row['closed_at'] ?? ''),
        ];
    }, $lotList),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
