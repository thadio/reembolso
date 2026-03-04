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
$router->get('/people/create', [PeopleController::class, 'create'], ['auth', 'permission:people.manage']);
$router->post('/people/store', [PeopleController::class, 'store'], ['auth', 'permission:people.manage', 'csrf']);
$router->get('/people/show', [PeopleController::class, 'show'], ['auth', 'permission:people.view']);
$router->get('/people/edit', [PeopleController::class, 'edit'], ['auth', 'permission:people.manage']);
$router->post('/people/update', [PeopleController::class, 'update'], ['auth', 'permission:people.manage', 'csrf']);
$router->post('/people/delete', [PeopleController::class, 'destroy'], ['auth', 'permission:people.manage', 'csrf']);
$router->post('/people/pipeline/advance', [PeopleController::class, 'advancePipeline'], ['auth', 'permission:people.manage', 'csrf']);
$router->post('/people/timeline/store', [PeopleController::class, 'storeTimelineEvent'], ['auth', 'permission:people.manage', 'csrf']);
$router->post('/people/timeline/rectify', [PeopleController::class, 'rectifyTimelineEvent'], ['auth', 'permission:people.manage', 'csrf']);
$router->get('/people/timeline/attachment', [PeopleController::class, 'downloadTimelineAttachment'], ['auth', 'permission:people.view']);
$router->get('/people/timeline/print', [PeopleController::class, 'timelinePrint'], ['auth', 'permission:people.view']);
$router->post('/people/documents/store', [PeopleController::class, 'storeDocument'], ['auth', 'permission:people.manage', 'csrf']);
$router->get('/people/documents/download', [PeopleController::class, 'downloadDocument'], ['auth', 'permission:people.view']);
$router->post('/people/costs/version/create', [PeopleController::class, 'createCostVersion'], ['auth', 'permission:people.manage', 'csrf']);
$router->post('/people/costs/item/store', [PeopleController::class, 'storeCostItem'], ['auth', 'permission:people.manage', 'csrf']);
$router->get('/people/audit/export', [PeopleController::class, 'exportAudit'], ['auth', 'permission:audit.view']);
$router->get('/organs', [OrgansController::class, 'index'], ['auth', 'permission:organs.view']);
$router->get('/organs/create', [OrgansController::class, 'create'], ['auth', 'permission:organs.manage']);
$router->post('/organs/store', [OrgansController::class, 'store'], ['auth', 'permission:organs.manage', 'csrf']);
$router->get('/organs/show', [OrgansController::class, 'show'], ['auth', 'permission:organs.view']);
$router->get('/organs/edit', [OrgansController::class, 'edit'], ['auth', 'permission:organs.manage']);
$router->post('/organs/update', [OrgansController::class, 'update'], ['auth', 'permission:organs.manage', 'csrf']);
$router->post('/organs/delete', [OrgansController::class, 'destroy'], ['auth', 'permission:organs.manage', 'csrf']);
