<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    /** @param array<string, mixed> $shared */
    public function __construct(private string $basePath, private array $shared = [])
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], string $layout = 'layout'): void
    {
        $templateFile = $this->basePath . '/' . $template . '.php';

        if (!is_file($templateFile)) {
            throw new \RuntimeException('Template not found: ' . $templateFile);
        }

        $data = array_merge($this->shared, $data, [
            'flash_success' => Session::getFlash('success'),
            'flash_error' => Session::getFlash('error'),
        ]);

        extract($data, EXTR_SKIP);

        ob_start();
        require $templateFile;
        $content = (string) ob_get_clean();

        $layoutFile = $this->basePath . '/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            echo $content;

            return;
        }

        require $layoutFile;
    }
}
