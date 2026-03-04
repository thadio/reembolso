<?php

declare(strict_types=1);

require __DIR__ . '/lib/image_migration_lib.php';
require dirname(__DIR__) . '/bootstrap.php';

use App\Services\ThumbnailCacheService;

$args = getopt('', [
    'ftp-host::',
    'ftp-port::',
    'ftp-user::',
    'ftp-pass::',
    'ftp-app-root::',
    'ftp-legacy-root::',
    'base-url::',
    'cookie::',
    'limit::',
    'skip-crawler',
]);

$logDir = imageMigrationLogDir();
$reportHtml = $logDir . '/broken_images_report.html';
$crossCsv = $logDir . '/db_fs_crosscheck.csv';
$brokenCsv = $logDir . '/broken_links_after.csv';

$db = imageMigrationBootstrapDb();
$pdo = $db['pdo'];
$ftp = imageMigrationFtpConfig($args);

$baseUrl = rtrim((string) ($args['base-url'] ?? getenv('APP_BASE_URL') ?: 'https://app.thadio.com'), '/');
$cookie = trim((string) ($args['cookie'] ?? ''));
$limit = isset($args['limit']) ? max(1, (int) $args['limit']) : 0;
$skipCrawler = isset($args['skip-crawler']);

$refs = imageMigrationCollectDbImageRefs($pdo);
if ($limit > 0) {
    $refs = array_slice($refs, 0, $limit);
}

$thumbService = new ThumbnailCacheService();

file_put_contents($crossCsv, "table,id,field,path_esperado,existe_original,thumb_existe,size_bytes,last_check\n");
file_put_contents($brokenCsv, "table,id,field,source,target_path,status,detail\n");

$brokenRows = [];
$summary = [
    'checked' => 0,
    'ok' => 0,
    'broken' => 0,
    'thumb_ok' => 0,
    'thumb_fail' => 0,
];

foreach ($refs as $ref) {
    $summary['checked']++;

    $table = (string) $ref['table'];
    $id = (int) $ref['id'];
    $field = (string) $ref['field'];
    $source = trim((string) $ref['source']);

    $mapped = imageMigrationMapSourceToTarget($source, 'uploads/products/legacy', $ftp);
    $expected = $source;
    if (is_string($mapped['target_path']) && $mapped['target_path'] !== '' && $mapped['class'] === 'legacy_wp_upload') {
        $expected = $mapped['target_path'];
    } elseif (preg_match('#^https?://app\.thadio\.com/uploads/#i', $source)) {
        $path = (string) parse_url($source, PHP_URL_PATH);
        if ($path !== '') {
            $expected = $path;
        }
    }

    $exists = false;
    $size = 0;
    $detail = '';

    if (str_starts_with($expected, '/uploads/') || str_starts_with($expected, 'uploads/')) {
        $path = str_starts_with($expected, '/') ? $expected : ('/' . $expected);
        $remote = trim($ftp['app_root'], '/') . '/' . ltrim($path, '/');
        $tmp = sys_get_temp_dir() . '/imgchk_' . md5($remote . microtime(true));
        $dl = imageMigrationFtpDownload($ftp, $remote, $tmp);
        if ($dl['ok']) {
            $exists = true;
            $size = is_file($tmp) ? (int) filesize($tmp) : 0;
            @unlink($tmp);
        } else {
            $exists = false;
            $detail = $dl['error'];
            @unlink($tmp);
        }
    } elseif (preg_match('#^https?://#i', $expected)) {
        $head = imageMigrationHttpHead($expected);
        $exists = $head['status'] >= 200 && $head['status'] < 400;
        $detail = $head['error'] !== '' ? $head['error'] : ('http=' . $head['status']);
    } else {
        $detail = 'path-nao-suportado';
    }

    $thumbPath = $thumbService->ensureCached($expected, 150);
    $thumbExists = $thumbPath !== null && is_file($thumbPath);

    if ($exists) {
        $summary['ok']++;
    } else {
        $summary['broken']++;
        $brokenRows[] = [
            'table' => $table,
            'id' => $id,
            'field' => $field,
            'source' => $source,
            'target_path' => $expected,
            'status' => 'BROKEN',
            'detail' => $detail,
        ];
    }

    if ($thumbExists) {
        $summary['thumb_ok']++;
    } else {
        $summary['thumb_fail']++;
    }

    $crossLine = sprintf(
        "%s,%d,%s,%s,%s,%s,%d,%s\n",
        str_replace(',', ';', $table),
        $id,
        str_replace(',', ';', $field),
        str_replace(',', ';', $expected),
        $exists ? 'sim' : 'nao',
        $thumbExists ? 'sim' : 'nao',
        $size,
        date('c')
    );
    file_put_contents($crossCsv, $crossLine, FILE_APPEND);
}

foreach ($brokenRows as $row) {
    $line = sprintf(
        "%s,%d,%s,%s,%s,%s,%s\n",
        str_replace(',', ';', (string) $row['table']),
        (int) $row['id'],
        str_replace(',', ';', (string) $row['field']),
        str_replace(',', ';', (string) $row['source']),
        str_replace(',', ';', (string) $row['target_path']),
        str_replace(',', ';', (string) $row['status']),
        str_replace(',', ';', (string) $row['detail'])
    );
    file_put_contents($brokenCsv, $line, FILE_APPEND);
}

// crawler de paginas principais
$crawlRows = [];
if (!$skipCrawler) {
    $routes = [
        '/index.php',
        '/produto-list.php',
        '/produto-cadastro.php',
        '/pedido-list.php',
        '/consignacao-painel.php',
        '/fornecedor-list.php',
        '/cliente-list.php',
    ];

    foreach ($routes as $route) {
        $url = $baseUrl . $route;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'ImageBrokenChecker/1.0',
            CURLOPT_HTTPHEADER => $cookie !== '' ? ['Cookie: ' . $cookie] : [],
        ]);
        $html = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (!is_string($html) || $html === '') {
            $crawlRows[] = [
                'page' => $url,
                'image' => '-',
                'status' => (string) $status,
                'referer' => '-',
                'suggestion' => 'pagina-sem-html-ou-bloqueada',
            ];
            continue;
        }

        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $imgs = $doc->getElementsByTagName('img');

        if ($imgs->length === 0) {
            $crawlRows[] = [
                'page' => $url,
                'image' => '-',
                'status' => (string) $status,
                'referer' => '-',
                'suggestion' => 'sem-img-ou-login-necessario',
            ];
            continue;
        }

        foreach ($imgs as $img) {
            $src = trim((string) $img->getAttribute('src'));
            if ($src === '') {
                continue;
            }
            $imgUrl = $src;
            if (!preg_match('#^https?://#i', $imgUrl)) {
                $imgUrl = $baseUrl . '/' . ltrim($imgUrl, '/');
            }

            $head = imageMigrationHttpHead($imgUrl);
            $imgStatus = $head['status'];
            if ($imgStatus < 200 || $imgStatus >= 400) {
                $crawlRows[] = [
                    'page' => $url,
                    'image' => $imgUrl,
                    'status' => (string) $imgStatus,
                    'referer' => $url,
                    'suggestion' => 'corrigir-path-ou-permissao',
                ];
            }
        }
    }
}

// broken_images_report.html
$rowsHtml = '';
foreach ($crawlRows as $row) {
    $rowsHtml .= '<tr>'
        . '<td>' . htmlspecialchars((string) $row['page'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars((string) $row['image'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars((string) $row['referer'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars((string) $row['suggestion'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '</tr>';
}

$htmlOut = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Broken Images Report</title>'
    . '<style>body{font-family:Arial,sans-serif;padding:16px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:6px;font-size:12px}th{background:#f4f4f4}</style>'
    . '</head><body>'
    . '<h1>Broken Images Report</h1>'
    . '<p>Gerado em: ' . htmlspecialchars(date('c'), ENT_QUOTES, 'UTF-8') . '</p>'
    . '<ul>'
    . '<li>DB refs checked: ' . $summary['checked'] . '</li>'
    . '<li>Originais OK: ' . $summary['ok'] . '</li>'
    . '<li>Originais BROKEN: ' . $summary['broken'] . '</li>'
    . '<li>Thumb OK: ' . $summary['thumb_ok'] . '</li>'
    . '<li>Thumb BROKEN: ' . $summary['thumb_fail'] . '</li>'
    . '</ul>'
    . '<h2>Crawler findings</h2>'
    . '<table><thead><tr><th>pagina</th><th>imagem</th><th>status code</th><th>referer</th><th>sugestao</th></tr></thead><tbody>'
    . $rowsHtml
    . '</tbody></table></body></html>';

file_put_contents($reportHtml, $htmlOut);

echo 'Relatorios gerados:' . PHP_EOL;
echo '- ' . $crossCsv . PHP_EOL;
echo '- ' . $brokenCsv . PHP_EOL;
echo '- ' . $reportHtml . PHP_EOL;
