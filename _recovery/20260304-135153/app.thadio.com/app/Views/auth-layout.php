<?php
// Variáveis disponíveis: $title, $content
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($title ?? 'Entrar', ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="icon" href="/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/app.css">
  <style>
    body.auth-body {
      margin: 0;
      font-family: "Space Grotesk","Segoe UI",system-ui,sans-serif;
      min-height: 100vh;
      display: grid;
      place-items: center;
      background: radial-gradient(60% 60% at 20% 20%, rgba(63,124,255,0.12), transparent 40%), radial-gradient(60% 60% at 80% 20%, rgba(0,198,174,0.12), transparent 38%), var(--bg);
      padding: 32px;
      color: var(--ink);
    }
    .auth-shell {
      width: min(1100px, 100%);
      display: grid;
      grid-template-columns: 1.05fr 0.95fr;
      gap: 16px;
    }
    .auth-card, .auth-panel {
      background: #ffffff;
      border: 1px solid #eef2f7;
      border-radius: 18px;
      padding: 26px;
      box-shadow: 0 18px 42px rgba(15, 23, 42, 0.12);
    }
    .auth-panel {
      background: linear-gradient(135deg, rgba(63,124,255,0.14), rgba(0,198,174,0.12));
      border: 1px solid #c7ddff;
      display: grid;
      gap: 12px;
    }
    .auth-lead {
      color: var(--muted);
      font-size: 15px;
      margin: 6px 0 18px;
    }
    .auth-meta {
      color: var(--muted);
      font-size: 13px;
      margin-top: 14px;
    }
    .auth-highlight {
      display: grid;
      gap: 10px;
    }
    .auth-highlight .pill {
      background: rgba(15, 23, 42, 0.08);
      color: #0f172a;
    }
    .auth-panel h3 {
      margin: 0;
      font-size: 20px;
      letter-spacing: -0.01em;
    }
    .auth-panel ul {
      padding-left: 16px;
      margin: 8px 0 0;
      color: #0f172a;
      font-weight: 600;
      display: grid;
      gap: 8px;
    }
    .auth-panel small {
      color: #0f172a;
      opacity: 0.7;
      font-weight: 500;
    }
    @media (max-width: 960px) {
      .auth-shell { grid-template-columns: 1fr; }
      body.auth-body { padding: 18px; }
    }
  </style>
</head>
<body class="auth-body">
  <div class="auth-shell">
    <?php echo $content; ?>
  </div>
</body>
</html>
