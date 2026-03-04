<?php

require __DIR__ . '/app/autoload.php';

use App\Services\ThumbnailCacheService;

if (!isset($_GET['src']) && !isset($_GET['u'])) {
    http_response_code(400);
    exit;
}

$url = (string) ($_GET['src'] ?? '');
if ($url === '' && isset($_GET['u'])) {
    $encoded = (string) $_GET['u'];
    $encoded = strtr($encoded, '-_', '+/');
    $decoded = base64_decode($encoded, true);
    if (is_string($decoded)) {
        $url = $decoded;
    }
}
$size = isset($_GET['size']) ? (int) $_GET['size'] : 150;
$maxSize = (int) (getenv('THUMBNAIL_MAX_SIZE') ?: getenv('PRODUCT_IMAGE_MAX_DIMENSION') ?: 600);
$maxSize = max(64, min(4000, $maxSize));
$size = max(32, min($maxSize, $size));

$service = new ThumbnailCacheService();
$path = $service->ensureCached($url, $size);
if ($path === null) {
    http_response_code(404);
    exit;
}

header_remove('Pragma');
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=86400');
readfile($path);
