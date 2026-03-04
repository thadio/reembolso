<?php

namespace App\Services;

use App\Support\Input;

class ProductImageService
{
    private string $rootPath;
    private string $relativeDir;
    private string $baseUrl;
    private int $maxFiles;
    private float $maxSizeMb;
    private int $maxBytes;
    private int $maxDimension;
    private int $jpegQuality;
    private int $webpQuality;
    private int $pngCompression;
    /** @var array<string, string> */
    private array $allowedMime;

    public function __construct(?array $config = null)
    {
        $config = $config ?? [];

        $this->rootPath = rtrim((string) ($config['root_path'] ?? dirname(__DIR__, 2)), '/');
        $this->relativeDir = trim((string) ($config['relative_dir'] ?? getenv('PRODUCT_IMAGE_DIR') ?: 'uploads/products'), '/');
        $this->maxFiles = max(1, (int) ($config['max_files'] ?? getenv('PRODUCT_IMAGE_MAX_FILES') ?: 6));
        $this->maxSizeMb = $this->normalizeMaxSizeMb($config['max_size_mb'] ?? getenv('PRODUCT_IMAGE_MAX_SIZE_MB') ?: 12);
        $this->maxBytes = (int) round($this->maxSizeMb * 1024 * 1024);
        $this->applyServerUploadLimit();
        $this->maxDimension = max(1, (int) ($config['max_dimension'] ?? getenv('PRODUCT_IMAGE_MAX_DIMENSION') ?: 2868));
        $this->jpegQuality = $this->normalizeQuality($config['jpeg_quality'] ?? getenv('PRODUCT_IMAGE_JPEG_QUALITY') ?: 85);
        $this->webpQuality = $this->normalizeQuality($config['webp_quality'] ?? getenv('PRODUCT_IMAGE_WEBP_QUALITY') ?: 85);
        $this->pngCompression = $this->normalizePngCompression(
            $config['png_compression'] ?? getenv('PRODUCT_IMAGE_PNG_COMPRESSION') ?: 6
        );
        $this->allowedMime = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'jpg',
            'image/heif' => 'jpg',
            'image/heic-sequence' => 'jpg',
            'image/heif-sequence' => 'jpg',
            'image/avif' => 'jpg',
            'image/tiff' => 'jpg',
            'image/bmp' => 'jpg',
        ];
        $this->baseUrl = $this->resolveBaseUrl($config['base_url'] ?? null);
    }

    /**
     * @return array{maxFiles:int, maxSizeMb:float, maxSizeBytes:int, maxSizeLabel:string, allowedExtensions:array<int, string>, accept:string}
     */
    public function info(): array
    {
        $extensions = $this->allowedExtensionsForLabel();

        return [
            'maxFiles' => $this->maxFiles,
            'maxSizeMb' => $this->maxSizeMb,
            'maxSizeBytes' => $this->maxBytes,
            'maxSizeLabel' => $this->formatSizeLabel($this->maxSizeMb),
            'allowedExtensions' => $extensions,
            'accept' => implode(',', array_keys($this->allowedMime)),
        ];
    }

    /**
     * @return array<int, array{tmp_name:string, original_name:string, extension:string, mime:string}>
     */
    public function prepareUploads(array $files, array &$errors): array
    {
        $items = $this->normalizeFiles($files);
        if (empty($items)) {
            return [];
        }

        if (count($items) > $this->maxFiles) {
            $errors[] = "Selecione no máximo {$this->maxFiles} imagens.";
            return [];
        }

        $prepared = [];
        $allowedLabel = strtoupper(implode(', ', $this->allowedExtensionsForLabel()));
        foreach ($items as $item) {
            $label = $this->safeImageName($item['name'] ?? '');
            $label = $label !== '' ? $label : 'imagem';

            $errorCode = $item['error'] ?? UPLOAD_ERR_NO_FILE;
            if ($errorCode === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($errorCode !== UPLOAD_ERR_OK) {
                $errorMessage = $this->uploadErrorMessage($errorCode, $label);
                $errors[] = $errorMessage ?? "Erro no envio da {$label}.";
                continue;
            }

            $tmpName = (string) ($item['tmp_name'] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                $errors[] = "Falha ao processar a {$label}.";
                continue;
            }

            $size = (int) ($item['size'] ?? 0);
            if ($size <= 0) {
                $errors[] = "A {$label} é inválida ou está vazia.";
                continue;
            }

            $mime = $this->detectMime($tmpName);
            if ($mime === null || !isset($this->allowedMime[$mime])) {
                $errors[] = "A {$label} deve ser {$allowedLabel}.";
                continue;
            }

            $prepared[] = [
                'tmp_name' => $tmpName,
                'original_name' => (string) ($item['name'] ?? ''),
                'extension' => $this->allowedMime[$mime],
                'mime' => $mime,
            ];
        }

        return empty($errors) ? $prepared : [];
    }

    /**
     * @param array<int, array{tmp_name:string, original_name:string, extension:string, mime:string}> $prepared
     * @return array<int, array<string, string>>
     */
    public function storePrepared(array $prepared, array &$errors): array
    {
        if (empty($prepared)) {
            return [];
        }

        $dateSegment = date('Y/m');
        $targetDir = $this->rootPath . '/' . $this->relativeDir . '/' . $dateSegment;
        if (!$this->ensureDirectory($targetDir)) {
            $errors[] = 'Não foi possível criar a pasta de upload de imagens.';
            return [];
        }

        $images = [];
        foreach ($prepared as $item) {
            $extension = $item['extension'];
            $fileName = $this->generateFileName($extension);
            $relativePath = $this->relativeDir . '/' . $dateSegment . '/' . $fileName;
            $absolutePath = $this->rootPath . '/' . $relativePath;

            if (!move_uploaded_file($item['tmp_name'], $absolutePath)) {
                $label = $this->safeImageName($item['original_name']);
                $label = $label !== '' ? $label : 'imagem';
                $errors[] = "Falha ao salvar a {$label}.";
                continue;
            }

            $label = $this->safeImageName($item['original_name']);
            $label = $label !== '' ? $label : 'imagem';
            $mime = (string) ($item['mime'] ?? $this->defaultMimeType((string) $extension));

            if (!$this->normalizeImageFormat($absolutePath, $mime, $extension, $label, $errors)) {
                @unlink($absolutePath);
                continue;
            }

            if (!$this->optimizeImage($absolutePath, $mime, $label, $errors)) {
                @unlink($absolutePath);
                continue;
            }
            if (!$this->ensureWithinSizeLimit($absolutePath, $mime, $label, $errors)) {
                @unlink($absolutePath);
                continue;
            }

            $originalName = $this->safeImageName($item['original_name']);
            $originalName = $originalName !== '' ? $originalName : 'imagem';
            $originalFileName = $originalName . '.' . $extension;
            $publicUrl = '/' . ltrim($relativePath, '/');

            $images[] = [
                'src' => $publicUrl,
                'name' => $originalName,
                'file_name' => $originalFileName,
                'path' => $absolutePath,
                'mime' => $mime,
            ];
        }

        return $images;
    }

    private function defaultMimeType(string $extension): string
    {
        $extension = strtolower($extension);
        $mime = array_search($extension, $this->allowedMime, true);
        return $mime !== false ? $mime : 'application/octet-stream';
    }

    private function normalizeMaxSizeMb($value): float
    {
        $parsed = Input::parseNumber($value);
        if ($parsed === null || $parsed <= 0) {
            return 12.0;
        }
        return $parsed;
    }

    private function normalizeQuality($value): int
    {
        $value = (int) $value;
        if ($value < 10) {
            return 10;
        }
        if ($value > 100) {
            return 100;
        }
        return $value;
    }

    private function normalizePngCompression($value): int
    {
        $value = (int) $value;
        if ($value < 0) {
            return 0;
        }
        if ($value > 9) {
            return 9;
        }
        return $value;
    }

    private function resolveBaseUrl(?string $override = null): string
    {
        $baseUrl = trim((string) ($override ?? ''));
        if ($baseUrl === '') {
            $baseUrl = trim((string) (getenv('APP_UPLOAD_BASE_URL') ?: getenv('APP_BASE_URL') ?: ''));
        }
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if ($host !== '') {
                $baseUrl = $scheme . '://' . $host;
            }
        }

        return rtrim($baseUrl, '/');
    }

    private function applyServerUploadLimit(): void
    {
        $uploadLimit = $this->parseIniSize((string) ini_get('upload_max_filesize'));
        $postLimit = $this->parseIniSize((string) ini_get('post_max_size'));

        $limits = array_filter([$uploadLimit, $postLimit], fn ($value) => $value > 0);
        if (empty($limits)) {
            return;
        }

        $serverLimit = min($limits);
        if ($serverLimit > 0 && $serverLimit < $this->maxBytes) {
            $this->maxBytes = $serverLimit;
            $this->maxSizeMb = $serverLimit / 1024 / 1024;
        }
    }

    private function parseIniSize(string $value): int
    {
        $raw = trim($value);
        if ($raw === '') {
            return 0;
        }
        if ($raw === '-1') {
            return 0;
        }

        $unit = strtolower(substr($raw, -1));
        $number = $raw;
        if (!ctype_digit($unit)) {
            $number = substr($raw, 0, -1);
        } else {
            $unit = '';
        }

        $number = trim($number);
        if ($number === '') {
            return 0;
        }

        $valueFloat = Input::parseNumber($number);
        if ($valueFloat === null || $valueFloat <= 0) {
            return 0;
        }

        switch ($unit) {
            case 'g':
                $valueFloat *= 1024;
                // no break
            case 'm':
                $valueFloat *= 1024;
                // no break
            case 'k':
                $valueFloat *= 1024;
                break;
        }

        return (int) round($valueFloat);
    }

    private function uploadErrorMessage(int $errorCode, string $label): ?string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $sizeLabel = $this->formatSizeLabel($this->maxSizeMb);
                return "A {$label} excede o limite de upload ({$sizeLabel} MB).";
            case UPLOAD_ERR_PARTIAL:
                return "O upload da {$label} foi interrompido.";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Servidor sem pasta temporária para a {$label}.";
            case UPLOAD_ERR_CANT_WRITE:
                return "Servidor não conseguiu gravar a {$label}.";
            case UPLOAD_ERR_EXTENSION:
                return "Upload da {$label} bloqueado pela configuração do PHP.";
        }

        return null;
    }

    /**
     * @return array<int, array{name:string, tmp_name:string, error:int, size:int}>
     */
    private function normalizeFiles(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }

        $names = (array) $files['name'];
        $tmpNames = (array) ($files['tmp_name'] ?? []);
        $errors = (array) ($files['error'] ?? []);
        $sizes = (array) ($files['size'] ?? []);

        $normalized = [];
        foreach ($names as $index => $name) {
            $error = $errors[$index] ?? UPLOAD_ERR_NO_FILE;
            if ($name === '' && $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $normalized[] = [
                'name' => (string) $name,
                'tmp_name' => (string) ($tmpNames[$index] ?? ''),
                'error' => (int) $error,
                'size' => (int) ($sizes[$index] ?? 0),
            ];
        }

        return $normalized;
    }

    private function detectMime(string $tmpName): ?string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($tmpName);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        $info = @getimagesize($tmpName);
        if (is_array($info) && isset($info['mime'])) {
            return (string) $info['mime'];
        }

        return null;
    }

    private function ensureDirectory(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }

        return mkdir($path, 0755, true);
    }

    private function formatSizeLabel(float $value): string
    {
        $label = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        return $label === '' ? '0' : $label;
    }

    private function buildPublicUrl(string $baseUrl, string $relativePath): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $relativePath = ltrim($relativePath, '/');
        if ($baseUrl === '' || $relativePath === '') {
            return $baseUrl;
        }

        $basePath = parse_url($baseUrl, PHP_URL_PATH);
        if (!is_string($basePath) || $basePath === '') {
            return $baseUrl . '/' . $relativePath;
        }

        $baseSegments = array_values(array_filter(explode('/', trim($basePath, '/')), 'strlen'));
        $relativeSegments = array_values(array_filter(explode('/', $relativePath), 'strlen'));
        if (empty($baseSegments) || empty($relativeSegments)) {
            return $baseUrl . '/' . $relativePath;
        }

        $maxOverlap = min(count($baseSegments), count($relativeSegments));
        for ($overlap = $maxOverlap; $overlap > 0; $overlap--) {
            $baseTail = array_slice($baseSegments, -$overlap);
            $relativeHead = array_slice($relativeSegments, 0, $overlap);
            if ($baseTail === $relativeHead) {
                $relativeSegments = array_slice($relativeSegments, $overlap);
                break;
            }
        }

        $normalizedRelative = implode('/', $relativeSegments);
        if ($normalizedRelative === '') {
            return $baseUrl;
        }

        return $baseUrl . '/' . $normalizedRelative;
    }

    /**
     * @return array<int, string>
     */
    private function allowedExtensionsForLabel(): array
    {
        $friendly = ['heic', 'heif', 'avif', 'tiff', 'bmp'];
        $extensions = array_values(array_unique(array_merge(
            array_values($this->allowedMime),
            $friendly
        )));
        sort($extensions);
        return $extensions;
    }

    private function safeImageName(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = preg_replace('/[^a-zA-Z0-9 _-]+/', '', $base) ?? '';
        $base = trim($base);
        return $base;
    }

    private function generateFileName(string $extension): string
    {
        try {
            $random = bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            $random = uniqid('img', false);
        }

        return $random . '.' . $extension;
    }

    private function normalizeImageFormat(string $path, string &$mime, string &$extension, string $label, array &$errors): bool
    {
        $convertibleMimes = [
            'image/heic',
            'image/heif',
            'image/heic-sequence',
            'image/heif-sequence',
            'image/avif',
            'image/tiff',
            'image/bmp',
        ];

        if (!in_array($mime, $convertibleMimes, true)) {
            return true;
        }

        if ($this->convertToJpegWithImagick($path)) {
            $mime = 'image/jpeg';
            $extension = 'jpg';
            return true;
        }

        if ($this->convertToJpegWithGd($path)) {
            $mime = 'image/jpeg';
            $extension = 'jpg';
            return true;
        }

        $errors[] = "A {$label} está em um formato (HEIC/HEIF/AVIF/TIFF/BMP) que não foi possível converter para JPG no servidor. Converta no aparelho ou habilite o Imagick com suporte a esses formatos.";
        return false;
    }

    private function convertToJpegWithImagick(string $path): bool
    {
        if (!class_exists('\\Imagick')) {
            return false;
        }

        try {
            $imagick = new \Imagick();
            $imagick->readImage($path);
            $imagick->setImageFormat('jpeg');
            if (method_exists($imagick, 'setImageCompression')) {
                $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
            }
            if (method_exists($imagick, 'setImageCompressionQuality')) {
                $imagick->setImageCompressionQuality($this->jpegQuality);
            }
            if (method_exists($imagick, 'setInterlaceScheme')) {
                $imagick->setInterlaceScheme(\Imagick::INTERLACE_JPEG);
            }
            $result = $imagick->writeImage($path);
            $imagick->clear();
            $imagick->destroy();
            return (bool) $result;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function convertToJpegWithGd(string $path): bool
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return false;
        }

        $data = @file_get_contents($path);
        if ($data === false) {
            return false;
        }

        $resource = @imagecreatefromstring($data);
        if ($resource === false) {
            return false;
        }

        $result = imagejpeg($resource, $path, $this->jpegQuality);
        imagedestroy($resource);
        return (bool) $result;
    }

    private function optimizeImage(string $path, string $mime, string $label, array &$errors): bool
    {
        $info = @getimagesize($path);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            return true;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $maxDimension = $this->maxDimension;
        // Keep the long edge under the WP media limit to avoid 503s on upload.
        $needsResize = $maxDimension > 0 && max($width, $height) > $maxDimension;

        if (!$this->gdAvailable()) {
            if ($needsResize) {
                $errors[] = "A {$label} excede {$maxDimension}px e não foi possível redimensionar (GD indisponível).";
                return false;
            }
            return true;
        }

        $source = $this->createImageResource($path, $mime);
        if ($source === null) {
            if ($needsResize) {
                $errors[] = "Falha ao processar a {$label} para redimensionamento.";
                return false;
            }
            return true;
        }

        $targetWidth = $width;
        $targetHeight = $height;
        if ($needsResize) {
            $ratio = $maxDimension / max($width, $height);
            $targetWidth = max(1, (int) round($width * $ratio));
            $targetHeight = max(1, (int) round($height * $ratio));
        }

        $resource = $source;
        $canvas = null;
        if ($needsResize) {
            $canvas = $this->resizeImageResource($source, $width, $height, $targetWidth, $targetHeight, $mime);
            if ($canvas === null) {
                imagedestroy($source);
                $errors[] = "Falha ao redimensionar a {$label}.";
                return false;
            }
            $resource = $canvas;
        }

        $written = $this->writeImageResource($resource, $mime, $path);

        if ($canvas && $canvas !== $source) {
            imagedestroy($canvas);
        }
        imagedestroy($source);

        if (!$written) {
            if ($needsResize) {
                $errors[] = "Falha ao salvar a {$label} otimizada.";
                return false;
            }
            return true;
        }

        return true;
    }

    private function ensureWithinSizeLimit(string $path, string $mime, string $label, array &$errors): bool
    {
        if ($this->maxBytes <= 0) {
            return true;
        }

        clearstatcache(true, $path);
        $fileSize = @filesize($path);
        if (!is_int($fileSize) || $fileSize <= 0 || $fileSize <= $this->maxBytes) {
            return true;
        }

        if (!$this->gdAvailable()) {
            $sizeLabel = $this->formatSizeLabel($this->maxSizeMb);
            $errors[] = "A {$label} excede {$sizeLabel} MB e não foi possível reduzir automaticamente (GD indisponível).";
            return false;
        }

        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $sizeLabel = $this->formatSizeLabel($this->maxSizeMb);
            $errors[] = "A {$label} excede {$sizeLabel} MB após processamento.";
            return false;
        }

        $quality = $mime === 'image/jpeg' ? $this->jpegQuality : $this->webpQuality;
        $pngCompression = $this->pngCompression;
        $scale = 1.0;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $source = $this->createImageResource($path, $mime);
            if ($source === null) {
                break;
            }

            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            if ($sourceWidth <= 0 || $sourceHeight <= 0) {
                imagedestroy($source);
                break;
            }

            $targetWidth = max(1, (int) round($sourceWidth * $scale));
            $targetHeight = max(1, (int) round($sourceHeight * $scale));

            $resource = $source;
            $canvas = null;
            if ($targetWidth !== $sourceWidth || $targetHeight !== $sourceHeight) {
                $canvas = $this->resizeImageResource(
                    $source,
                    $sourceWidth,
                    $sourceHeight,
                    $targetWidth,
                    $targetHeight,
                    $mime
                );
                if ($canvas === null) {
                    imagedestroy($source);
                    break;
                }
                $resource = $canvas;
            }

            $written = $this->writeImageResource(
                $resource,
                $mime,
                $path,
                $mime === 'image/jpeg' ? $quality : null,
                $mime === 'image/webp' ? $quality : null,
                $mime === 'image/png' ? $pngCompression : null
            );

            if ($canvas && $canvas !== $source) {
                imagedestroy($canvas);
            }
            imagedestroy($source);

            if (!$written) {
                break;
            }

            clearstatcache(true, $path);
            $fileSize = @filesize($path);
            if (is_int($fileSize) && $fileSize > 0 && $fileSize <= $this->maxBytes) {
                return true;
            }

            if ($mime === 'image/png') {
                if ($pngCompression < 9) {
                    $pngCompression++;
                    continue;
                }
            } else {
                if ($quality > 55) {
                    $quality = max(55, $quality - 7);
                    continue;
                }
            }

            $scale *= 0.9;
            if ($scale < 0.55) {
                break;
            }
        }

        $sizeLabel = $this->formatSizeLabel($this->maxSizeMb);
        $errors[] = "A {$label} excede {$sizeLabel} MB mesmo após otimização automática.";
        return false;
    }

    private function gdAvailable(): bool
    {
        return extension_loaded('gd')
            && (
                function_exists('imagecreatefromjpeg')
                || function_exists('imagecreatefrompng')
                || function_exists('imagecreatefromwebp')
            );
    }

    private function createImageResource(string $path, string $mime)
    {
        switch ($mime) {
            case 'image/jpeg':
                if (!function_exists('imagecreatefromjpeg')) {
                    return null;
                }
                $resource = @imagecreatefromjpeg($path);
                return $resource ?: null;
            case 'image/png':
                if (!function_exists('imagecreatefrompng')) {
                    return null;
                }
                $resource = @imagecreatefrompng($path);
                return $resource ?: null;
            case 'image/webp':
                if (!function_exists('imagecreatefromwebp')) {
                    return null;
                }
                $resource = @imagecreatefromwebp($path);
                return $resource ?: null;
            default:
                return null;
        }
    }

    private function writeImageResource(
        $resource,
        string $mime,
        string $path,
        ?int $jpegQuality = null,
        ?int $webpQuality = null,
        ?int $pngCompression = null
    ): bool
    {
        $jpegQuality = $jpegQuality !== null ? $this->normalizeQuality($jpegQuality) : $this->jpegQuality;
        $webpQuality = $webpQuality !== null ? $this->normalizeQuality($webpQuality) : $this->webpQuality;
        $pngCompression = $pngCompression !== null
            ? $this->normalizePngCompression($pngCompression)
            : $this->pngCompression;

        switch ($mime) {
            case 'image/jpeg':
                return function_exists('imagejpeg') ? imagejpeg($resource, $path, $jpegQuality) : false;
            case 'image/png':
                return function_exists('imagepng') ? imagepng($resource, $path, $pngCompression) : false;
            case 'image/webp':
                return function_exists('imagewebp') ? imagewebp($resource, $path, $webpQuality) : false;
            default:
                return false;
        }
    }

    private function resizeImageResource(
        $source,
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight,
        string $mime
    ) {
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($canvas === false) {
            return null;
        }

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        if (!imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        )) {
            imagedestroy($canvas);
            return null;
        }

        return $canvas;
    }
}
