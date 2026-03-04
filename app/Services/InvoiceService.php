<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\InvoiceRepository;

final class InvoiceService
{
    private const ALLOWED_STATUSES = ['aberto', 'vencido', 'pago_parcial', 'pago', 'cancelado'];
    private const FINAL_STATUSES = ['pago', 'cancelado'];
    private const ALLOWED_EXTENSIONS = ['pdf'];
    private const ALLOWED_MIME = ['application/pdf'];
    private const MAX_FILE_SIZE = 15728640; // 15MB

    public function __construct(
        private InvoiceRepository $invoices,
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
        $result = $this->invoices->paginate($filters, $page, $perPage);

        foreach ($result['items'] as &$item) {
            $item['status'] = $this->effectiveStatus(
                status: (string) ($item['status'] ?? 'aberto'),
                dueDate: (string) ($item['due_date'] ?? ''),
                paidAmount: (float) ($item['paid_amount'] ?? 0),
                totalAmount: (float) ($item['total_amount'] ?? 0)
            );
        }

        return $result;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $invoice = $this->invoices->findById($id);
        if ($invoice === null) {
            return null;
        }

        $invoice['status'] = $this->effectiveStatus(
            status: (string) ($invoice['status'] ?? 'aberto'),
            dueDate: (string) ($invoice['due_date'] ?? ''),
            paidAmount: (float) ($invoice['paid_amount'] ?? 0),
            totalAmount: (float) ($invoice['total_amount'] ?? 0)
        );

        return $invoice;
    }

    /** @return array<int, array<string, mixed>> */
    public function links(int $invoiceId): array
    {
        return $this->invoices->linksByInvoice($invoiceId);
    }

    /** @return array<int, array<string, mixed>> */
    public function availablePeople(int $invoiceId, int $limit = 300): array
    {
        return $this->invoices->availablePeopleForLinking($invoiceId, $limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeOrgans(): array
    {
        return $this->invoices->activeOrgans();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function statusOptions(): array
    {
        return [
            ['value' => 'aberto', 'label' => 'Aberto'],
            ['value' => 'vencido', 'label' => 'Vencido'],
            ['value' => 'pago_parcial', 'label' => 'Pago parcial'],
            ['value' => 'pago', 'label' => 'Pago'],
            ['value' => 'cancelado', 'label' => 'Cancelado'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>, id?: int}
     */
    public function create(array $input, ?array $file, int $userId, string $ip, string $userAgent): array
    {
        $validation = $this->validateInvoiceInput($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        $invoiceNumber = (string) $validation['data']['invoice_number'];
        if ($this->invoices->invoiceNumberExists($invoiceNumber)) {
            return [
                'ok' => false,
                'errors' => ['Ja existe boleto cadastrado com este numero.'],
                'data' => $validation['data'],
            ];
        }

        $pdfResult = $this->persistPdf($file, (int) ($validation['data']['organ_id'] ?? 0));
        if (!$pdfResult['ok']) {
            return [
                'ok' => false,
                'errors' => [$pdfResult['error']],
                'data' => $validation['data'],
            ];
        }

        $payload = $validation['data'];
        $payload['status'] = $this->effectiveStatus(
            status: (string) ($payload['status'] ?? 'aberto'),
            dueDate: (string) ($payload['due_date'] ?? ''),
            paidAmount: 0.0,
            totalAmount: (float) ($payload['total_amount'] ?? 0)
        );
        $payload['paid_amount'] = '0.00';
        $payload['created_by'] = $userId > 0 ? $userId : null;

        $pdfMeta = $pdfResult['meta'];
        $payload['pdf_original_name'] = $pdfMeta['pdf_original_name'] ?? null;
        $payload['pdf_stored_name'] = $pdfMeta['pdf_stored_name'] ?? null;
        $payload['pdf_mime_type'] = $pdfMeta['pdf_mime_type'] ?? null;
        $payload['pdf_file_size'] = $pdfMeta['pdf_file_size'] ?? null;
        $payload['pdf_storage_path'] = $pdfMeta['pdf_storage_path'] ?? null;

        $id = $this->invoices->create($payload);

        $this->audit->log(
            entity: 'invoice',
            entityId: $id,
            action: 'create',
            beforeData: null,
            afterData: $payload,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        if ($pdfMeta !== null) {
            $this->events->recordEvent(
                entity: 'invoice',
                type: 'invoice.pdf_uploaded',
                payload: [
                    'invoice_number' => $invoiceNumber,
                    'pdf_original_name' => $pdfMeta['pdf_original_name'],
                ],
                entityId: $id,
                userId: $userId
            );
        }

        $this->events->recordEvent(
            entity: 'invoice',
            type: 'invoice.created',
            payload: [
                'invoice_number' => $invoiceNumber,
                'status' => $payload['status'],
                'total_amount' => $payload['total_amount'],
                'organ_id' => $payload['organ_id'],
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
        $before = $this->invoices->findById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Boleto nao encontrado.'],
                'data' => [],
            ];
        }

        $validation = $this->validateInvoiceInput($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        $invoiceNumber = (string) $validation['data']['invoice_number'];
        if ($this->invoices->invoiceNumberExists($invoiceNumber, $id)) {
            return [
                'ok' => false,
                'errors' => ['Ja existe boleto cadastrado com este numero.'],
                'data' => $validation['data'],
            ];
        }

        $currentAllocated = max(0.0, (float) ($before['allocated_amount'] ?? 0));
        $newTotal = (float) ($validation['data']['total_amount'] ?? 0);
        if ($newTotal + 0.009 < $currentAllocated) {
            return [
                'ok' => false,
                'errors' => ['Valor total nao pode ficar abaixo do total ja rateado para pessoas.'],
                'data' => $validation['data'],
            ];
        }

        $pdfResult = $this->persistPdf($file, (int) ($validation['data']['organ_id'] ?? 0));
        if (!$pdfResult['ok']) {
            return [
                'ok' => false,
                'errors' => [$pdfResult['error']],
                'data' => $validation['data'],
            ];
        }

        $payload = $validation['data'];
        $payload['status'] = $this->effectiveStatus(
            status: (string) ($payload['status'] ?? 'aberto'),
            dueDate: (string) ($payload['due_date'] ?? ''),
            paidAmount: (float) ($before['paid_amount'] ?? 0),
            totalAmount: (float) ($payload['total_amount'] ?? 0)
        );

        $pdfMeta = $pdfResult['meta'];
        if ($pdfMeta !== null) {
            $payload['pdf_original_name'] = $pdfMeta['pdf_original_name'];
            $payload['pdf_stored_name'] = $pdfMeta['pdf_stored_name'];
            $payload['pdf_mime_type'] = $pdfMeta['pdf_mime_type'];
            $payload['pdf_file_size'] = $pdfMeta['pdf_file_size'];
            $payload['pdf_storage_path'] = $pdfMeta['pdf_storage_path'];
        } else {
            $payload['pdf_original_name'] = $before['pdf_original_name'] ?? null;
            $payload['pdf_stored_name'] = $before['pdf_stored_name'] ?? null;
            $payload['pdf_mime_type'] = $before['pdf_mime_type'] ?? null;
            $payload['pdf_file_size'] = $before['pdf_file_size'] ?? null;
            $payload['pdf_storage_path'] = $before['pdf_storage_path'] ?? null;
        }

        $this->invoices->update($id, $payload);
        $after = $this->invoices->findById($id);

        $this->audit->log(
            entity: 'invoice',
            entityId: $id,
            action: 'update',
            beforeData: $before,
            afterData: $payload,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        if ($pdfMeta !== null) {
            $this->events->recordEvent(
                entity: 'invoice',
                type: 'invoice.pdf_uploaded',
                payload: [
                    'invoice_number' => $invoiceNumber,
                    'pdf_original_name' => $pdfMeta['pdf_original_name'],
                ],
                entityId: $id,
                userId: $userId
            );
        }

        $beforeStatus = (string) ($before['status'] ?? '');
        $afterStatus = (string) ($after['status'] ?? ($payload['status'] ?? ''));
        if ($beforeStatus !== $afterStatus) {
            $this->events->recordEvent(
                entity: 'invoice',
                type: 'invoice.status_changed',
                payload: [
                    'invoice_number' => $invoiceNumber,
                    'before_status' => $beforeStatus,
                    'after_status' => $afterStatus,
                ],
                entityId: $id,
                userId: $userId
            );
        }

        $this->events->recordEvent(
            entity: 'invoice',
            type: 'invoice.updated',
            payload: [
                'invoice_number' => $invoiceNumber,
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
        $before = $this->invoices->findById($id);
        if ($before === null) {
            return false;
        }

        try {
            $this->invoices->beginTransaction();
            $this->invoices->softDeleteLinksByInvoice($id);
            $this->invoices->softDelete($id);

            $this->audit->log(
                entity: 'invoice',
                entityId: $id,
                action: 'delete',
                beforeData: $before,
                afterData: null,
                metadata: [
                    'linked_people_count' => (int) ($before['linked_people_count'] ?? 0),
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'invoice',
                type: 'invoice.deleted',
                payload: [
                    'invoice_number' => (string) ($before['invoice_number'] ?? ''),
                ],
                entityId: $id,
                userId: $userId
            );

            $this->invoices->commit();
        } catch (\Throwable $exception) {
            $this->invoices->rollBack();

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>}
     */
    public function linkPerson(int $invoiceId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $invoice = $this->invoices->findById($invoiceId);
        if ($invoice === null) {
            return [
                'ok' => false,
                'message' => 'Boleto nao encontrado.',
                'errors' => ['Boleto nao encontrado.'],
            ];
        }

        if ($this->isFinalStatus((string) ($invoice['status'] ?? ''))) {
            return [
                'ok' => false,
                'message' => 'Boleto liquidado/cancelado nao permite novos vinculos.',
                'errors' => ['Boleto em status final nao permite novos vinculos.'],
            ];
        }

        $personId = (int) ($input['person_id'] ?? 0);
        $amount = $this->parseMoneyOptional($input['allocated_amount'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];

        if ($personId <= 0) {
            $errors[] = 'Pessoa invalida para vinculo.';
        }

        if ($amount === null) {
            $errors[] = 'Valor de rateio invalido.';
        } elseif ((float) $amount < 0.0) {
            $errors[] = 'Valor de rateio nao pode ser negativo.';
        }

        $organId = (int) ($invoice['organ_id'] ?? 0);
        if ($personId > 0 && !$this->invoices->personBelongsToOrgan($personId, $organId)) {
            $errors[] = 'Pessoa informada nao pertence ao orgao do boleto.';
        }

        if ($personId > 0 && $this->invoices->activeLinkExists($invoiceId, $personId)) {
            $errors[] = 'Pessoa ja vinculada a este boleto.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel vincular pessoa ao boleto.',
                'errors' => $errors,
            ];
        }

        $available = max(0.0, (float) ($invoice['available_amount'] ?? 0));
        if ((float) $amount - $available > 0.009) {
            return [
                'ok' => false,
                'message' => 'Saldo insuficiente no boleto para este rateio.',
                'errors' => ['Rateio bloqueado: valor excede o saldo disponivel do boleto.'],
            ];
        }

        try {
            $this->invoices->beginTransaction();

            $linkId = $this->invoices->createPersonLink(
                invoiceId: $invoiceId,
                personId: $personId,
                allocatedAmount: $amount,
                notes: $notes,
                createdBy: $userId > 0 ? $userId : null
            );

            $this->audit->log(
                entity: 'invoice_person',
                entityId: $linkId,
                action: 'link',
                beforeData: null,
                afterData: [
                    'invoice_id' => $invoiceId,
                    'person_id' => $personId,
                    'allocated_amount' => $amount,
                    'notes' => $notes,
                ],
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'invoice',
                type: 'invoice.person_linked',
                payload: [
                    'link_id' => $linkId,
                    'person_id' => $personId,
                    'allocated_amount' => $amount,
                ],
                entityId: $invoiceId,
                userId: $userId
            );

            $this->invoices->commit();
        } catch (\Throwable $exception) {
            $this->invoices->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel vincular pessoa ao boleto.',
                'errors' => ['Falha ao persistir vinculo. Tente novamente.'],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Pessoa vinculada ao boleto com sucesso.',
            'errors' => [],
        ];
    }

    /**
     * @return array{ok: bool, message: string, errors: array<int, string>}
     */
    public function unlinkPerson(int $invoiceId, int $linkId, int $userId, string $ip, string $userAgent): array
    {
        $invoice = $this->invoices->findById($invoiceId);
        if ($invoice === null) {
            return [
                'ok' => false,
                'message' => 'Boleto nao encontrado.',
                'errors' => ['Boleto nao encontrado.'],
            ];
        }

        if ($this->isFinalStatus((string) ($invoice['status'] ?? ''))) {
            return [
                'ok' => false,
                'message' => 'Boleto liquidado/cancelado nao permite alteracao de vinculos.',
                'errors' => ['Boleto em status final nao permite remover vinculos.'],
            ];
        }

        $link = $this->invoices->findPersonLinkById($linkId, $invoiceId);
        if ($link === null) {
            return [
                'ok' => false,
                'message' => 'Vinculo nao encontrado.',
                'errors' => ['Vinculo de pessoa nao encontrado para este boleto.'],
            ];
        }

        try {
            $this->invoices->beginTransaction();
            $this->invoices->softDeletePersonLink($linkId);

            $this->audit->log(
                entity: 'invoice_person',
                entityId: $linkId,
                action: 'unlink',
                beforeData: $link,
                afterData: null,
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'invoice',
                type: 'invoice.person_unlinked',
                payload: [
                    'link_id' => $linkId,
                    'person_id' => (int) ($link['person_id'] ?? 0),
                    'allocated_amount' => (string) ($link['allocated_amount'] ?? '0.00'),
                ],
                entityId: $invoiceId,
                userId: $userId
            );

            $this->invoices->commit();
        } catch (\Throwable $exception) {
            $this->invoices->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel remover vinculo.',
                'errors' => ['Falha ao remover vinculo. Tente novamente.'],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Vinculo removido com sucesso.',
            'errors' => [],
        ];
    }

    /** @return array{path: string, original_name: string, mime_type: string, id: int, invoice_number: string}|null */
    public function pdfForDownload(int $invoiceId, int $userId, string $ip, string $userAgent): ?array
    {
        $invoice = $this->invoices->findPdfById($invoiceId);
        if ($invoice === null) {
            return null;
        }

        $base = rtrim((string) $this->config->get('paths.storage_uploads', ''), '/');
        if ($base === '') {
            return null;
        }

        $relative = ltrim((string) ($invoice['pdf_storage_path'] ?? ''), '/');
        $path = $base . '/' . $relative;
        if (!is_file($path)) {
            return null;
        }

        $this->audit->log(
            entity: 'invoice',
            entityId: (int) ($invoice['id'] ?? 0),
            action: 'download_pdf',
            beforeData: null,
            afterData: [
                'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
                'pdf_original_name' => (string) ($invoice['pdf_original_name'] ?? ''),
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'invoice',
            type: 'invoice.pdf_downloaded',
            payload: [
                'invoice_id' => (int) ($invoice['id'] ?? 0),
                'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
            ],
            entityId: (int) ($invoice['id'] ?? 0),
            userId: $userId
        );

        return [
            'path' => $path,
            'original_name' => (string) ($invoice['pdf_original_name'] ?? 'boleto.pdf'),
            'mime_type' => (string) ($invoice['pdf_mime_type'] ?? 'application/pdf'),
            'id' => (int) ($invoice['id'] ?? 0),
            'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, data: array<string, mixed>}
     */
    private function validateInvoiceInput(array $input): array
    {
        $organId = (int) ($input['organ_id'] ?? 0);
        $invoiceNumber = $this->clean($input['invoice_number'] ?? null);
        $title = $this->clean($input['title'] ?? null);
        $referenceMonthRaw = $this->clean($input['reference_month'] ?? null);
        $issueDateRaw = $this->clean($input['issue_date'] ?? null);
        $dueDateRaw = $this->clean($input['due_date'] ?? null);
        $totalAmount = $this->parseMoneyStrict($input['total_amount'] ?? null);
        $status = mb_strtolower((string) ($input['status'] ?? 'aberto'));
        $digitableLine = $this->clean($input['digitable_line'] ?? null);
        $referenceCode = $this->clean($input['reference_code'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];

        if ($organId <= 0 || !$this->invoices->organExists($organId)) {
            $errors[] = 'Orgao invalido para o boleto.';
        }

        if ($invoiceNumber === null || mb_strlen($invoiceNumber) < 3) {
            $errors[] = 'Numero do boleto e obrigatorio (minimo 3 caracteres).';
        }

        if ($title === null || mb_strlen($title) < 3) {
            $errors[] = 'Titulo do boleto e obrigatorio (minimo 3 caracteres).';
        }

        $referenceMonth = $this->normalizeReferenceMonth($referenceMonthRaw);
        if ($referenceMonth === null) {
            $errors[] = 'Competencia invalida.';
        }

        $issueDate = $this->normalizeDate($issueDateRaw);
        if ($issueDateRaw !== null && $issueDate === null) {
            $errors[] = 'Data de emissao invalida.';
        }

        $dueDate = $this->normalizeDate($dueDateRaw);
        if ($dueDate === null) {
            $errors[] = 'Data de vencimento invalida.';
        }

        if ($referenceMonth !== null && $dueDate !== null && strtotime($dueDate) < strtotime($referenceMonth)) {
            $errors[] = 'Vencimento nao pode ser anterior a competencia do boleto.';
        }

        if ($totalAmount === null || (float) $totalAmount <= 0.0) {
            $errors[] = 'Valor total do boleto deve ser maior que zero.';
        }

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors[] = 'Status do boleto invalido.';
        }

        $data = [
            'organ_id' => $organId,
            'invoice_number' => $invoiceNumber === null ? '' : mb_substr($invoiceNumber, 0, 120),
            'title' => $title === null ? '' : mb_substr($title, 0, 190),
            'reference_month' => $referenceMonth,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'total_amount' => $totalAmount,
            'status' => $status,
            'digitable_line' => $digitableLine === null ? null : mb_substr($digitableLine, 0, 255),
            'reference_code' => $referenceCode === null ? null : mb_substr($referenceCode, 0, 120),
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
    private function persistPdf(?array $file, int $organId): array
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
                'error' => 'Falha no upload do PDF do boleto.',
                'meta' => null,
            ];
        }

        $originalName = (string) ($file['name'] ?? '');
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            return [
                'ok' => false,
                'error' => 'Arquivo PDF fora do limite permitido (15MB).',
                'meta' => null,
            ];
        }

        $ext = mb_strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return [
                'ok' => false,
                'error' => 'Apenas arquivo PDF e permitido para o boleto.',
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
                'error' => 'Tipo de arquivo invalido. Envie um PDF valido.',
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

        $subDir = sprintf('invoices/%d/%s', max(0, $organId), date('Y/m'));
        $targetDir = $baseUploads . '/' . $subDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return [
                'ok' => false,
                'error' => 'Nao foi possivel preparar diretorio de upload do boleto.',
                'meta' => null,
            ];
        }

        try {
            $storedName = bin2hex(random_bytes(16)) . '.pdf';
            $targetPath = $targetDir . '/' . $storedName;

            if (!move_uploaded_file($tmpName, $targetPath)) {
                if (!rename($tmpName, $targetPath)) {
                    return [
                        'ok' => false,
                        'error' => 'Nao foi possivel salvar PDF do boleto.',
                        'meta' => null,
                    ];
                }
            }
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'error' => 'Falha ao processar nome seguro do PDF do boleto.',
                'meta' => null,
            ];
        }

        return [
            'ok' => true,
            'error' => '',
            'meta' => [
                'pdf_original_name' => mb_substr($originalName, 0, 255),
                'pdf_stored_name' => $storedName,
                'pdf_mime_type' => $mime,
                'pdf_file_size' => $size,
                'pdf_storage_path' => $subDir . '/' . $storedName,
            ],
        ];
    }

    private function effectiveStatus(string $status, string $dueDate, float $paidAmount, float $totalAmount): string
    {
        $normalized = trim($status) === '' ? 'aberto' : $status;

        if ($normalized === 'cancelado') {
            return 'cancelado';
        }

        if ($paidAmount + 0.009 >= $totalAmount && $totalAmount > 0.0) {
            return 'pago';
        }

        if ($paidAmount > 0.009) {
            return 'pago_parcial';
        }

        if (in_array($normalized, ['pago', 'pago_parcial'], true)) {
            return $normalized;
        }

        $dueTimestamp = strtotime($dueDate);
        if ($dueTimestamp !== false && $dueTimestamp < strtotime(date('Y-m-d'))) {
            return 'vencido';
        }

        return 'aberto';
    }

    private function isFinalStatus(string $status): bool
    {
        return in_array($status, self::FINAL_STATUSES, true);
    }

    private function clean(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizeReferenceMonth(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);
        if (preg_match('/^\d{4}-\d{2}$/', $trimmed) === 1) {
            return $trimmed . '-01';
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-01', $timestamp);
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

    private function parseMoneyStrict(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = $raw;
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    private function parseMoneyOptional(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '0.00';
        }

        $normalized = $raw;
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }
}
