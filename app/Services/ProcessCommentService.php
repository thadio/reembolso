<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProcessCommentRepository;

final class ProcessCommentService
{
    private const ALLOWED_STATUS = ['aberto', 'arquivado'];

    public function __construct(
        private ProcessCommentRepository $comments,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{
     *   summary: array<string, int>,
     *   items: array<int, array<string, mixed>>
     * }
     */
    public function profileData(int $personId, int $limit = 80): array
    {
        return [
            'summary' => $this->normalizeSummary($this->comments->summaryByPerson($personId)),
            'items' => $this->comments->listByPerson($personId, $limit),
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    public function statusOptions(): array
    {
        return [
            ['value' => 'aberto', 'label' => 'Aberto'],
            ['value' => 'arquivado', 'label' => 'Arquivado'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, warnings: array<int, string>}
     */
    public function create(
        int $personId,
        ?int $defaultAssignmentId,
        array $input,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        $assignmentId = max(0, (int) ($input['assignment_id'] ?? ($defaultAssignmentId ?? 0)));
        $commentText = $this->sanitizeComment($input['comment_text'] ?? null);
        $status = $this->normalizeStatus($input['status'] ?? null, 'aberto');
        $isPinned = $this->isTruthy($input['is_pinned'] ?? null);

        $errors = $this->validate(
            personId: $personId,
            commentText: $commentText,
            status: $status,
            assignmentId: $assignmentId > 0 ? $assignmentId : null
        );
        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel registrar comentario interno.',
                'errors' => $errors,
                'warnings' => [],
            ];
        }

        $assignmentId = $assignmentId > 0 ? $assignmentId : null;

        try {
            $this->comments->beginTransaction();

            $commentId = $this->comments->createComment(
                personId: $personId,
                assignmentId: $assignmentId,
                commentText: $commentText,
                status: $status,
                isPinned: $isPinned,
                createdBy: $userId > 0 ? $userId : null
            );

            $this->audit->log(
                entity: 'process_comment',
                entityId: $commentId,
                action: 'create',
                beforeData: null,
                afterData: [
                    'person_id' => $personId,
                    'assignment_id' => $assignmentId,
                    'status' => $status,
                    'is_pinned' => $isPinned ? 1 : 0,
                    'comment_size' => mb_strlen($commentText),
                ],
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'person',
                type: 'process_comment.created',
                payload: [
                    'comment_id' => $commentId,
                    'status' => $status,
                    'is_pinned' => $isPinned,
                ],
                entityId: $personId,
                userId: $userId
            );

            $this->comments->commit();
        } catch (\Throwable $exception) {
            $this->comments->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel registrar comentario interno.',
                'errors' => ['Falha ao persistir comentario interno. Tente novamente.'],
                'warnings' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Comentario interno registrado com sucesso.',
            'errors' => [],
            'warnings' => [],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, warnings: array<int, string>}
     */
    public function update(
        int $personId,
        int $commentId,
        array $input,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        if ($commentId <= 0) {
            return [
                'ok' => false,
                'message' => 'Comentario interno invalido.',
                'errors' => ['Comentario interno invalido.'],
                'warnings' => [],
            ];
        }

        $before = $this->comments->findByIdForPerson($commentId, $personId);
        if ($before === null) {
            return [
                'ok' => false,
                'message' => 'Comentario interno nao encontrado.',
                'errors' => ['Comentario interno nao encontrado para esta pessoa.'],
                'warnings' => [],
            ];
        }

        $commentText = $this->sanitizeComment($input['comment_text'] ?? ($before['comment_text'] ?? ''));
        $status = $this->normalizeStatus($input['status'] ?? ($before['status'] ?? 'aberto'), 'aberto');
        $isPinned = array_key_exists('is_pinned', $input)
            ? $this->isTruthy($input['is_pinned'])
            : ((int) ($before['is_pinned'] ?? 0) === 1);
        $assignmentId = isset($before['assignment_id']) ? (int) $before['assignment_id'] : null;

        $errors = $this->validate(
            personId: $personId,
            commentText: $commentText,
            status: $status,
            assignmentId: $assignmentId
        );
        if ($errors !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel atualizar comentario interno.',
                'errors' => $errors,
                'warnings' => [],
            ];
        }

        $beforeStatus = (string) ($before['status'] ?? 'aberto');
        $beforePinned = (int) ($before['is_pinned'] ?? 0) === 1;
        $beforeText = (string) ($before['comment_text'] ?? '');
        $statusChanged = $beforeStatus !== $status;
        $changed = $beforeText !== $commentText || $beforePinned !== $isPinned || $statusChanged;

        if (!$changed) {
            return [
                'ok' => true,
                'message' => 'Comentario mantido sem alteracoes.',
                'errors' => [],
                'warnings' => [],
            ];
        }

        try {
            $this->comments->beginTransaction();

            $updated = $this->comments->updateComment(
                commentId: $commentId,
                commentText: $commentText,
                status: $status,
                isPinned: $isPinned,
                updatedBy: $userId > 0 ? $userId : null
            );

            if (!$updated) {
                throw new \RuntimeException('update_failed');
            }

            $after = $this->comments->findByIdForPerson($commentId, $personId) ?? $before;

            $this->audit->log(
                entity: 'process_comment',
                entityId: $commentId,
                action: 'update',
                beforeData: [
                    'status' => $beforeStatus,
                    'is_pinned' => $beforePinned ? 1 : 0,
                    'comment_text' => $beforeText,
                ],
                afterData: [
                    'status' => $status,
                    'is_pinned' => $isPinned ? 1 : 0,
                    'comment_text' => $commentText,
                ],
                metadata: [
                    'person_id' => $personId,
                    'assignment_id' => $assignmentId,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            if ($statusChanged) {
                $this->audit->log(
                    entity: 'process_comment',
                    entityId: $commentId,
                    action: 'status.update',
                    beforeData: ['status' => $beforeStatus],
                    afterData: ['status' => $status],
                    metadata: [
                        'person_id' => $personId,
                    ],
                    userId: $userId,
                    ip: $ip,
                    userAgent: $userAgent
                );
            }

            $this->events->recordEvent(
                entity: 'person',
                type: 'process_comment.updated',
                payload: [
                    'comment_id' => $commentId,
                    'status' => (string) ($after['status'] ?? ''),
                    'is_pinned' => (int) ($after['is_pinned'] ?? 0) === 1,
                ],
                entityId: $personId,
                userId: $userId
            );

            if ($statusChanged) {
                $this->events->recordEvent(
                    entity: 'person',
                    type: 'process_comment.status_updated',
                    payload: [
                        'comment_id' => $commentId,
                        'from_status' => $beforeStatus,
                        'to_status' => $status,
                    ],
                    entityId: $personId,
                    userId: $userId
                );
            }

            $this->comments->commit();
        } catch (\Throwable $exception) {
            $this->comments->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel atualizar comentario interno.',
                'errors' => ['Falha ao atualizar comentario interno. Tente novamente.'],
                'warnings' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Comentario interno atualizado com sucesso.',
            'errors' => [],
            'warnings' => [],
        ];
    }

    /** @return array{ok: bool, message: string, errors: array<int, string>, warnings: array<int, string>} */
    public function delete(
        int $personId,
        int $commentId,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        if ($commentId <= 0) {
            return [
                'ok' => false,
                'message' => 'Comentario interno invalido.',
                'errors' => ['Comentario interno invalido.'],
                'warnings' => [],
            ];
        }

        $before = $this->comments->findByIdForPerson($commentId, $personId);
        if ($before === null) {
            return [
                'ok' => false,
                'message' => 'Comentario interno nao encontrado.',
                'errors' => ['Comentario interno nao encontrado para esta pessoa.'],
                'warnings' => [],
            ];
        }

        try {
            $this->comments->beginTransaction();

            $deleted = $this->comments->softDelete(
                commentId: $commentId,
                deletedBy: $userId > 0 ? $userId : null
            );
            if (!$deleted) {
                throw new \RuntimeException('delete_failed');
            }

            $this->audit->log(
                entity: 'process_comment',
                entityId: $commentId,
                action: 'delete',
                beforeData: [
                    'status' => (string) ($before['status'] ?? ''),
                    'is_pinned' => (int) ($before['is_pinned'] ?? 0),
                    'comment_text' => (string) ($before['comment_text'] ?? ''),
                ],
                afterData: [
                    'deleted' => true,
                ],
                metadata: [
                    'person_id' => $personId,
                    'assignment_id' => isset($before['assignment_id']) ? (int) $before['assignment_id'] : null,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'person',
                type: 'process_comment.deleted',
                payload: [
                    'comment_id' => $commentId,
                ],
                entityId: $personId,
                userId: $userId
            );

            $this->comments->commit();
        } catch (\Throwable $exception) {
            $this->comments->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel excluir comentario interno.',
                'errors' => ['Falha ao excluir comentario interno. Tente novamente.'],
                'warnings' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Comentario interno removido com sucesso.',
            'errors' => [],
            'warnings' => [],
        ];
    }

    /** @return array<string, int> */
    private function normalizeSummary(array $raw): array
    {
        return [
            'total_comments' => max(0, (int) ($raw['total_comments'] ?? 0)),
            'open_count' => max(0, (int) ($raw['open_count'] ?? 0)),
            'archived_count' => max(0, (int) ($raw['archived_count'] ?? 0)),
            'pinned_count' => max(0, (int) ($raw['pinned_count'] ?? 0)),
        ];
    }

    /** @return array<int, string> */
    private function validate(int $personId, string $commentText, string $status, ?int $assignmentId): array
    {
        $errors = [];

        if ($personId <= 0) {
            $errors[] = 'Pessoa invalida para comentario interno.';
        }

        if (mb_strlen($commentText) < 3) {
            $errors[] = 'Comentario interno deve ter no minimo 3 caracteres.';
        }

        if (mb_strlen($commentText) > 5000) {
            $errors[] = 'Comentario interno excede limite de 5000 caracteres.';
        }

        if (!in_array($status, self::ALLOWED_STATUS, true)) {
            $errors[] = 'Status invalido para comentario interno.';
        }

        if ($assignmentId !== null && $assignmentId > 0 && !$this->comments->assignmentBelongsToPerson($assignmentId, $personId)) {
            $errors[] = 'Movimentacao informada nao pertence a pessoa selecionada.';
        }

        return $errors;
    }

    private function sanitizeComment(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized);

        return is_string($normalized) ? trim($normalized) : '';
    }

    private function normalizeStatus(mixed $value, string $fallback): string
    {
        $candidate = mb_strtolower(trim((string) $value));

        return in_array($candidate, self::ALLOWED_STATUS, true) ? $candidate : $fallback;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes', 'sim'], true);
    }
}
