<?php

require __DIR__ . '/bootstrap.php';

[$pdo, $connectionError] = bootstrapPdo();
requirePermission($pdo, 'delivery_types.view');

http_response_code(410);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Módulo descontinuado</title>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body style="max-width:900px;margin:40px auto;padding:0 16px;">
  <h1>Tipos de entrega (legado) descontinuado</h1>
  <p>O cadastro legado de tipos de entrega foi removido do fluxo operacional.</p>
  <p>Use o modelo unificado na tela de pedidos (`Entrega` + `Tipo de envio`) e o cadastro de transportadoras.</p>
  <p>
    <a class="btn primary" href="transportadora-list.php">Ir para transportadoras</a>
    <a class="btn ghost" href="pedido-cadastro.php" style="margin-left:8px;">Ir para pedidos</a>
  </p>
</body>
</html>
