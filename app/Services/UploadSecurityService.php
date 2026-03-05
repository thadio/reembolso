<?php

declare(strict_types=1);

namespace App\Services;

final class UploadSecurityService
{
    public static function isSafeOriginalName(string $name, int $maxLength = 180): bool
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return false;
        }

        if (mb_strlen($trimmed) > $maxLength) {
            return false;
        }

        if (str_contains($trimmed, "\0") || str_contains($trimmed, '/') || str_contains($trimmed, '\\')) {
            return false;
        }

        if (str_contains($trimmed, '..')) {
            return false;
        }

        if (!preg_match('/^[\pL\pN _\.\-\(\)]+$/u', $trimmed)) {
            return false;
        }

        return true;
    }

    public static function isNativeUploadedFile(string $tmpName): bool
    {
        if (trim($tmpName) === '') {
            return false;
        }

        return is_uploaded_file($tmpName);
    }

    public static function matchesKnownSignature(string $tmpName, string $mimeType): bool
    {
        $handle = @fopen($tmpName, 'rb');
        if ($handle === false) {
            return false;
        }

        $bytes = fread($handle, 16);
        fclose($handle);

        if (!is_string($bytes) || $bytes === '') {
            return false;
        }

        return match ($mimeType) {
            'application/pdf' => str_starts_with($bytes, "%PDF-"),
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1A\n"),
            'image/jpeg' => str_starts_with($bytes, "\xFF\xD8\xFF"),
            default => false,
        };
    }
}
