<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\ProcessMetadataRepository;

final class ProcessMetadataService
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg'];
    private const ALLOWED_MIME = [
        'application/pdf',
        'image/png',
        'image/jpeg',
    ];
    private const MAX_FILE_SIZE = 15728640; // 15MB
    private const ALLOWED_CHANNELS = ['sei', 'email', 'protocolo_fisico', 'sistema_externo', 'outro'];

    public function __construct(
        private ProcessMetadataRepository $meta,
        private AuditService $audit,
        private EventService $events,
        private Config $config
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->meta->paginate($filters, $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->meta->findById($id);
    }

    /** @return array<int, array<string, mixed>> */
    public function activePeople(int $organId = 0, int $limit = 1200): array
    {
        return $this->meta->activePeople($organId, $limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeOrgans(): array
    {
        return $this->meta->activeOrgans();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function channelOptions(): array
    {
        return [
            ['value' => 'sei', 'label' => 'SEI'],
            ['value' => 'email', 'label' => 'Email'],
            ['value' => 'protocolo_fisico', 'label' => 'Protocolo fisico'],
            ['value' => 'sistema_externo', 'label' => 'Sistema externo'],
            ['value' => 'outro', 'label' => 'Outro'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>, id?: int}
     */
    public function create(array $input, ?array $file, int $userId, string $ip, string $userAgent): array
    {
        $validation = $this->validateInput($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        $personId = (int) ($validation['data']['person_id'] ?? 0);
        if ($this->meta->findByPersonId($personId) !== null) {
            return [
                'ok' => false,
                'errors' => ['Ja existe metadado formal cadastrado para esta pessoa. Edite o registro existente.'],
                'data' => $validation['data'],
            ];
        }

        $attachment = $this->persistAttachment($file, $personId);
        if (!$attachment['ok']) {
            return [
                'ok' => false,
                'errors' => [$attachment['error']],
                'data' => $validation['data'],
            ];
        }

        $payload = $validation['data'];
        $payload['created_by'] = $userId > 0 ? $userId : null;
        $payload['dou_attachment_original_name'] = $attachment['meta']['dou_attachment_original_name'] ?? null;
        $payload['dou_attachment_stored_name'] = $attachment['meta']['dou_attachment_stored_name'] ?? null;
        $payload['dou_attachment_mime_type'] = $attachment['meta']['dou_attachment_mime_type'] ?? null;
        $payload['dou_attachment_file_size'] = $attachment['meta']['dou_attachment_file_size'] ?? null;
        $payload['dou_attachment_storage_path'] = $attachment['meta']['dou_attachment_storage_path'] ?? null;

        $id = $this->meta->create($payload);

        $this->audit->log(
            entity: 'process_metadata',
            entityId: $id,
            action: 'create',
            beforeData: null,
            afterData: $payload,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'process_metadata',
            type: 'process_meta.created',
            payload: [
                'person_id' => $personId,
                'office_number' => (string) ($payload['office_number'] ?? ''),
                'dou_edition' => (string) ($payload['dou_edition'] ?? ''),
                'mte_entry_date' => (string) ($payload['mte_entry_date'] ?? ''),
            ],
            entityId: $id,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $payload,
            'id' => $id,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>}
     */
    public function update(int $id, array $input, ?array $file, int $userId, string $ip, string $userAgent): array
    {
        $before = $this->meta->findById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Metadado formal nao encontrado.'],
                'data' => [],
            ];
        }

        $validation = $this->validateInput($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        $personId = (int) ($validation['data']['person_id'] ?? 0);
        $existingByPerson = $this->meta->findByPersonId($personId);
        if ($existingByPerson !== null && (int) ($existingByPerson['id'] ?? 0) !== $id) {
            return [
                'ok' => false,
                'errors' => ['Ja existe metadado formal cadastrado para esta pessoa.'],
                'data' => $validation['data'],
            ];
        }

        $attachment = $this->persistAttachment($file, $personId);
        if (!$attachment['ok']) {
            return [
                'ok' => false,
                'errors' => [$attachment['error']],
                'data' => $validation['data'],
            ];
        }

        $payload = $validation['data'];
        if ($attachment['meta'] !== null) {
            $payload['dou_attachment_original_name'] = $attachment['meta']['dou_attachment_original_name'];
            $payload['dou_attachment_stored_name'] = $attachment['meta']['dou_attachment_stored_name'];
            $payload['dou_attachment_mime_type'] = $attachment['meta']['dou_attachment_mime_type'];
            $payload['dou_attachment_file_size'] = $attachment['meta']['dou_attachment_file_size'];
            $payload['dou_attachment_storage_path'] = $attachment['meta']['dou_attachment_storage_path'];
        } else {
            $payload['dou_attachment_original_name'] = $before['dou_attachment_original_name'] ?? null;
            $payload['dou_attachment_stored_name'] = $before['dou_attachment_stored_name'] ?? null;
            $payload['dou_attachment_mime_type'] = $before['dou_attachment_mime_type'] ?? null;
            $payload['dou_attachment_file_size'] = $before['dou_attachment_file_size'] ?? null;
            $payload['dou_attachment_storage_path'] = $before['dou_attachment_storage_path'] ?? null;
        }

        $this->meta->update($id, $payload);
        $after = $this->meta->findById($id);

        $this->audit->log(
            entity: 'process_metadata',
            entityId: $id,
            action: 'update',
            beforeData: $before,
            afterData: $after ?? $payload,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'process_metadata',
            type: 'process_meta.updated',
            payload: [
                'person_id' => $personId,
                'office_number' => (string) ($payload['office_number'] ?? ''),
                'dou_edition' => (string) ($payload['dou_edition'] ?? ''),
                'mte_entry_date' => (string) ($payload['mte_entry_date'] ?? ''),
            ],
            entityId: $id,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $payload,
        ];
    }

    public function delete(int $id, int $userId, string $ip, string $userAgent): bool
    {
        $before = $this->meta->findById($id);
        if ($before === null) {
            return false;
        }

        $deleted = $this->meta->softDelete($id);
        if (!$deleted) {
            return false;
        }

        $this->audit->log(
            entity: 'process_metadata',
            entityId: $id,
            action: 'delete',
            beforeData: $before,
            afterData: null,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'process_metadata',
            type: 'process_meta.deleted',
            payload: [
                'person_id' => (int) ($before['person_id'] ?? 0),
            ],
            entityId: $id,
            userId: $userId
        );

        return true;
    }

    /** @return array{path: string, original_name: string, mime_type: string, id: int, person_id: int}|null */
    public function attachmentForDownload(int $id, int $userId, string $ip, string $userAgent): ?array
    {
        $row = $this->meta->findAttachmentById($id);
        if ($row === null) {
            return null;
        }

        $base = rtrim((string) $this->config->get('paths.storage_uploads', ''), '/');
        if ($base === '') {
            return null;
        }

        $relative = ltrim((string) ($row['dou_attachment_storage_path'] ?? ''), '/');
        $path = $base . '/' . $relative;
        if (!is_file($path)) {
            return null;
        }

        $this->audit->log(
            entity: 'process_metadata',
            entityId: (int) ($row['id'] ?? 0),
            action: 'download_dou_attachment',
            beforeData: null,
            afterData: [
                'person_id' => (int) ($row['person_id'] ?? 0),
                'file_name' => (string) ($row['dou_attachment_original_name'] ?? ''),
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'process_metadata',
            type: 'process_meta.attachment_downloaded',
            payload: [
                'person_id' => (int) ($row['person_id'] ?? 0),
            ],
            entityId: (int) ($row['id'] ?? 0),
            userId: $userId
        );

        return [
            'path' => $path,
            'original_name' => (string) ($row['dou_attachment_original_name'] ?? 'anexo_dou'),
            'mime_type' => (string) ($row['dou_attachment_mime_type'] ?? 'application/octet-stream'),
            'id' => (int) ($row['id'] ?? 0),
            'person_id' => (int) ($row['person_id'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, data: array<string, mixed>}
     */
    private function validateInput(array $input): array
    {
        $personId = (int) ($input['person_id'] ?? 0);
        $officeNumber = $this->clean($input['office_number'] ?? null);
        $officeSentAtRaw = $this->clean($input['office_sent_at'] ?? null);
        $officeSentAt = $this->normalizeDate($officeSentAtRaw);
        $officeChannel = mb_strtolower((string) ($input['office_channel'] ?? ''));
        $officeProtocol = $this->clean($input['office_protocol'] ?? null);
        $douEdition = $this->clean($input['dou_edition'] ?? null);
        $douPublishedAtRaw = $this->clean($input['dou_published_at'] ?? null);
        $douPublishedAt = $this->normalizeDate($douPublishedAtRaw);
        $douLink = $this->clean($input['dou_link'] ?? null);
        $mteEntryDateRaw = $this->clean($input['mte_entry_date'] ?? null);
        $mteEntryDate = $this->normalizeDate($mteEntryDateRaw);
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];
        $person = $personId > 0 ? $this->meta->findPersonById($personId) : null;
        if ($person === null) {
            $errors[] = 'Pessoa invalida para metadados de processo.';
        }

        if ($officeNumber !== null && mb_strlen($officeNumber) > 120) {
            $errors[] = 'Numero de oficio excede limite de 120 caracteres.';
        }

        if ($officeSentAtRaw !== null && $officeSentAt === null) {
            $errors[] = 'Data de envio do oficio invalida.';
        }

        if ($officeChannel !== '' && !in_array($officeChannel, self::ALLOWED_CHANNELS, true)) {
            $errors[] = 'Canal de envio invalido.';
        }

        if ($officeProtocol !== null && mb_strlen($officeProtocol) > 120) {
            $errors[] = 'Protocolo excede limite de 120 caracteres.';
        }

        if ($douEdition !== null && mb_strlen($douEdition) > 120) {
            $errors[] = 'Edicao DOU excede limite de 120 caracteres.';
        }

        if ($douPublishedAtRaw !== null && $douPublishedAt === null) {
            $errors[] = 'Data de publicacao DOU invalida.';
        }

        if ($douLink !== null && mb_strlen($douLink) > 500) {
            $errors[] = 'Link da publicacao DOU excede limite de 500 caracteres.';
        }

        if ($douLink !== null && !filter_var($douLink, FILTER_VALIDATE_URL)) {
            $errors[] = 'Link da publicacao DOU invalido.';
        }

        if ($mteEntryDateRaw !== null && $mteEntryDate === null) {
            $errors[] = 'Data oficial de entrada no MTE invalida.';
        }

        if ($officeSentAt !== null && $mteEntryDate !== null && strtotime($mteEntryDate) < strtotime($officeSentAt)) {
            $errors[] = 'Data de entrada oficial no MTE nao pode ser anterior ao envio do oficio.';
        }

        if ($douPublishedAt !== null && $officeSentAt !== null && strtotime($douPublishedAt) < strtotime($officeSentAt)) {
            $errors[] = 'Data de publicacao DOU nao pode ser anterior ao envio do oficio.';
        }

        $data = [
            'person_id' => $personId,
            'office_number' => $officeNumber === null ? null : mb_substr($officeNumber, 0, 120),
            'office_sent_at' => $officeSentAt,
            'office_channel' => $officeChannel === '' ? null : $officeChannel,
            'office_protocol' => $officeProtocol === null ? null : mb_substr($officeProtocol, 0, 120),
            'dou_edition' => $douEdition === null ? null : mb_substr($douEdition, 0, 120),
            'dou_published_at' => $douPublishedAt,
            'dou_link' => $douLink === null ? null : mb_substr($douLink, 0, 500),
            'mte_entry_date' => $mteEntryDate,
            'notes' => $notes,
        ];

        return [
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, error: string, meta: array<string, mixed>|null}
     */
    private function persistAttachment(?array $file, int $personId): array
    {
        if ($file === null || !isset($file['error'])) {
            return ['ok' => true, 'error' => '', 'meta' => null];
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'error' => '', 'meta' => null];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return [
                'ok' => false,
                'error' => 'Falha no upload do anexo DOU.',
                'meta' => null,
            ];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            return [
                'ok' => false,
                'error' => 'Anexo DOU fora do limite permitido (15MB).',
                'meta' => null,
            ];
        }

        $ext = mb_strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return [
                'ok' => false,
                'error' => 'Anexo DOU invalido. Envie PDF, PNG ou JPG.',
                'meta' => null,
            ];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? (string) finfo_file($finfo, $tmpName) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return [
                'ok' => false,
                'error' => 'Tipo de arquivo invalido para anexo DOU.',
                'meta' => null,
            ];
        }

        $baseUploads = rtrim((string) $this->config->get('paths.storage_uploads', ''), '/');
        if ($baseUploads === '') {
            return [
                'ok' => false,
                'error' => 'Diretorio de uploads nao configurado.',
                'meta' => null,
            ];
        }

        $subDir = sprintf('process_metadata/%d/%s', max(0, $personId), date('Y/m'));
        $targetDir = $baseUploads . '/' . $subDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return [
                'ok' => false,
                'error' => 'Nao foi possivel preparar diretorio de anexo DOU.',
                'meta' => null,
            ];
        }

        try {
            $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
            $targetPath = $targetDir . '/' . $storedName;
            if (!move_uploaded_file($tmpName, $targetPath)) {
                if (!rename($tmpName, $targetPath)) {
                    return [
                        'ok' => false,
                        'error' => 'Nao foi possivel salvar anexo DOU.',
                        'meta' => null,
                    ];
                }
            }
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'error' => 'Falha ao gerar nome seguro do anexo DOU.',
                'meta' => null,
            ];
        }

        return [
            'ok' => true,
            'error' => '',
            'meta' => [
                'dou_attachment_original_name' => mb_substr($originalName, 0, 255),
                'dou_attachment_stored_name' => $storedName,
                'dou_attachment_mime_type' => $mime,
                'dou_attachment_file_size' => $size,
                'dou_attachment_storage_path' => $subDir . '/' . $storedName,
            ],
        ];
    }

    private function clean(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }
}
