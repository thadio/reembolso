<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\AuditService;
use App\Services\EventService;
use PDO;

final class App
{
    private ?Router $router = null;

    public function __construct(
        private Config $config,
        private PDO $db,
        private View $view,
        private Auth $auth,
        private AuditService $audit,
        private EventService $events,
        private Request $request,
    ) {
    }

    public function setRouter(Router $router): void
    {
        $this->router = $router;
    }

    public function router(): Router
    {
        if ($this->router === null) {
            throw new \RuntimeException('Router not initialized.');
        }

        return $this->router;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function db(): PDO
    {
        return $this->db;
    }

    public function view(): View
    {
        return $this->view;
    }

    public function auth(): Auth
    {
        return $this->auth;
    }

    public function audit(): AuditService
    {
        return $this->audit;
    }

    public function events(): EventService
    {
        return $this->events;
    }

    public function request(): Request
    {
        return $this->request;
    }
}
