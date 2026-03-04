<?php

namespace App\Support;

class Phone
{
    public static function normalizeBrazilCell(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === null || $digits === '') {
            return $value;
        }

        $length = strlen($digits);

        if ($length === 13 && substr($digits, 0, 2) === '55') {
            $ddd = substr($digits, 2, 2);
            $number = substr($digits, 4, 9);
        } elseif ($length === 12 && substr($digits, 0, 2) === '55') {
            $ddd = substr($digits, 2, 2);
            $number = '9' . substr($digits, 4, 8);
        } elseif ($length === 11) {
            $ddd = substr($digits, 0, 2);
            $number = substr($digits, 2, 9);
        } elseif ($length === 10) {
            $ddd = substr($digits, 0, 2);
            $number = '9' . substr($digits, 2, 8);
        } else {
            return $value;
        }

        if (strlen($ddd) !== 2 || strlen($number) !== 9) {
            return $value;
        }

        $prefix = substr($number, 0, 5);
        $suffix = substr($number, 5, 4);
        if (strlen($prefix) !== 5 || strlen($suffix) !== 4) {
            return $value;
        }

        return '+55 ' . $ddd . ' ' . $prefix . '-' . $suffix;
    }
}
