<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\DocumentRepository;

final class DocumentService
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];
    private const ALLOWED_MIME = ['application/pdf', 'image/jpeg', 'image/png'];
    private const MAX_FILE_SIZE = 10485760; // 10MB
    private const SENSITIVITY_PUBLIC = 'public';
    private const SENSITIVITY_RESTRICTED = 'restricted';
    private const SENSITIVITY_SENSITIVE = 'sensitive';
    private const SENSITIVITY_LEVELS = [
        self::SENSITIVITY_PUBLIC,
        self::SENSITIVITY_RESTRICTED,
        self::SENSITIVITY_SENSITIVE,
    ];

    public function __construct(
        private DocumentRepository $documents,
        private AuditService $audit,
        private EventService $events,
        private Config $config,
        private LgpdService $lgpd,
        private SecuritySettingsService $security
    ) {
    }

    /**
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   pagination: array<string, int>,
     *   document_types: array<int, array<string, mixed>>,
     *   sensitivity_options: array<int, array{value: string, label: string}>
     * }
     */
    public function profileData(
        int $personId,
        int $page = 1,
        int $perPage = 10,
        bool $canViewSensitiveDocuments = false
    ): array
    {
        $result = $this->documents->paginateByPerson($personId, $page, $perPage, $canViewSensitiveDocuments);

        return [
            'items' => $result['items'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'pages' => $result['pages'],
            ],
            'document_types' => $this->documents->activeDocumentTypes(),
            'sensitivity_options' => $this->sensitivityOptions($canViewSensitiveDocuments),
        ];
    }

    /**
     * @return array{ok: bool, message: string, warnings: array<int, string>, errors: array<int, string>, created_ids: array<int, int>}
     */
    public function uploadDocuments(
        int $personId,
        array $input,
        array $files,
        int $userId,
        string $ip,
        string $userAgent,
        bool $canAssignSensitiveDocuments = false
    ): array {
        $documentTypeId = (int) ($input['document_type_id'] ?? 0);
        $titleInput = trim((string) ($input['title'] ?? ''));
        $referenceSei = $this->clean($input['reference_sei'] ?? null);
        $documentDate = $this->normalizeDate($this->clean($input['document_date'] ?? null));
        $tags = $this->normalizeTags($this->clean($input['tags'] ?? null));
        $notes = $this->clean($input['notes'] ?? null);
        $sensitivityLevel = $this->normalizeSensitivityLevel($input['sensitivity_level'] ?? null);

        $errors = [];
        $warnings = [];

        $types = $this->documents->activeDocumentTypes();
        $validTypeIds = array_map(static fn (array $type): int => (int) ($type['id'] ?? 0), $types);

        if ($documentTypeId <= 0 || !in_array($documentTypeId, $validTypeIds, true)) {
            $errors[] = 'Tipo de documento inválido.';
        }

        if ($this->clean($input['document_date'] ?? null) !== null && $documentDate === null) {
            $errors[] = 'Data do documento inválida.';
        }

        if ($sensitivityLevel === null) {
            $errors[] = 'Nível de sensibilidade inválido.';
        }

        if (
            $sensitivityLevel !== null
            && $sensitivityLevel !== self::SENSITIVITY_PUBLIC
            && !$canAssignSensitiveDocuments
        ) {
            $errors[] = 'Você não tem permissão para classificar documentos como restritos ou sensíveis.';
        }

        $normalizedFiles = $this->normalizeFilesArray($files['files'] ?? null);
        if ($normalizedFiles === []) {
            $errors[] = 'Selecione ao menos um arquivo.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Não foi possível registrar documentos.',
                'warnings' => [],
                'errors' => $errors,
                'created_ids' => [],
            ];
        }

        $baseUploads = rtrim((string) $this->config->get('paths.storage_uploads', ''), '/');
        if ($baseUploads === '') {
            return [
                'ok' => false,
                'message' => 'Diretório de uploads não configurado.',
                'warnings' => [],
                'errors' => ['Diretório de uploads não configurado.'],
                'created_ids' => [],
            ];
        }

        $subDir = sprintf('%d/documents/%s', $personId, date('Y/m'));
        $targetDir = $baseUploads . '/' . $subDir;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return [
                'ok' => false,
                'message' => 'Não foi possível preparar diretório de documentos.',
                'warnings' => [],
                'errors' => ['Não foi possível preparar diretório de documentos.'],
                'created_ids' => [],
            ];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $createdIds = [];
        $maxBytes = $this->maxUploadBytes();
        $maxMb = max(1, (int) ceil($maxBytes / 1048576));

        foreach ($normalizedFiles as $file) {
            $name = (string) ($file['name'] ?? '');
            $tmpName = (string) ($file['tmp_name'] ?? '');
            $size = (int) ($file['size'] ?? 0);
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                $warnings[] = 'Falha ao processar arquivo: ' . $name;
                continue;
            }

            if (!UploadSecurityService::isSafeOriginalName($name)) {
                $warnings[] = 'Nome de arquivo invalido: ' . $name;
                continue;
            }

            if (!UploadSecurityService::isNativeUploadedFile($tmpName)) {
                $warnings[] = 'Upload invalido ou nao confiavel: ' . $name;
                continue;
            }

            if ($size <= 0 || $size > $maxBytes) {
                $warnings[] = sprintf('Arquivo fora do limite permitido (%dMB): %s', $maxMb, $name);
                continue;
            }

            $ext = mb_strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                $warnings[] = 'Extensão não permitida: ' . $name;
                continue;
            }

            $mime = $finfo !== false ? (string) finfo_file($finfo, $tmpName) : '';
            if (!in_array($mime, self::ALLOWED_MIME, true)) {
                $warnings[] = 'Tipo de arquivo não permitido: ' . $name;
                continue;
            }

            if (!UploadSecurityService::matchesKnownSignature($tmpName, $mime)) {
                $warnings[] = 'Assinatura binaria invalida para o tipo informado: ' . $name;
                continue;
            }

            $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
            $targetPath = $targetDir . '/' . $storedName;

            if (!move_uploaded_file($tmpName, $targetPath)) {
                $warnings[] = 'Não foi possível salvar arquivo: ' . $name;
                continue;
            }

            $relativePath = $subDir . '/' . $storedName;
            $title = $this->normalizeTitle($titleInput, $name);

            $documentId = $this->documents->createDocument([
                'person_id' => $personId,
                'document_type_id' => $documentTypeId,
                'title' => $title,
                'reference_sei' => $referenceSei,
                'document_date' => $documentDate,
                'tags' => $tags,
                'notes' => $notes,
                'sensitivity_level' => $sensitivityLevel,
                'original_name' => $name,
                'stored_name' => $storedName,
                'mime_type' => $mime,
                'file_size' => $size,
                'storage_path' => $relativePath,
                'uploaded_by' => $userId,
            ]);

            $createdIds[] = $documentId;

            $this->audit->log(
                entity: 'document',
                entityId: $documentId,
                action: 'upload',
                beforeData: null,
                afterData: [
                    'person_id' => $personId,
                    'document_type_id' => $documentTypeId,
                    'title' => $title,
                    'original_name' => $name,
                    'mime_type' => $mime,
                    'file_size' => $size,
                    'sensitivity_level' => $sensitivityLevel,
                ],
                metadata: [
                    'reference_sei' => $referenceSei,
                    'document_date' => $documentDate,
                    'tags' => $tags,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );
        }

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if ($createdIds === []) {
            return [
                'ok' => false,
                'message' => 'Nenhum arquivo válido foi registrado.',
                'warnings' => $warnings,
                'errors' => ['Nenhum arquivo válido foi registrado.'],
                'created_ids' => [],
            ];
        }

        $this->events->recordEvent(
            entity: 'person',
            type: 'document.uploaded',
            payload: [
                'count' => count($createdIds),
                'document_type_id' => $documentTypeId,
                'sensitivity_level' => $sensitivityLevel,
                'document_ids' => $createdIds,
            ],
            entityId: $personId,
            userId: $userId
        );

        return [
            'ok' => true,
            'message' => count($createdIds) . ' documento(s) registrado(s) com sucesso.',
            'warnings' => $warnings,
            'errors' => [],
            'created_ids' => $createdIds,
        ];
    }

    /** @return array{path: string, original_name: string, mime_type: string, id: int, title: string}|null */
    public function documentForDownload(
        int $documentId,
        int $personId,
        int $userId,
        string $ip,
        string $userAgent,
        bool $canAccessSensitiveDocuments = false
    ): ?array
    {
        $document = $this->documents->findByIdForPerson($documentId, $personId);
        if ($document === null) {
            return null;
        }

        $sensitivityLevel = $this->normalizeSensitivityLevel($document['sensitivity_level'] ?? null) ?? self::SENSITIVITY_PUBLIC;
        $requiresSensitivePermission = $sensitivityLevel !== self::SENSITIVITY_PUBLIC;

        if ($requiresSensitivePermission && !$canAccessSensitiveDocuments) {
            $this->audit->log(
                entity: 'document',
                entityId: (int) ($document['id'] ?? 0),
                action: 'download_denied',
                beforeData: null,
                afterData: [
                    'person_id' => $personId,
                    'title' => (string) ($document['title'] ?? ''),
                    'sensitivity_level' => $sensitivityLevel,
                ],
                metadata: [
                    'reason' => 'missing_sensitive_permission',
                    'required_permission' => 'people.documents.sensitive',
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'person',
                type: 'document.download_denied',
                payload: [
                    'document_id' => (int) ($document['id'] ?? 0),
                    'title' => (string) ($document['title'] ?? ''),
                    'sensitivity_level' => $sensitivityLevel,
                    'required_permission' => 'people.documents.sensitive',
                ],
                entityId: $personId,
                userId: $userId
            );

            $this->lgpd->registerSensitiveAccess(
                entity: 'document',
                entityId: (int) ($document['id'] ?? 0),
                action: 'document_download_denied',
                sensitivity: $this->lgpdSensitivity($sensitivityLevel),
                subjectPersonId: $personId,
                subjectLabel: (string) ($document['title'] ?? ''),
                contextPath: '/people/documents/download',
                metadata: [
                    'document_type_id' => (int) ($document['document_type_id'] ?? 0),
                    'document_type_name' => (string) ($document['document_type_name'] ?? ''),
                    'sensitivity_level' => $sensitivityLevel,
                    'required_permission' => 'people.documents.sensitive',
                    'access_granted' => false,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            return null;
        }

        $base = rtrim((string) $this->config->get('paths.storage_uploads', ''), '/');
        if ($base === '') {
            return null;
        }

        $relative = ltrim((string) ($document['storage_path'] ?? ''), '/');
        $path = $base . '/' . $relative;

        if (!is_file($path)) {
            return null;
        }

        $this->audit->log(
            entity: 'document',
            entityId: (int) ($document['id'] ?? 0),
            action: 'download',
            beforeData: null,
            afterData: [
                'person_id' => $personId,
                'title' => (string) ($document['title'] ?? ''),
                'original_name' => (string) ($document['original_name'] ?? ''),
                'sensitivity_level' => $sensitivityLevel,
            ],
            metadata: [
                'sensitivity_label' => $this->sensitivityLabel($sensitivityLevel),
            ],
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'person',
            type: 'document.downloaded',
            payload: [
                'document_id' => (int) ($document['id'] ?? 0),
                'title' => (string) ($document['title'] ?? ''),
                'sensitivity_level' => $sensitivityLevel,
            ],
            entityId: $personId,
            userId: $userId
        );

        $this->lgpd->registerSensitiveAccess(
            entity: 'document',
            entityId: (int) ($document['id'] ?? 0),
            action: 'document_download',
            sensitivity: $this->lgpdSensitivity($sensitivityLevel),
            subjectPersonId: $personId,
            subjectLabel: (string) ($document['title'] ?? ''),
            contextPath: '/people/documents/download',
            metadata: [
                'document_type_id' => (int) ($document['document_type_id'] ?? 0),
                'document_type_name' => (string) ($document['document_type_name'] ?? ''),
                'sensitivity_level' => $sensitivityLevel,
                'access_granted' => true,
            ],
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        return [
            'path' => $path,
            'original_name' => (string) ($document['original_name'] ?? 'documento'),
            'mime_type' => (string) ($document['mime_type'] ?? 'application/octet-stream'),
            'id' => (int) ($document['id'] ?? 0),
            'title' => (string) ($document['title'] ?? ''),
        ];
    }

    /**
     * @return array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    private function normalizeFilesArray(mixed $raw): array
    {
        if (!is_array($raw) || !isset($raw['name'])) {
            return [];
        }

        if (!is_array($raw['name'])) {
            return [[
                'name' => (string) ($raw['name'] ?? ''),
                'type' => (string) ($raw['type'] ?? ''),
                'tmp_name' => (string) ($raw['tmp_name'] ?? ''),
                'error' => (int) ($raw['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($raw['size'] ?? 0),
            ]];
        }

        $files = [];
        foreach ($raw['name'] as $index => $name) {
            $files[] = [
                'name' => (string) $name,
                'type' => (string) ($raw['type'][$index] ?? ''),
                'tmp_name' => (string) ($raw['tmp_name'][$index] ?? ''),
                'error' => (int) ($raw['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($raw['size'][$index] ?? 0),
            ];
        }

        return $files;
    }

    private function normalizeTitle(string $inputTitle, string $fileName): string
    {
        $title = trim($inputTitle);
        if ($title === '') {
            $title = trim((string) pathinfo($fileName, PATHINFO_FILENAME));
        }

        if ($title === '') {
            $title = 'Documento ' . date('YmdHis');
        }

        return mb_substr($title, 0, 190);
    }

    private function normalizeDate(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $time = strtotime($input);
        if ($time === false) {
            return null;
        }

        return date('Y-m-d', $time);
    }

    private function normalizeTags(?string $tags): ?string
    {
        if ($tags === null) {
            return null;
        }

        $parts = array_filter(array_map(static fn (string $part): string => trim($part), explode(',', $tags)));
        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    private function clean(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function maxUploadBytes(): int
    {
        $globalLimit = max(1048576, $this->security->uploadMaxBytes());

        return min(self::MAX_FILE_SIZE, $globalLimit);
    }

    /** @return array<int, array{value: string, label: string}> */
    private function sensitivityOptions(bool $includeSensitiveLevels): array
    {
        if (!$includeSensitiveLevels) {
            return [['value' => self::SENSITIVITY_PUBLIC, 'label' => $this->sensitivityLabel(self::SENSITIVITY_PUBLIC)]];
        }

        return [
            ['value' => self::SENSITIVITY_PUBLIC, 'label' => $this->sensitivityLabel(self::SENSITIVITY_PUBLIC)],
            ['value' => self::SENSITIVITY_RESTRICTED, 'label' => $this->sensitivityLabel(self::SENSITIVITY_RESTRICTED)],
            ['value' => self::SENSITIVITY_SENSITIVE, 'label' => $this->sensitivityLabel(self::SENSITIVITY_SENSITIVE)],
        ];
    }

    private function sensitivityLabel(string $sensitivityLevel): string
    {
        return match ($sensitivityLevel) {
            self::SENSITIVITY_PUBLIC => 'Publico',
            self::SENSITIVITY_RESTRICTED => 'Restrito',
            self::SENSITIVITY_SENSITIVE => 'Sensivel',
            default => 'Publico',
        };
    }

    private function lgpdSensitivity(string $sensitivityLevel): string
    {
        return match ($sensitivityLevel) {
            self::SENSITIVITY_RESTRICTED => 'document_restricted',
            self::SENSITIVITY_SENSITIVE => 'document_sensitive',
            default => 'document_public',
        };
    }

    private function normalizeSensitivityLevel(mixed $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        if (!in_array($normalized, self::SENSITIVITY_LEVELS, true)) {
            return null;
        }

        return $normalized;
    }
}
