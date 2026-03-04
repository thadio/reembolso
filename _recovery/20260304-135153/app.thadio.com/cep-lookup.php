<?php

require __DIR__ . '/bootstrap.php';

use App\Integrations\CepDatabase;
use App\Repositories\CepRepository;

requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$cepRaw = $_GET['cep'] ?? $_POST['cep'] ?? '';
$cep = preg_replace('/\D/', '', (string) $cepRaw);

if ($cep === '' || strlen($cep) !== 8) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => 'CEP inválido.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

[$pdo, $error] = CepDatabase::bootstrap();
if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $error ?: 'Falha ao conectar ao banco de CEP.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$repo = new CepRepository($pdo);
$data = $repo->findByCep($cep);

if (!$data) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'message' => 'CEP não encontrado.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'ok' => true,
    'data' => $data,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
