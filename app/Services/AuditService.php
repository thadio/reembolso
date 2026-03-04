<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AuditService
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @param array<string, mixed>|null $beforeData
     * @param array<string, mixed>|null $afterData
     * @param array<string, mixed>|null $metadata
     */
    public function log(
        string $entity,
        ?int $entityId,
        string $action,
        ?array $beforeData,
        ?array $afterData,
        ?array $metadata,
        ?int $userId,
        string $ip,
        string $userAgent
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_log (
                entity,
                entity_id,
                action,
                before_data,
                after_data,
                metadata,
                user_id,
                ip,
                user_agent,
                created_at
            ) VALUES (
                :entity,
                :entity_id,
                :action,
                :before_data,
                :after_data,
                :metadata,
                :user_id,
                :ip,
                :user_agent,
                NOW()
            )'
        );

        $stmt->execute([
            'entity' => $entity,
            'entity_id' => $entityId,
            'action' => $action,
            'before_data' => $beforeData === null ? null : json_encode($beforeData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'after_data' => $afterData === null ? null : json_encode($afterData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'metadata' => $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'user_id' => $userId,
            'ip' => substr($ip, 0, 64),
            'user_agent' => substr($userAgent, 0, 255),
        ]);
    }
}
