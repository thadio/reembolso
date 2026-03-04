<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReimbursementRepository;

final class ReimbursementService
{
    private const ALLOWED_TYPES = ['boleto', 'pagamento', 'ajuste'];
    private const ALLOWED_STATUSES = ['pendente', 'pago', 'cancelado'];

    public function __construct(
        private ReimbursementRepository $entries,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{summary: array<string, int|float>, items: array<int, array<string, mixed>>}
     */
    public function profileData(int $personId, int $limit = 80): array
    {
        return [
            'summary' => $this->normalizeSummary($this->entries->summaryByPerson($personId)),
            'items' => $this->entries->listByPerson($personId, $limit),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, warnings: array<int, string>}
     */
    public function createEntry(int $personId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $entryType = mb_strtolower(trim((string) ($input['entry_type'] ?? 'boleto')));
        $title = trim((string) ($input['title'] ?? ''));
        $amount = $this->parseMoney($input['amount'] ?? null);
        $referenceMonthRaw = $this->clean($input['reference_month'] ?? null);
        $dueDateRaw = $this->clean($input['due_date'] ?? null);
        $paidAtRaw = $this->clean($input['paid_at'] ?? null);
        $referenceMonth = $this->normalizeReferenceMonth($referenceMonthRaw);
        $dueDate = $this->normalizeDate($dueDateRaw);
        $paidAt = $this->normalizeDateTime($paidAtRaw);
        $notes = $this->clean($input['notes'] ?? null);

        $statusInput = $this->clean($input['status'] ?? null);
        $status = $statusInput !== null
            ? mb_strtolower($statusInput)
            : ($entryType === 'pagamento' ? 'pago' : 'pendente');

        if ($status === 'cancelado') {
            $paidAt = null;
        } elseif ($paidAt !== null && $status === 'pendente') {
            $status = 'pago';
        }

        if ($status === 'pago' && $paidAt === null) {
            $paidAt = date('Y-m-d H:i:s');
        }

        if ($status !== 'pago') {
            $paidAt = null;
        }

        $errors = [];

        if (!in_array($entryType, self::ALLOWED_TYPES, true)) {
            $errors[] = 'Tipo de lançamento inválido.';
        }

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors[] = 'Status do lançamento inválido.';
        }

        if ($title === '' || mb_strlen($title) < 3) {
            $errors[] = 'Título do lançamento é obrigatório (mínimo 3 caracteres).';
        }

        if ($amount === null || (float) $amount <= 0.0) {
            $errors[] = 'Valor do lançamento deve ser maior que zero.';
        }

        if ($referenceMonthRaw !== null && $referenceMonth === null) {
            $errors[] = 'Competência inválida.';
        }

        if ($dueDateRaw !== null && $dueDate === null) {
            $errors[] = 'Data de vencimento inválida.';
        }

        if ($paidAtRaw !== null && $paidAt === null) {
            $errors[] = 'Data de pagamento inválida.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Não foi possível registrar lançamento financeiro.',
                'errors' => $errors,
                'warnings' => [],
            ];
        }

        $assignmentId = null;

        try {
            $this->entries->beginTransaction();

            $entryId = $this->entries->createEntry(
                personId: $personId,
                assignmentId: $assignmentId,
                entryType: $entryType,
                status: $status,
                title: mb_substr($title, 0, 190),
                amount: $amount,
                referenceMonth: $referenceMonth,
                dueDate: $dueDate,
                paidAt: $paidAt,
                notes: $notes,
                createdBy: $userId
            );

            $afterData = [
                'person_id' => $personId,
                'assignment_id' => $assignmentId,
                'entry_type' => $entryType,
                'status' => $status,
                'title' => mb_substr($title, 0, 190),
                'amount' => $amount,
                'reference_month' => $referenceMonth,
                'due_date' => $dueDate,
                'paid_at' => $paidAt,
            ];

            $this->audit->log(
                entity: 'reimbursement_entry',
                entityId: $entryId,
                action: 'create',
                beforeData: null,
                afterData: $afterData,
                metadata: ['notes' => $notes],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'person',
                type: $status === 'pago' ? 'reimbursement.entry_paid_created' : 'reimbursement.entry_created',
                payload: [
                    'entry_id' => $entryId,
                    'entry_type' => $entryType,
                    'status' => $status,
                    'amount' => $amount,
                    'due_date' => $dueDate,
                    'paid_at' => $paidAt,
                ],
                entityId: $personId,
                userId: $userId
            );

            $this->entries->commit();
        } catch (\Throwable $exception) {
            $this->entries->rollBack();

            return [
                'ok' => false,
                'message' => 'Não foi possível registrar lançamento financeiro.',
                'errors' => ['Falha ao persistir o lançamento. Tente novamente.'],
                'warnings' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Lançamento financeiro registrado com sucesso.',
            'errors' => [],
            'warnings' => [],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, warnings: array<int, string>}
     */
    public function markAsPaid(int $personId, int $entryId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $entry = $this->entries->findByIdForPerson($entryId, $personId);
        if ($entry === null) {
            return [
                'ok' => false,
                'message' => 'Lançamento não encontrado.',
                'errors' => ['Lançamento financeiro não encontrado para esta pessoa.'],
                'warnings' => [],
            ];
        }

        if ((string) ($entry['status'] ?? '') === 'pago' && trim((string) ($entry['paid_at'] ?? '')) !== '') {
            return [
                'ok' => true,
                'message' => 'Lançamento já estava marcado como pago.',
                'errors' => [],
                'warnings' => [],
            ];
        }

        $paidAtInput = $this->clean($input['paid_at'] ?? null);
        $paidAt = $this->normalizeDateTime($paidAtInput);

        if ($paidAtInput !== null && $paidAt === null) {
            return [
                'ok' => false,
                'message' => 'Não foi possível marcar lançamento como pago.',
                'errors' => ['Data de pagamento inválida.'],
                'warnings' => [],
            ];
        }

        if ($paidAt === null) {
            $paidAt = date('Y-m-d H:i:s');
        }

        try {
            $this->entries->beginTransaction();

            $this->entries->markAsPaid($entryId, $paidAt);

            $afterData = $entry;
            $afterData['status'] = 'pago';
            $afterData['paid_at'] = $paidAt;

            $this->audit->log(
                entity: 'reimbursement_entry',
                entityId: $entryId,
                action: 'mark_paid',
                beforeData: $entry,
                afterData: $afterData,
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'person',
                type: 'reimbursement.entry_paid',
                payload: [
                    'entry_id' => $entryId,
                    'entry_type' => (string) ($entry['entry_type'] ?? ''),
                    'amount' => (string) ($entry['amount'] ?? '0'),
                    'paid_at' => $paidAt,
                ],
                entityId: $personId,
                userId: $userId
            );

            $this->entries->commit();
        } catch (\Throwable $exception) {
            $this->entries->rollBack();

            return [
                'ok' => false,
                'message' => 'Não foi possível marcar lançamento como pago.',
                'errors' => ['Falha ao atualizar o lançamento. Tente novamente.'],
                'warnings' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Lançamento marcado como pago.',
            'errors' => [],
            'warnings' => [],
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, int|float>
     */
    private function normalizeSummary(array $raw): array
    {
        return [
            'total_entries' => max(0, (int) ($raw['total_entries'] ?? 0)),
            'pending_total' => max(0, (float) ($raw['pending_total'] ?? 0)),
            'paid_total' => max(0, (float) ($raw['paid_total'] ?? 0)),
            'canceled_total' => max(0, (float) ($raw['canceled_total'] ?? 0)),
            'overdue_total' => max(0, (float) ($raw['overdue_total'] ?? 0)),
            'pending_count' => max(0, (int) ($raw['pending_count'] ?? 0)),
            'paid_count' => max(0, (int) ($raw['paid_count'] ?? 0)),
            'canceled_count' => max(0, (int) ($raw['canceled_count'] ?? 0)),
            'overdue_count' => max(0, (int) ($raw['overdue_count'] ?? 0)),
            'boletos_count' => max(0, (int) ($raw['boletos_count'] ?? 0)),
            'payments_count' => max(0, (int) ($raw['payments_count'] ?? 0)),
            'adjustments_count' => max(0, (int) ($raw['adjustments_count'] ?? 0)),
        ];
    }

    private function parseMoney(mixed $value): ?string
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

    private function normalizeDateTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            return $trimmed . ' 00:00:00';
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function clean(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
