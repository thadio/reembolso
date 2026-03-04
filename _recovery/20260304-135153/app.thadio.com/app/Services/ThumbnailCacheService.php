<?php

namespace App\Services;

class ThumbnailCacheService
{
    private string $cacheDir;
    private string $projectRoot;
    private int $maxSize;

    public function __construct(?string $cacheDir = null, ?int $maxSize = null)
    {
        $this->projectRoot = dirname(__DIR__, 2);
        if ($cacheDir === null) {
            $cacheDir = $this->projectRoot . '/uploads/thumbs';
        }
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->maxSize = $this->normalizeMaxSize($maxSize);
        if ($this->cacheDir === '') {
            throw new \RuntimeException('Cache directory não pode ser vazio.');
        }
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }
    }

    /**
     * @param string $url
     * @param int $size
     * @return string|null Caminho absoluto do arquivo cacheado
     */
    public function ensureCached(string $source, int $size): ?string
    {
        $source = trim($source);
        if ($source === '') {
            return null;
        }

        $size = max(32, min($this->maxSize, $size));
        $normalizedSource = $this->normalizeSource($source);
        $hash = sha1($normalizedSource . '|' . $size);
        $subdir = substr($hash, 0, 2);
        $targetDir = $this->cacheDir . '/' . $subdir;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }
        $targetPath = $targetDir . '/' . $hash . '.jpg';
        if (is_file($targetPath)) {
            return $targetPath;
        }

        $data = $this->fetchSourceData($normalizedSource);
        if ($data === null) {
            return null;
        }

        $source = @imagecreatefromstring($data);
        if (!$source) {
            return null;
        }

        $thumb = $this->resizeResource($source, $size);
        imagedestroy($source);
        if (!$thumb) {
            return null;
        }

        imagejpeg($thumb, $targetPath, 82);
        imagedestroy($thumb);
        return $targetPath;
    }

    private function fetchSourceData(string $source): ?string
    {
        if (preg_match('#^https?://#i', $source)) {
            return $this->fetchRemote($source);
        }

        $localPath = $this->resolveLocalPath($source);
        if ($localPath === null || !is_file($localPath) || !is_readable($localPath)) {
            return null;
        }

        $data = @file_get_contents($localPath);
        return is_string($data) ? $data : null;
    }

    private function fetchRemote(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'RetratoApp Thumbnail Proxy',
            ]);
            $data = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 400 || $data === false) {
                return null;
            }
            return is_string($data) ? $data : null;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 6,
                'header' => 'User-Agent: RetratoApp Thumbnail Proxy',
            ],
            'https' => [
                'timeout' => 6,
                'header' => 'User-Agent: RetratoApp Thumbnail Proxy',
            ],
        ]);
        $data = @file_get_contents($url, false, $context);
        return $data === false ? null : $data;
    }

    private function normalizeSource(string $source): string
    {
        if (!preg_match('#^https?://#i', $source)) {
            if (str_starts_with($source, 'uploads/')) {
                return '/' . $source;
            }
            return $source;
        }

        $parts = parse_url($source);
        if (!is_array($parts)) {
            return $source;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        if ($path === '') {
            return $source;
        }

        if ($this->isAppHost($host) && str_starts_with($path, '/uploads/')) {
            return $path;
        }

        return $source;
    }

    private function resolveLocalPath(string $source): ?string
    {
        $path = trim($source);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'uploads/')) {
            $path = '/' . $path;
        }

        if (!str_starts_with($path, '/uploads/')) {
            return null;
        }

        $absolute = $this->projectRoot . $path;
        $dir = dirname($absolute);
        $realDir = realpath($dir);
        if ($realDir === false) {
            return null;
        }

        $uploadsRoot = realpath($this->projectRoot . '/uploads');
        if ($uploadsRoot === false) {
            return null;
        }

        if (strpos($realDir, $uploadsRoot) !== 0) {
            return null;
        }

        return $realDir . '/' . basename($absolute);
    }

    private function isAppHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        $known = ['app.thadio.com', 'www.app.thadio.com'];
        if (in_array($host, $known, true)) {
            return true;
        }

        $baseUrl = trim((string) (getenv('APP_BASE_URL') ?: ''));
        if ($baseUrl !== '') {
            $baseHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
            if ($baseHost !== '' && $host === $baseHost) {
                return true;
            }
        }

        return false;
    }

    private function resizeResource($source, int $size)
    {
        $width = imagesx($source);
        $height = imagesy($source);
        if ($width <= 0 || $height <= 0) {
            return null;
        }
        $ratio = min($size / $width, $size / $height, 1);
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        if (!imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height
        )) {
            imagedestroy($canvas);
            return null;
        }

        return $canvas;
    }

    private function normalizeMaxSize(?int $maxSize): int
    {
        $raw = $maxSize;
        if ($raw === null) {
            $envValue = getenv('THUMBNAIL_MAX_SIZE');
            if ($envValue === false || $envValue === '') {
                $envValue = getenv('PRODUCT_IMAGE_MAX_DIMENSION');
            }
            $raw = is_numeric($envValue) ? (int) $envValue : 600;
        }

        if ($raw < 64) {
            return 64;
        }
        if ($raw > 4000) {
            return 4000;
        }
        return $raw;
    }
}
