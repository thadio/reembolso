<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Impressão') ?></title>
  <style>
    :root {
      --text: #1f2937;
      --muted: #6b7280;
      --line: #d1d5db;
      --surface: #ffffff;
      --chip: #eef2f7;
      --chip-info: #d9effb;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      padding: 24px;
      color: var(--text);
      background: #f9fafb;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.45;
    }

    .print-shell {
      max-width: 980px;
      margin: 0 auto;
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 24px;
    }

    .print-header {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      border-bottom: 1px solid var(--line);
      padding-bottom: 14px;
      margin-bottom: 16px;
    }

    .print-title {
      margin: 0;
      font-size: 1.35rem;
    }

    .muted {
      color: var(--muted);
      margin: 4px 0 0;
    }

    .print-actions {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .btn-print {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 8px 12px;
      color: var(--text);
      text-decoration: none;
      background: #fff;
      font-weight: 600;
      cursor: pointer;
    }

    .summary-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      margin-bottom: 16px;
    }

    .summary-item {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 8px 10px;
      background: #fbfcfd;
    }

    .timeline-list {
      display: grid;
      gap: 10px;
    }

    .timeline-item {
      border: 1px solid var(--line);
      border-left: 4px solid #9cc4d8;
      border-radius: 8px;
      padding: 10px 12px;
      break-inside: avoid;
    }

    .timeline-row {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: baseline;
    }

    .badge {
      display: inline-flex;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 700;
      background: var(--chip);
      color: #334155;
      margin-left: 8px;
    }

    .badge-info {
      background: var(--chip-info);
      color: #114b72;
    }

    .attachments {
      margin-top: 8px;
      padding-left: 18px;
    }

    @media (max-width: 760px) {
      body {
        padding: 12px;
      }

      .print-shell {
        padding: 14px;
      }

      .summary-grid {
        grid-template-columns: 1fr;
      }

      .print-header,
      .timeline-row {
        flex-direction: column;
        align-items: flex-start;
      }
    }

    @media print {
      body {
        background: #fff;
        padding: 0;
      }

      .print-shell {
        border: 0;
        border-radius: 0;
        margin: 0;
        max-width: none;
        padding: 0;
      }

      .print-actions {
        display: none;
      }

      @page {
        size: A4;
        margin: 10mm;
      }
    }
  </style>
</head>
<body>
  <?= $content ?>
</body>
</html>
