<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\ThumbnailCacheService;

$args = getopt('', ['all', 'sizes::', 'limit::']);

[$pdo, $error] = bootstrapPdo();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, 'Erro DB: ' . (string) $error . PHP_EOL);
    exit(1);
}

$sizesRaw = (string) ($args['sizes'] ?? '60,110,150,300');
$sizes = [];
foreach (explode(',', $sizesRaw) as $s) {
    $v = (int) trim($s);
    if ($v > 0) {
        $sizes[$v] = $v;
    }
}
if (empty($sizes)) {
    $sizes = [150 => 150];
}

$limit = isset($args['limit']) ? max(1, (int) $args['limit']) : 0;
$service = new ThumbnailCacheService();

$sources = [];
$stmt = $pdo->query('SELECT metadata FROM products');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $meta = json_decode((string) ($row['metadata'] ?? ''), true);
    if (!is_array($meta) || !isset($meta['images']) || !is_array($meta['images'])) {
        continue;
    }
    foreach ($meta['images'] as $img) {
        if (!is_array($img)) {
            continue;
        }
        $src = trim((string) ($img['src'] ?? ''));
        if ($src !== '') {
            $sources[$src] = true;
        }
    }
}

if (isset($args['all'])) {
    try {
        $stmt = $pdo->query('SELECT image_url FROM sacolinha_itens WHERE image_url IS NOT NULL AND image_url <> ""');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $src = trim((string) ($row['image_url'] ?? ''));
            if ($src !== '') {
                $sources[$src] = true;
            }
        }
    } catch (Throwable $e) {
        // opcional
    }
}

$sourceList = array_keys($sources);
sort($sourceList);
if ($limit > 0) {
    $sourceList = array_slice($sourceList, 0, $limit);
}

$total = count($sourceList);
$ok = 0;
$fail = 0;

foreach ($sourceList as $src) {
    foreach ($sizes as $size) {
        $path = $service->ensureCached($src, $size);
        if ($path !== null && is_file($path)) {
            $ok++;
        } else {
            $fail++;
        }
    }
}

echo 'Thumb rebuild finalizado' . PHP_EOL;
echo 'sources=' . $total . ' sizes=' . implode(',', $sizes) . PHP_EOL;
echo 'ok=' . $ok . ' fail=' . $fail . PHP_EOL;
