<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\InvoiceRepository;

final class InvoiceService
{
    private const ALLOWED_STATUSES = ['aberto', 'vencido', 'pago_parcial', 'pago', 'cancelado'];
    private const ALLOWED_FINANCIAL_NATURES = ['despesa_reembolso', 'receita_reembolso'];
    private const FINAL_STATUSES = ['pago', 'cancelado'];
    private const PAYMENT_BATCH_ALLOWED_STATUS = ['aberto', 'em_processamento', 'pago', 'cancelado'];
    private const PAYMENT_BATCH_FINAL_STATUS = ['pago', 'cancelado'];
    private const PAYMENT_BATCH_FINAL_SIMULATION_TTL_SECONDS = 1800;
    private const INVOICE_PDF_ALLOWED_EXTENSIONS = ['pdf'];
    private const INVOICE_PDF_ALLOWED_MIME = ['application/pdf'];
    private const PAYMENT_PROOF_ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg'];
    private const PAYMENT_PROOF_ALLOWED_MIME = ['application/pdf', 'image/png', 'image/jpeg'];
    private const MAX_FILE_SIZE = 15728640; // 15MB

    public function __construct(
        private InvoiceRepository $invoices,
        private AuditService $audit,
        private EventService $events,
        private Config $config,
        private LgpdService $lgpd,
        private SecuritySettingsService $security
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
    public function payments(int $invoiceId): array
    {
        return $this->invoices->paymentsByInvoice($invoiceId);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginatePaymentBatches(array $filters, int $page, int $perPage): array
    {
        $normalized = $this->normalizePaymentBatchFilters($filters);
        $result = $this->invoices->paginatePaymentBatches($normalized, $page, $perPage);

        foreach ($result['items'] as &$item) {
            $item['status_label'] = $this->paymentBatchStatusLabel((string) ($item['status'] ?? ''));
        }
        unset($item);

        return $result;
    }

    /** @param array<string, mixed> $filters
     *  @return array<int, array<string, mixed>>
     */
    public function paymentBatchCandidates(array $filters, int $limit = 220): array
    {
        return $this->invoices->paymentBatchCandidates(
            $this->normalizePaymentBatchCandidateFilters($filters),
            $limit
        );
    }

    /** @return array{batch: array<string, mixed>, items: array<int, array<string, mixed>>}|null */
    public function paymentBatchDetail(int $batchId): ?array
    {
        $batch = $this->invoices->findPaymentBatchById($batchId);
        if ($batch === null) {
            return null;
        }

        $batch['status_label'] = $this->paymentBatchStatusLabel((string) ($batch['status'] ?? ''));
        $items = $this->invoices->paymentBatchItems($batchId);

        return [
            'batch' => $batch,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, id?: int}
     */
    public function createPaymentBatch(array $input, int $userId, string $ip, string $userAgent): array
    {
        $title = $this->clean($input['title'] ?? null);
        $referenceMonthRaw = $this->clean($input['reference_month'] ?? null);
        $scheduledDateRaw = $this->clean($input['scheduled_payment_date'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);
        $paymentIds = $this->collectPositiveIds($input['payment_ids'] ?? []);
        $financialNatureRaw = $this->normalizeFinancialNature($input['financial_nature'] ?? null, true);

        $errors = [];
        if ($financialNatureRaw === null) {
            $errors[] = 'Natureza financeira do lote invalida.';
        }

        if ($title !== null && mb_strlen($title) > 190) {
            $errors[] = 'Titulo do lote excede limite de 190 caracteres.';
        }

        $referenceMonth = $this->normalizeReferenceMonth($referenceMonthRaw);
        if ($referenceMonthRaw !== null && $referenceMonth === null) {
            $errors[] = 'Referencia do lote invalida (use AAAA-MM).';
        }

        $scheduledPaymentDate = $this->normalizeDate($scheduledDateRaw);
        if ($scheduledDateRaw !== null && $scheduledPaymentDate === null) {
            $errors[] = 'Data prevista de pagamento invalida.';
        }

        if ($notes !== null && mb_strlen($notes) > 4000) {
            $errors[] = 'Observacoes do lote excedem limite de 4000 caracteres.';
        }

        if ($paymentIds === []) {
            $errors[] = 'Selecione ao menos um pagamento para montar o lote.';
        } elseif (count($paymentIds) > 600) {
            $errors[] = 'Selecao excede limite de 600 pagamentos por lote.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel criar lote de pagamento.',
                'errors' => $errors,
            ];
        }

        $eligiblePayments = $this->invoices->findEligiblePaymentsForBatchByIds($paymentIds);
        $eligibleIds = array_values(array_map(
            static fn (array $row): int => (int) ($row['payment_id'] ?? 0),
            $eligiblePayments
        ));
        $missing = array_values(array_diff($paymentIds, $eligibleIds));
        if ($missing !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel criar lote de pagamento.',
                'errors' => [
                    'Alguns pagamentos nao estao elegiveis para lote (inexistentes, removidos ou ja vinculados): '
                    . implode(', ', array_slice($missing, 0, 8)),
                ],
            ];
        }

        $paymentsCount = count($eligiblePayments);
        if ($paymentsCount <= 0) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel criar lote de pagamento.',
                'errors' => ['Nenhum pagamento elegivel encontrado para o lote.'],
            ];
        }

        $detectedFinancialNatures = [];
        foreach ($eligiblePayments as $payment) {
            $candidate = (string) ($payment['invoice_financial_nature'] ?? $payment['financial_nature'] ?? '');
            $normalizedCandidate = $this->normalizeFinancialNature($candidate, true);
            $resolvedNature = $normalizedCandidate === null || $normalizedCandidate === ''
                ? 'despesa_reembolso'
                : $normalizedCandidate;
            $detectedFinancialNatures[$resolvedNature] = true;
        }

        if (count($detectedFinancialNatures) > 1) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel criar lote de pagamento.',
                'errors' => ['Selecione pagamentos de uma unica natureza financeira por lote.'],
            ];
        }

        $detectedFinancialNature = (string) (array_key_first($detectedFinancialNatures) ?? 'despesa_reembolso');
        $batchFinancialNature = $financialNatureRaw === '' ? $detectedFinancialNature : (string) $financialNatureRaw;

        if ($batchFinancialNature !== $detectedFinancialNature) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel criar lote de pagamento.',
                'errors' => ['A natureza financeira informada diverge dos pagamentos selecionados.'],
            ];
        }

        $totalAmount = 0.0;
        foreach ($eligiblePayments as $payment) {
            $totalAmount += (float) ($payment['amount'] ?? 0);
        }
        $totalAmountFormatted = number_format($totalAmount, 2, '.', '');

        $batchCode = $this->generatePaymentBatchCode();
        $payload = [
            'batch_code' => $batchCode,
            'title' => $title === null ? null : mb_substr($title, 0, 190),
            'status' => 'aberto',
            'financial_nature' => $batchFinancialNature,
            'reference_month' => $referenceMonth,
            'scheduled_payment_date' => $scheduledPaymentDate,
            'total_amount' => $totalAmountFormatted,
            'payments_count' => $paymentsCount,
            'notes' => $notes === null ? null : mb_substr($notes, 0, 4000),
            'created_by' => $userId > 0 ? $userId : null,
            'closed_by' => null,
            'closed_at' => null,
        ];

        try {
            $this->invoices->beginTransaction();

            $batchId = $this->invoices->createPaymentBatch($payload);
            if ($batchId <= 0) {
                throw new \RuntimeException('Falha ao persistir lote de pagamento.');
            }

            foreach ($eligiblePayments as $payment) {
                $paymentId = (int) ($payment['payment_id'] ?? 0);
                $invoiceId = (int) ($payment['invoice_id'] ?? 0);
                $paymentDate = (string) ($payment['payment_date'] ?? '');
                $amount = number_format((float) ($payment['amount'] ?? 0), 2, '.', '');

                if ($paymentId <= 0 || $invoiceId <= 0 || $paymentDate === '') {
                    throw new \RuntimeException('Pagamento invalido encontrado durante criacao do lote.');
                }

                $this->invoices->addPaymentToBatch(
                    batchId: $batchId,
                    paymentId: $paymentId,
                    invoiceId: $invoiceId,
                    amount: $amount,
                    paymentDate: $paymentDate
                );
            }

            $this->audit->log(
                entity: 'payment_batch',
                entityId: $batchId,
                action: 'create',
                beforeData: null,
                afterData: [
                    'batch_code' => $batchCode,
                    'status' => 'aberto',
                    'financial_nature' => $batchFinancialNature,
                    'payments_count' => $paymentsCount,
                    'total_amount' => $totalAmountFormatted,
                    'reference_month' => $referenceMonth,
                    'scheduled_payment_date' => $scheduledPaymentDate,
                ],
                metadata: [
                    'payment_ids' => $eligibleIds,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'payment_batch',
                type: 'payment_batch.created',
                payload: [
                    'batch_id' => $batchId,
                    'batch_code' => $batchCode,
                    'financial_nature' => $batchFinancialNature,
                    'payments_count' => $paymentsCount,
                    'total_amount' => $totalAmountFormatted,
                ],
                entityId: $batchId,
                userId: $userId
            );

            $this->invoices->commit();
        } catch (\Throwable $exception) {
            $this->invoices->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel criar lote de pagamento.',
                'errors' => ['Falha ao persistir lote e itens de pagamento.'],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Lote de pagamento criado com sucesso.',
            'errors' => [],
            'id' => $batchId,
        ];
    }

    /**
     * @return array{ok: bool, message: string, errors: array<int, string>, simulation?: array<string, mixed>}
     */
    public function simulatePaymentBatchFinalApproval(
        int $batchId,
        string $targetStatus,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        if ($batchId <= 0) {
            return [
                'ok' => false,
                'message' => 'Lote invalido para simulacao.',
                'errors' => ['Lote invalido para simulacao.'],
            ];
        }

        $batch = $this->invoices->findPaymentBatchById($batchId);
        if ($batch === null) {
            return [
                'ok' => false,
                'message' => 'Lote de pagamento nao encontrado.',
                'errors' => ['Lote de pagamento nao encontrado.'],
            ];
        }

        $target = mb_strtolower(trim($targetStatus));
        if (!in_array($target, self::PAYMENT_BATCH_FINAL_STATUS, true)) {
            return [
                'ok' => false,
                'message' => 'Status final invalido para simulacao.',
                'errors' => ['Selecione um status final valido (pago ou cancelado).'],
            ];
        }

        $currentStatus = (string) ($batch['status'] ?? 'aberto');
        if (in_array($currentStatus, self::PAYMENT_BATCH_FINAL_STATUS, true)) {
            return [
                'ok' => false,
                'message' => 'Lote ja finalizado.',
                'errors' => ['Lote em status final nao permite nova simulacao de aprovacao.'],
            ];
        }

        if (!$this->canTransitionPaymentBatchStatus($currentStatus, $target)) {
            return [
                'ok' => false,
                'message' => 'Transicao final nao permitida para simulacao.',
                'errors' => ['Fluxo invalido: finalize apenas transicoes permitidas do lote atual.'],
            ];
        }

        $items = $this->invoices->paymentBatchItems($batchId);
        if ($items === []) {
            return [
                'ok' => false,
                'message' => 'Lote sem itens para simulacao final.',
                'errors' => ['Nao ha pagamentos vinculados para simular aprovacao final.'],
            ];
        }

        $paymentsCount = count($items);
        $invoiceIds = [];
        $organNames = [];
        $totalItems = 0.0;
        $proofsMissing = 0;
        $processMissing = 0;
        $paymentDateFrom = null;
        $paymentDateTo = null;

        foreach ($items as $item) {
            $invoiceId = (int) ($item['invoice_id'] ?? 0);
            if ($invoiceId > 0) {
                $invoiceIds[$invoiceId] = true;
            }

            $organName = trim((string) ($item['organ_name'] ?? ''));
            if ($organName !== '') {
                $organNames[$organName] = true;
            }

            $totalItems += (float) ($item['amount'] ?? 0);

            if (trim((string) ($item['proof_storage_path'] ?? '')) === '') {
                $proofsMissing++;
            }

            if (trim((string) ($item['process_reference'] ?? '')) === '') {
                $processMissing++;
            }

            $paymentDate = trim((string) ($item['payment_date'] ?? ''));
            if ($paymentDate !== '') {
                $paymentDateFrom = $paymentDateFrom === null || $paymentDate < $paymentDateFrom ? $paymentDate : $paymentDateFrom;
                $paymentDateTo = $paymentDateTo === null || $paymentDate > $paymentDateTo ? $paymentDate : $paymentDateTo;
            }
        }

        $batchTotal = (float) ($batch['total_amount'] ?? 0);
        $amountGap = abs($batchTotal - $totalItems);

        $riskLevel = 'baixo';
        $riskNotes = [];

        if ($amountGap > 0.009) {
            $riskLevel = 'alto';
            $riskNotes[] = 'Soma dos itens diverge do total consolidado do lote.';
        }

        if ($target === 'pago' && $proofsMissing > 0) {
            $riskLevel = 'alto';
            $riskNotes[] = sprintf('%d pagamento(s) sem comprovante anexo.', $proofsMissing);
        }

        if ($processMissing > 0 && $riskLevel !== 'alto') {
            $riskLevel = 'medio';
            $riskNotes[] = sprintf('%d pagamento(s) sem referencia de processo.', $processMissing);
        }

        if ($riskNotes === []) {
            $riskNotes[] = 'Nenhuma inconsistencia critica detectada para aprovacao final.';
        }

        try {
            $token = bin2hex(random_bytes(20));
        } catch (\Throwable $exception) {
            $token = sha1((string) microtime(true) . '-' . (string) $batchId . '-' . (string) mt_rand());
        }

        $expiresAt = time() + self::PAYMENT_BATCH_FINAL_SIMULATION_TTL_SECONDS;
        $simulation = [
            'batch_id' => $batchId,
            'batch_code' => (string) ($batch['batch_code'] ?? ''),
            'source_status' => $currentStatus,
            'target_status' => $target,
            'target_status_label' => $this->paymentBatchStatusLabel($target),
            'generated_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'expires_at_label' => date('Y-m-d H:i:s', $expiresAt),
            'batch_updated_at' => (string) ($batch['updated_at'] ?? ''),
            'token' => $token,
            'risk_level' => $riskLevel,
            'risk_notes' => $riskNotes,
            'summary' => [
                'payments_count' => $paymentsCount,
                'invoices_count' => count($invoiceIds),
                'organs_count' => count($organNames),
                'total_amount' => number_format($totalItems, 2, '.', ''),
                'payment_date_from' => $paymentDateFrom,
                'payment_date_to' => $paymentDateTo,
            ],
            'quality' => [
                'proofs_missing_count' => $proofsMissing,
                'process_missing_count' => $processMissing,
                'amount_gap' => number_format($amountGap, 2, '.', ''),
            ],
        ];

        $this->audit->log(
            entity: 'payment_batch',
            entityId: $batchId,
            action: 'final_approval.simulate',
            beforeData: null,
            afterData: [
                'source_status' => $currentStatus,
                'target_status' => $target,
                'risk_level' => $riskLevel,
                'payments_count' => $paymentsCount,
            ],
            metadata: [
                'batch_code' => (string) ($batch['batch_code'] ?? ''),
                'proofs_missing_count' => $proofsMissing,
                'process_missing_count' => $processMissing,
                'amount_gap' => number_format($amountGap, 2, '.', ''),
            ],
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'payment_batch',
            type: 'payment_batch.final_approval_simulated',
            payload: [
                'batch_id' => $batchId,
                'batch_code' => (string) ($batch['batch_code'] ?? ''),
                'source_status' => $currentStatus,
                'target_status' => $target,
                'risk_level' => $riskLevel,
            ],
            entityId: $batchId,
            userId: $userId
        );

        return [
            'ok' => true,
            'message' => 'Simulacao previa da aprovacao final executada com sucesso.',
            'errors' => [],
            'simulation' => $simulation,
        ];
    }

    /**
     * @return array{ok: bool, message: string, errors: array<int, string>}
     */
    public function updatePaymentBatchStatus(
        int $batchId,
        string $status,
        string $note,
        int $userId,
        string $ip,
        string $userAgent,
        string $simulationToken = '',
        ?array $finalApprovalSimulation = null
    ): array {
        if ($batchId <= 0) {
            return [
                'ok' => false,
                'message' => 'Lote invalido para atualizacao.',
                'errors' => ['Lote invalido para atualizacao.'],
            ];
        }

        $batch = $this->invoices->findPaymentBatchById($batchId);
        if ($batch === null) {
            return [
                'ok' => false,
                'message' => 'Lote de pagamento nao encontrado.',
                'errors' => ['Lote de pagamento nao encontrado.'],
            ];
        }

        $normalizedStatus = mb_strtolower(trim($status));
        if (!in_array($normalizedStatus, self::PAYMENT_BATCH_ALLOWED_STATUS, true)) {
            return [
                'ok' => false,
                'message' => 'Status invalido para lote de pagamento.',
                'errors' => ['Status invalido para lote de pagamento.'],
            ];
        }

        $statusNote = $this->clean($note);
        if ($statusNote !== null && mb_strlen($statusNote) > 4000) {
            return [
                'ok' => false,
                'message' => 'Observacao excede limite de 4000 caracteres.',
                'errors' => ['Observacao excede limite de 4000 caracteres.'],
            ];
        }

        $currentStatus = (string) ($batch['status'] ?? 'aberto');
        if ($currentStatus !== $normalizedStatus && !$this->canTransitionPaymentBatchStatus($currentStatus, $normalizedStatus)) {
            return [
                'ok' => false,
                'message' => 'Transicao de status nao permitida para o lote.',
                'errors' => ['Transicao invalida: revise o fluxo do lote (aberto -> em_processamento -> pago/cancelado).'],
            ];
        }

        $appliedFinalSimulation = false;
        if (in_array($normalizedStatus, self::PAYMENT_BATCH_FINAL_STATUS, true) && $currentStatus !== $normalizedStatus) {
            $simulationError = $this->validatePaymentBatchFinalApprovalSimulation(
                batch: $batch,
                currentStatus: $currentStatus,
                targetStatus: $normalizedStatus,
                simulationToken: $simulationToken,
                finalApprovalSimulation: $finalApprovalSimulation
            );

            if ($simulationError !== null) {
                return [
                    'ok' => false,
                    'message' => 'Aprovacao final bloqueada sem simulacao valida.',
                    'errors' => [$simulationError],
                ];
            }

            $appliedFinalSimulation = true;
        }

        $notesToPersist = $statusNote ?? $this->clean($batch['notes'] ?? null);
        $closedBy = in_array($normalizedStatus, self::PAYMENT_BATCH_FINAL_STATUS, true) && $userId > 0 ? $userId : null;
        $closedAt = in_array($normalizedStatus, self::PAYMENT_BATCH_FINAL_STATUS, true) ? date('Y-m-d H:i:s') : null;

        try {
            $this->invoices->beginTransaction();

            $updated = $this->invoices->updatePaymentBatchStatus(
                batchId: $batchId,
                status: $normalizedStatus,
                notes: $notesToPersist,
                closedBy: $closedBy,
                closedAt: $closedAt
            );
            if (!$updated) {
                throw new \RuntimeException('Falha ao atualizar status do lote.');
            }

            $after = $this->invoices->findPaymentBatchById($batchId) ?? $batch;

            $this->audit->log(
                entity: 'payment_batch',
                entityId: $batchId,
                action: 'status.update',
                beforeData: [
                    'status' => $batch['status'] ?? null,
                    'closed_by' => $batch['closed_by'] ?? null,
                    'closed_at' => $batch['closed_at'] ?? null,
                    'notes' => $batch['notes'] ?? null,
                ],
                afterData: [
                    'status' => $after['status'] ?? null,
                    'closed_by' => $after['closed_by'] ?? null,
                    'closed_at' => $after['closed_at'] ?? null,
                    'notes' => $after['notes'] ?? null,
                ],
                metadata: [
                    'batch_code' => (string) ($after['batch_code'] ?? ''),
                    'status_note' => $statusNote,
                    'final_approval_simulation_applied' => $appliedFinalSimulation,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'payment_batch',
                type: 'payment_batch.status_updated',
                payload: [
                    'batch_id' => $batchId,
                    'batch_code' => (string) ($after['batch_code'] ?? ''),
                    'before_status' => $currentStatus,
                    'after_status' => $normalizedStatus,
                    'final_approval_simulation_applied' => $appliedFinalSimulation,
                ],
                entityId: $batchId,
                userId: $userId
            );

            $this->invoices->commit();
        } catch (\Throwable $exception) {
            $this->invoices->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel atualizar o status do lote.',
                'errors' => ['Falha ao persistir atualizacao de status do lote.'],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Status do lote atualizado com sucesso.',
            'errors' => [],
        ];
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
     * @return array<int, array{value: string, label: string}>
     */
    public function financialNatureOptions(bool $includeAll = false): array
    {
        $options = [];
        if ($includeAll) {
            $options[] = ['value' => '', 'label' => 'Todas as naturezas'];
        }

        $options[] = ['value' => 'despesa_reembolso', 'label' => 'Despesa de reembolso (a pagar)'];
        $options[] = ['value' => 'receita_reembolso', 'label' => 'Receita de reembolso (a receber)'];

        return $options;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function paymentBatchStatusOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Todos os status'],
            ['value' => 'aberto', 'label' => 'Aberto'],
            ['value' => 'em_processamento', 'label' => 'Em processamento'],
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
            $this->invoices->softDeletePaymentPeopleByInvoice($id);
            $this->invoices->softDeletePaymentsByInvoice($id);
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

        if ((float) ($link['paid_amount'] ?? 0) > 0.009) {
            return [
                'ok' => false,
                'message' => 'Vinculo com pagamento registrado nao pode ser removido.',
                'errors' => ['Remocao bloqueada: ja existe valor pago para esta pessoa no boleto.'],
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

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, message: string, errors: array<int, string>}
     */
    public function registerPayment(int $invoiceId, array $input, ?array $file, int $userId, string $ip, string $userAgent): array
    {
        $invoice = $this->invoices->findById($invoiceId);
        if ($invoice === null) {
            return [
                'ok' => false,
                'message' => 'Boleto nao encontrado para registro de pagamento.',
                'errors' => ['Boleto nao encontrado para registro de pagamento.'],
            ];
        }

        $currentStatus = $this->effectiveStatus(
            status: (string) ($invoice['status'] ?? 'aberto'),
            dueDate: (string) ($invoice['due_date'] ?? ''),
            paidAmount: (float) ($invoice['paid_amount'] ?? 0),
            totalAmount: (float) ($invoice['total_amount'] ?? 0)
        );

        if ($currentStatus === 'cancelado') {
            return [
                'ok' => false,
                'message' => 'Boleto cancelado nao permite registro de pagamento.',
                'errors' => ['Boleto em status cancelado nao permite baixa financeira.'],
            ];
        }

        $paymentDate = $this->normalizeDate($this->clean($input['payment_date'] ?? null));
        $amount = $this->parseMoneyStrict($input['amount'] ?? null);
        $processReference = $this->clean($input['process_reference'] ?? null);
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];
        if ($paymentDate === null) {
            $errors[] = 'Data do pagamento invalida.';
        }

        if ($amount === null || (float) $amount <= 0.0) {
            $errors[] = 'Valor do pagamento invalido (deve ser maior que zero).';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel registrar pagamento.',
                'errors' => $errors,
            ];
        }

        $totalAmount = max(0.0, (float) ($invoice['total_amount'] ?? 0));
        $paidAmount = max(0.0, (float) ($invoice['paid_amount'] ?? 0));
        $outstanding = max(0.0, $totalAmount - $paidAmount);
        if ($outstanding <= 0.009) {
            return [
                'ok' => false,
                'message' => 'Boleto ja esta quitado.',
                'errors' => ['Nao ha saldo pendente para nova baixa.'],
            ];
        }

        if ((float) $amount - $outstanding > 0.009) {
            return [
                'ok' => false,
                'message' => 'Pagamento excede saldo pendente do boleto.',
                'errors' => ['Valor de baixa acima do saldo pendente.'],
            ];
        }

        $proofResult = $this->persistPaymentProof($file, (int) ($invoice['organ_id'] ?? 0));
        if (!$proofResult['ok']) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel registrar pagamento.',
                'errors' => [$proofResult['error']],
            ];
        }

        $proofMeta = $proofResult['meta'] ?? [];
        $paidByLinks = 0.0;
        $allocationCount = 0;

        try {
            $this->invoices->beginTransaction();

            $paymentId = $this->invoices->createPayment([
                'invoice_id' => $invoiceId,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'financial_nature' => (string) ($invoice['financial_nature'] ?? 'despesa_reembolso'),
                'process_reference' => $processReference === null ? null : mb_substr($processReference, 0, 120),
                'proof_original_name' => $proofMeta['proof_original_name'] ?? null,
                'proof_stored_name' => $proofMeta['proof_stored_name'] ?? null,
                'proof_mime_type' => $proofMeta['proof_mime_type'] ?? null,
                'proof_file_size' => $proofMeta['proof_file_size'] ?? null,
                'proof_storage_path' => $proofMeta['proof_storage_path'] ?? null,
                'notes' => $notes === null ? null : mb_substr($notes, 0, 4000),
                'created_by' => $userId > 0 ? $userId : null,
            ]);

            if ($paymentId <= 0) {
                throw new \RuntimeException('Falha ao persistir pagamento.');
            }

            $remaining = (float) $amount;
            $links = $this->invoices->activeLinksForPayment($invoiceId);
            foreach ($links as $link) {
                if ($remaining <= 0.009) {
                    break;
                }

                $linkAllocated = max(0.0, (float) ($link['allocated_amount'] ?? 0));
                $linkPaid = max(0.0, (float) ($link['paid_amount'] ?? 0));
                $linkRemaining = max(0.0, $linkAllocated - $linkPaid);

                if ($linkRemaining <= 0.009) {
                    continue;
                }

                $chunk = min($remaining, $linkRemaining);
                if ($chunk <= 0.0) {
                    continue;
                }

                $chunkFormatted = number_format($chunk, 2, '.', '');
                $this->invoices->incrementPersonLinkPaidAmount((int) ($link['id'] ?? 0), $chunkFormatted);
                $this->invoices->createPaymentPersonAllocation(
                    paymentId: $paymentId,
                    invoiceId: $invoiceId,
                    invoicePersonId: (int) ($link['id'] ?? 0),
                    personId: (int) ($link['person_id'] ?? 0),
                    amount: $chunkFormatted
                );

                $paidByLinks += $chunk;
                $remaining = max(0.0, round($remaining - $chunk, 2));
                $allocationCount++;
            }

            $newPaidAmount = $this->invoices->sumPaymentsByInvoice($invoiceId);
            $newStatus = $this->effectiveStatus(
                status: $currentStatus,
                dueDate: (string) ($invoice['due_date'] ?? ''),
                paidAmount: (float) $newPaidAmount,
                totalAmount: $totalAmount
            );
            $this->invoices->updateInvoicePaidAmountAndStatus($invoiceId, $newPaidAmount, $newStatus);

            $unallocatedAmount = number_format(max(0.0, (float) $amount - $paidByLinks), 2, '.', '');
            $this->audit->log(
                entity: 'payment',
                entityId: $paymentId,
                action: 'create',
                beforeData: null,
                afterData: [
                    'invoice_id' => $invoiceId,
                    'payment_date' => $paymentDate,
                    'amount' => $amount,
                    'process_reference' => $processReference,
                    'allocated_to_people' => number_format($paidByLinks, 2, '.', ''),
                    'unallocated_amount' => $unallocatedAmount,
                    'allocation_count' => $allocationCount,
                ],
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'invoice',
                type: 'invoice.payment_registered',
                payload: [
                    'invoice_id' => $invoiceId,
                    'payment_id' => $paymentId,
                    'amount' => $amount,
                    'allocated_to_people' => number_format($paidByLinks, 2, '.', ''),
                    'unallocated_amount' => $unallocatedAmount,
                    'status' => $newStatus,
                ],
                entityId: $invoiceId,
                userId: $userId
            );

            if ($currentStatus !== $newStatus) {
                $this->events->recordEvent(
                    entity: 'invoice',
                    type: 'invoice.status_changed',
                    payload: [
                        'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
                        'before_status' => $currentStatus,
                        'after_status' => $newStatus,
                    ],
                    entityId: $invoiceId,
                    userId: $userId
                );
            }

            $this->invoices->commit();
        } catch (\Throwable $exception) {
            $this->invoices->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel registrar pagamento.',
                'errors' => ['Falha ao persistir pagamento e atualizar financeiro do boleto.'],
            ];
        }

        $isFullyPaid = ((float) $amount + $paidAmount) + 0.009 >= $totalAmount;
        $message = $isFullyPaid
            ? 'Pagamento registrado. Boleto liquidado com sucesso.'
            : 'Pagamento registrado com sucesso.';

        return [
            'ok' => true,
            'message' => $message,
            'errors' => [],
        ];
    }

    /** @return array{path: string, original_name: string, mime_type: string, id: int, invoice_id: int, invoice_number: string}|null */
    public function paymentProofForDownload(int $paymentId, ?int $invoiceId, int $userId, string $ip, string $userAgent): ?array
    {
        $payment = $this->invoices->findPaymentProofById($paymentId, $invoiceId);
        if ($payment === null) {
            return null;
        }

        $base = rtrim((string) $this->config->get('paths.storage_uploads', ''), '/');
        if ($base === '') {
            return null;
        }

        $relative = ltrim((string) ($payment['proof_storage_path'] ?? ''), '/');
        $path = $base . '/' . $relative;
        if (!is_file($path)) {
            return null;
        }

        $this->audit->log(
            entity: 'payment',
            entityId: (int) ($payment['id'] ?? 0),
            action: 'download_proof',
            beforeData: null,
            afterData: [
                'invoice_id' => (int) ($payment['invoice_id'] ?? 0),
                'invoice_number' => (string) ($payment['invoice_number'] ?? ''),
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'invoice',
            type: 'invoice.payment_proof_downloaded',
            payload: [
                'invoice_id' => (int) ($payment['invoice_id'] ?? 0),
                'payment_id' => (int) ($payment['id'] ?? 0),
            ],
            entityId: (int) ($payment['invoice_id'] ?? 0),
            userId: $userId
        );

        $this->lgpd->registerSensitiveAccess(
            entity: 'payment',
            entityId: (int) ($payment['id'] ?? 0),
            action: 'payment_proof_download',
            sensitivity: 'payment_proof',
            subjectPersonId: null,
            subjectLabel: (string) ($payment['invoice_number'] ?? ''),
            contextPath: '/invoices/payments/proof',
            metadata: [
                'invoice_id' => (int) ($payment['invoice_id'] ?? 0),
                'invoice_number' => (string) ($payment['invoice_number'] ?? ''),
            ],
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        return [
            'path' => $path,
            'original_name' => (string) ($payment['proof_original_name'] ?? ('comprovante_pagamento_' . $paymentId . '.pdf')),
            'mime_type' => (string) ($payment['proof_mime_type'] ?? 'application/octet-stream'),
            'id' => (int) ($payment['id'] ?? 0),
            'invoice_id' => (int) ($payment['invoice_id'] ?? 0),
            'invoice_number' => (string) ($payment['invoice_number'] ?? ''),
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

        $this->lgpd->registerSensitiveAccess(
            entity: 'invoice',
            entityId: (int) ($invoice['id'] ?? 0),
            action: 'invoice_pdf_download',
            sensitivity: 'document',
            subjectPersonId: null,
            subjectLabel: (string) ($invoice['invoice_number'] ?? ''),
            contextPath: '/invoices/pdf',
            metadata: [
                'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
                'pdf_original_name' => (string) ($invoice['pdf_original_name'] ?? ''),
            ],
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
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
        $financialNature = $this->normalizeFinancialNature($input['financial_nature'] ?? null);
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

        if ($financialNature === null) {
            $errors[] = 'Natureza financeira do boleto invalida.';
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
            'financial_nature' => $financialNature ?? 'despesa_reembolso',
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
        $maxBytes = $this->maxUploadBytes();
        $maxMb = max(1, (int) ceil($maxBytes / 1048576));

        if (!UploadSecurityService::isSafeOriginalName($originalName)) {
            return [
                'ok' => false,
                'error' => 'Nome do arquivo PDF invalido.',
                'meta' => null,
            ];
        }

        if (!UploadSecurityService::isNativeUploadedFile($tmpName)) {
            return [
                'ok' => false,
                'error' => 'Upload do PDF invalido ou nao confiavel.',
                'meta' => null,
            ];
        }

        if ($size <= 0 || $size > $maxBytes) {
            return [
                'ok' => false,
                'error' => sprintf('Arquivo PDF fora do limite permitido (%dMB).', $maxMb),
                'meta' => null,
            ];
        }

        $ext = mb_strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::INVOICE_PDF_ALLOWED_EXTENSIONS, true)) {
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

        if (!in_array($mime, self::INVOICE_PDF_ALLOWED_MIME, true)) {
            return [
                'ok' => false,
                'error' => 'Tipo de arquivo invalido. Envie um PDF valido.',
                'meta' => null,
            ];
        }

        if (!UploadSecurityService::matchesKnownSignature($tmpName, $mime)) {
            return [
                'ok' => false,
                'error' => 'Assinatura binaria invalida para PDF.',
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
                return [
                    'ok' => false,
                    'error' => 'Nao foi possivel salvar PDF do boleto.',
                    'meta' => null,
                ];
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

    /**
     * @param array<string, mixed>|null $file
     * @return array{ok: bool, error: string, meta: array<string, mixed>|null}
     */
    private function persistPaymentProof(?array $file, int $organId): array
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
                'error' => 'Falha no upload do comprovante de pagamento.',
                'meta' => null,
            ];
        }

        $originalName = (string) ($file['name'] ?? '');
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $maxBytes = $this->maxUploadBytes();
        $maxMb = max(1, (int) ceil($maxBytes / 1048576));

        if (!UploadSecurityService::isSafeOriginalName($originalName)) {
            return [
                'ok' => false,
                'error' => 'Nome do comprovante invalido.',
                'meta' => null,
            ];
        }

        if (!UploadSecurityService::isNativeUploadedFile($tmpName)) {
            return [
                'ok' => false,
                'error' => 'Upload do comprovante invalido ou nao confiavel.',
                'meta' => null,
            ];
        }

        if ($size <= 0 || $size > $maxBytes) {
            return [
                'ok' => false,
                'error' => sprintf('Comprovante fora do limite permitido (%dMB).', $maxMb),
                'meta' => null,
            ];
        }

        $ext = mb_strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::PAYMENT_PROOF_ALLOWED_EXTENSIONS, true)) {
            return [
                'ok' => false,
                'error' => 'Comprovante invalido. Formatos aceitos: PDF, PNG, JPG e JPEG.',
                'meta' => null,
            ];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? (string) finfo_file($finfo, $tmpName) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (!in_array($mime, self::PAYMENT_PROOF_ALLOWED_MIME, true)) {
            return [
                'ok' => false,
                'error' => 'Tipo de arquivo invalido para comprovante de pagamento.',
                'meta' => null,
            ];
        }

        if (!UploadSecurityService::matchesKnownSignature($tmpName, $mime)) {
            return [
                'ok' => false,
                'error' => 'Assinatura binaria invalida para comprovante.',
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

        $safeExt = $ext === 'jpeg' ? 'jpg' : $ext;
        $subDir = sprintf('payments/%d/%s', max(0, $organId), date('Y/m'));
        $targetDir = $baseUploads . '/' . $subDir;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return [
                'ok' => false,
                'error' => 'Nao foi possivel preparar diretorio do comprovante.',
                'meta' => null,
            ];
        }

        try {
            $storedName = bin2hex(random_bytes(16)) . '.' . $safeExt;
            $targetPath = $targetDir . '/' . $storedName;

            if (!move_uploaded_file($tmpName, $targetPath)) {
                return [
                    'ok' => false,
                    'error' => 'Nao foi possivel salvar comprovante de pagamento.',
                    'meta' => null,
                ];
            }
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'error' => 'Falha ao processar nome seguro do comprovante.',
                'meta' => null,
            ];
        }

        return [
            'ok' => true,
            'error' => '',
            'meta' => [
                'proof_original_name' => mb_substr($originalName, 0, 255),
                'proof_stored_name' => $storedName,
                'proof_mime_type' => $mime,
                'proof_file_size' => $size,
                'proof_storage_path' => $subDir . '/' . $storedName,
            ],
        ];
    }

    /** @param array<string, mixed> $filters
     *  @return array<string, mixed>
     */
    private function normalizePaymentBatchFilters(array $filters): array
    {
        $statusRaw = mb_strtolower(trim((string) ($filters['status'] ?? '')));
        $status = in_array($statusRaw, self::PAYMENT_BATCH_ALLOWED_STATUS, true) ? $statusRaw : '';

        $referenceMonthNormalized = $this->normalizeReferenceMonth($this->clean($filters['reference_month'] ?? null));
        $referenceMonth = $referenceMonthNormalized === null ? '' : substr($referenceMonthNormalized, 0, 7);
        $financialNature = $this->normalizeFinancialNature($filters['financial_nature'] ?? null, true);

        $sortRaw = trim((string) ($filters['sort'] ?? 'created_at'));
        $allowedSort = ['batch_code', 'status', 'scheduled_payment_date', 'payments_count', 'total_amount', 'created_at'];
        $sort = in_array($sortRaw, $allowedSort, true) ? $sortRaw : 'created_at';

        $dirRaw = mb_strtolower(trim((string) ($filters['dir'] ?? 'desc')));
        $dir = $dirRaw === 'asc' ? 'asc' : 'desc';

        return [
            'q' => $this->clean($filters['q'] ?? null) ?? '',
            'status' => $status,
            'organ_id' => max(0, (int) ($filters['organ_id'] ?? 0)),
            'reference_month' => $referenceMonth,
            'financial_nature' => $financialNature ?? '',
            'sort' => $sort,
            'dir' => $dir,
        ];
    }

    /** @param array<string, mixed> $filters
     *  @return array<string, mixed>
     */
    private function normalizePaymentBatchCandidateFilters(array $filters): array
    {
        $referenceMonthNormalized = $this->normalizeReferenceMonth($this->clean($filters['reference_month'] ?? null));
        $referenceMonth = $referenceMonthNormalized === null ? '' : substr($referenceMonthNormalized, 0, 7);
        $financialNature = $this->normalizeFinancialNature($filters['financial_nature'] ?? null, true);

        $paymentDateFrom = $this->normalizeDate($this->clean($filters['payment_date_from'] ?? null));
        $paymentDateTo = $this->normalizeDate($this->clean($filters['payment_date_to'] ?? null));

        if ($paymentDateFrom !== null && $paymentDateTo !== null && strtotime($paymentDateFrom) > strtotime($paymentDateTo)) {
            [$paymentDateFrom, $paymentDateTo] = [$paymentDateTo, $paymentDateFrom];
        }

        return [
            'q' => $this->clean($filters['q'] ?? null) ?? '',
            'organ_id' => max(0, (int) ($filters['organ_id'] ?? 0)),
            'reference_month' => $referenceMonth,
            'financial_nature' => $financialNature ?? '',
            'payment_date_from' => $paymentDateFrom ?? '',
            'payment_date_to' => $paymentDateTo ?? '',
        ];
    }

    /** @return array<int, int> */
    private function collectPositiveIds(mixed $value): array
    {
        $rawValues = [];

        if (is_array($value)) {
            $rawValues = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $rawValues = preg_split('/[\s,;]+/', trim($value)) ?: [];
        } elseif ($value !== null) {
            $rawValues = [$value];
        }

        $ids = [];
        $seen = [];

        foreach ($rawValues as $rawValue) {
            if (is_array($rawValue)) {
                foreach ($rawValue as $nestedValue) {
                    $id = (int) $nestedValue;
                    if ($id <= 0 || isset($seen[$id])) {
                        continue;
                    }

                    $seen[$id] = true;
                    $ids[] = $id;
                }

                continue;
            }

            $id = (int) $rawValue;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $ids[] = $id;
        }

        return $ids;
    }

    private function generatePaymentBatchCode(): string
    {
        try {
            $token = strtoupper(bin2hex(random_bytes(3)));
        } catch (\Throwable $exception) {
            $token = strtoupper(substr(sha1((string) microtime(true) . '-' . (string) mt_rand()), 0, 6));
        }

        return 'LP-' . date('YmdHis') . '-' . $token;
    }

    private function paymentBatchStatusLabel(string $status): string
    {
        return match ($status) {
            'aberto' => 'Aberto',
            'em_processamento' => 'Em processamento',
            'pago' => 'Pago',
            'cancelado' => 'Cancelado',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function canTransitionPaymentBatchStatus(string $current, string $next): bool
    {
        $from = mb_strtolower(trim($current));
        $to = mb_strtolower(trim($next));

        if ($from === $to) {
            return true;
        }

        if (in_array($from, self::PAYMENT_BATCH_FINAL_STATUS, true)) {
            return false;
        }

        $allowed = [
            'aberto' => ['em_processamento', 'cancelado'],
            'em_processamento' => ['pago', 'cancelado'],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }

    /** @param array<string, mixed> $batch */
    private function validatePaymentBatchFinalApprovalSimulation(
        array $batch,
        string $currentStatus,
        string $targetStatus,
        string $simulationToken,
        ?array $finalApprovalSimulation
    ): ?string {
        $token = trim($simulationToken);
        if ($token === '') {
            return 'Execute a simulacao previa antes da aprovacao final do lote.';
        }

        if (!is_array($finalApprovalSimulation)) {
            return 'Nao foi encontrada simulacao previa valida para este lote.';
        }

        $batchId = (int) ($batch['id'] ?? 0);
        if ((int) ($finalApprovalSimulation['batch_id'] ?? 0) !== $batchId) {
            return 'Simulacao previa nao corresponde ao lote selecionado.';
        }

        if ((string) ($finalApprovalSimulation['target_status'] ?? '') !== $targetStatus) {
            return 'Simulacao previa nao corresponde ao status final selecionado.';
        }

        if ((string) ($finalApprovalSimulation['source_status'] ?? '') !== $currentStatus) {
            return 'Status do lote mudou apos a simulacao. Execute nova simulacao.';
        }

        if ((string) ($finalApprovalSimulation['token'] ?? '') !== $token) {
            return 'Token de simulacao invalido para aprovacao final.';
        }

        $expiresAt = (int) ($finalApprovalSimulation['expires_at'] ?? 0);
        if ($expiresAt <= time()) {
            return 'Simulacao previa expirada. Execute novamente antes de aprovar.';
        }

        if ((string) ($finalApprovalSimulation['batch_updated_at'] ?? '') !== (string) ($batch['updated_at'] ?? '')) {
            return 'O lote foi alterado apos a simulacao. Execute nova simulacao.';
        }

        return null;
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

    private function maxUploadBytes(): int
    {
        $globalLimit = max(1048576, $this->security->uploadMaxBytes());

        return min(self::MAX_FILE_SIZE, $globalLimit);
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

    private function normalizeFinancialNature(mixed $value, bool $allowEmpty = false): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));
        if ($normalized === '') {
            return $allowEmpty ? '' : 'despesa_reembolso';
        }

        return in_array($normalized, self::ALLOWED_FINANCIAL_NATURES, true) ? $normalized : null;
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
