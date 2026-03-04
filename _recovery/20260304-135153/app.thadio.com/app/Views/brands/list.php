<?php
/**
 * Legacy compatibility view.
 * Routes that still reference brands/list.php are forwarded to the
 * server-side implementation in brands/list-catalog.php.
 */

$rows = $rows ?? [];
if (is_array($rows) && !empty($rows)) {
    $normalized = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $row['id'] = (int) ($row['id'] ?? $row['term_id'] ?? 0);
        $row['product_count'] = (int) ($row['product_count'] ?? $row['count'] ?? 0);
        $row['status'] = (string) ($row['status'] ?? 'ativa');
        $normalized[] = $row;
    }
    $rows = $normalized;
}

require __DIR__ . '/list-catalog.php';
