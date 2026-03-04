<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CostMirrorRepository;

final class CostMirrorService
{
    private const ALLOWED_STATUSES = ['aberto', 'conferido', 'conciliado'];
    private const ALLOWED_SOURCES = ['manual', 'csv', 'misto'];
    private const CSV_ALLOWED_EXTENSIONS = ['csv', 'txt'];
    private const CSV_ALLOWED_MIME = [
        'text/plain',
        'text/csv',
        'application/csv',
        'application/vnd.ms-excel',
        'text/comma-separated-values',
        'application/octet-stream',
    ];
    private const MAX_CSV_SIZE = 5242880; // 5MB

    public function __construct(
        private CostMirrorRepository $mirrors,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->mirrors->paginate($filters, $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->mirrors->findById($id);
    }

    /** @return array<int, array<string, mixed>> */
    public function items(int $mirrorId): array
    {
        return $this->mirrors->itemsByMirror($mirrorId);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeOrgans(): array
    {
        return $this->mirrors->activeOrgans();
    }

    /** @return array<int, array<string, mixed>> */
    public function activePeople(int $organId = 0, int $limit = 600): array
    {
        return $this->mirrors->activePeople($organId, $limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeInvoices(int $organId = 0, ?string $referenceMonth = null, int $limit = 400): array
    {
        $month = null;
        if ($referenceMonth !== null && trim($referenceMonth) !== '') {
            $month = $this->normalizeReferenceMonth($referenceMonth);
            $month = $month === null ? null : substr($month, 0, 7);
        }

        return $this->mirrors->activeInvoices($organId, $month, $limit);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function statusOptions(): array
    {
        return [
            ['value' => 'aberto', 'label' => 'Aberto'],
            ['value' => 'conferido', 'label' => 'Conferido'],
            ['value' => 'conciliado', 'label' => 'Conciliado'],
        ];
    }

    public function sourceLabel(string $source): string
    {
        return match ($source) {
            'manual' => 'Manual',
            'csv' => 'CSV',
            'misto' => 'Misto',
            default => ucfirst($source),
        };
    }

    public function isLockedForEditing(int $mirrorId): bool
    {
        return $this->mirrors->isLockedByReconciliation($mirrorId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>, id?: int}
     */
    public function create(array $input, int $userId, string $ip, string $userAgent): array
    {
        $validation = $this->validateMirrorInput($input, null);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        $payload = $validation['data'];
        $payload['source'] = 'manual';
        $payload['total_amount'] = '0.00';
        $payload['created_by'] = $userId > 0 ? $userId : null;

        $id = $this->mirrors->create($payload);

        $this->audit->log(
            entity: 'cost_mirror',
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
            entity: 'cost_mirror',
            type: 'cost_mirror.created',
            payload: [
                'person_id' => (int) $payload['person_id'],
                'reference_month' => (string) $payload['reference_month'],
                'invoice_id' => $payload['invoice_id'] === null ? null : (int) $payload['invoice_id'],
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
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>}
     */
    public function update(int $id, array $input, int $userId, string $ip, string $userAgent): array
    {
        $before = $this->mirrors->findById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Espelho nao encontrado.'],
                'data' => [],
            ];
        }

        if ($this->mirrors->isLockedByReconciliation($id)) {
            return [
                'ok' => false,
                'errors' => ['Espelho bloqueado: conciliacao aprovada impede edicao.'],
                'data' => [],
            ];
        }

        $validation = $this->validateMirrorInput($input, $id);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        $payload = $validation['data'];
        $payload['source'] = in_array((string) ($before['source'] ?? ''), self::ALLOWED_SOURCES, true)
            ? (string) $before['source']
            : 'manual';

        $this->mirrors->update($id, $payload);
        $after = $this->mirrors->findById($id);

        $this->audit->log(
            entity: 'cost_mirror',
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
            entity: 'cost_mirror',
            type: 'cost_mirror.updated',
            payload: [
                'person_id' => (int) $payload['person_id'],
                'reference_month' => (string) $payload['reference_month'],
                'invoice_id' => $payload['invoice_id'] === null ? null : (int) $payload['invoice_id'],
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
        $before = $this->mirrors->findById($id);
        if ($before === null) {
            return false;
        }

        if ($this->mirrors->isLockedByReconciliation($id)) {
            return false;
        }

        try {
            $this->mirrors->beginTransaction();
            $this->mirrors->softDeleteItemsByMirror($id);
            $this->mirrors->softDelete($id);

            $this->audit->log(
                entity: 'cost_mirror',
                entityId: $id,
                action: 'delete',
                beforeData: $before,
                afterData: null,
                metadata: [
                    'items_count' => (int) ($before['items_count'] ?? 0),
                    'total_amount' => (string) ($before['total_amount'] ?? '0.00'),
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'cost_mirror',
                type: 'cost_mirror.deleted',
                payload: [
                    'person_id' => (int) ($before['person_id'] ?? 0),
                    'reference_month' => (string) ($before['reference_month'] ?? ''),
                ],
                entityId: $id,
                userId: $userId
            );

            $this->mirrors->commit();
        } catch (\Throwable $exception) {
            $this->mirrors->rollBack();

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>}
     */
    public function addItem(int $mirrorId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $mirror = $this->mirrors->findById($mirrorId);
        if ($mirror === null) {
            return [
                'ok' => false,
                'message' => 'Espelho nao encontrado.',
                'errors' => ['Espelho nao encontrado.'],
            ];
        }

        if ($this->mirrors->isLockedByReconciliation($mirrorId)) {
            return [
                'ok' => false,
                'message' => 'Espelho bloqueado para edicao.',
                'errors' => ['Conciliacao aprovada impede inclusao de novos itens.'],
            ];
        }

        $itemValidation = $this->validateItemInput($input);
        if ($itemValidation['errors'] !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel registrar item do espelho.',
                'errors' => $itemValidation['errors'],
            ];
        }

        $itemData = $itemValidation['data'];
        $itemData['created_by'] = $userId > 0 ? $userId : null;

        try {
            $this->mirrors->beginTransaction();

            $itemId = $this->mirrors->createItem($mirrorId, $itemData);
            $total = $this->mirrors->recalculateTotal($mirrorId);

            $currentSource = (string) ($mirror['source'] ?? 'manual');
            $nextSource = $this->mergeSource($currentSource, 'manual');
            if ($nextSource !== $currentSource) {
                $this->mirrors->updateSource($mirrorId, $nextSource);
            }

            $this->audit->log(
                entity: 'cost_mirror_item',
                entityId: $itemId,
                action: 'create',
                beforeData: null,
                afterData: [
                    'cost_mirror_id' => $mirrorId,
                    ...$itemData,
                ],
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'cost_mirror',
                type: 'cost_mirror.item_added',
                payload: [
                    'item_id' => $itemId,
                    'item_name' => $itemData['item_name'],
                    'item_amount' => $itemData['amount'],
                    'total_amount' => $total,
                ],
                entityId: $mirrorId,
                userId: $userId
            );

            $this->mirrors->commit();
        } catch (\Throwable $exception) {
            $this->mirrors->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel registrar item do espelho.',
                'errors' => ['Falha ao salvar item. Tente novamente.'],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Item registrado com sucesso.',
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, message: string, errors: array<int, string>}
     */
    public function importCsv(int $mirrorId, ?array $file, int $userId, string $ip, string $userAgent): array
    {
        $mirror = $this->mirrors->findById($mirrorId);
        if ($mirror === null) {
            return [
                'ok' => false,
                'message' => 'Espelho nao encontrado.',
                'errors' => ['Espelho nao encontrado.'],
            ];
        }

        if ($this->mirrors->isLockedByReconciliation($mirrorId)) {
            return [
                'ok' => false,
                'message' => 'Espelho bloqueado para edicao.',
                'errors' => ['Conciliacao aprovada impede importacao de itens por CSV.'],
            ];
        }

        $csv = $this->readCsvFile($file);
        if (!$csv['ok']) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel importar CSV.',
                'errors' => [$csv['error']],
            ];
        }

        $parsed = $this->parseCsvRows($csv['headers'], $csv['rows']);
        if ($parsed['errors'] !== []) {
            return [
                'ok' => false,
                'message' => 'CSV rejeitado por erros de validacao.',
                'errors' => $parsed['errors'],
            ];
        }

        if ($parsed['items'] === []) {
            return [
                'ok' => false,
                'message' => 'CSV sem itens validos.',
                'errors' => ['Nenhum item valido encontrado no CSV.'],
            ];
        }

        try {
            $this->mirrors->beginTransaction();

            $importedTotal = 0.0;
            foreach ($parsed['items'] as $item) {
                $item['created_by'] = $userId > 0 ? $userId : null;
                $this->mirrors->createItem($mirrorId, $item);
                $importedTotal += (float) $item['amount'];
            }

            $newTotal = $this->mirrors->recalculateTotal($mirrorId);

            $currentSource = (string) ($mirror['source'] ?? 'manual');
            $nextSource = $this->mergeSource($currentSource, 'csv');
            if ($nextSource !== $currentSource) {
                $this->mirrors->updateSource($mirrorId, $nextSource);
            }

            $this->audit->log(
                entity: 'cost_mirror',
                entityId: $mirrorId,
                action: 'import_csv',
                beforeData: null,
                afterData: [
                    'imported_items' => count($parsed['items']),
                    'imported_total' => number_format($importedTotal, 2, '.', ''),
                    'new_total_amount' => $newTotal,
                ],
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'cost_mirror',
                type: 'cost_mirror.csv_imported',
                payload: [
                    'imported_items' => count($parsed['items']),
                    'imported_total' => number_format($importedTotal, 2, '.', ''),
                    'new_total_amount' => $newTotal,
                ],
                entityId: $mirrorId,
                userId: $userId
            );

            $this->mirrors->commit();
        } catch (\Throwable $exception) {
            $this->mirrors->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel importar CSV.',
                'errors' => ['Falha ao persistir os itens do CSV.'],
            ];
        }

        return [
            'ok' => true,
            'message' => sprintf('%d item(ns) importado(s) com sucesso.', count($parsed['items'])),
            'errors' => [],
        ];
    }

    /**
     * @return array{ok: bool, message: string, errors: array<int, string>}
     */
    public function removeItem(int $mirrorId, int $itemId, int $userId, string $ip, string $userAgent): array
    {
        $mirror = $this->mirrors->findById($mirrorId);
        if ($mirror === null) {
            return [
                'ok' => false,
                'message' => 'Espelho nao encontrado.',
                'errors' => ['Espelho nao encontrado.'],
            ];
        }

        if ($this->mirrors->isLockedByReconciliation($mirrorId)) {
            return [
                'ok' => false,
                'message' => 'Espelho bloqueado para edicao.',
                'errors' => ['Conciliacao aprovada impede remocao de itens.'],
            ];
        }

        $item = $this->mirrors->findItemById($mirrorId, $itemId);
        if ($item === null) {
            return [
                'ok' => false,
                'message' => 'Item nao encontrado.',
                'errors' => ['Item do espelho nao encontrado.'],
            ];
        }

        try {
            $this->mirrors->beginTransaction();

            $this->mirrors->softDeleteItem($mirrorId, $itemId);
            $newTotal = $this->mirrors->recalculateTotal($mirrorId);

            $this->audit->log(
                entity: 'cost_mirror_item',
                entityId: $itemId,
                action: 'delete',
                beforeData: $item,
                afterData: null,
                metadata: [
                    'cost_mirror_id' => $mirrorId,
                    'new_total_amount' => $newTotal,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'cost_mirror',
                type: 'cost_mirror.item_removed',
                payload: [
                    'item_id' => $itemId,
                    'item_name' => (string) ($item['item_name'] ?? ''),
                    'removed_amount' => (string) ($item['amount'] ?? '0.00'),
                    'new_total_amount' => $newTotal,
                ],
                entityId: $mirrorId,
                userId: $userId
            );

            $this->mirrors->commit();
        } catch (\Throwable $exception) {
            $this->mirrors->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel remover item.',
                'errors' => ['Falha ao remover item do espelho.'],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Item removido com sucesso.',
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, data: array<string, mixed>}
     */
    private function validateMirrorInput(array $input, ?int $ignoreId): array
    {
        $personId = (int) ($input['person_id'] ?? 0);
        $referenceMonthRaw = $this->clean($input['reference_month'] ?? null);
        $invoiceIdRaw = (int) ($input['invoice_id'] ?? 0);
        $titleRaw = $this->clean($input['title'] ?? null);
        $status = mb_strtolower((string) ($input['status'] ?? 'aberto'));
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];
        $person = $personId > 0 ? $this->mirrors->findPersonById($personId) : null;
        if ($person === null) {
            $errors[] = 'Pessoa invalida para espelho de custo.';
        }

        $referenceMonth = $this->normalizeReferenceMonth($referenceMonthRaw);
        if ($referenceMonth === null) {
            $errors[] = 'Competencia invalida.';
        }

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors[] = 'Status do espelho invalido.';
        }

        $invoiceId = $invoiceIdRaw > 0 ? $invoiceIdRaw : null;
        $invoice = null;
        if ($invoiceId !== null) {
            $invoice = $this->mirrors->findInvoiceById($invoiceId);
            if ($invoice === null) {
                $errors[] = 'Boleto informado nao foi encontrado.';
            }
        }

        if ($person !== null && $referenceMonth !== null) {
            $duplicate = $this->mirrors->findByPersonAndMonth($personId, $referenceMonth, $ignoreId);
            if ($duplicate !== null) {
                $errors[] = 'Ja existe espelho cadastrado para esta pessoa na competencia informada.';
            }
        }

        if ($person !== null && $invoice !== null) {
            if ((int) ($invoice['organ_id'] ?? 0) !== (int) ($person['organ_id'] ?? 0)) {
                $errors[] = 'Boleto nao pertence ao mesmo orgao da pessoa do espelho.';
            }

            $invoiceMonth = $this->normalizeReferenceMonth((string) ($invoice['reference_month'] ?? ''));
            if ($referenceMonth !== null && $invoiceMonth !== $referenceMonth) {
                $errors[] = 'Competencia do boleto diverge da competencia do espelho.';
            }
        }

        $title = $titleRaw;
        if ($title === null || mb_strlen($title) < 3) {
            if ($person !== null && $referenceMonth !== null) {
                $title = sprintf(
                    'Espelho %s - %s',
                    (string) ($person['name'] ?? 'Pessoa'),
                    date('m/Y', strtotime($referenceMonth))
                );
            } else {
                $errors[] = 'Titulo do espelho e obrigatorio (minimo 3 caracteres).';
            }
        }

        $data = [
            'person_id' => $personId,
            'organ_id' => $person === null ? 0 : (int) ($person['organ_id'] ?? 0),
            'invoice_id' => $invoiceId,
            'reference_month' => $referenceMonth,
            'title' => $title === null ? '' : mb_substr($title, 0, 190),
            'status' => $status,
            'notes' => $notes,
        ];

        return [
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, data: array<string, mixed>}
     */
    private function validateItemInput(array $input): array
    {
        $itemName = $this->clean($input['item_name'] ?? null);
        $itemCode = $this->clean($input['item_code'] ?? null);
        $quantity = $this->parseDecimal($input['quantity'] ?? null, 1.0);
        $unitAmount = $this->parseMoney($input['unit_amount'] ?? null);
        $amount = $this->parseMoney($input['amount'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];

        if ($itemName === null || mb_strlen($itemName) < 3) {
            $errors[] = 'Nome do item e obrigatorio (minimo 3 caracteres).';
        }

        if ($quantity === null || $quantity <= 0) {
            $errors[] = 'Quantidade invalida para o item.';
        }

        if ($amount === null && $unitAmount === null) {
            $errors[] = 'Informe valor total ou valor unitario para o item.';
        }

        if ($unitAmount !== null && $unitAmount < 0) {
            $errors[] = 'Valor unitario nao pode ser negativo.';
        }

        if ($amount !== null && $amount <= 0) {
            $errors[] = 'Valor total do item deve ser maior que zero.';
        }

        if ($quantity !== null && $amount === null && $unitAmount !== null) {
            $amount = $quantity * $unitAmount;
        }

        if ($quantity !== null && $unitAmount === null && $amount !== null) {
            $unitAmount = $quantity > 0 ? ($amount / $quantity) : $amount;
        }

        if ($amount !== null && $amount <= 0) {
            $errors[] = 'Valor total do item deve ser maior que zero.';
        }

        $data = [
            'item_name' => $itemName === null ? '' : mb_substr($itemName, 0, 190),
            'item_code' => $itemCode === null ? null : mb_substr($itemCode, 0, 80),
            'quantity' => $quantity === null ? '1.00' : number_format($quantity, 2, '.', ''),
            'unit_amount' => $unitAmount === null ? '0.00' : number_format($unitAmount, 2, '.', ''),
            'amount' => $amount === null ? '0.00' : number_format($amount, 2, '.', ''),
            'notes' => $notes,
        ];

        return [
            'errors' => array_values(array_unique($errors)),
            'data' => $data,
        ];
    }

    /**
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, error: string, headers: array<int, string>, rows: array<int, array<int, string>>}
     */
    private function readCsvFile(?array $file): array
    {
        if ($file === null || !isset($file['error'])) {
            return [
                'ok' => false,
                'error' => 'Arquivo CSV nao enviado.',
                'headers' => [],
                'rows' => [],
            ];
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return [
                'ok' => false,
                'error' => 'Selecione um arquivo CSV para importacao.',
                'headers' => [],
                'rows' => [],
            ];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return [
                'ok' => false,
                'error' => 'Falha no upload do CSV.',
                'headers' => [],
                'rows' => [],
            ];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_CSV_SIZE) {
            return [
                'ok' => false,
                'error' => 'CSV fora do limite permitido (5MB).',
                'headers' => [],
                'rows' => [],
            ];
        }

        $ext = mb_strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::CSV_ALLOWED_EXTENSIONS, true)) {
            return [
                'ok' => false,
                'error' => 'Extensao invalida. Envie arquivo CSV.',
                'headers' => [],
                'rows' => [],
            ];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? (string) finfo_file($finfo, $tmpName) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if ($mime !== '' && !in_array($mime, self::CSV_ALLOWED_MIME, true)) {
            return [
                'ok' => false,
                'error' => 'Tipo de arquivo invalido para importacao CSV.',
                'headers' => [],
                'rows' => [],
            ];
        }

        $handle = @fopen($tmpName, 'rb');
        if ($handle === false) {
            return [
                'ok' => false,
                'error' => 'Nao foi possivel ler o CSV enviado.',
                'headers' => [],
                'rows' => [],
            ];
        }

        $firstLine = fgets($handle);
        if (!is_string($firstLine)) {
            fclose($handle);

            return [
                'ok' => false,
                'error' => 'CSV vazio.',
                'headers' => [],
                'rows' => [],
            ];
        }

        $delimiter = $this->detectDelimiter($firstLine);
        rewind($handle);

        $headerRow = fgetcsv($handle, 0, $delimiter);
        if (!is_array($headerRow)) {
            fclose($handle);

            return [
                'ok' => false,
                'error' => 'CSV sem cabecalho valido.',
                'headers' => [],
                'rows' => [],
            ];
        }

        $headers = [];
        foreach ($headerRow as $index => $column) {
            $value = (string) $column;
            if ($index === 0) {
                $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
            }
            $headers[] = trim($value);
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (!is_array($row)) {
                continue;
            }

            $rows[] = array_map(
                static fn (mixed $value): string => trim((string) $value),
                $row
            );
        }

        fclose($handle);

        return [
            'ok' => true,
            'error' => '',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [
            ';' => substr_count($line, ';'),
            ',' => substr_count($line, ','),
            "\t" => substr_count($line, "\t"),
        ];

        arsort($candidates);
        $delimiter = (string) array_key_first($candidates);

        return $delimiter === '' ? ';' : $delimiter;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $rows
     * @return array{items: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    private function parseCsvRows(array $headers, array $rows): array
    {
        $indexMap = [];
        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeCsvHeader($header);
            if ($normalized !== '') {
                $indexMap[$normalized] = $index;
            }
        }

        $resolveColumn = static function (array $map, array $aliases): ?int {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $map)) {
                    return (int) $map[$alias];
                }
            }

            return null;
        };

        $itemNameCol = $resolveColumn($indexMap, ['item_name', 'item', 'descricao', 'description', 'nome_item']);
        $amountCol = $resolveColumn($indexMap, ['amount', 'valor', 'valor_total', 'total']);
        $quantityCol = $resolveColumn($indexMap, ['quantity', 'quantidade', 'qtd']);
        $unitAmountCol = $resolveColumn($indexMap, ['unit_amount', 'valor_unitario', 'valor_unit', 'unitario']);
        $itemCodeCol = $resolveColumn($indexMap, ['item_code', 'codigo', 'rubrica', 'categoria']);
        $notesCol = $resolveColumn($indexMap, ['notes', 'observacoes', 'obs']);

        $errors = [];
        if ($itemNameCol === null) {
            $errors[] = 'Cabecalho obrigatorio ausente: item_name (ou alias equivalente).';
        }

        if ($errors !== []) {
            return [
                'items' => [],
                'errors' => $errors,
            ];
        }

        $items = [];
        foreach ($rows as $lineIndex => $row) {
            $lineNumber = $lineIndex + 2;
            $rowIsEmpty = true;
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    $rowIsEmpty = false;
                    break;
                }
            }
            if ($rowIsEmpty) {
                continue;
            }

            $input = [
                'item_name' => $itemNameCol === null ? '' : ($row[$itemNameCol] ?? ''),
                'amount' => $amountCol === null ? '' : ($row[$amountCol] ?? ''),
                'quantity' => $quantityCol === null ? '' : ($row[$quantityCol] ?? ''),
                'unit_amount' => $unitAmountCol === null ? '' : ($row[$unitAmountCol] ?? ''),
                'item_code' => $itemCodeCol === null ? '' : ($row[$itemCodeCol] ?? ''),
                'notes' => $notesCol === null ? '' : ($row[$notesCol] ?? ''),
            ];

            $validation = $this->validateItemInput($input);
            if ($validation['errors'] !== []) {
                $errors[] = sprintf('Linha %d: %s', $lineNumber, implode(' ', $validation['errors']));
                if (count($errors) >= 20) {
                    $errors[] = 'Limite de 20 erros atingido. Corrija o arquivo e tente novamente.';
                    break;
                }
                continue;
            }

            $items[] = $validation['data'];
        }

        return [
            'items' => $items,
            'errors' => $errors,
        ];
    }

    private function normalizeCsvHeader(string $header): string
    {
        $value = mb_strtolower(trim($header));
        $value = str_replace([' ', '-'], '_', $value);
        $value = str_replace(
            ['á', 'à', 'ã', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'õ', 'ô', 'ö', 'ú', 'ù', 'û', 'ü', 'ç'],
            ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c'],
            $value
        );
        $value = preg_replace('/[^a-z0-9_]/', '', $value) ?? '';

        return trim($value, '_');
    }

    private function mergeSource(string $currentSource, string $incomingSource): string
    {
        $current = in_array($currentSource, self::ALLOWED_SOURCES, true) ? $currentSource : 'manual';
        $incoming = in_array($incomingSource, self::ALLOWED_SOURCES, true) ? $incomingSource : 'manual';

        if ($current === 'misto' || $incoming === 'misto') {
            return 'misto';
        }

        if ($current === $incoming) {
            return $current;
        }

        return 'misto';
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

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            return date('Y-m-01', strtotime($trimmed));
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-01', $timestamp);
    }

    private function parseMoney(mixed $value): ?float
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

        return round((float) $normalized, 2);
    }

    private function parseDecimal(mixed $value, float $default): ?float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $default;
        }

        $normalized = str_replace(',', '.', $raw);
        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }
}
