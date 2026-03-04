<?php

declare(strict_types=1);

require __DIR__ . '/lib/image_migration_lib.php';

$args = getopt('', [
    'execute',
    'dry-run',
    'limit::',
    'offset::',
    'check-existing',
    'copy-only',
    'db-only',
    'skip-after-inventory',
    'ftp-host::',
    'ftp-port::',
    'ftp-user::',
    'ftp-pass::',
    'ftp-app-root::',
    'ftp-legacy-root::',
    'target-base::',
    'max-dirs::',
    'no-resume',
]);

$execute = isset($args['execute']) && !isset($args['dry-run']);
$limit = isset($args['limit']) ? max(1, (int) $args['limit']) : 0;
$offset = isset($args['offset']) ? max(0, (int) $args['offset']) : 0;
$checkExisting = isset($args['check-existing']);
$copyOnly = isset($args['copy-only']);
$dbOnly = isset($args['db-only']);
$skipAfterInventory = isset($args['skip-after-inventory']);
$targetBase = trim((string) ($args['target-base'] ?? 'uploads/products/legacy'), '/');
$maxDirs = max(100, (int) ($args['max-dirs'] ?? 25000));
$resumeFromLog = !isset($args['no-resume']);

$logDir = imageMigrationLogDir();
$copyLogFile = $logDir . '/copy_log.txt';
$errorsCsv = $logDir . '/errors.csv';
$brokenCsv = $logDir . '/broken_links_after.csv';

if (!is_file($copyLogFile)) {
    file_put_contents($copyLogFile, "timestamp\tstatus\tsource\ttarget\tsha256_before\tsha256_after\tmd5_before\tmd5_after\tbytes\tmessage\n");
}
if (!is_file($errorsCsv)) {
    file_put_contents($errorsCsv, "timestamp,stage,source,target,message\n");
}

$db = imageMigrationBootstrapDb();
$pdo = $db['pdo'];
$ftp = imageMigrationFtpConfig($args);

$refs = imageMigrationCollectDbImageRefs($pdo);
$entries = [];
$copyMap = [];
$sourceToTarget = [];
$classCounters = [];

foreach ($refs as $ref) {
    $source = trim((string) ($ref['source'] ?? ''));
    $mapped = imageMigrationMapSourceToTarget($source, $targetBase, $ftp);

    $entry = [
        'table' => (string) $ref['table'],
        'id' => (int) $ref['id'],
        'field' => (string) $ref['field'],
        'source' => $source,
        'class' => $mapped['class'],
        'target_path' => $mapped['target_path'],
        'source_ftp_path' => $mapped['source_ftp_path'],
        'reason' => $mapped['reason'],
    ];
    $entries[] = $entry;
    $classCounters[$mapped['class']] = ($classCounters[$mapped['class']] ?? 0) + 1;

    if (is_string($mapped['target_path']) && $mapped['target_path'] !== '' && $source !== $mapped['target_path']) {
        $sourceToTarget[$source] = $mapped['target_path'];
    }

    if ($mapped['class'] === 'legacy_wp_upload' && is_string($mapped['source_ftp_path']) && is_string($mapped['target_path'])) {
        $copyKey = $mapped['source_ftp_path'] . '|' . $mapped['target_path'];
        if (!isset($copyMap[$copyKey])) {
            $copyMap[$copyKey] = [
                'source_ftp_path' => $mapped['source_ftp_path'],
                'target_path' => $mapped['target_path'],
            ];
        }
    }
}

$copiedEntriesFromLog = 0;
$skippedFromLog = 0;
$completedKeys = [];
if ($resumeFromLog && is_file($copyLogFile)) {
    $lines = file($copyLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $idx => $line) {
        if ($idx === 0) {
            continue;
        }
        $parts = explode("\t", $line);
        $status = trim((string) ($parts[1] ?? ''));
        if ($status !== 'copied_verified' && $status !== 'already_verified') {
            continue;
        }
        $src = trim((string) ($parts[2] ?? ''));
        $dst = trim((string) ($parts[3] ?? ''));
        if ($src === '' || $dst === '') {
            continue;
        }
        $completedKeys[$src . '|' . $dst] = true;
    }
    $copiedEntriesFromLog = count($completedKeys);
}

$copiesAll = array_values($copyMap);
$copiesPendingAll = [];
foreach ($copiesAll as $copy) {
    $srcRemote = (string) $copy['source_ftp_path'];
    $targetPath = (string) $copy['target_path'];
    $dstRemote = trim($ftp['app_root'], '/') . '/' . ltrim($targetPath, '/');
    $copyKey = $srcRemote . '|' . $dstRemote;
    if (isset($completedKeys[$copyKey])) {
        $skippedFromLog++;
        continue;
    }
    $copiesPendingAll[] = $copy;
}

$copies = $copiesPendingAll;
if ($offset > 0 || $limit > 0) {
    $copies = array_slice($copiesPendingAll, $offset, $limit > 0 ? $limit : null);
}

$plan = [
    'generated_at' => date('c'),
    'execute' => $execute,
    'references_total' => count($entries),
    'class_counters' => $classCounters,
    'copy_candidates_total' => count($copiesAll),
    'copy_pending_total' => count($copiesPendingAll),
    'copy_selected' => count($copies),
    'offset' => $offset,
    'limit' => $limit,
    'target_base' => '/' . $targetBase,
    'check_existing' => $checkExisting,
    'resume_from_log' => $resumeFromLog,
    'copied_entries_found_in_log' => $copiedEntriesFromLog,
    'skipped_from_log' => $skippedFromLog,
    'copy_only' => $copyOnly,
    'db_only' => $dbOnly,
    'entries_sample' => array_slice($entries, 0, 300),
    'copies' => $copies,
];
imageMigrationWriteJson($logDir . '/migration_plan.json', $plan);

$copyResult = [
    'attempted' => 0,
    'copied' => 0,
    'already_verified' => 0,
    'failed' => 0,
    'skipped_from_log' => $skippedFromLog,
];

$tempDir = sys_get_temp_dir() . '/image_migrate_' . date('Ymd_His') . '_' . substr(sha1((string) microtime(true)), 0, 8);
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0700, true);
}

if ($execute && !$dbOnly) {
    $totalCopies = count($copies);

    foreach ($copies as $idx => $copy) {
        $copyResult['attempted']++;

        $srcRemote = (string) $copy['source_ftp_path'];
        $targetPath = (string) $copy['target_path'];
        $dstRemote = trim($ftp['app_root'], '/') . '/' . ltrim($targetPath, '/');

        $tmpSrc = $tempDir . '/src_' . $idx;
        $tmpDst = $tempDir . '/dst_' . $idx;

        $dlSrc = imageMigrationFtpDownload($ftp, $srcRemote, $tmpSrc);
        if (!$dlSrc['ok']) {
            $copyResult['failed']++;
            file_put_contents($errorsCsv, sprintf(
                "%s,%s,%s,%s,%s\n",
                date('c'),
                'download-source',
                str_replace(',', ';', $srcRemote),
                str_replace(',', ';', $dstRemote),
                str_replace(',', ';', $dlSrc['error'])
            ), FILE_APPEND);
            continue;
        }

        $shaBefore = hash_file('sha256', $tmpSrc) ?: '';
        $md5Before = md5_file($tmpSrc) ?: '';
        $bytes = (int) filesize($tmpSrc);

        if ($checkExisting) {
            $destExists = imageMigrationFtpDownload($ftp, $dstRemote, $tmpDst);
            if ($destExists['ok']) {
                $shaExisting = hash_file('sha256', $tmpDst) ?: '';
                if ($shaExisting !== '' && $shaExisting === $shaBefore) {
                    $copyResult['already_verified']++;
                    file_put_contents($copyLogFile, implode("\t", [
                        date('c'),
                        'already_verified',
                        $srcRemote,
                        $dstRemote,
                        $shaBefore,
                        $shaExisting,
                        $md5Before,
                        md5_file($tmpDst) ?: '',
                        (string) $bytes,
                        'arquivo-destino-ja-confere',
                    ]) . PHP_EOL, FILE_APPEND);
                    @unlink($tmpSrc);
                    @unlink($tmpDst);
                    if ((($idx + 1) % 25) === 0 || ($idx + 1) === $totalCopies) {
                        echo 'copy-progress ' . ($idx + 1) . '/' . $totalCopies
                            . ' copied=' . $copyResult['copied']
                            . ' verified=' . $copyResult['already_verified']
                            . ' failed=' . $copyResult['failed'] . PHP_EOL;
                    }
                    continue;
                }
            }
        }

        $up = imageMigrationFtpUpload($ftp, $tmpSrc, $dstRemote);
        if (!$up['ok']) {
            $copyResult['failed']++;
            file_put_contents($errorsCsv, sprintf(
                "%s,%s,%s,%s,%s\n",
                date('c'),
                'upload-target',
                str_replace(',', ';', $srcRemote),
                str_replace(',', ';', $dstRemote),
                str_replace(',', ';', $up['error'])
            ), FILE_APPEND);
            @unlink($tmpSrc);
            @unlink($tmpDst);
            continue;
        }

        @unlink($tmpDst);
        $verify = imageMigrationFtpDownload($ftp, $dstRemote, $tmpDst);
        if (!$verify['ok']) {
            $copyResult['failed']++;
            file_put_contents($errorsCsv, sprintf(
                "%s,%s,%s,%s,%s\n",
                date('c'),
                'verify-download',
                str_replace(',', ';', $srcRemote),
                str_replace(',', ';', $dstRemote),
                str_replace(',', ';', $verify['error'])
            ), FILE_APPEND);
            @unlink($tmpSrc);
            @unlink($tmpDst);
            continue;
        }

        $shaAfter = hash_file('sha256', $tmpDst) ?: '';
        $md5After = md5_file($tmpDst) ?: '';

        if ($shaBefore === '' || $shaAfter === '' || !hash_equals($shaBefore, $shaAfter)) {
            $copyResult['failed']++;
            file_put_contents($errorsCsv, sprintf(
                "%s,%s,%s,%s,%s\n",
                date('c'),
                'checksum',
                str_replace(',', ';', $srcRemote),
                str_replace(',', ';', $dstRemote),
                str_replace(',', ';', 'sha256 before=' . $shaBefore . ' after=' . $shaAfter)
            ), FILE_APPEND);
            file_put_contents($copyLogFile, implode("\t", [
                date('c'),
                'checksum_failed',
                $srcRemote,
                $dstRemote,
                $shaBefore,
                $shaAfter,
                $md5Before,
                $md5After,
                (string) $bytes,
                'checksum-divergente',
            ]) . PHP_EOL, FILE_APPEND);
            @unlink($tmpSrc);
            @unlink($tmpDst);
            continue;
        }

        $copyResult['copied']++;
        file_put_contents($copyLogFile, implode("\t", [
            date('c'),
            'copied_verified',
            $srcRemote,
            $dstRemote,
            $shaBefore,
            $shaAfter,
            $md5Before,
            $md5After,
            (string) $bytes,
            'ok',
        ]) . PHP_EOL, FILE_APPEND);

        @unlink($tmpSrc);
        @unlink($tmpDst);

        if ((($idx + 1) % 25) === 0 || ($idx + 1) === $totalCopies) {
            echo 'copy-progress ' . ($idx + 1) . '/' . $totalCopies
                . ' copied=' . $copyResult['copied']
                . ' verified=' . $copyResult['already_verified']
                . ' failed=' . $copyResult['failed'] . PHP_EOL;
        }
    }
}

$productUpdated = 0;
$productScanned = 0;
$sacolinhaUpdated = 0;

$shouldSwitchDb = $execute && !$copyOnly && ($offset === 0 && $limit === 0);
$shouldBuildBroken = $execute && !$copyOnly && ($offset === 0 && $limit === 0);

if ($shouldSwitchDb || $dbOnly) {
    $stmt = $pdo->query('SELECT sku, metadata FROM products');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $productScanned++;
        $sku = (int) ($row['sku'] ?? 0);
        if ($sku <= 0) {
            continue;
        }
        $raw = (string) ($row['metadata'] ?? '');
        $meta = json_decode($raw, true);
        if (!is_array($meta) || !isset($meta['images']) || !is_array($meta['images'])) {
            continue;
        }

        $changed = false;
        foreach ($meta['images'] as $i => $img) {
            if (!is_array($img)) {
                continue;
            }
            $src = trim((string) ($img['src'] ?? ''));
            if ($src === '' || !isset($sourceToTarget[$src])) {
                continue;
            }
            $newSrc = $sourceToTarget[$src];
            if ($newSrc !== '' && $newSrc !== $src) {
                $meta['images'][$i]['src'] = $newSrc;
                $changed = true;
            }
        }

        if (isset($meta['image_src']) && is_string($meta['image_src'])) {
            $old = trim($meta['image_src']);
            if ($old !== '' && isset($sourceToTarget[$old])) {
                $meta['image_src'] = $sourceToTarget[$old];
                $changed = true;
            }
        }
        if (isset($meta['image_url']) && is_string($meta['image_url'])) {
            $old = trim($meta['image_url']);
            if ($old !== '' && isset($sourceToTarget[$old])) {
                $meta['image_url'] = $sourceToTarget[$old];
                $changed = true;
            }
        }

        if (!$changed) {
            continue;
        }

        $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            file_put_contents($errorsCsv, sprintf(
                "%s,%s,%s,%s,%s\n",
                date('c'),
                'db-encode-products',
                'products/' . $sku,
                '',
                'falha-json'
            ), FILE_APPEND);
            continue;
        }

        $up = $pdo->prepare('UPDATE products SET metadata = :m WHERE sku = :sku');
        $up->execute([':m' => $json, ':sku' => $sku]);
        if ($up->rowCount() > 0) {
            $productUpdated++;
        }
    }

    try {
        $stmt = $pdo->query('SELECT id, image_url FROM sacolinha_itens');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            $old = trim((string) ($row['image_url'] ?? ''));
            if ($id <= 0 || $old === '' || !isset($sourceToTarget[$old])) {
                continue;
            }
            $new = $sourceToTarget[$old];
            if ($new === '' || $new === $old) {
                continue;
            }

            $up = $pdo->prepare('UPDATE sacolinha_itens SET image_url = :u WHERE id = :id');
            $up->execute([':u' => $new, ':id' => $id]);
            if ($up->rowCount() > 0) {
                $sacolinhaUpdated++;
            }
        }
    } catch (Throwable $e) {
        file_put_contents($errorsCsv, sprintf(
            "%s,%s,%s,%s,%s\n",
            date('c'),
            'db-update-sacolinha',
            'sacolinha_itens',
            '',
            str_replace(',', ';', $e->getMessage())
        ), FILE_APPEND);
    }
} elseif ($execute && !$copyOnly) {
    file_put_contents($errorsCsv, sprintf(
        "%s,%s,%s,%s,%s\n",
        date('c'),
        'db-switch-skipped',
        'products+sacolinha',
        '',
        'execucao-parcial-use-sem-offset-e-sem-limit-para-switch'
    ), FILE_APPEND);
}

if ($shouldBuildBroken) {
    file_put_contents($brokenCsv, "table,id,field,source,target_path,status,detail\n");
    foreach ($entries as $entry) {
        $source = (string) $entry['source'];
        $table = (string) $entry['table'];
        $id = (int) $entry['id'];
        $field = (string) $entry['field'];
        $targetPath = (string) ($entry['target_path'] ?? '');

        if ($targetPath !== '') {
            $remote = trim($ftp['app_root'], '/') . '/' . ltrim($targetPath, '/');
            $tmp = $tempDir . '/chk_' . md5($remote);
            $chk = imageMigrationFtpDownload($ftp, $remote, $tmp);
            @unlink($tmp);
            if (!$chk['ok']) {
                file_put_contents($brokenCsv, sprintf(
                    "%s,%d,%s,%s,%s,%s,%s\n",
                    str_replace(',', ';', $table),
                    $id,
                    str_replace(',', ';', $field),
                    str_replace(',', ';', $source),
                    str_replace(',', ';', $targetPath),
                    'BROKEN',
                    str_replace(',', ';', $chk['error'])
                ), FILE_APPEND);
            }
            continue;
        }

        if ((string) $entry['class'] === 'legacy_attachment_id') {
            file_put_contents($brokenCsv, sprintf(
                "%s,%d,%s,%s,%s,%s,%s\n",
                str_replace(',', ';', $table),
                $id,
                str_replace(',', ';', $field),
                str_replace(',', ';', $source),
                '',
                'UNRESOLVED',
                'attachment_id sem path fisico'
            ), FILE_APPEND);
        }
    }
}

if ($execute && !$skipAfterInventory) {
    $after = scanRemoteTree($ftp, trim($ftp['app_root'], '/') . '/uploads', $maxDirs);
    imageMigrationWriteJson($logDir . '/inventory_after.json', [
        'generated_at' => date('c'),
        'copy_result' => $copyResult,
        'db_updates' => [
            'products_scanned' => $productScanned,
            'products_updated' => $productUpdated,
            'sacolinha_updated' => $sacolinhaUpdated,
        ],
        'app_uploads_after' => [
            'files' => $after['files'],
            'dirs' => $after['dirs'],
            'total_bytes' => $after['total_bytes'],
            'by_ext' => $after['by_ext'],
            'errors' => $after['errors'],
        ],
    ]);
} elseif (!$execute) {
    if (!is_file($brokenCsv)) {
        file_put_contents($brokenCsv, "table,id,field,source,target_path,status,detail\n");
    }
    if (!is_file($logDir . '/inventory_after.json')) {
        imageMigrationWriteJson($logDir . '/inventory_after.json', [
            'generated_at' => date('c'),
            'mode' => 'dry-run',
            'note' => 'Execute com --execute para copiar e atualizar DB.',
        ]);
    }
}

if (is_dir($tempDir)) {
    $files = glob($tempDir . '/*') ?: [];
    foreach ($files as $f) {
        @unlink($f);
    }
    @rmdir($tempDir);
}

echo 'Migration plan: ' . $logDir . '/migration_plan.json' . PHP_EOL;
echo 'Copy log: ' . $copyLogFile . PHP_EOL;
echo 'Errors: ' . $errorsCsv . PHP_EOL;
echo 'Broken links: ' . $brokenCsv . PHP_EOL;
echo 'Inventory after: ' . $logDir . '/inventory_after.json' . PHP_EOL;

if ($execute) {
    echo 'Copy summary: attempted=' . $copyResult['attempted']
        . ' copied=' . $copyResult['copied']
        . ' already_verified=' . $copyResult['already_verified']
        . ' skipped_from_log=' . $copyResult['skipped_from_log']
        . ' failed=' . $copyResult['failed'] . PHP_EOL;
    echo 'DB summary: products_scanned=' . $productScanned
        . ' products_updated=' . $productUpdated
        . ' sacolinha_updated=' . $sacolinhaUpdated . PHP_EOL;
}

/**
 * @return array{files:int,dirs:int,total_bytes:int,by_ext:array<string,int>,sample_dirs:array<int,string>,sample_files:array<int,array<string,mixed>>,errors:array<int,string>,index:array<string,int>}
 */
function scanRemoteTree(array $ftp, string $rootDir, int $maxDirs = 25000): array
{
    $queue = [trim($rootDir, '/')];
    $visited = [];
    $dirs = 0;
    $files = 0;
    $bytes = 0;
    $byExt = [];
    $sampleDirs = [];
    $sampleFiles = [];
    $errors = [];
    $index = [];

    while (!empty($queue)) {
        $current = array_shift($queue);
        if ($current === null || isset($visited[$current])) {
            continue;
        }
        $visited[$current] = true;
        $dirs++;

        if (count($visited) > $maxDirs) {
            $errors[] = 'limite-diretorios';
            break;
        }

        $list = imageMigrationFtpListDetails($ftp, $current);
        if (!$list['ok']) {
            $errors[] = $current . ': ' . $list['error'];
            continue;
        }

        foreach ($list['output'] as $line) {
            $item = imageMigrationParseListLine($line);
            $name = $item['name'];
            if ($name === '.' || $name === '..' || $name === '') {
                continue;
            }
            $full = trim($current . '/' . $name, '/');
            if ($item['type'] === 'dir') {
                $queue[] = $full;
                continue;
            }
            if ($item['type'] !== 'file') {
                continue;
            }

            $files++;
            $size = (int) $item['size'];
            $bytes += $size;
            $index['/' . $full] = $size;

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext === '') {
                $ext = '(sem_ext)';
            }
            $byExt[$ext] = ($byExt[$ext] ?? 0) + 1;
        }
    }

    ksort($byExt);

    return [
        'files' => $files,
        'dirs' => $dirs,
        'total_bytes' => $bytes,
        'by_ext' => $byExt,
        'sample_dirs' => $sampleDirs,
        'sample_files' => $sampleFiles,
        'errors' => $errors,
        'index' => $index,
    ];
}
