<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\SlaAlertRepository;

final class SlaAlertService
{
    private const DEFAULT_WARNING_DAYS = 5;
    private const DEFAULT_OVERDUE_DAYS = 10;

    public function __construct(
        private SlaAlertRepository $sla,
        private AuditService $audit,
        private EventService $events,
        private Config $config
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   per_page: int,
     *   pages: int,
     *   summary: array{total: int, no_prazo: int, em_risco: int, vencido: int}
     * }
     */
    public function panel(array $filters, int $page, int $perPage): array
    {
        $list = $this->sla->paginatePending($filters, $page, $perPage);
        $summary = $this->sla->summary($filters);

        return [
            ...$list,
            'summary' => $summary,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function statusOptions(): array
    {
        return $this->sla->activeStatusesForSla();
    }

    /** @return array<int, array{value: string, label: string}> */
    public function severityOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Todos os niveis'],
            ['value' => 'no_prazo', 'label' => 'No prazo'],
            ['value' => 'em_risco', 'label' => 'Em risco'],
            ['value' => 'vencido', 'label' => 'Vencido'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function rulesGrid(): array
    {
        $statuses = $this->sla->activeStatusesForSla();
        $rulesMap = [];

        foreach ($this->sla->rules() as $rule) {
            $rulesMap[(string) ($rule['status_code'] ?? '')] = $rule;
        }

        $rows = [];
        foreach ($statuses as $status) {
            $code = (string) ($status['code'] ?? '');
            $rule = $rulesMap[$code] ?? null;

            $rows[] = [
                'status_code' => $code,
                'status_label' => (string) ($status['label'] ?? $code),
                'sort_order' => (int) ($status['sort_order'] ?? 0),
                'warning_days' => (int) ($rule['warning_days'] ?? self::DEFAULT_WARNING_DAYS),
                'overdue_days' => (int) ($rule['overdue_days'] ?? self::DEFAULT_OVERDUE_DAYS),
                'notify_email' => (int) ($rule['notify_email'] ?? 0),
                'notify_recipients' => (string) ($rule['notify_recipients'] ?? ''),
                'is_active' => (int) ($rule['is_active'] ?? 1),
                'updated_at' => (string) ($rule['updated_at'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>}
     */
    public function saveRule(array $input, int $userId, string $ip, string $userAgent): array
    {
        $statusCode = trim((string) ($input['status_code'] ?? ''));
        $warningDays = max(0, (int) ($input['warning_days'] ?? 0));
        $overdueDays = max(0, (int) ($input['overdue_days'] ?? 0));
        $notifyEmail = (int) ($input['notify_email'] ?? 0) === 1 ? 1 : 0;
        $notifyRecipients = $this->clean($input['notify_recipients'] ?? null);
        $isActive = (int) ($input['is_active'] ?? 1) === 1 ? 1 : 0;

        $errors = [];

        $status = $statusCode !== '' ? $this->sla->findStatusForSla($statusCode) : null;
        if ($status === null) {
            $errors[] = 'Status invalido para regra de SLA.';
        }

        if ($warningDays < 1 || $warningDays > 365) {
            $errors[] = 'Prazo de risco deve estar entre 1 e 365 dias.';
        }

        if ($overdueDays < 1 || $overdueDays > 730) {
            $errors[] = 'Prazo de vencimento deve estar entre 1 e 730 dias.';
        }

        if ($overdueDays <= $warningDays) {
            $errors[] = 'Prazo de vencimento deve ser maior que o prazo de risco.';
        }

        if ($notifyRecipients !== null && mb_strlen($notifyRecipients) > 700) {
            $errors[] = 'Lista de emails excede limite de 700 caracteres.';
        }

        $emails = $this->parseRecipients($notifyRecipients);
        if ($notifyEmail === 1 && $emails === []) {
            $errors[] = 'Informe ao menos um email valido para notificacao.';
        }

        $invalidEmails = $this->invalidRecipients($notifyRecipients);
        if ($invalidEmails !== []) {
            $errors[] = 'Emails invalidos: ' . implode(', ', array_slice($invalidEmails, 0, 4));
        }

        $data = [
            'status_code' => $statusCode,
            'warning_days' => $warningDays,
            'overdue_days' => $overdueDays,
            'notify_email' => $notifyEmail,
            'notify_recipients' => $notifyRecipients,
            'is_active' => $isActive,
            'created_by' => $userId > 0 ? $userId : null,
        ];

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'data' => $data,
            ];
        }

        $before = $this->sla->findRuleByStatus($statusCode);
        $this->sla->upsertRule($data);
        $after = $this->sla->findRuleByStatus($statusCode);

        $this->audit->log(
            entity: 'sla_rule',
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
            entity: 'sla',
            type: 'sla.rule_upserted',
            payload: [
                'status_code' => $statusCode,
                'warning_days' => $warningDays,
                'overdue_days' => $overdueDays,
                'notify_email' => $notifyEmail,
                'is_active' => $isActive,
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

    /** @return array{ok: bool, message: string, attempted: int, sent: int, failed: int, skipped: int} */
    public function dispatchNotifications(string $severity, int $userId, string $ip, string $userAgent): array
    {
        $normalizedSeverity = in_array($severity, ['all', 'em_risco', 'vencido'], true)
            ? $severity
            : 'all';

        $candidates = $this->sla->notificationCandidates($normalizedSeverity, 200);

        $attempted = 0;
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($candidates as $candidate) {
            $ruleId = (int) ($candidate['rule_id'] ?? 0);
            $assignmentId = (int) ($candidate['assignment_id'] ?? 0);
            $personId = (int) ($candidate['person_id'] ?? 0);
            $statusCode = (string) ($candidate['status_code'] ?? '');
            $statusLabel = (string) ($candidate['status_label'] ?? $statusCode);
            $personName = (string) ($candidate['person_name'] ?? 'Pessoa');
            $organName = (string) ($candidate['organ_name'] ?? 'Orgao');
            $days = (int) ($candidate['days_in_status'] ?? 0);
            $level = (string) ($candidate['sla_level'] ?? 'em_risco');
            $recipientsRaw = (string) ($candidate['notify_recipients'] ?? '');

            $recipients = $this->parseRecipients($recipientsRaw);
            if ($recipients === []) {
                $skipped++;
                continue;
            }

            $subject = sprintf('[SLA %s] %s - %s', strtoupper($this->severityLabel($level)), $personName, $statusLabel);
            $personUrl = rtrim((string) $this->config->get('app.base_url', ''), '/') . '/people/show?id=' . $personId;
            $body = implode("\n", [
                'Alerta de pendencia processual',
                '',
                'Pessoa: ' . $personName,
                'Orgao: ' . $organName,
                'Status atual: ' . $statusLabel . ' (' . $statusCode . ')',
                'Dias na etapa: ' . $days,
                'Nivel SLA: ' . $this->severityLabel($level),
                'Link: ' . $personUrl,
                '',
                'Mensagem automatica do sistema Reembolso.',
            ]);

            foreach ($recipients as $recipient) {
                $attempted++;

                $result = $this->sendMail($recipient, $subject, $body);
                if ($result['sent']) {
                    $sent++;
                } else {
                    $failed++;
                }

                $this->sla->createNotificationLog([
                    'rule_id' => $ruleId > 0 ? $ruleId : null,
                    'assignment_id' => $assignmentId,
                    'person_id' => $personId,
                    'status_code' => $statusCode,
                    'severity' => $level,
                    'recipient' => mb_substr($recipient, 0, 255),
                    'subject' => mb_substr($subject, 0, 255),
                    'body_preview' => mb_substr($body, 0, 4000),
                    'sent_success' => $result['sent'] ? 1 : 0,
                    'response_message' => $result['message'],
                    'sent_by' => $userId > 0 ? $userId : null,
                ]);
            }
        }

        $this->audit->log(
            entity: 'sla',
            entityId: null,
            action: 'dispatch_notifications',
            beforeData: null,
            afterData: [
                'severity' => $normalizedSeverity,
                'attempted' => $attempted,
                'sent' => $sent,
                'failed' => $failed,
                'skipped' => $skipped,
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'sla',
            type: 'sla.notifications_dispatched',
            payload: [
                'severity' => $normalizedSeverity,
                'attempted' => $attempted,
                'sent' => $sent,
                'failed' => $failed,
                'skipped' => $skipped,
            ],
            entityId: null,
            userId: $userId
        );

        $message = sprintf(
            'Disparo concluido: %d tentativa(s), %d enviada(s), %d falha(s), %d ignorada(s).',
            $attempted,
            $sent,
            $failed,
            $skipped
        );

        return [
            'ok' => true,
            'message' => $message,
            'attempted' => $attempted,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function recentLogs(int $limit = 20): array
    {
        return $this->sla->recentNotificationLogs($limit);
    }

    public function severityLabel(string $severity): string
    {
        return match ($severity) {
            'vencido' => 'Vencido',
            'em_risco' => 'Em risco',
            'no_prazo' => 'No prazo',
            default => 'N/A',
        };
    }

    /** @return array{sent: bool, message: string} */
    private function sendMail(string $recipient, string $subject, string $body): array
    {
        if (!function_exists('mail')) {
            return [
                'sent' => false,
                'message' => 'Funcao mail() indisponivel.',
            ];
        }

        $safeSubject = str_replace(["\r", "\n"], ' ', $subject);
        $headers = 'MIME-Version: 1.0' . "\r\n"
            . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
            . 'From: noreply@reembolso.local';

        $sent = @mail($recipient, $safeSubject, $body, $headers);

        return [
            'sent' => $sent,
            'message' => $sent ? 'ok' : 'Falha no envio com mail().',
        ];
    }

    /** @return array<int, string> */
    private function parseRecipients(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $tokens = preg_split('/[;,\s]+/u', trim($raw));
        if (!is_array($tokens)) {
            return [];
        }

        $emails = [];
        foreach ($tokens as $token) {
            $email = mb_strtolower(trim((string) $token));
            if ($email === '') {
                continue;
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $emails[$email] = $email;
        }

        return array_values($emails);
    }

    /** @return array<int, string> */
    private function invalidRecipients(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $tokens = preg_split('/[;,\s]+/u', trim($raw));
        if (!is_array($tokens)) {
            return [];
        }

        $invalid = [];
        foreach ($tokens as $token) {
            $value = trim((string) $token);
            if ($value === '') {
                continue;
            }

            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $invalid[] = $value;
            }
        }

        return array_values(array_unique($invalid));
    }

    private function clean(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
