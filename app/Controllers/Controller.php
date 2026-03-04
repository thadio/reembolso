<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;

abstract class Controller
{
    public function __construct(protected App $app)
    {
    }

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = []): void
    {
        $authUser = $this->app->auth()->user();

        $this->app->view()->render($template, array_merge($data, [
            'authUser' => $authUser,
            'currentPath' => $this->app->request()->path(),
            'title' => $data['title'] ?? 'Reembolso',
        ]));
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }
}
