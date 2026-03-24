<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Services\ModuleCatalogService;

abstract class Controller
{
    public function __construct(protected App $app)
    {
    }

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = []): void
    {
        $authUser = $this->app->auth()->user();
        $currentPath = $this->app->request()->path();
        $moduleCatalog = new ModuleCatalogService($this->app->config());
        $availableModules = $moduleCatalog->availableForUser($authUser);
        $activeModuleSlug = (string) ($moduleCatalog->activeModuleSlugForPath($authUser, $currentPath) ?? '');
        $activeModule = null;

        foreach ($availableModules as $module) {
            if ((string) ($module['slug'] ?? '') !== $activeModuleSlug) {
                continue;
            }

            $activeModule = $module;
            break;
        }

        $this->app->view()->render($template, array_merge($data, [
            'authUser' => $authUser,
            'currentPath' => $currentPath,
            'availableModules' => $availableModules,
            'activeModule' => $activeModule,
            'activeModuleSlug' => $activeModuleSlug,
            'title' => $data['title'] ?? 'Reembolso',
        ]));
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }
}
