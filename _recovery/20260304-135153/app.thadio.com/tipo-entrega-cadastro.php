<?php

require __DIR__ . '/bootstrap.php';

[$pdo, $connectionError] = bootstrapPdo();
$editing = (isset($_GET['id']) && $_GET['id'] !== '') || (isset($_POST['id']) && $_POST['id'] !== '');
requirePermission($pdo, $editing ? 'delivery_types.edit' : 'delivery_types.create');

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
  <h1>Cadastro de tipo de entrega descontinuado</h1>
  <p>O sistema agora usa o modelo unificado de entrega em pedidos.</p>
  <p>Para logística rastreável/local, utilize o cadastro de transportadoras.</p>
  <p>
    <a class="btn primary" href="transportadora-cadastro.php">Cadastrar transportadora</a>
    <a class="btn ghost" href="pedido-cadastro.php" style="margin-left:8px;">Ir para pedidos</a>
  </p>
</body>
</html>
