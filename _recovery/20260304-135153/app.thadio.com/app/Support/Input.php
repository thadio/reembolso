<?php

namespace App\Support;

class Input
{
    /**
     * Aplica trim em todos os valores string do array (profundidade 1).
     */
    public static function trimStrings(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value);
            }
        }

        return $input;
    }

    /**
     * Normaliza numeros aceitando formatos pt-BR e en-US.
     */
    public static function parseNumber($value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace(["\xc2\xa0", ' '], '', $raw);
        $sign = '';
        $firstChar = $raw[0] ?? '';
        if ($firstChar === '-' || $firstChar === '+') {
            $sign = $firstChar;
            $raw = substr($raw, 1);
        }
        $raw = preg_replace('/[^0-9,\\.]/', '', $raw);
        if ($raw === '') {
            return null;
        }

        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');
        $decimalThreshold = 2;

        if ($lastComma !== false && $lastDot !== false) {
            $decimalChar = $lastComma > $lastDot ? ',' : '.';
            $thousandChar = $decimalChar === ',' ? '.' : ',';
            $raw = str_replace($thousandChar, '', $raw);
            $decimalPos = strrpos($raw, $decimalChar);
            $before = $decimalPos !== false ? substr($raw, 0, $decimalPos) : $raw;
            $after = $decimalPos !== false ? substr($raw, $decimalPos + 1) : '';
            $before = str_replace($decimalChar, '', $before);
            $after = str_replace($decimalChar, '', $after);
            $raw = $before . ($decimalPos !== false ? '.' : '') . $after;
        } elseif ($lastComma !== false) {
            $raw = self::normalizeSingleSeparator($raw, ',', $decimalThreshold);
        } elseif ($lastDot !== false) {
            $raw = self::normalizeSingleSeparator($raw, '.', $decimalThreshold);
        }

        $raw = $sign . $raw;
        if ($raw === '' || $raw === '-' || $raw === '+') {
            return null;
        }
        if (!is_numeric($raw)) {
            return null;
        }
        return (float) $raw;
    }

    private static function normalizeSingleSeparator(string $raw, string $separator, int $decimalThreshold): string
    {
        $count = substr_count($raw, $separator);
        if ($count === 0) {
            return $raw;
        }
        $lastPos = strrpos($raw, $separator);
        $digitsAfter = $lastPos !== false ? (strlen($raw) - $lastPos - 1) : 0;
        if ($count > 1) {
            if ($digitsAfter <= $decimalThreshold) {
                $before = $lastPos !== false ? substr($raw, 0, $lastPos) : $raw;
                $after = $lastPos !== false ? substr($raw, $lastPos + 1) : '';
                $before = str_replace($separator, '', $before);
                $after = str_replace($separator, '', $after);
                return $before . '.' . $after;
            }
            return str_replace($separator, '', $raw);
        }
        if ($digitsAfter <= $decimalThreshold) {
            return str_replace($separator, '.', $raw);
        }
        return str_replace($separator, '', $raw);
    }
}
