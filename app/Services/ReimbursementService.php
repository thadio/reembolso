<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReimbursementRepository;

final class ReimbursementService
{
    private const ALLOWED_TYPES = ['boleto', 'pagamento', 'ajuste'];
    private const ALLOWED_STATUSES = ['pendente', 'pago', 'cancelado'];
    private const CALCULATOR_COMPONENTS = [
        'calc_base_amount' => 'Base',
        'calc_transport_amount' => 'Transporte',
        'calc_lodging_amount' => 'Hospedagem',
        'calc_food_amount' => 'Alimentacao',
        'calc_other_amount' => 'Outros',
        'calc_discount_amount' => 'Desconto',
    ];

    public function __construct(
        private ReimbursementRepository $entries,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{
     *   summary: array<string, int|float>,
     *   items: array<int, array<string, mixed>>,
     *   calculation_memories: array<int, array<string, mixed>>
     * }
     */
    public function profileData(int $personId, int $limit = 80): array
    {
        return [
            'summary' => $this->normalizeSummary($this->entries->summaryByPerson($personId)),
            'items' => $this->entries->listByPerson($personId, $limit),
            'calculation_memories' => $this->entries->recentCalculationMemoriesByPerson($personId, 8),
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
        $manualAmount = $this->parseMoney($input['amount'] ?? null);
        $calculation = $this->resolveCalculation($input, $manualAmount);
        $amount = $calculation['enabled'] ? $calculation['amount'] : $manualAmount;
        $referenceMonthRaw = $this->clean($input['reference_month'] ?? null);
        $dueDateRaw = $this->clean($input['due_date'] ?? null);
        $paidAtRaw = $this->clean($input['paid_at'] ?? null);
        $referenceMonth = $this->normalizeReferenceMonth($referenceMonthRaw);
        $dueDate = $this->normalizeDate($dueDateRaw);
        $paidAt = $this->normalizeDateTime($paidAtRaw);
        $notes = $this->clean($input['notes'] ?? null);
        $calculationMemoryJson = $calculation['memory_json'];
        $warnings = $calculation['warnings'];

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

        $errors = $calculation['errors'];

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
                'warnings' => $warnings,
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
                calculationMemory: $calculationMemoryJson,
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
                'calculated' => $calculation['enabled'] ? 1 : 0,
            ];

            $this->audit->log(
                entity: 'reimbursement_entry',
                entityId: $entryId,
                action: 'create',
                beforeData: null,
                afterData: $afterData,
                metadata: [
                    'notes' => $notes,
                    'calculated' => $calculation['enabled'],
                    'formula' => $calculation['formula_label'],
                ],
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
                    'calculated' => $calculation['enabled'],
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
                'warnings' => $warnings,
            ];
        }

        return [
            'ok' => true,
            'message' => $calculation['enabled']
                ? 'Lançamento financeiro registrado com cálculo automático.'
                : 'Lançamento financeiro registrado com sucesso.',
            'errors' => [],
            'warnings' => $warnings,
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

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   enabled: bool,
     *   amount: ?string,
     *   memory_json: ?string,
     *   formula_label: ?string,
     *   warnings: array<int, string>,
     *   errors: array<int, string>
     * }
     */
    private function resolveCalculation(array $input, ?string $manualAmount): array
    {
        $isCalculatorEnabled = $this->isTruthy($input['use_calculator'] ?? null);
        $hasCalculatorInput = false;

        foreach (array_keys(self::CALCULATOR_COMPONENTS) as $field) {
            if ($this->clean($input[$field] ?? null) !== null) {
                $hasCalculatorInput = true;
                break;
            }
        }

        if (!$hasCalculatorInput && $this->clean($input['calc_adjustment_percent'] ?? null) !== null) {
            $hasCalculatorInput = true;
        }

        if (!$isCalculatorEnabled && !$hasCalculatorInput) {
            return [
                'enabled' => false,
                'amount' => null,
                'memory_json' => null,
                'formula_label' => null,
                'warnings' => [],
                'errors' => [],
            ];
        }

        $errors = [];
        $warnings = [];
        $components = [];

        foreach (self::CALCULATOR_COMPONENTS as $field => $label) {
            $raw = $this->clean($input[$field] ?? null);
            if ($raw === null) {
                $components[$field] = 0.0;
                continue;
            }

            $parsed = $this->parseMoney($raw);
            if ($parsed === null) {
                $errors[] = sprintf('Valor invalido para %s na calculadora.', $label);
                continue;
            }

            $numeric = (float) $parsed;
            if ($numeric < 0) {
                $errors[] = sprintf('Valor de %s nao pode ser negativo.', $label);
                continue;
            }

            $components[$field] = $numeric;
        }

        $percentInput = $this->clean($input['calc_adjustment_percent'] ?? null);
        $adjustmentPercent = 0.0;
        if ($percentInput !== null) {
            $parsedPercent = $this->parseDecimal($percentInput);
            if ($parsedPercent === null) {
                $errors[] = 'Percentual de ajuste invalido na calculadora.';
            } else {
                $adjustmentPercent = $parsedPercent;
            }
        }

        if ($adjustmentPercent < -100.0 || $adjustmentPercent > 300.0) {
            $errors[] = 'Percentual de ajuste deve estar entre -100 e 300.';
        }

        if ($errors !== []) {
            return [
                'enabled' => true,
                'amount' => null,
                'memory_json' => null,
                'formula_label' => null,
                'warnings' => [],
                'errors' => $errors,
            ];
        }

        $subtotal = ($components['calc_base_amount'] ?? 0.0)
            + ($components['calc_transport_amount'] ?? 0.0)
            + ($components['calc_lodging_amount'] ?? 0.0)
            + ($components['calc_food_amount'] ?? 0.0)
            + ($components['calc_other_amount'] ?? 0.0);

        $discount = $components['calc_discount_amount'] ?? 0.0;

        if ($subtotal <= 0.0) {
            return [
                'enabled' => true,
                'amount' => null,
                'memory_json' => null,
                'formula_label' => null,
                'warnings' => [],
                'errors' => ['Informe ao menos um componente maior que zero na calculadora.'],
            ];
        }

        $adjustmentAmount = round($subtotal * ($adjustmentPercent / 100), 2);
        $calculatedTotal = round($subtotal + $adjustmentAmount - $discount, 2);

        if ($calculatedTotal <= 0.0) {
            return [
                'enabled' => true,
                'amount' => null,
                'memory_json' => null,
                'formula_label' => null,
                'warnings' => [],
                'errors' => ['Total calculado deve ser maior que zero.'],
            ];
        }

        $amount = number_format($calculatedTotal, 2, '.', '');
        $formulaLabel = 'Total = (Base + Transporte + Hospedagem + Alimentacao + Outros) + Ajuste - Desconto';
        $memory = [
            'version' => 1,
            'formula' => '(base + transporte + hospedagem + alimentacao + outros) + ajuste - desconto',
            'components' => [
                'base' => number_format((float) ($components['calc_base_amount'] ?? 0.0), 2, '.', ''),
                'transporte' => number_format((float) ($components['calc_transport_amount'] ?? 0.0), 2, '.', ''),
                'hospedagem' => number_format((float) ($components['calc_lodging_amount'] ?? 0.0), 2, '.', ''),
                'alimentacao' => number_format((float) ($components['calc_food_amount'] ?? 0.0), 2, '.', ''),
                'outros' => number_format((float) ($components['calc_other_amount'] ?? 0.0), 2, '.', ''),
                'desconto' => number_format($discount, 2, '.', ''),
            ],
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'adjustment_percent' => number_format($adjustmentPercent, 2, '.', ''),
            'adjustment_amount' => number_format($adjustmentAmount, 2, '.', ''),
            'total' => $amount,
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        $encoded = json_encode($memory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            return [
                'enabled' => true,
                'amount' => null,
                'memory_json' => null,
                'formula_label' => null,
                'warnings' => [],
                'errors' => ['Falha ao gerar memoria de calculo.'],
            ];
        }

        if ($manualAmount !== null && abs((float) $manualAmount - (float) $amount) > 0.009) {
            $warnings[] = 'Valor informado manualmente foi substituido pelo total calculado.';
        }

        return [
            'enabled' => true,
            'amount' => $amount,
            'memory_json' => $encoded,
            'formula_label' => $formulaLabel,
            'warnings' => $warnings,
            'errors' => [],
        ];
    }

    private function parseDecimal(mixed $value): ?float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace('%', '', $raw);
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes', 'sim'], true);
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
