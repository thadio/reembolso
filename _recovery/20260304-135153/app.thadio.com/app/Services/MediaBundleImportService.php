<?php

namespace App\Services;

use PDO;
use ZipArchive;

class MediaBundleImportService
{
    private PDO $pdo;
    private string $projectRoot;
    private string $targetBaseDir;

    public function __construct(PDO $pdo, ?string $projectRoot = null, ?string $targetBaseDir = null)
    {
        $this->pdo = $pdo;
        $this->projectRoot = rtrim((string) ($projectRoot ?: dirname(__DIR__, 2)), '/');
        $defaultBaseDir = trim((string) (getenv('PRODUCT_IMAGE_DIR') ?: 'uploads/products'), '/');
        $this->targetBaseDir = trim((string) ($targetBaseDir ?: ($defaultBaseDir . '/migration-media')), '/');
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function importBundle(string $bundlePath, array $options = []): array
    {
        $dryRun = !empty($options['dry_run']);
        $continueOnError = !empty($options['continue_on_error']);
        $updateProducts = !array_key_exists('update_products', $options) || !empty($options['update_products']);
        $updateMediaFiles = !array_key_exists('update_media_files', $options) || !empty($options['update_media_files']);
        $sourceSystemOption = trim((string) ($options['source_system'] ?? ''));
        $dayFolder = date('Ymd');

        $report = [
            'status' => 'success',
            'bundle_path' => $bundlePath,
            'dry_run' => $dryRun,
            'continue_on_error' => $continueOnError,
            'update_products' => $updateProducts,
            'update_media_files' => $updateMediaFiles,
            'started_at' => date('c'),
            'finished_at' => null,
            'manifest' => null,
            'totals' => [
                'items_total' => 0,
                'processed' => 0,
                'imported_files' => 0,
                'skipped_files' => 0,
                'updated_products' => 0,
                'updated_media_files' => 0,
                'warnings' => 0,
                'errors' => 0,
            ],
            'items' => [],
            'warnings' => [],
            'errors' => [],
        ];

        if (!is_file($bundlePath) || !is_readable($bundlePath)) {
            $this->pushError($report, 'Bundle nao encontrado ou sem permissao de leitura: ' . $bundlePath, 0);
            $report['status'] = 'error';
            $report['finished_at'] = date('c');
            return $report;
        }

        if (!class_exists(ZipArchive::class)) {
            $this->pushError($report, 'Extensao ZIP nao disponivel no PHP. Habilite ZipArchive.', 0);
            $report['status'] = 'error';
            $report['finished_at'] = date('c');
            return $report;
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($bundlePath);
        if ($openResult !== true) {
            $this->pushError($report, 'Falha ao abrir bundle ZIP. Codigo=' . $openResult, 0);
            $report['status'] = 'error';
            $report['finished_at'] = date('c');
            return $report;
        }

        try {
            $manifest = $this->readManifest($zip);
            if ($manifest === null) {
                $this->pushError($report, 'Manifesto de midia nao encontrado no ZIP.', 0);
                $report['status'] = 'error';
                return $report;
            }
            $report['manifest'] = $manifest;

            $sourceSystemRaw = $sourceSystemOption !== ''
                ? $sourceSystemOption
                : (string) ($manifest['source_system'] ?? 'legacy');
            $sourceSystem = $this->sanitizeSegment($sourceSystemRaw);
            if ($sourceSystem === '') {
                $sourceSystem = 'legacy';
            }

            $items = $manifest['items'] ?? [];
            if (!is_array($items)) {
                $this->pushError($report, 'Manifesto de midia invalido: items deve ser array.', 0);
                $report['status'] = 'error';
                return $report;
            }
            $report['totals']['items_total'] = count($items);

            foreach ($items as $index => $item) {
                $itemIndex = $index + 1;
                if (!is_array($item)) {
                    $this->pushError($report, 'Item de midia invalido no indice ' . $itemIndex . '.', $itemIndex);
                    if (!$continueOnError) {
                        break;
                    }
                    continue;
                }

                $blobPath = trim((string) ($item['blob_path'] ?? ($item['path'] ?? '')));
                if ($blobPath === '') {
                    $this->pushError($report, 'Item sem blob_path no indice ' . $itemIndex . '.', $itemIndex);
                    if (!$continueOnError) {
                        break;
                    }
                    continue;
                }

                $legacySrc = trim((string) ($item['legacy_src'] ?? ''));
                $productSku = (int) ($item['product_sku'] ?? 0);
                $mediaFileId = (int) ($item['media_file_id'] ?? 0);
                $declaredSha256 = strtolower(trim((string) ($item['sha256'] ?? '')));
                $declaredSize = isset($item['size']) ? (int) $item['size'] : null;
                $mimeType = trim((string) ($item['mime_type'] ?? ''));

                $zipIndex = $zip->locateName($blobPath, ZipArchive::FL_NOCASE);
                if ($zipIndex === false) {
                    $this->pushError($report, 'Blob nao encontrado no ZIP: ' . $blobPath, $itemIndex);
                    if (!$continueOnError) {
                        break;
                    }
                    continue;
                }

                $extension = $this->resolveExtension($item, $blobPath, $mimeType);
                $fileToken = $declaredSha256 !== '' && preg_match('/^[a-f0-9]{64}$/', $declaredSha256)
                    ? $declaredSha256
                    : hash('sha256', $blobPath . '|' . $itemIndex . '|' . microtime(true));
                $relativeTargetPath = $this->targetBaseDir . '/'
                    . $sourceSystem . '/'
                    . $dayFolder . '/'
                    . $fileToken . ($extension !== '' ? ('.' . $extension) : '');
                $absoluteTargetPath = $this->projectRoot . '/' . $relativeTargetPath;
                $publicSrc = '/' . ltrim($relativeTargetPath, '/');

                $itemReport = [
                    'index' => $itemIndex,
                    'blob_path' => $blobPath,
                    'legacy_src' => $legacySrc,
                    'product_sku' => $productSku > 0 ? $productSku : null,
                    'media_file_id' => $mediaFileId > 0 ? $mediaFileId : null,
                    'target_path' => $relativeTargetPath,
                    'status' => 'success',
                    'message' => '',
                ];

                try {
                    if (!$dryRun) {
                        $this->copyBlobFromZip($zip, (int) $zipIndex, $absoluteTargetPath);
                        $actualSha256 = hash_file('sha256', $absoluteTargetPath) ?: '';
                        $actualSize = filesize($absoluteTargetPath);
                        if (!is_int($actualSize)) {
                            $actualSize = null;
                        }

                        if ($declaredSha256 !== '' && $actualSha256 !== '' && !hash_equals($declaredSha256, $actualSha256)) {
                            throw new \RuntimeException('Hash SHA-256 divergente para blob ' . $blobPath . '.');
                        }
                        if ($declaredSize !== null && $actualSize !== null && $declaredSize !== $actualSize) {
                            throw new \RuntimeException('Tamanho divergente para blob ' . $blobPath . '.');
                        }

                        $report['totals']['imported_files']++;

                        if ($updateProducts && $productSku > 0) {
                            $updatedProduct = $this->updateProductImages($productSku, $legacySrc, $publicSrc);
                            if ($updatedProduct) {
                                $report['totals']['updated_products']++;
                            }
                        }

                        if ($updateMediaFiles && $mediaFileId > 0) {
                            $updatedMediaFile = $this->updateMediaFile(
                                $mediaFileId,
                                $publicSrc,
                                $mimeType,
                                $actualSize,
                                $actualSha256,
                                basename($absoluteTargetPath)
                            );
                            if ($updatedMediaFile) {
                                $report['totals']['updated_media_files']++;
                            }
                        }
                    } else {
                        $report['totals']['skipped_files']++;
                    }
                } catch (\Throwable $exception) {
                    $itemReport['status'] = 'error';
                    $itemReport['message'] = $exception->getMessage();
                    $this->pushError($report, 'Falha no item #' . $itemIndex . ': ' . $exception->getMessage(), $itemIndex);
                    if (!$continueOnError) {
                        $report['items'][] = $itemReport;
                        break;
                    }
                }

                $report['totals']['processed']++;
                $report['items'][] = $itemReport;
            }
        } finally {
            $zip->close();
        }

        $report['finished_at'] = date('c');
        if ($report['totals']['errors'] > 0) {
            $report['status'] = $report['totals']['processed'] > 0 ? 'partial' : 'error';
        } elseif ($report['totals']['warnings'] > 0) {
            $report['status'] = 'success_with_warnings';
        }

        return $report;
    }

    private function readManifest(ZipArchive $zip): ?array
    {
        $candidates = [
            'media-manifest.json',
            'manifest.json',
            'media/manifest.json',
            'media_bundle_manifest.json',
        ];

        foreach ($candidates as $candidate) {
            $idx = $zip->locateName($candidate, ZipArchive::FL_NOCASE);
            if ($idx === false) {
                continue;
            }
            $raw = $zip->getFromIndex((int) $idx);
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = (string) $zip->getNameIndex($i);
            if (!preg_match('/manifest\\.json$/i', $entryName)) {
                continue;
            }
            $raw = $zip->getFromIndex($i);
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['items']) && is_array($decoded['items'])) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function resolveExtension(array $item, string $blobPath, string $mimeType): string
    {
        $extension = strtolower(trim((string) ($item['extension'] ?? '')));
        if ($extension !== '') {
            return preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
        }

        $fromPath = strtolower((string) pathinfo($blobPath, PATHINFO_EXTENSION));
        if ($fromPath !== '') {
            return preg_replace('/[^a-z0-9]/', '', $fromPath) ?: 'bin';
        }

        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/avif' => 'avif',
        ];
        $normalizedMime = strtolower(trim($mimeType));
        return $mimeMap[$normalizedMime] ?? 'bin';
    }

    private function copyBlobFromZip(ZipArchive $zip, int $zipIndex, string $absoluteTargetPath): void
    {
        $targetDir = dirname($absoluteTargetPath);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Nao foi possivel criar diretorio de destino: ' . $targetDir);
        }

        $in = $zip->getStream($zip->getNameIndex($zipIndex));
        if (!is_resource($in)) {
            throw new \RuntimeException('Falha ao abrir stream do blob no ZIP.');
        }

        $out = fopen($absoluteTargetPath, 'wb');
        if (!is_resource($out)) {
            fclose($in);
            throw new \RuntimeException('Falha ao abrir arquivo de destino para escrita.');
        }

        try {
            $bytes = stream_copy_to_stream($in, $out);
            if ($bytes === false) {
                throw new \RuntimeException('Falha ao copiar blob do ZIP para destino.');
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    private function updateProductImages(int $productSku, string $legacySrc, string $newSrc): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT metadata
             FROM products
             WHERE sku = :sku
             LIMIT 1"
        );
        $statement->execute([':sku' => $productSku]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return false;
        }

        $metadataRaw = $row['metadata'] ?? null;
        $metadata = [];
        if (is_string($metadataRaw) && trim($metadataRaw) !== '') {
            $decoded = json_decode($metadataRaw, true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        } elseif (is_array($metadataRaw)) {
            $metadata = $metadataRaw;
        }

        $images = [];
        if (isset($metadata['images']) && is_array($metadata['images'])) {
            $images = $metadata['images'];
        }

        $changed = false;
        foreach ($images as $index => $image) {
            if (!is_array($image)) {
                continue;
            }
            $currentSrc = trim((string) ($image['src'] ?? ''));
            if ($legacySrc !== '' && $currentSrc !== '' && $currentSrc === $legacySrc) {
                $images[$index]['src'] = $newSrc;
                if (!isset($images[$index]['name']) || trim((string) $images[$index]['name']) === '') {
                    $images[$index]['name'] = basename($newSrc);
                }
                $changed = true;
            }
        }

        if (!$changed) {
            foreach ($images as $image) {
                if (!is_array($image)) {
                    continue;
                }
                $currentSrc = trim((string) ($image['src'] ?? ''));
                if ($currentSrc !== '' && $currentSrc === $newSrc) {
                    return false;
                }
            }

            $images[] = [
                'src' => $newSrc,
                'name' => basename($newSrc),
                'position' => count($images) + 1,
            ];
            $changed = true;
        }

        if (!$changed) {
            return false;
        }

        $metadata['images'] = array_values($images);
        $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new \RuntimeException('Falha ao serializar metadata de produto SKU ' . $productSku . '.');
        }

        $update = $this->pdo->prepare("UPDATE products SET metadata = :metadata WHERE sku = :sku");
        $update->execute([
            ':metadata' => $encoded,
            ':sku' => $productSku,
        ]);

        return $update->rowCount() > 0;
    }

    private function updateMediaFile(
        int $mediaFileId,
        string $filePath,
        string $mimeType,
        ?int $fileSize,
        string $sha256,
        string $fileName
    ): bool {
        $sha1 = $sha256 !== '' ? sha1($sha256) : null;
        $statement = $this->pdo->prepare(
            "UPDATE media_files
             SET file_name = :file_name,
                 file_path = :file_path,
                 mime_type = :mime_type,
                 file_size = :file_size,
                 hash_sha1 = :hash_sha1
             WHERE id = :id"
        );
        $statement->execute([
            ':file_name' => $fileName,
            ':file_path' => $filePath,
            ':mime_type' => $mimeType !== '' ? $mimeType : null,
            ':file_size' => $fileSize,
            ':hash_sha1' => $sha1,
            ':id' => $mediaFileId,
        ]);

        return $statement->rowCount() > 0;
    }

    private function sanitizeSegment(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $clean = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $value);
        return trim((string) $clean, '._-');
    }

    /**
     * @param array<string, mixed> $report
     */
    private function pushWarning(array &$report, string $message, int $itemIndex): void
    {
        $report['totals']['warnings']++;
        $report['warnings'][] = [
            'item_index' => $itemIndex,
            'message' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    private function pushError(array &$report, string $message, int $itemIndex): void
    {
        $report['totals']['errors']++;
        $report['errors'][] = [
            'item_index' => $itemIndex,
            'message' => $message,
        ];
    }
}
