<?php
// Endpoint leve para medir a latência do servidor sem carregar o MVC completo.
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$start = microtime(true);
$payload = [
  'status' => 'ok',
  'timestamp' => time(),
  'php_version' => PHP_VERSION,
  'memory_bytes' => memory_get_usage(true),
];
$payload['execution_ms'] = round((microtime(true) - $start) * 1000, 2);
http_response_code(200);
echo json_encode($payload);
