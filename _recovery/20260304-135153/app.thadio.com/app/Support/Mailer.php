<?php

namespace App\Support;

class Mailer
{
    public static function send(string $to, string $subject, string $html): bool
    {
        $fromEmail = self::cleanHeaderValue(getenv('MAIL_FROM_EMAIL') ?: getenv('MAIL_FROM') ?: '');
        $fromName = self::cleanHeaderValue(getenv('MAIL_FROM_NAME') ?: 'Retrato App');

        if ($fromEmail === '') {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $fromEmail = 'no-reply@' . $host;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
        ];

        return mail($to, $subject, $html, implode("\r\n", $headers));
    }

    private static function cleanHeaderValue(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }
}
