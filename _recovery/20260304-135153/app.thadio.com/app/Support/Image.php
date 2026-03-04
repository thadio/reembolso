<?php

namespace App\Support;

class Image
{
    public static function imageUrl(string $src, string $kind = 'original', ?int $size = null): string
    {
        $normalized = self::normalizeSource($src);
        if ($normalized === '') {
            return '';
        }

        $kind = strtolower(trim($kind));
        if ($kind === 'thumb' || $kind === 'thumbnail') {
            $thumbSize = $size ?? 150;
            return self::proxyUrl($normalized, $thumbSize);
        }

        return $normalized;
    }

    public static function thumbUrl(string $src, int $size = 150): string
    {
        return self::imageUrl($src, 'thumb', $size);
    }

    public static function originalUrl(string $src): string
    {
        return self::imageUrl($src, 'original');
    }

    public static function normalizeSource(string $src): string
    {
        $src = trim($src);
        if ($src === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $src)) {
            $parts = parse_url($src);
            if (!is_array($parts)) {
                return $src;
            }

            $host = strtolower((string) ($parts['host'] ?? ''));
            $path = (string) ($parts['path'] ?? '');
            if ($path !== '' && self::isAppHost($host) && str_starts_with($path, '/uploads/')) {
                return $path;
            }

            return $src;
        }

        if (str_starts_with($src, 'uploads/')) {
            return '/' . $src;
        }

        if (str_starts_with($src, '/uploads/')) {
            return $src;
        }

        return $src;
    }

    public static function proxyUrl(string $src, int $size = 150): string
    {
        $normalized = self::normalizeSource($src);
        if ($normalized === '') {
            return '';
        }

        $maxSize = (int) (getenv('THUMBNAIL_MAX_SIZE') ?: getenv('PRODUCT_IMAGE_MAX_DIMENSION') ?: 800);
        $maxSize = max(64, min(4000, $maxSize));
        $size = max(32, min($maxSize, $size));

        $encoded = rtrim(strtr(base64_encode($normalized), '+/', '-_'), '=');
        if ($encoded === '') {
            return '/thumbnail.php?src=' . rawurlencode($normalized) . '&size=' . $size;
        }

        return '/thumbnail.php?u=' . $encoded . '&size=' . $size;
    }

    private static function isAppHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        $known = [
            'app.thadio.com',
            'www.app.thadio.com',
        ];
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
}
