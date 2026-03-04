<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\OrgansController;
use App\Controllers\PeopleController;

$app = app();
$router = $app->router();

$router->get('/health', [HealthController::class, 'index']);

$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest', 'csrf']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);

$router->get('/', [DashboardController::class, 'index'], ['auth', 'permission:dashboard.view']);
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth', 'permission:dashboard.view']);
$router->get('/people', [PeopleController::class, 'index'], ['auth', 'permission:people.view']);
$router->get('/organs', [OrgansController::class, 'index'], ['auth', 'permission:organs.view']);
