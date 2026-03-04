<?php

namespace App\Controllers;

use App\Core\View;
use App\Services\MediaBundleImportService;
use App\Services\TableMigrationUploadService;
use App\Services\UnifiedJsonlMigrationImportService;
use App\Support\Html;
use PDO;

class TableMigrationUploadController
{
    private ?PDO $pdo;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->pdo = $pdo;
        $this->connectionError = $connectionError;
    }

    public function index(): void
    {
        $errors = [];
        $success = '';
        $results = [];
        $unifiedReport = null;
        $unifiedUploadName = '';
        $mediaReport = null;
        $mediaUploadName = '';
        $selectedMode = (string) ($_POST['csv_mode'] ?? 'upsert');
        if (!in_array($selectedMode, ['insert', 'upsert'], true)) {
            $selectedMode = 'upsert';
        }
        $continueOnError = isset($_POST['continue_on_error']) && $_POST['continue_on_error'] === '1';
        $unifiedMode = (string) ($_POST['unified_mode'] ?? 'upsert');
        if (!in_array($unifiedMode, ['insert', 'upsert'], true)) {
            $unifiedMode = 'upsert';
        }
        $unifiedDryRun = (string) ($_POST['unified_dry_run'] ?? '1') === '1';
        $unifiedContinueOnError = (string) ($_POST['unified_continue_on_error'] ?? '0') === '1';
        $unifiedStrict = (string) ($_POST['unified_strict'] ?? '1') === '1';
        $unifiedSkipUnchanged = (string) ($_POST['unified_skip_unchanged'] ?? '1') === '1';
        $unifiedIdempotencyMap = (string) ($_POST['unified_idempotency_map'] ?? '1') === '1';
        $mediaDryRun = (string) ($_POST['media_dry_run'] ?? '1') === '1';
        $mediaContinueOnError = (string) ($_POST['media_continue_on_error'] ?? '0') === '1';
        $mediaUpdateProducts = (string) ($_POST['media_update_products'] ?? '1') === '1';
        $mediaUpdateMediaFiles = (string) ($_POST['media_update_media_files'] ?? '1') === '1';
        $mediaSourceSystem = trim((string) ($_POST['media_source_system'] ?? ''));
        $plan = [];

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        } elseif (!$this->pdo) {
            $errors[] = 'Conexão com banco indisponível para importação.';
        } else {
            $service = new TableMigrationUploadService($this->pdo);
            $plan = $service->buildExecutionPlan();
            if (empty($plan)) {
                $errors[] = 'Nenhuma tabela disponível para importação no schema atual.';
            }

            $shouldDownloadTemplate = isset($_GET['download_template']) && $_GET['download_template'] === '1';
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && $shouldDownloadTemplate && empty($errors)) {
                $tableName = (string) ($_GET['table'] ?? '');
                $format = (string) ($_GET['format'] ?? '');

                try {
                    $download = $service->buildTemplateDownload($tableName, $format);
                    $content = (string) ($download['content'] ?? '');
                    $mime = (string) ($download['mime'] ?? 'application/octet-stream');
                    $fileName = (string) ($download['file_name'] ?? 'modelo-download');

                    header('Content-Type: ' . $mime);
                    header('X-Content-Type-Options: nosniff');
                    header('Content-Disposition: attachment; filename="' . addcslashes($fileName, "\"\\") . '"');
                    header('Content-Length: ' . strlen($content));
                    echo $content;
                    return;
                } catch (\Throwable $exception) {
                    $errors[] = 'Falha ao gerar modelo: ' . $exception->getMessage();
                }
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
                $action = (string) ($_POST['action'] ?? 'table_upload');
                if ($action === 'import_unified_jsonl') {
                    try {
                        set_time_limit(0);
                    } catch (\Throwable) {
                        // segue sem alterar timeout quando a diretiva estiver bloqueada
                    }

                    $uploadedFile = $_FILES['unified_jsonl_file'] ?? [];
                    if (!is_array($uploadedFile)) {
                        $errors[] = 'Requisição inválida para arquivo único JSONL.';
                    } else {
                        try {
                            $storedFile = $this->storeUnifiedJsonlUpload($uploadedFile);
                            $unifiedUploadName = (string) ($uploadedFile['name'] ?? '');
                            $unifiedImporter = new UnifiedJsonlMigrationImportService($this->pdo);
                            $unifiedReport = $unifiedImporter->importFile($storedFile, [
                                'mode' => $unifiedMode,
                                'dry_run' => $unifiedDryRun,
                                'continue_on_error' => $unifiedContinueOnError,
                                'strict' => $unifiedStrict,
                                'skip_unchanged' => $unifiedSkipUnchanged,
                                'idempotency_map' => $unifiedIdempotencyMap,
                            ]);

                            $status = (string) ($unifiedReport['status'] ?? 'error');
                            if ($status === 'success' || $status === 'success_with_warnings') {
                                $success = 'Arquivo único processado com sucesso (' . $status . ').';
                            } elseif ($status === 'partial') {
                                $errors[] = 'Arquivo único processado parcialmente com erros. Revise o relatório abaixo.';
                            } else {
                                $errors[] = 'Falha ao processar arquivo único JSONL. Revise o relatório abaixo.';
                            }
                        } catch (\Throwable $exception) {
                            $errors[] = 'Falha ao importar arquivo único: ' . $exception->getMessage();
                        }
                    }
                } elseif ($action === 'import_media_bundle') {
                    try {
                        set_time_limit(0);
                    } catch (\Throwable) {
                        // segue sem alterar timeout quando a diretiva estiver bloqueada
                    }

                    $uploadedFile = $_FILES['media_bundle_file'] ?? [];
                    if (!is_array($uploadedFile)) {
                        $errors[] = 'Requisição inválida para bundle de mídia.';
                    } else {
                        try {
                            $storedFile = $this->storeMediaBundleUpload($uploadedFile);
                            $mediaUploadName = (string) ($uploadedFile['name'] ?? '');
                            $mediaImporter = new MediaBundleImportService($this->pdo);
                            $mediaReport = $mediaImporter->importBundle($storedFile, [
                                'dry_run' => $mediaDryRun,
                                'continue_on_error' => $mediaContinueOnError,
                                'update_products' => $mediaUpdateProducts,
                                'update_media_files' => $mediaUpdateMediaFiles,
                                'source_system' => $mediaSourceSystem,
                            ]);

                            $status = (string) ($mediaReport['status'] ?? 'error');
                            if ($status === 'success' || $status === 'success_with_warnings') {
                                $success = 'Bundle de mídia processado com sucesso (' . $status . ').';
                            } elseif ($status === 'partial') {
                                $errors[] = 'Bundle de mídia processado parcialmente com erros. Revise o relatório abaixo.';
                            } else {
                                $errors[] = 'Falha ao processar bundle de mídia. Revise o relatório abaixo.';
                            }
                        } catch (\Throwable $exception) {
                            $errors[] = 'Falha ao importar bundle de mídia: ' . $exception->getMessage();
                        }
                    }
                } else {
                    $files = $_FILES['table_files'] ?? [];
                    if (!is_array($files)) {
                        $errors[] = 'Requisição inválida de upload.';
                    } else {
                        try {
                            set_time_limit(0);
                        } catch (\Throwable) {
                            // segue sem alterar timeout quando a diretiva estiver bloqueada
                        }

                        $results = $service->importUploadedTables($files, [
                            'csv_mode' => $selectedMode,
                            'continue_on_error' => $continueOnError,
                        ]);

                        if (empty($results)) {
                            $errors[] = 'Nenhum arquivo foi selecionado para importação.';
                        } else {
                            $hasError = false;
                            foreach ($results as $result) {
                                if (($result['status'] ?? '') !== 'success') {
                                    $hasError = true;
                                    break;
                                }
                            }

                            if ($hasError) {
                                $errors[] = 'Importação finalizada com falhas. Revise o relatório por tabela.';
                            } else {
                                $success = 'Importação concluída com sucesso para todas as tabelas enviadas.';
                            }

                            $plan = $service->buildExecutionPlan();
                        }
                    }
                }
            }
        }

        View::render('migration/table-upload', [
            'plan' => $plan,
            'results' => $results,
            'errors' => $errors,
            'success' => $success,
            'selectedMode' => $selectedMode,
            'continueOnError' => $continueOnError,
            'unifiedReport' => $unifiedReport,
            'unifiedUploadName' => $unifiedUploadName,
            'unifiedMode' => $unifiedMode,
            'unifiedDryRun' => $unifiedDryRun,
            'unifiedContinueOnError' => $unifiedContinueOnError,
            'unifiedStrict' => $unifiedStrict,
            'unifiedSkipUnchanged' => $unifiedSkipUnchanged,
            'unifiedIdempotencyMap' => $unifiedIdempotencyMap,
            'mediaReport' => $mediaReport,
            'mediaUploadName' => $mediaUploadName,
            'mediaDryRun' => $mediaDryRun,
            'mediaContinueOnError' => $mediaContinueOnError,
            'mediaUpdateProducts' => $mediaUpdateProducts,
            'mediaUpdateMediaFiles' => $mediaUpdateMediaFiles,
            'mediaSourceSystem' => $mediaSourceSystem,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Migração de Tabelas',
        ]);
    }

    /**
     * @param array<string, mixed> $file
     */
    private function storeUnifiedJsonlUpload(array $file): string
    {
        $name = trim((string) ($file['name'] ?? ''));
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $size = (int) ($file['size'] ?? 0);

        if ($name === '') {
            throw new \RuntimeException('Nenhum arquivo JSONL foi selecionado.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->translateUploadError($error));
        }
        if (!is_uploaded_file($tmpName)) {
            throw new \RuntimeException('Arquivo temporário inválido para importação unificada.');
        }
        if ($size <= 0) {
            throw new \RuntimeException('Arquivo JSONL vazio.');
        }

        $maxMb = (int) (getenv('MIGRATION_UPLOAD_MAX_MB') ?: 50);
        $maxMb = max(1, min(500, $maxMb));
        $maxBytes = $maxMb * 1024 * 1024;
        if ($size > $maxBytes) {
            throw new \RuntimeException('Arquivo acima do limite permitido (' . $maxMb . ' MB).');
        }

        $normalizedName = strtolower($name);
        if (!preg_match('/\\.jsonl(\\.gz)?$/', $normalizedName)) {
            throw new \RuntimeException('Formato inválido. Use arquivo .jsonl ou .jsonl.gz.');
        }

        $targetDir = dirname(__DIR__, 2) . '/uploads/migration-imports/unified-jsonl/' . date('Ymd_His');
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Não foi possível criar diretório de upload unificado.');
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name)) ?: 'arquivo.jsonl';
        $targetPath = $targetDir . '/' . $safeName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new \RuntimeException('Falha ao mover arquivo JSONL enviado.');
        }

        return $targetPath;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function storeMediaBundleUpload(array $file): string
    {
        $name = trim((string) ($file['name'] ?? ''));
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $size = (int) ($file['size'] ?? 0);

        if ($name === '') {
            throw new \RuntimeException('Nenhum bundle ZIP foi selecionado.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->translateUploadError($error));
        }
        if (!is_uploaded_file($tmpName)) {
            throw new \RuntimeException('Arquivo temporário inválido para importação de mídia.');
        }
        if ($size <= 0) {
            throw new \RuntimeException('Bundle ZIP vazio.');
        }

        $maxMb = (int) (getenv('MIGRATION_MEDIA_UPLOAD_MAX_MB') ?: 1024);
        $maxMb = max(10, min(4096, $maxMb));
        $maxBytes = $maxMb * 1024 * 1024;
        if ($size > $maxBytes) {
            throw new \RuntimeException('Bundle acima do limite permitido (' . $maxMb . ' MB).');
        }

        $normalizedName = strtolower($name);
        if (!preg_match('/\\.zip$/', $normalizedName)) {
            throw new \RuntimeException('Formato inválido. Use arquivo .zip.');
        }

        $targetDir = dirname(__DIR__, 2) . '/uploads/migration-imports/media-bundles/' . date('Ymd_His');
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Não foi possível criar diretório de upload de mídia.');
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name)) ?: 'media-bundle.zip';
        $targetPath = $targetDir . '/' . $safeName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new \RuntimeException('Falha ao mover bundle de mídia enviado.');
        }

        return $targetPath;
    }

    private function translateUploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o limite permitido pelo servidor.',
            UPLOAD_ERR_PARTIAL => 'Upload incompleto. Envie novamente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário indisponível.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão do PHP.',
            default => 'Erro desconhecido no upload.',
        };
    }
}
