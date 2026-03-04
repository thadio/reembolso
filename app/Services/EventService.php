<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class EventService
{
    public function __construct(private PDO $db, private AuditService $audit)
    {
    }

    /** @param array<string, mixed> $payload */
    public function recordEvent(string $entity, string $type, array $payload = [], ?int $entityId = null, ?int $userId = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO system_events (entity, entity_id, event_type, payload, user_id, created_at)
             VALUES (:entity, :entity_id, :event_type, :payload, :user_id, NOW())'
        );

        $stmt->execute([
            'entity' => $entity,
            'entity_id' => $entityId,
            'event_type' => $type,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'user_id' => $userId,
        ]);

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

        $this->audit->log(
            entity: $entity,
            entityId: $entityId,
            action: 'event.recorded',
            beforeData: null,
            afterData: [
                'event_type' => $type,
                'payload' => $payload,
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );
    }
}
