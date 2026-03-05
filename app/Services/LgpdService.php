<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LgpdRepository;

final class LgpdService
{
    /** @var array<string, array{label: string, description: string, supports_anonymization: bool, default_retention_days: int, default_anonymize_after_days: int|null}> */
    private const POLICY_DEFINITIONS = [
        'sensitive_access_logs' => [
            'label' => 'Logs de acesso sensivel',
            'description' => 'Retencao dos registros de visualizacao/download de dados sensiveis.',
            'supports_anonymization' => false,
            'default_retention_days' => 365,
            'default_anonymize_after_days' => null,
        ],
        'audit_log' => [
            'label' => 'Trilha de auditoria geral',
            'description' => 'Retencao de eventos gerais da tabela audit_log.',
            'supports_anonymization' => false,
            'default_retention_days' => 730,
            'default_anonymize_after_days' => null,
        ],
        'people_soft_deleted' => [
            'label' => 'Anonimizacao de pessoas removidas',
            'description' => 'Anonimiza dados pessoais de pessoas em soft delete apos o prazo configurado.',
            'supports_anonymization' => true,
            'default_retention_days' => 0,
            'default_anonymize_after_days' => 180,
        ],
        'users_soft_deleted' => [
            'label' => 'Anonimizacao de usuarios removidos',
            'description' => 'Anonimiza dados pessoais de usuarios em soft delete apos o prazo configurado.',
            'supports_anonymization' => true,
            'default_retention_days' => 0,
            'default_anonymize_after_days' => 365,
        ],
    ];

    public function __construct(
        private LgpdRepository $lgpd,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters, int $page, int $perPage): array
    {
        $normalized = $this->normalizeFilters($filters);
        $list = $this->lgpd->paginateSensitiveAccess($normalized, $page, $perPage);

        return [
            'filters' => $normalized,
            'logs' => $list,
            'summary' => $this->lgpd->summarySensitiveAccess($normalized),
            'actions' => $this->lgpd->actionOptions(),
            'sensitivities' => $this->lgpd->sensitivityOptions(),
            'users' => $this->lgpd->usersForFilter(300),
            'policies' => $this->policiesGrid(),
            'runs' => $this->lgpd->retentionRuns(12),
            'latest_run' => $this->lgpd->latestRetentionRun(),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>}
     */
    public function upsertPolicy(array $input, int $userId, string $ip, string $userAgent): array
    {
        $policyKey = trim((string) ($input['policy_key'] ?? ''));
        $catalog = self::POLICY_DEFINITIONS[$policyKey] ?? null;

        if ($catalog === null) {
            return [
                'ok' => false,
                'errors' => ['Politica LGPD invalida.'],
                'data' => [],
            ];
        }

        $retentionDays = $this->parseNonNegativeInt($input['retention_days'] ?? null);
        $anonymizeAfterDays = $this->parseNonNegativeInt($input['anonymize_after_days'] ?? null);
        $isActive = (int) ($input['is_active'] ?? 0) === 1 ? 1 : 0;

        $errors = [];

        if ($retentionDays === null || $retentionDays > 3650) {
            $errors[] = 'Retencao deve ser um inteiro entre 0 e 3650 dias.';
        }

        if (($catalog['supports_anonymization'] ?? false) !== true) {
            $anonymizeAfterDays = null;
        } else {
            if ($anonymizeAfterDays !== null && $anonymizeAfterDays > 3650) {
                $errors[] = 'Prazo de anonimizacao deve ser um inteiro entre 0 e 3650 dias.';
            }

            if ($anonymizeAfterDays !== null && $anonymizeAfterDays < 1) {
                $errors[] = 'Prazo de anonimizacao deve ser maior que zero quando informado.';
            }
        }

        if ($retentionDays !== null && $retentionDays === 0 && $anonymizeAfterDays === null) {
            $errors[] = 'Configure retencao maior que zero ou prazo de anonimizacao para a politica.';
        }

        $data = [
            'policy_key' => $policyKey,
            'policy_label' => (string) $catalog['label'],
            'description' => (string) $catalog['description'],
            'retention_days' => $retentionDays ?? (int) $catalog['default_retention_days'],
            'anonymize_after_days' => $anonymizeAfterDays,
            'supports_anonymization' => ($catalog['supports_anonymization'] ?? false) ? 1 : 0,
            'is_active' => $isActive,
            'created_by' => $userId > 0 ? $userId : null,
            'updated_by' => $userId > 0 ? $userId : null,
        ];

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'data' => $data,
            ];
        }

        $before = $this->lgpd->findPolicyByKey($policyKey);
        $this->lgpd->upsertPolicy($data);
        $after = $this->lgpd->findPolicyByKey($policyKey);

        $this->audit->log(
            entity: 'lgpd_policy',
            entityId: (int) ($after['id'] ?? 0),
            action: 'upsert',
            beforeData: $before,
            afterData: $after,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'lgpd',
            type: 'lgpd.policy_upserted',
            payload: [
                'policy_key' => $policyKey,
                'is_active' => $isActive,
                'retention_days' => $data['retention_days'],
                'anonymize_after_days' => $data['anonymize_after_days'],
            ],
            entityId: (int) ($after['id'] ?? 0),
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $data,
        ];
    }

    /**
     * @return array{ok: bool, mode: string, message: string, status: string, run_id: int, stats: array<string, int>, errors: array<int, string>}
     */
    public function executeRetention(bool $apply, int $userId, string $ip, string $userAgent): array
    {
        $policies = [];
        foreach ($this->policiesGrid() as $policy) {
            $policies[(string) ($policy['policy_key'] ?? '')] = $policy;
        }

        $stats = [
            'sensitive_access_candidates' => 0,
            'sensitive_access_purged' => 0,
            'audit_log_candidates' => 0,
            'audit_log_purged' => 0,
            'people_candidates' => 0,
            'people_anonymized' => 0,
            'users_candidates' => 0,
            'users_anonymized' => 0,
        ];

        $mode = $apply ? 'apply' : 'preview';

        try {
            if ($apply) {
                $this->lgpd->beginTransaction();
            }

            $sensitivePolicy = $policies['sensitive_access_logs'] ?? null;
            if ($this->isPolicyRetentionEnabled($sensitivePolicy)) {
                $cutoff = $this->cutoffDate((int) ($sensitivePolicy['retention_days'] ?? 0));
                if ($cutoff !== null) {
                    $stats['sensitive_access_candidates'] = $this->lgpd->countSensitiveAccessOlderThan($cutoff);
                    if ($apply) {
                        $stats['sensitive_access_purged'] = $this->lgpd->purgeSensitiveAccessOlderThan($cutoff);
                    }
                }
            }

            $auditPolicy = $policies['audit_log'] ?? null;
            if ($this->isPolicyRetentionEnabled($auditPolicy)) {
                $cutoff = $this->cutoffDate((int) ($auditPolicy['retention_days'] ?? 0));
                if ($cutoff !== null) {
                    $stats['audit_log_candidates'] = $this->lgpd->countAuditLogOlderThan($cutoff);
                    if ($apply) {
                        $stats['audit_log_purged'] = $this->lgpd->purgeAuditLogOlderThan($cutoff);
                    }
                }
            }

            $peoplePolicy = $policies['people_soft_deleted'] ?? null;
            if ($this->isPolicyAnonymizationEnabled($peoplePolicy)) {
                $cutoff = $this->cutoffDate((int) ($peoplePolicy['anonymize_after_days'] ?? 0));
                if ($cutoff !== null) {
                    $stats['people_candidates'] = $this->lgpd->countPeopleForAnonymization($cutoff);
                    if ($apply) {
                        $stats['people_anonymized'] = $this->lgpd->anonymizePeople($cutoff);
                    }
                }
            }

            $usersPolicy = $policies['users_soft_deleted'] ?? null;
            if ($this->isPolicyAnonymizationEnabled($usersPolicy)) {
                $cutoff = $this->cutoffDate((int) ($usersPolicy['anonymize_after_days'] ?? 0));
                if ($cutoff !== null) {
                    $stats['users_candidates'] = $this->lgpd->countUsersForAnonymization($cutoff);
                    if ($apply) {
                        $stats['users_anonymized'] = $this->lgpd->anonymizeUsers($cutoff);
                    }
                }
            }

            if ($apply) {
                $this->lgpd->commit();
            }
        } catch (\Throwable $exception) {
            if ($apply) {
                $this->lgpd->rollBack();
            }

            $summary = [
                'mode' => $mode,
                'ok' => false,
                'error' => $exception->getMessage(),
                'stats' => $stats,
            ];

            $runId = $this->lgpd->createRetentionRun([
                'run_mode' => $mode,
                'status' => 'failed',
                'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'initiated_by' => $userId > 0 ? $userId : null,
            ]);

            $this->audit->log(
                entity: 'lgpd_retention',
                entityId: $runId,
                action: 'run_' . $mode,
                beforeData: null,
                afterData: $summary,
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            return [
                'ok' => false,
                'mode' => $mode,
                'message' => 'Falha ao executar rotina LGPD: ' . $exception->getMessage(),
                'status' => 'failed',
                'run_id' => $runId,
                'stats' => $stats,
                'errors' => ['Falha ao executar rotina LGPD.'],
            ];
        }

        $summary = [
            'mode' => $mode,
            'ok' => true,
            'executed_at' => date('Y-m-d H:i:s'),
            'stats' => $stats,
        ];

        $runId = $this->lgpd->createRetentionRun([
            'run_mode' => $mode,
            'status' => 'ok',
            'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'initiated_by' => $userId > 0 ? $userId : null,
        ]);

        $this->audit->log(
            entity: 'lgpd_retention',
            entityId: $runId,
            action: 'run_' . $mode,
            beforeData: null,
            afterData: $summary,
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'lgpd',
            type: 'lgpd.retention_executed',
            payload: [
                'mode' => $mode,
                ...$stats,
            ],
            entityId: $runId,
            userId: $userId
        );

        $message = $apply
            ? 'Rotina LGPD aplicada com sucesso.'
            : 'Preview de rotina LGPD executado com sucesso.';

        return [
            'ok' => true,
            'mode' => $mode,
            'message' => $message,
            'status' => 'ok',
            'run_id' => $runId,
            'stats' => $stats,
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed> $inputFilters
     * @return array{file_name: string, rows: array<int, array<int, string>>}
     */
    public function exportAccessCsv(array $inputFilters, int $userId, string $ip, string $userAgent): array
    {
        $filters = $this->normalizeFilters($inputFilters);
        $rows = $this->lgpd->sensitiveAccessForExport($filters, 10000);

        $csvRows = [];
        $csvRows[] = [
            'id',
            'data_hora',
            'acao',
            'sensibilidade',
            'entidade',
            'entidade_id',
            'pessoa_id',
            'alvo',
            'usuario',
            'email_usuario',
            'contexto',
            'ip',
            'user_agent',
            'metadata',
        ];

        foreach ($rows as $row) {
            $csvRows[] = [
                (string) ((int) ($row['id'] ?? 0)),
                (string) ($row['created_at'] ?? ''),
                (string) ($row['action'] ?? ''),
                (string) ($row['sensitivity'] ?? ''),
                (string) ($row['entity'] ?? ''),
                isset($row['entity_id']) ? (string) $row['entity_id'] : '',
                isset($row['subject_person_id']) ? (string) $row['subject_person_id'] : '',
                (string) ($row['subject_label'] ?? ''),
                (string) ($row['user_name'] ?? ''),
                (string) ($row['user_email'] ?? ''),
                (string) ($row['context_path'] ?? ''),
                (string) ($row['ip'] ?? ''),
                (string) ($row['user_agent'] ?? ''),
                $this->normalizeJsonString($row['metadata'] ?? null),
            ];
        }

        $this->audit->log(
            entity: 'lgpd_access',
            entityId: null,
            action: 'export_csv',
            beforeData: null,
            afterData: [
                'filters' => $filters,
                'rows_exported' => count($rows),
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'lgpd',
            type: 'lgpd.access_exported_csv',
            payload: [
                'rows_exported' => count($rows),
                'action_filter' => (string) ($filters['action'] ?? ''),
                'sensitivity_filter' => (string) ($filters['sensitivity'] ?? ''),
            ],
            entityId: null,
            userId: $userId
        );

        return [
            'file_name' => 'lgpd-acessos-sensiveis-' . date('Ymd_His') . '.csv',
            'rows' => $csvRows,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function registerSensitiveAccess(
        string $entity,
        ?int $entityId,
        string $action,
        string $sensitivity,
        ?int $subjectPersonId,
        ?string $subjectLabel,
        ?string $contextPath,
        array $metadata,
        int $userId,
        string $ip,
        string $userAgent
    ): void {
        if ($userId <= 0) {
            return;
        }

        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $payload = [
            'entity' => $this->truncate($entity, 120),
            'entity_id' => $entityId,
            'action' => $this->truncate($action, 120),
            'sensitivity' => $this->truncate($sensitivity, 60),
            'subject_person_id' => $subjectPersonId,
            'subject_label' => $this->truncate($subjectLabel, 190),
            'context_path' => $this->truncate($contextPath, 255),
            'metadata' => is_string($encodedMetadata) ? $encodedMetadata : null,
            'user_id' => $userId,
            'ip' => $this->truncate($ip, 64),
            'user_agent' => $this->truncate($userAgent, 255),
        ];

        try {
            $this->lgpd->createSensitiveAccessLog($payload);
        } catch (\Throwable) {
            // Nao interromper o fluxo principal por falha de trilha LGPD.
        }
    }

    public function sensitivityLabel(string $value): string
    {
        return match ($value) {
            'cpf' => 'CPF',
            'document' => 'Documento',
            'document_public' => 'Documento (Publico)',
            'document_restricted' => 'Documento (Restrito)',
            'document_sensitive' => 'Documento (Sensivel)',
            'attachment' => 'Anexo',
            'payment_proof' => 'Comprovante',
            'office_document' => 'Oficio',
            default => ucfirst(str_replace('_', ' ', $value)),
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function policiesGrid(): array
    {
        $rows = $this->lgpd->policies();
        $map = [];

        foreach ($rows as $row) {
            $map[(string) ($row['policy_key'] ?? '')] = $row;
        }

        $grid = [];

        foreach (self::POLICY_DEFINITIONS as $policyKey => $definition) {
            $row = $map[$policyKey] ?? null;

            $grid[] = [
                'id' => (int) ($row['id'] ?? 0),
                'policy_key' => $policyKey,
                'policy_label' => (string) ($row['policy_label'] ?? $definition['label']),
                'description' => (string) ($row['description'] ?? $definition['description']),
                'retention_days' => (int) ($row['retention_days'] ?? $definition['default_retention_days']),
                'anonymize_after_days' => isset($row['anonymize_after_days']) ? (int) $row['anonymize_after_days'] : $definition['default_anonymize_after_days'],
                'supports_anonymization' => isset($row['supports_anonymization'])
                    ? (int) $row['supports_anonymization'] === 1
                    : (($definition['supports_anonymization'] ?? false) === true),
                'is_active' => isset($row['is_active']) ? (int) $row['is_active'] : 1,
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }

        return $grid;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $sort = (string) ($filters['sort'] ?? 'created_at');
        $dir = mb_strtolower((string) ($filters['dir'] ?? 'desc'));

        if (!in_array($sort, ['created_at', 'user', 'action', 'sensitivity', 'entity'], true)) {
            $sort = 'created_at';
        }

        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'action' => trim((string) ($filters['action'] ?? '')),
            'sensitivity' => trim((string) ($filters['sensitivity'] ?? '')),
            'user_id' => max(0, (int) ($filters['user_id'] ?? 0)),
            'from_date' => $this->normalizeDate((string) ($filters['from_date'] ?? '')),
            'to_date' => $this->normalizeDate((string) ($filters['to_date'] ?? '')),
            'sort' => $sort,
            'dir' => $dir,
        ];
    }

    /** @param array<string, mixed>|null $policy */
    private function isPolicyRetentionEnabled(?array $policy): bool
    {
        return $policy !== null
            && (int) ($policy['is_active'] ?? 0) === 1
            && (int) ($policy['retention_days'] ?? 0) > 0;
    }

    /** @param array<string, mixed>|null $policy */
    private function isPolicyAnonymizationEnabled(?array $policy): bool
    {
        return $policy !== null
            && (int) ($policy['is_active'] ?? 0) === 1
            && (int) ($policy['supports_anonymization'] ?? 0) === 1
            && (int) ($policy['anonymize_after_days'] ?? 0) > 0;
    }

    private function cutoffDate(int $days): ?string
    {
        if ($days <= 0) {
            return null;
        }

        $seconds = $days * 86400;

        return date('Y-m-d H:i:s', time() - $seconds);
    }

    private function truncate(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, $limit);
    }

    private function parseNonNegativeInt(mixed $value): ?int
    {
        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        if (!preg_match('/^\d+$/', $string)) {
            return null;
        }

        return (int) $string;
    }

    private function normalizeDate(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d', $timestamp);
    }

    private function normalizeJsonString(mixed $payload): string
    {
        if (!is_string($payload) || trim($payload) === '') {
            return '';
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return trim($payload);
        }

        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : trim($payload);
    }
}
