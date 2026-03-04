<?php

declare(strict_types=1);

require __DIR__ . '/lib/image_migration_lib.php';

$args = getopt('', [
    'ftp-host::',
    'ftp-port::',
    'ftp-user::',
    'ftp-pass::',
    'ftp-app-root::',
    'ftp-legacy-root::',
    'max-dirs::',
]);

$logDir = imageMigrationLogDir();
$startedAt = imageMigrationNow();
$maxDirs = max(100, (int) ($args['max-dirs'] ?? 30000));

$db = imageMigrationBootstrapDb();
$pdo = $db['pdo'];
$ftp = imageMigrationFtpConfig($args);

$refs = imageMigrationCollectDbImageRefs($pdo);
$refClassCounts = [];
foreach ($refs as $ref) {
    $source = (string) ($ref['source'] ?? '');
    $class = 'other';
    if (preg_match('#^https?://retratobrecho\.com/#i', $source)) {
        $class = 'abs_retratobrecho';
    } elseif (preg_match('#^https?://retratobrecho\.thadio\.com/#i', $source)) {
        $class = 'abs_retratobrecho_subdomain';
    } elseif (preg_match('#^https?://app\.thadio\.com/#i', $source)) {
        $class = 'abs_app';
    } elseif (str_starts_with($source, '/uploads/') || str_starts_with($source, 'uploads/')) {
        $class = 'relative_uploads';
    } elseif (preg_match('#^https?://#i', $source)) {
        $class = 'abs_other';
    }
    $refClassCounts[$class] = ($refClassCounts[$class] ?? 0) + 1;
}

// 1) db_image_fields.md
$fieldRows = $pdo->query(
    "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND (
         COLUMN_NAME REGEXP '(image|img|thumb|photo|foto|logo|icon|avatar|banner|cover|path|url|src|file|arquivo|metadata)'
         OR DATA_TYPE = 'json'
       )
     ORDER BY TABLE_NAME, ORDINAL_POSITION"
)->fetchAll(PDO::FETCH_ASSOC);

$dbFieldsMd = [];
$dbFieldsMd[] = '# DB Image Fields';
$dbFieldsMd[] = '';
$dbFieldsMd[] = 'Gerado em: ' . date('Y-m-d H:i:s');
$dbFieldsMd[] = '';
$dbFieldsMd[] = '## Campos mapeados';
$dbFieldsMd[] = '';
$dbFieldsMd[] = '| tabela | coluna | tipo | formato detectado | amostra |';
$dbFieldsMd[] = '|---|---|---|---|---|';

$sampleCounter = 0;
foreach ($fieldRows as $field) {
    $table = (string) $field['TABLE_NAME'];
    $column = (string) $field['COLUMN_NAME'];
    $dataType = (string) $field['DATA_TYPE'];

    $sample = '';
    $format = 'n/a';

    if (in_array($dataType, ['varchar', 'text', 'mediumtext', 'longtext', 'json'], true)) {
        $sql = sprintf(
            "SELECT `%s` AS v FROM `%s` WHERE `%s` IS NOT NULL AND CAST(`%s` AS CHAR) <> '' LIMIT 20",
            str_replace('`', '``', $column),
            str_replace('`', '``', $table),
            str_replace('`', '``', $column),
            str_replace('`', '``', $column)
        );
        try {
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $values = [];
            foreach ($rows as $r) {
                $v = trim((string) ($r['v'] ?? ''));
                if ($v === '') {
                    continue;
                }
                $values[] = $v;
            }

            if (!empty($values)) {
                $sample = $values[0];
                $sampleLower = strtolower($sample);
                if (preg_match('#^https?://#i', $sample)) {
                    $format = 'url_absoluta';
                } elseif (str_starts_with($sample, '/')) {
                    $format = 'path_relativo_com_barra';
                } elseif (str_starts_with($sample, '{') || str_starts_with($sample, '[')) {
                    $format = 'json';
                } elseif (str_contains($sampleLower, '/')) {
                    $format = 'path_relativo';
                } else {
                    $format = 'texto';
                }
                $sampleCounter += count($values);
            }
        } catch (Throwable $e) {
            $format = 'erro_consulta';
            $sample = $e->getMessage();
        }
    }

    $dbFieldsMd[] = sprintf(
        '| %s | %s | %s | %s | %s |',
        $table,
        $column,
        $dataType,
        $format,
        str_replace('|', '\\|', substr($sample, 0, 120))
    );
}

$dbFieldsMd[] = '';
$dbFieldsMd[] = '## Padrões consolidados de referências de imagem';
$dbFieldsMd[] = '';
foreach ($refClassCounts as $class => $count) {
    $dbFieldsMd[] = '- ' . $class . ': ' . $count;
}
$dbFieldsMd[] = '';
$dbFieldsMd[] = 'Amostra total analisada (campos textuais/json): ' . $sampleCounter;
file_put_contents(imageMigrationProjectRoot() . '/db_image_fields.md', implode(PHP_EOL, $dbFieldsMd) . PHP_EOL);

/**
 * @return array{files:int,dirs:int,total_bytes:int,by_ext:array<string,int>,sample_dirs:array<int,string>,sample_files:array<int,array<string,mixed>>,errors:array<int,string>,index:array<string,int>}
 */
function scanRemoteTree(array $ftp, string $rootDir, int $maxDirs = 30000): array
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
            $errors[] = 'Limite de diretorios atingido em ' . $current;
            break;
        }

        if (count($sampleDirs) < 120) {
            $sampleDirs[] = $current;
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

            $full = $current . '/' . $name;
            $full = trim($full, '/');

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

            if (count($sampleFiles) < 160) {
                $sampleFiles[] = [
                    'path' => '/' . $full,
                    'size' => $size,
                ];
            }
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

function scanLocalMediaDirs(string $projectRoot): array
{
    $candidates = [
        $projectRoot . '/uploads',
        $projectRoot . '/public/uploads',
        $projectRoot . '/public/images',
        $projectRoot . '/storage/uploads',
        $projectRoot . '/assets/uploads',
    ];

    $result = [];
    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            $result[$dir] = [
                'exists' => false,
                'files' => 0,
                'total_bytes' => 0,
                'by_ext' => [],
            ];
            continue;
        }

        $files = 0;
        $bytes = 0;
        $byExt = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if (!$fileInfo->isFile()) {
                continue;
            }
            $files++;
            $size = (int) $fileInfo->getSize();
            $bytes += $size;
            $ext = strtolower($fileInfo->getExtension());
            if ($ext === '') {
                $ext = '(sem_ext)';
            }
            $byExt[$ext] = ($byExt[$ext] ?? 0) + 1;
        }
        ksort($byExt);

        $result[$dir] = [
            'exists' => true,
            'files' => $files,
            'total_bytes' => $bytes,
            'by_ext' => $byExt,
        ];
    }

    return $result;
}

$projectRoot = imageMigrationProjectRoot();
$localInventory = scanLocalMediaDirs($projectRoot);
$remoteApp = scanRemoteTree($ftp, trim($ftp['app_root'], '/') . '/uploads', $maxDirs);
$remoteLegacy = scanRemoteTree($ftp, trim($ftp['legacy_root'], '/'), $maxDirs);

// Arquivos usados (referencias no banco) vs orfaos no app/uploads
$usedRelative = [];
foreach ($refs as $ref) {
    $source = trim((string) ($ref['source'] ?? ''));
    if ($source === '') {
        continue;
    }
    if (str_starts_with($source, '/uploads/')) {
        $usedRelative[$source] = true;
    }
    if (str_starts_with($source, 'uploads/')) {
        $usedRelative['/' . $source] = true;
    }
    if (preg_match('#^https?://app\.thadio\.com/uploads/#i', $source)) {
        $path = (string) parse_url($source, PHP_URL_PATH);
        if ($path !== '') {
            $usedRelative[$path] = true;
        }
    }
}

$appFileIndex = $remoteApp['index'];
$usedCount = 0;
$orphanCount = 0;
foreach ($appFileIndex as $path => $size) {
    if (isset($usedRelative[$path])) {
        $usedCount++;
    } else {
        $orphanCount++;
    }
}

$inventory = [
    'generated_at' => date('c'),
    'started_at' => $startedAt,
    'project_root' => $projectRoot,
    'db' => [
        'image_refs_total' => count($refs),
        'image_ref_classes' => $refClassCounts,
    ],
    'filesystem' => [
        'local' => $localInventory,
        'remote' => [
            'app_uploads' => [
                'root' => '/' . trim($ftp['app_root'], '/') . '/uploads',
                'files' => $remoteApp['files'],
                'dirs' => $remoteApp['dirs'],
                'total_bytes' => $remoteApp['total_bytes'],
                'by_ext' => $remoteApp['by_ext'],
                'used_files' => $usedCount,
                'orphan_files' => $orphanCount,
                'errors' => $remoteApp['errors'],
                'sample_dirs' => $remoteApp['sample_dirs'],
                'sample_files' => $remoteApp['sample_files'],
            ],
            'legacy_uploads' => [
                'root' => '/' . trim($ftp['legacy_root'], '/'),
                'files' => $remoteLegacy['files'],
                'dirs' => $remoteLegacy['dirs'],
                'total_bytes' => $remoteLegacy['total_bytes'],
                'by_ext' => $remoteLegacy['by_ext'],
                'errors' => $remoteLegacy['errors'],
                'sample_dirs' => $remoteLegacy['sample_dirs'],
                'sample_files' => $remoteLegacy['sample_files'],
            ],
        ],
    ],
];

imageMigrationWriteJson($logDir . '/inventory_before.json', $inventory);

$fsMd = [];
$fsMd[] = '# Inventory FS Before';
$fsMd[] = '';
$fsMd[] = 'Gerado em: ' . date('Y-m-d H:i:s');
$fsMd[] = '';
$fsMd[] = '## Remoto app.thadio.com/uploads';
$fsMd[] = '';
$fsMd[] = '- arquivos: ' . $remoteApp['files'];
$fsMd[] = '- diretorios: ' . $remoteApp['dirs'];
$fsMd[] = '- bytes totais: ' . $remoteApp['total_bytes'];
$fsMd[] = '- usados (DB): ' . $usedCount;
$fsMd[] = '- orfaos estimados: ' . $orphanCount;
$fsMd[] = '';
$fsMd[] = '### Extensoes (app)';
$fsMd[] = '';
foreach ($remoteApp['by_ext'] as $ext => $count) {
    $fsMd[] = '- ' . $ext . ': ' . $count;
}
$fsMd[] = '';
$fsMd[] = '## Remoto retratobrecho/wp-content/uploads';
$fsMd[] = '';
$fsMd[] = '- arquivos: ' . $remoteLegacy['files'];
$fsMd[] = '- diretorios: ' . $remoteLegacy['dirs'];
$fsMd[] = '- bytes totais: ' . $remoteLegacy['total_bytes'];
$fsMd[] = '';
$fsMd[] = '### Extensoes (legacy)';
$fsMd[] = '';
foreach ($remoteLegacy['by_ext'] as $ext => $count) {
    $fsMd[] = '- ' . $ext . ': ' . $count;
}
$fsMd[] = '';
$fsMd[] = '## Local (workspace)';
$fsMd[] = '';
foreach ($localInventory as $dir => $info) {
    $fsMd[] = '- ' . $dir . ': ' . ($info['exists'] ? 'existe' : 'nao existe')
        . ', files=' . $info['files']
        . ', bytes=' . $info['total_bytes'];
}
file_put_contents(imageMigrationProjectRoot() . '/inventory_fs_before.md', implode(PHP_EOL, $fsMd) . PHP_EOL);

echo 'Inventory concluido. Arquivos gerados:' . PHP_EOL;
echo '- ' . $logDir . '/inventory_before.json' . PHP_EOL;
echo '- ' . imageMigrationProjectRoot() . '/inventory_fs_before.md' . PHP_EOL;
echo '- ' . imageMigrationProjectRoot() . '/db_image_fields.md' . PHP_EOL;
