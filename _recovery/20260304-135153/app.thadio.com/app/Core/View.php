<?php

namespace App\Core;

/**
 * Renderizador simples com suporte a layout.
 */
class View
{
    public static function render(string $template, array $data = [], array $layoutData = []): void
    {
        $templateFile = __DIR__ . '/../Views/' . ltrim($template, '/');
        if (substr($templateFile, -4) !== '.php') {
            $templateFile .= '.php';
        }

        if (!file_exists($templateFile)) {
            throw new \RuntimeException("View {$template} não encontrada.");
        }

        extract($data, EXTR_SKIP);
        if (!isset($esc) || !is_callable($esc)) {
            $esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }
        ob_start();
        include $templateFile;
        $content = ob_get_clean();

        $layout = $layoutData['layout'] ?? __DIR__ . '/../Views/layout.php';
        $title = $layoutData['title'] ?? 'Retrato App';

        if ($layout === null) {
            echo $content;
            return;
        }

        if (is_string($layout) && $layout !== '' && !str_contains($layout, '/')
            && !str_contains($layout, '\\')) {
            $resolvedLayout = __DIR__ . '/../Views/' . ltrim($layout, '/');
            if (substr($resolvedLayout, -4) !== '.php') {
                $resolvedLayout .= '.php';
            }
            $layout = $resolvedLayout;
        }

        if (!is_string($layout) || $layout === '' || !file_exists($layout)) {
            throw new \RuntimeException('Layout de view não encontrado: ' . (string) $layout);
        }

        include $layout;
    }
}
