<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/routes/web.php';

$app->router()->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
