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
$router->get('/organs/create', [OrgansController::class, 'create'], ['auth', 'permission:organs.manage']);
$router->post('/organs/store', [OrgansController::class, 'store'], ['auth', 'permission:organs.manage', 'csrf']);
$router->get('/organs/show', [OrgansController::class, 'show'], ['auth', 'permission:organs.view']);
$router->get('/organs/edit', [OrgansController::class, 'edit'], ['auth', 'permission:organs.manage']);
$router->post('/organs/update', [OrgansController::class, 'update'], ['auth', 'permission:organs.manage', 'csrf']);
$router->post('/organs/delete', [OrgansController::class, 'destroy'], ['auth', 'permission:organs.manage', 'csrf']);
