<?php
// Variaveis disponiveis: $title, $content
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($title ?? 'Retrato App', ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="assets/app.css">
  <style>
    body {
      background: #fff;
      padding: 0;
    }
    .print-shell {
      max-width: 860px;
      margin: 0 auto;
      padding: 32px 24px 48px;
    }
    .print-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-bottom: 24px;
    }
    .doc-meta {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
      margin: 16px 0 20px;
    }
    .doc-meta div {
      padding: 12px 14px;
      border: 1px solid #dfe3eb;
      border-radius: 12px;
      background: #f9fafc;
      font-size: 14px;
    }
    .doc-meta strong {
      display: block;
      font-size: 12px;
      text-transform: uppercase;
      color: #6b7280;
      letter-spacing: 0.08em;
      margin-bottom: 6px;
    }
    .doc-section {
      margin-top: 24px;
    }
    .signature-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 24px;
      margin-top: 32px;
    }
    .signature-line {
      margin-top: 40px;
      border-top: 1px solid #111827;
      padding-top: 8px;
      font-size: 13px;
      text-align: center;
    }
    @media print {
      body {
        background: #fff;
      }
      .print-actions {
        display: none;
      }
      .print-shell {
        padding: 0;
        max-width: 100%;
      }
    }
  </style>
</head>
<body>
  <main class="print-shell">
    <?php echo $content; ?>
  </main>
</body>
</html>
