<?php

namespace App\Support;

class Html
{
    public static function esc($value): string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
