<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class AuditService
{
    private ?bool $supportsScopeOrganId = null;

    /** @var array<string, \PDOStatement> */
    private array $scopeResolverStatements = [];

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
        string $userAgent,
        ?int $scopeOrganId = null
    ): void {
        $resolvedScopeOrganId = $scopeOrganId ?? $this->resolveScopeOrganId($entity, $entityId, $metadata);

        if ($this->supportsScopeOrganId()) {
            $stmt = $this->db->prepare(
                'INSERT INTO audit_log (
                    entity,
                    entity_id,
                    scope_organ_id,
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
                    :scope_organ_id,
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
                'scope_organ_id' => $resolvedScopeOrganId,
                'action' => $action,
                'before_data' => $beforeData === null ? null : json_encode($beforeData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'after_data' => $afterData === null ? null : json_encode($afterData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'metadata' => $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'user_id' => $userId,
                'ip' => substr($ip, 0, 64),
                'user_agent' => substr($userAgent, 0, 255),
            ]);

            return;
        }

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

    private function supportsScopeOrganId(): bool
    {
        if ($this->supportsScopeOrganId !== null) {
            return $this->supportsScopeOrganId;
        }

        $stmt = $this->db->query(
            "SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'audit_log'
               AND COLUMN_NAME = 'scope_organ_id'"
        );

        $this->supportsScopeOrganId = ((int) $stmt->fetchColumn()) > 0;

        return $this->supportsScopeOrganId;
    }

    private function resolveScopeOrganId(string $entity, ?int $entityId, ?array $metadata): ?int
    {
        $metadataScope = $this->extractScopeOrganIdFromMetadata($metadata);
        if ($metadataScope !== null) {
            return $metadataScope;
        }

        if ($entity === 'organ' && $entityId !== null && $entityId > 0) {
            return $entityId;
        }

        if ($entityId === null || $entityId <= 0) {
            return null;
        }

        $resolverSql = $this->scopeResolverSql($entity);
        if ($resolverSql === null) {
            return null;
        }

        try {
            if (!isset($this->scopeResolverStatements[$entity])) {
                $this->scopeResolverStatements[$entity] = $this->db->prepare($resolverSql);
            }

            $stmt = $this->scopeResolverStatements[$entity];
            $stmt->execute(['id' => $entityId]);

            $organId = (int) ($stmt->fetchColumn() ?: 0);
            return $organId > 0 ? $organId : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function extractScopeOrganIdFromMetadata(?array $metadata): ?int
    {
        if ($metadata === null) {
            return null;
        }

        $candidateKeys = [
            'scope_organ_id',
            'organ_id',
            'target_organ_id',
            'counterparty_organ_id',
            'origin_organ_id',
            'destination_organ_id',
        ];

        foreach ($candidateKeys as $key) {
            if (!array_key_exists($key, $metadata)) {
                continue;
            }

            $organId = (int) $metadata[$key];
            if ($organId > 0) {
                return $organId;
            }
        }

        return null;
    }

    private function scopeResolverSql(string $entity): ?string
    {
        return match ($entity) {
            'person' => 'SELECT p.organ_id FROM people p WHERE p.id = :id LIMIT 1',
            'assignment', 'assignment_checklist' =>
                'SELECT p.organ_id
                 FROM assignments a
                 INNER JOIN people p ON p.id = a.person_id
                 WHERE a.id = :id
                 LIMIT 1',
            'assignment_checklist_item' =>
                'SELECT p.organ_id
                 FROM assignment_checklist_items aci
                 INNER JOIN assignments a ON a.id = aci.assignment_id
                 INNER JOIN people p ON p.id = a.person_id
                 WHERE aci.id = :id
                 LIMIT 1',
            'timeline_event' =>
                'SELECT p.organ_id
                 FROM timeline_events t
                 INNER JOIN people p ON p.id = t.person_id
                 WHERE t.id = :id
                 LIMIT 1',
            'timeline_attachment' =>
                'SELECT p.organ_id
                 FROM timeline_event_attachments ta
                 INNER JOIN people p ON p.id = ta.person_id
                 WHERE ta.id = :id
                 LIMIT 1',
            'document' =>
                'SELECT p.organ_id
                 FROM documents d
                 INNER JOIN people p ON p.id = d.person_id
                 WHERE d.id = :id
                 LIMIT 1',
            'process_metadata' =>
                'SELECT p.organ_id
                 FROM process_metadata pm
                 INNER JOIN people p ON p.id = pm.person_id
                 WHERE pm.id = :id
                 LIMIT 1',
            'cost_plan' =>
                'SELECT p.organ_id
                 FROM cost_plans cp
                 INNER JOIN people p ON p.id = cp.person_id
                 WHERE cp.id = :id
                 LIMIT 1',
            'cost_plan_item' =>
                'SELECT p.organ_id
                 FROM cost_plan_items cpi
                 INNER JOIN people p ON p.id = cpi.person_id
                 WHERE cpi.id = :id
                 LIMIT 1',
            'reimbursement_entry' =>
                'SELECT p.organ_id
                 FROM reimbursement_entries r
                 INNER JOIN people p ON p.id = r.person_id
                 WHERE r.id = :id
                 LIMIT 1',
            'process_comment' =>
                'SELECT p.organ_id
                 FROM process_comments pc
                 INNER JOIN people p ON p.id = pc.person_id
                 WHERE pc.id = :id
                 LIMIT 1',
            'process_admin_timeline_note' =>
                'SELECT p.organ_id
                 FROM process_admin_timeline_notes patn
                 INNER JOIN people p ON p.id = patn.person_id
                 WHERE patn.id = :id
                 LIMIT 1',
            'analyst_pending_item' =>
                'SELECT p.organ_id
                 FROM analyst_pending_items api
                 INNER JOIN people p ON p.id = api.person_id
                 WHERE api.id = :id
                 LIMIT 1',
            'invoice' =>
                'SELECT i.organ_id
                 FROM invoices i
                 WHERE i.id = :id
                 LIMIT 1',
            'invoice_person' =>
                'SELECT i.organ_id
                 FROM invoice_people ip
                 INNER JOIN invoices i ON i.id = ip.invoice_id
                 WHERE ip.id = :id
                 LIMIT 1',
            'payment' =>
                'SELECT i.organ_id
                 FROM payments pmt
                 INNER JOIN invoices i ON i.id = pmt.invoice_id
                 WHERE pmt.id = :id
                 LIMIT 1',
            'payment_batch' =>
                'SELECT CASE WHEN COUNT(DISTINCT i.organ_id) = 1 THEN MIN(i.organ_id) ELSE NULL END AS organ_id
                 FROM payment_batch_items pbi
                 INNER JOIN invoices i ON i.id = pbi.invoice_id
                 WHERE pbi.batch_id = :id',
            'cost_mirror' =>
                'SELECT cm.organ_id
                 FROM cost_mirrors cm
                 WHERE cm.id = :id
                 LIMIT 1',
            'cost_mirror_item' =>
                'SELECT cm.organ_id
                 FROM cost_mirror_items cmi
                 INNER JOIN cost_mirrors cm ON cm.id = cmi.cost_mirror_id
                 WHERE cmi.id = :id
                 LIMIT 1',
            'cost_mirror_reconciliation' =>
                'SELECT cm.organ_id
                 FROM cost_mirror_reconciliations r
                 INNER JOIN cost_mirrors cm ON cm.id = r.cost_mirror_id
                 WHERE r.id = :id
                 LIMIT 1',
            'cost_mirror_divergence' =>
                'SELECT cm.organ_id
                 FROM cost_mirror_divergences d
                 INNER JOIN cost_mirrors cm ON cm.id = d.cost_mirror_id
                 WHERE d.id = :id
                 LIMIT 1',
            'cdo_person' =>
                'SELECT p.organ_id
                 FROM cdo_people cp
                 INNER JOIN people p ON p.id = cp.person_id
                 WHERE cp.id = :id
                 LIMIT 1',
            'hiring_scenario' =>
                'SELECT hs.organ_id
                 FROM hiring_scenarios hs
                 WHERE hs.id = :id
                 LIMIT 1',
            'budget_scenario_parameter' =>
                'SELECT bsp.organ_id
                 FROM budget_scenario_parameters bsp
                 WHERE bsp.id = :id
                 LIMIT 1',
            default => null,
        };
    }
}
