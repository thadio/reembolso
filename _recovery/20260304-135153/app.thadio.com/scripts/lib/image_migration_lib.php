<?php

declare(strict_types=1);

function imageMigrationProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

function imageMigrationLogDir(): string
{
    $dir = imageMigrationProjectRoot() . '/storage/logs/image-migration';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function imageMigrationNow(): string
{
    return date('c');
}

/**
 * @return array{pdo:PDO, config:array<string,mixed>}
 */
function imageMigrationBootstrapDb(): array
{
    require_once imageMigrationProjectRoot() . '/bootstrap.php';
    [$pdo, $error, $config] = bootstrapPdo();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Falha na conexao com banco: ' . (string) $error);
    }
    return ['pdo' => $pdo, 'config' => $config];
}

/**
 * @return array{host:string,port:int,user:string,pass:string,app_root:string,legacy_root:string}
 */
function imageMigrationFtpConfig(array $args): array
{
    $host = (string) ($args['ftp-host'] ?? getenv('IMAGE_FTP_HOST') ?: 'ftp.thadio.com');
    $port = (int) ($args['ftp-port'] ?? getenv('IMAGE_FTP_PORT') ?: 21);
    $user = (string) ($args['ftp-user'] ?? getenv('IMAGE_FTP_USER') ?: '');
    $pass = (string) ($args['ftp-pass'] ?? getenv('IMAGE_FTP_PASS') ?: '');
    $appRoot = trim((string) ($args['ftp-app-root'] ?? getenv('IMAGE_FTP_APP_ROOT') ?: 'app.thadio.com'), '/');
    $legacyRoot = trim((string) ($args['ftp-legacy-root'] ?? getenv('IMAGE_FTP_LEGACY_ROOT') ?: 'retratobrecho/wp-content/uploads'), '/');

    if ($host === '' || $user === '' || $pass === '') {
        throw new InvalidArgumentException('Credenciais FTP ausentes. Use --ftp-host --ftp-user --ftp-pass.');
    }

    return [
        'host' => $host,
        'port' => $port,
        'user' => $user,
        'pass' => $pass,
        'app_root' => $appRoot,
        'legacy_root' => $legacyRoot,
    ];
}

/**
 * @return array<string,mixed>
 */
function &imageMigrationFtpPool(): array
{
    if (!isset($GLOBALS['imageMigrationFtpPool']) || !is_array($GLOBALS['imageMigrationFtpPool'])) {
        $GLOBALS['imageMigrationFtpPool'] = [];
    }
    return $GLOBALS['imageMigrationFtpPool'];
}

function imageMigrationFtpConnKey(array $ftp): string
{
    return $ftp['host'] . ':' . (int) $ftp['port'] . ':' . $ftp['user'] . ':' . md5((string) $ftp['pass']);
}

function imageMigrationFtpResetConn(array $ftp): void
{
    $key = imageMigrationFtpConnKey($ftp);
    $pool = &imageMigrationFtpPool();
    if (!isset($pool[$key])) {
        return;
    }
    $conn = $pool[$key];
    if ($conn !== null) {
        @ftp_close($conn);
    }
    unset($pool[$key]);
}

/**
 * @return mixed
 */
function imageMigrationFtpConn(array $ftp)
{
    $key = imageMigrationFtpConnKey($ftp);
    $pool = &imageMigrationFtpPool();

    if (isset($pool[$key]) && $pool[$key] !== null) {
        $existing = $pool[$key];
        $alive = @ftp_pwd($existing);
        if ($alive !== false) {
            return $existing;
        }
        @ftp_close($existing);
        unset($pool[$key]);
    }

    $conn = @ftp_connect($ftp['host'], (int) $ftp['port'], 30);
    if ($conn === false) {
        if (function_exists('ftp_ssl_connect')) {
            $conn = @ftp_ssl_connect($ftp['host'], (int) $ftp['port'], 30);
        }
    }
    if ($conn === false) {
        throw new RuntimeException('Falha ao conectar no FTP.');
    }

    @ftp_set_option($conn, FTP_TIMEOUT_SEC, 120);

    if (!@ftp_login($conn, $ftp['user'], $ftp['pass'])) {
        @ftp_close($conn);
        throw new RuntimeException('Falha no login FTP.');
    }

    @ftp_pasv($conn, true);
    $pool[$key] = $conn;
    return $conn;
}

/**
 * @param mixed $conn
 */
function imageMigrationFtpEnsureDir($conn, string $remoteDir): void
{
    $remoteDir = trim($remoteDir, '/');
    if ($remoteDir === '' || $remoteDir === '.') {
        return;
    }

    $parts = explode('/', $remoteDir);
    $path = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $path .= ($path === '' ? '' : '/') . $part;
        @ftp_mkdir($conn, $path);
    }
}

/**
 * @return array{ok:bool,error:string,bytes:int}
 */
function imageMigrationFtpDownload(array $ftp, string $remotePath, string $localPath): array
{
    $remotePath = ltrim($remotePath, '/');
    $maxAttempts = 2;
    $lastError = 'Erro FTP download.';

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            $conn = imageMigrationFtpConn($ftp);
            $warn = '';
            set_error_handler(static function (int $errno, string $errstr) use (&$warn): bool {
                $warn = $errstr;
                return true;
            });
            $ok = @ftp_get($conn, $localPath, $remotePath, FTP_BINARY);
            restore_error_handler();

            if ($ok === true && is_file($localPath)) {
                $bytes = (int) filesize($localPath);
                return ['ok' => true, 'error' => '', 'bytes' => $bytes];
            }

            $lastError = $warn !== '' ? $warn : 'Erro FTP download.';
            @unlink($localPath);
            imageMigrationFtpResetConn($ftp);
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
            @unlink($localPath);
            imageMigrationFtpResetConn($ftp);
        }

        if ($attempt < $maxAttempts) {
            usleep($attempt * 1200000);
        }
    }

    return ['ok' => false, 'error' => $lastError . ' [tentativas=' . $maxAttempts . ']', 'bytes' => 0];
}

/**
 * @return array{ok:bool,error:string}
 */
function imageMigrationFtpUpload(array $ftp, string $localPath, string $remotePath): array
{
    if (!is_file($localPath)) {
        return ['ok' => false, 'error' => 'Arquivo local inexistente para upload.'];
    }

    $remotePath = ltrim($remotePath, '/');
    $maxAttempts = 2;
    $lastError = 'Erro FTP upload.';

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            $conn = imageMigrationFtpConn($ftp);
            imageMigrationFtpEnsureDir($conn, dirname($remotePath));

            $warn = '';
            set_error_handler(static function (int $errno, string $errstr) use (&$warn): bool {
                $warn = $errstr;
                return true;
            });
            $ok = @ftp_put($conn, $remotePath, $localPath, FTP_BINARY);
            restore_error_handler();

            if ($ok === true) {
                return ['ok' => true, 'error' => ''];
            }

            $lastError = $warn !== '' ? $warn : 'Erro FTP upload.';
            imageMigrationFtpResetConn($ftp);
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
            imageMigrationFtpResetConn($ftp);
        }

        if ($attempt < $maxAttempts) {
            usleep($attempt * 1200000);
        }
    }

    return ['ok' => false, 'error' => $lastError . ' [tentativas=' . $maxAttempts . ']'];
}

/**
 * @return array{ok:bool,error:string,output:array<int,string>}
 */
function imageMigrationFtpListDetails(array $ftp, string $remoteDir): array
{
    $remoteDir = trim($remoteDir, '/');
    $maxAttempts = 2;
    $lines = [];
    $lastError = '';

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            $conn = imageMigrationFtpConn($ftp);
            $warn = '';
            set_error_handler(static function (int $errno, string $errstr) use (&$warn): bool {
                $warn = $errstr;
                return true;
            });
            $rawList = @ftp_rawlist($conn, $remoteDir);
            restore_error_handler();

            if (is_array($rawList)) {
                $lines = $rawList;
                $lastError = '';
                break;
            }

            $lastError = $warn !== '' ? $warn : 'Erro FTP list.';
            imageMigrationFtpResetConn($ftp);
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
            imageMigrationFtpResetConn($ftp);
        }

        if ($attempt < $maxAttempts) {
            usleep($attempt * 1200000);
        }
    }

    if ($lines === []) {
        $msg = trim($lastError) !== '' ? $lastError : 'Erro FTP list.';
        return ['ok' => false, 'error' => $msg . ' [tentativas=' . $maxAttempts . ']', 'output' => []];
    }

    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $out[] = $line;
    }

    return ['ok' => true, 'error' => '', 'output' => $out];
}

/**
 * @return array{type:string,name:string,size:int}
 */
function imageMigrationParseListLine(string $line): array
{
    if (!preg_match('/^([d\-l])[rwx\-]{9}\s+\d+\s+\S+\s+\S+\s+(\d+)\s+\w+\s+\d+\s+[\d:]+\s+(.+)$/', $line, $m)) {
        return ['type' => 'unknown', 'name' => $line, 'size' => 0];
    }

    $typeFlag = $m[1];
    $type = $typeFlag === 'd' ? 'dir' : 'file';
    $size = (int) $m[2];
    $name = trim($m[3]);

    return ['type' => $type, 'name' => $name, 'size' => $size];
}

/**
 * @return array<int,array<string,mixed>>
 */
function imageMigrationCollectDbImageRefs(PDO $pdo): array
{
    $refs = [];

    $stmt = $pdo->query('SELECT sku, metadata FROM products');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sku = (int) ($row['sku'] ?? 0);
        if ($sku <= 0) {
            continue;
        }

        $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
        if (!is_array($metadata)) {
            continue;
        }

        $images = $metadata['images'] ?? null;
        if (!is_array($images)) {
            continue;
        }

        foreach ($images as $index => $img) {
            if (!is_array($img)) {
                continue;
            }
            $src = trim((string) ($img['src'] ?? ''));
            if ($src === '') {
                continue;
            }
            $refs[] = [
                'table' => 'products',
                'id' => $sku,
                'field' => 'metadata.images[' . (int) $index . '].src',
                'source' => $src,
            ];
        }
    }

    try {
        $stmt = $pdo->query('SELECT id, image_url FROM sacolinha_itens');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            $src = trim((string) ($row['image_url'] ?? ''));
            if ($id <= 0 || $src === '') {
                continue;
            }
            $refs[] = [
                'table' => 'sacolinha_itens',
                'id' => $id,
                'field' => 'image_url',
                'source' => $src,
            ];
        }
    } catch (Throwable $e) {
        // tabela opcional em alguns ambientes
    }

    return $refs;
}

/**
 * @return array{class:string,target_path:?string,source_ftp_path:?string,reason:string}
 */
function imageMigrationMapSourceToTarget(string $source, string $targetBaseDir, array $ftp): array
{
    $source = trim($source);
    if ($source === '') {
        return ['class' => 'empty', 'target_path' => null, 'source_ftp_path' => null, 'reason' => 'source-vazio'];
    }

    if (str_starts_with($source, '/uploads/') || str_starts_with($source, 'uploads/')) {
        $targetPath = str_starts_with($source, '/') ? $source : ('/' . $source);
        return ['class' => 'already_relative', 'target_path' => $targetPath, 'source_ftp_path' => null, 'reason' => 'ja-relativo'];
    }

    if (!preg_match('#^https?://#i', $source)) {
        return ['class' => 'unsupported', 'target_path' => null, 'source_ftp_path' => null, 'reason' => 'nao-http'];
    }

    $parts = parse_url($source);
    if (!is_array($parts)) {
        return ['class' => 'unsupported', 'target_path' => null, 'source_ftp_path' => null, 'reason' => 'url-invalida'];
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');
    $query = (string) ($parts['query'] ?? '');

    if (str_starts_with($path, '/uploads/')) {
        return ['class' => 'app_absolute_uploads', 'target_path' => $path, 'source_ftp_path' => null, 'reason' => 'app-uploads'];
    }

    $legacyHosts = [
        'retratobrecho.com',
        'www.retratobrecho.com',
        'retratobrecho.thadio.com',
        'www.retratobrecho.thadio.com',
    ];

    if (in_array($host, $legacyHosts, true) && str_starts_with($path, '/wp-content/uploads/')) {
        $relative = ltrim(substr($path, strlen('/wp-content/uploads/')), '/');
        if ($relative === '') {
            return ['class' => 'unsupported', 'target_path' => null, 'source_ftp_path' => null, 'reason' => 'legacy-sem-path'];
        }

        $targetPath = '/' . trim($targetBaseDir, '/') . '/' . $relative;
        $sourceFtp = trim($ftp['legacy_root'], '/') . '/' . $relative;

        return [
            'class' => 'legacy_wp_upload',
            'target_path' => $targetPath,
            'source_ftp_path' => $sourceFtp,
            'reason' => 'migrar-ftp',
        ];
    }

    if (in_array($host, $legacyHosts, true) && str_contains($query, 'attachment_id=')) {
        return ['class' => 'legacy_attachment_id', 'target_path' => null, 'source_ftp_path' => null, 'reason' => 'attachment-id-sem-path'];
    }

    return ['class' => 'external_url', 'target_path' => null, 'source_ftp_path' => null, 'reason' => 'host-externo'];
}

function imageMigrationWriteJson(string $file, array $payload): void
{
    file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
}

function imageMigrationCsvAppend(string $file, array $row, bool $headerIfMissing = false): void
{
    $exists = is_file($file);
    $fp = fopen($file, $exists ? 'ab' : 'wb');
    if (!is_resource($fp)) {
        throw new RuntimeException('Nao foi possivel escrever CSV: ' . $file);
    }

    if ($headerIfMissing && !$exists) {
        fputcsv($fp, array_keys($row), ',', '\"', '\\\\');
    }
    fputcsv($fp, array_values($row), ',', '\"', '\\\\');
    fclose($fp);
}

/**
 * @return array{status:int,effective_url:string,error:string}
 */
function imageMigrationHttpHead(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'ImageMigrationAudit/1.0',
    ]);

    $out = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($out === false || $errno !== 0) {
        return ['status' => 0, 'effective_url' => $effective, 'error' => $error !== '' ? $error : 'curl-error'];
    }

    return ['status' => $status, 'effective_url' => $effective, 'error' => ''];
}
