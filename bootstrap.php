<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditService;
use App\Services\EventService;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

require BASE_PATH . '/app/Core/helpers.php';
require BASE_PATH . '/app/Core/autoload.php';

Env::load(BASE_PATH . '/.env');
$config = Config::load();

date_default_timezone_set((string) $config->get('app.timezone', 'America/Sao_Paulo'));

Logger::init((string) $config->get('paths.storage_logs', BASE_PATH . '/storage/logs/app.log'));

set_exception_handler(static function (Throwable $throwable) use ($config): void {
    Logger::error('Unhandled exception', [
        'message' => $throwable->getMessage(),
        'file' => $throwable->getFile(),
        'line' => $throwable->getLine(),
    ]);

    http_response_code(500);

    if ($config->get('app.debug', false) === true) {
        echo '<pre>' . e($throwable->getMessage()) . "\n" . e($throwable->getTraceAsString()) . '</pre>';

        return;
    }

    if (isset($GLOBALS['app']) && $GLOBALS['app'] instanceof App) {
        $GLOBALS['app']->view()->render('errors/500', ['title' => 'Erro interno']);

        return;
    }

    echo 'Erro interno do servidor.';
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

Session::start($config);
$db = Database::connect($config);
$view = new View(BASE_PATH . '/app/Views', [
    'appName' => $config->get('app.name', 'Reembolso'),
]);
$request = new Request();
$audit = new AuditService($db);
$events = new EventService($db, $audit);
$auth = new Auth($db, $config, $audit);

$app = new App($config, $db, $view, $auth, $audit, $events, $request);
$router = new Router($app);
$app->setRouter($router);

$GLOBALS['app'] = $app;

return $app;
