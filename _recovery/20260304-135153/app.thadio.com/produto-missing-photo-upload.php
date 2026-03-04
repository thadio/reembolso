<?php

require __DIR__ . '/bootstrap.php';

use App\Services\ProductImageService;
use App\Repositories\ProductRepository;

[$pdo, $connectionError] = bootstrapPdo();
if ($connectionError) {
  http_response_code(503);
  echo json_encode(['status' => 'error', 'message' => 'Erro ao conectar ao banco.']);
  exit;
}

requirePermission($pdo, 'products.edit');
header('Content-Type: application/json');

$productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
if ($productId <= 0) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Produto inválido para inserir imagem.']);
  exit;
}

$imageService = new ProductImageService();
$productRepo = new ProductRepository($pdo);
$errors = [];
$warnings = [];
$prepared = $imageService->prepareUploads($_FILES['images'] ?? [], $errors);
if (empty($prepared) && empty($errors)) {
  $errors[] = 'Selecione ao menos uma imagem.';
}

if (!empty($errors)) {
  http_response_code(422);
  echo json_encode(['status' => 'error', 'message' => implode(' ', $errors)]);
  exit;
}

$uploadedImages = $imageService->storePrepared($prepared, $errors);
if (!empty($errors)) {
  http_response_code(422);
  echo json_encode(['status' => 'error', 'message' => implode(' ', $errors)]);
  exit;
}

$mediaService = null;
try {
  } catch (\Throwable $e) {
  $warnings[] = 'Upload de midia indisponivel: ' . $e->getMessage();
}

$mediaImages = [];
if ($mediaService) {
  foreach ($uploadedImages as $image) {
    $path = (string) ($image['path'] ?? '');
    if ($path === '') {
      continue;
    }
    $fileName = (string) ($image['file_name'] ?? basename($path));
    $mime = (string) ($image['mime'] ?? 'image/jpeg');
    try {
      $result = $mediaService->upload($path, $fileName, $mime);
      if (!empty($result['id'])) {
        $mediaImages[] = [
          'id' => (int) $result['id'],
          'src' => (string) ($result['src'] ?? ''),
        ];
      }
    } catch (\Throwable $e) {
      $warnings[] = 'Erro ao enviar imagem para o servico de midia: ' . $e->getMessage();
    }
  }
}

$newMedia = [];
foreach ($mediaImages as $media) {
  if (!empty($media['id'])) {
    $newMedia[] = ['id' => (int) $media['id']];
  } elseif (!empty($media['src'])) {
    $newMedia[] = ['src' => $media['src']];
  }
}
foreach ($uploadedImages as $image) {
  if (!empty($image['src'])) {
    $newMedia[] = ['src' => $image['src']];
  }
}

if (empty($newMedia)) {
  http_response_code(422);
  echo json_encode(['status' => 'error', 'message' => 'Não foi possível preparar as imagens para envio.']);
  exit;
}

// MIGRADO: Buscar e atualizar produto do banco local
try {
  $product = $productRepo->findById($productId);
  if (!$product) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Produto não encontrado.']);
    exit;
  }
} catch (\Throwable $e) {
  http_response_code(404);
  echo json_encode(['status' => 'error', 'message' => 'Produto não encontrado.']);
  exit;
}

$existingImages = json_decode($product->images ?? '[]', true);
if (!is_array($existingImages)) {
  $existingImages = [];
}
$payload = array_merge($existingImages, $newMedia);

try {
  $product->images = json_encode($payload);
  $productRepo->save($product);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar imagens do produto: ' . $e->getMessage()]);
  exit;
}

$message = 'Imagens adicionadas com sucesso.';
if (!empty($warnings)) {
  $message .= ' ' . implode(' ', $warnings);
}

header('Content-Type: application/json');
echo json_encode([
  'status' => 'ok',
  'message' => $message,
  'images' => json_decode($product->images ?? '[]', true),
]);
