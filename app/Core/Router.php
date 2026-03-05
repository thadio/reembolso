<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array<string, mixed>> */
    private array $routes = [];

    public function __construct(private App $app)
    {
    }

    /** @param array<int, string> $middlewares */
    public function get(string $path, array $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    /** @param array<int, string> $middlewares */
    public function post(string $path, array $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    /** @param array<int, string> $middlewares */
    private function addRoute(string $method, string $path, array $handler, array $middlewares): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->normalizePath($path),
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->normalizePath((string) parse_url($uri, PHP_URL_PATH));
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || $route['path'] !== $path) {
                continue;
            }

            if (!$this->runMiddlewares($route['middlewares'])) {
                return;
            }

            [$controllerClass, $controllerMethod] = $route['handler'];
            $controller = new $controllerClass($this->app);
            $controller->{$controllerMethod}($this->app->request());

            return;
        }

        http_response_code(404);
        $this->app->view()->render('errors/404', ['title' => 'Página não encontrada']);
    }

    /** @param array<int, string> $middlewares */
    private function runMiddlewares(array $middlewares): bool
    {
        foreach ($middlewares as $middleware) {
            if ($middleware === 'auth') {
                if (!$this->app->auth()->check()) {
                    flash('error', 'Faça login para continuar.');
                    $this->redirect('/login');

                    return false;
                }

                if ($this->app->auth()->passwordExpired()) {
                    $currentPath = $this->app->request()->path();
                    $allowedPaths = [
                        '/users/password',
                        '/users/password/update',
                        '/logout',
                    ];

                    if (!in_array($currentPath, $allowedPaths, true)) {
                        flash('error', 'Sua senha expirou. Atualize a senha para continuar.');
                        $this->redirect('/users/password');

                        return false;
                    }
                }

                continue;
            }

            if ($middleware === 'guest') {
                if ($this->app->auth()->check()) {
                    $this->redirect('/dashboard');

                    return false;
                }

                continue;
            }

            if ($middleware === 'csrf') {
                $token = $this->app->request()->input('_token');
                if (!Csrf::validate($token)) {
                    flash('error', 'Token CSRF inválido. Atualize a página e tente novamente.');
                    $this->redirect('/login');

                    return false;
                }

                continue;
            }

            if (str_starts_with($middleware, 'permission:')) {
                $permission = substr($middleware, strlen('permission:'));
                if (!$this->app->auth()->hasPermission($permission)) {
                    http_response_code(403);
                    $this->app->view()->render('errors/403', ['title' => 'Acesso negado']);

                    return false;
                }
            }
        }

        return true;
    }

    private function normalizePath(string $path): string
    {
        $trimmed = '/' . trim($path, '/');

        return $trimmed === '//' ? '/' : $trimmed;
    }

    private function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }
}
