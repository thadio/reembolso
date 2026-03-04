<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;

final class HealthController extends Controller
{
    public function index(Request $request): void
    {
        $checks = [
            'database' => 'ok',
            'storage_logs' => is_writable(dirname((string) $this->app->config()->get('paths.storage_logs'))),
            'storage_uploads' => is_writable((string) $this->app->config()->get('paths.storage_uploads')),
        ];

        try {
            $this->app->db()->query('SELECT 1');
        } catch (\Throwable $throwable) {
            $checks['database'] = 'error';
        }

        $isHealthy = $checks['database'] === 'ok' && $checks['storage_logs'] === true && $checks['storage_uploads'] === true;

        http_response_code($isHealthy ? 200 : 503);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'timestamp' => date(DATE_ATOM),
            'checks' => $checks,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
