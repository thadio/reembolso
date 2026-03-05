<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentIntelligenceRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function beginTransaction(): void
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /** @param array<string, mixed> $data */
    public function createReview(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO document_ai_reviews (
                person_id,
                trigger_source,
                status,
                documents_total,
                extractions_total,
                inconsistencies_total,
                suggestions_total,
                summary,
                executed_by,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :person_id,
                :trigger_source,
                :status,
                :documents_total,
                :extractions_total,
                :inconsistencies_total,
                :suggestions_total,
                :summary,
                :executed_by,
                NOW(),
                NOW(),
                NULL
            )'
        );

        $stmt->execute([
            'person_id' => $data['person_id'],
            'trigger_source' => $data['trigger_source'] ?? 'manual',
            'status' => $data['status'] ?? 'concluido',
            'documents_total' => $data['documents_total'] ?? 0,
            'extractions_total' => $data['extractions_total'] ?? 0,
            'inconsistencies_total' => $data['inconsistencies_total'] ?? 0,
            'suggestions_total' => $data['suggestions_total'] ?? 0,
            'summary' => $data['summary'] ?? null,
            'executed_by' => $data['executed_by'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateReviewSummary(int $reviewId, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE document_ai_reviews
             SET
                status = :status,
                documents_total = :documents_total,
                extractions_total = :extractions_total,
                inconsistencies_total = :inconsistencies_total,
                suggestions_total = :suggestions_total,
                summary = :summary,
                updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $reviewId,
            'status' => $data['status'] ?? 'concluido',
            'documents_total' => $data['documents_total'] ?? 0,
            'extractions_total' => $data['extractions_total'] ?? 0,
            'inconsistencies_total' => $data['inconsistencies_total'] ?? 0,
            'suggestions_total' => $data['suggestions_total'] ?? 0,
            'summary' => $data['summary'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function createExtraction(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO document_ai_extractions (
                review_id,
                person_id,
                document_id,
                source_scope,
                extracted_sei,
                extracted_cpf,
                extracted_competence,
                extracted_amount,
                confidence_score,
                raw_excerpt,
                extracted_payload,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :review_id,
                :person_id,
                :document_id,
                :source_scope,
                :extracted_sei,
                :extracted_cpf,
                :extracted_competence,
                :extracted_amount,
                :confidence_score,
                :raw_excerpt,
                :extracted_payload,
                NOW(),
                NOW(),
                NULL
            )'
        );

        $stmt->execute([
            'review_id' => $data['review_id'],
            'person_id' => $data['person_id'],
            'document_id' => $data['document_id'],
            'source_scope' => $data['source_scope'] ?? 'metadata',
            'extracted_sei' => $data['extracted_sei'] ?? null,
            'extracted_cpf' => $data['extracted_cpf'] ?? null,
            'extracted_competence' => $data['extracted_competence'] ?? null,
            'extracted_amount' => $data['extracted_amount'] ?? null,
            'confidence_score' => $data['confidence_score'] ?? 0,
            'raw_excerpt' => $data['raw_excerpt'] ?? null,
            'extracted_payload' => $data['extracted_payload'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function createFinding(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO document_ai_findings (
                review_id,
                person_id,
                document_id,
                divergence_id,
                finding_type,
                rule_key,
                severity,
                status,
                title,
                description,
                suggested_justification,
                confidence_score,
                metadata,
                created_by,
                resolved_by,
                resolved_at,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :review_id,
                :person_id,
                :document_id,
                :divergence_id,
                :finding_type,
                :rule_key,
                :severity,
                :status,
                :title,
                :description,
                :suggested_justification,
                :confidence_score,
                :metadata,
                :created_by,
                :resolved_by,
                :resolved_at,
                NOW(),
                NOW(),
                NULL
            )'
        );

        $stmt->execute([
            'review_id' => $data['review_id'],
            'person_id' => $data['person_id'],
            'document_id' => $data['document_id'] ?? null,
            'divergence_id' => $data['divergence_id'] ?? null,
            'finding_type' => $data['finding_type'],
            'rule_key' => $data['rule_key'],
            'severity' => $data['severity'] ?? 'media',
            'status' => $data['status'] ?? 'aberta',
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'suggested_justification' => $data['suggested_justification'] ?? null,
            'confidence_score' => $data['confidence_score'] ?? 0,
            'metadata' => $data['metadata'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'resolved_by' => $data['resolved_by'] ?? null,
            'resolved_at' => $data['resolved_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function latestReviewByPerson(int $personId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                r.id,
                r.person_id,
                r.trigger_source,
                r.status,
                r.documents_total,
                r.extractions_total,
                r.inconsistencies_total,
                r.suggestions_total,
                r.summary,
                r.executed_by,
                r.created_at,
                r.updated_at,
                u.name AS executed_by_name
             FROM document_ai_reviews r
             LEFT JOIN users u ON u.id = r.executed_by
             WHERE r.person_id = :person_id
               AND r.deleted_at IS NULL
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT 1'
        );
        $stmt->execute(['person_id' => $personId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function extractionsByReview(int $reviewId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                e.id,
                e.review_id,
                e.person_id,
                e.document_id,
                e.source_scope,
                e.extracted_sei,
                e.extracted_cpf,
                e.extracted_competence,
                e.extracted_amount,
                e.confidence_score,
                e.raw_excerpt,
                e.extracted_payload,
                e.created_at,
                d.document_type_id,
                d.title AS document_title,
                d.reference_sei,
                d.document_date,
                d.original_name,
                dt.name AS document_type_name
             FROM document_ai_extractions e
             INNER JOIN documents d ON d.id = e.document_id AND d.deleted_at IS NULL
             INNER JOIN document_types dt ON dt.id = d.document_type_id
             WHERE e.review_id = :review_id
               AND e.deleted_at IS NULL
             ORDER BY e.confidence_score DESC, e.id ASC'
        );
        $stmt->execute(['review_id' => $reviewId]);

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function findingsByReview(int $reviewId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                f.id,
                f.review_id,
                f.person_id,
                f.document_id,
                f.divergence_id,
                f.finding_type,
                f.rule_key,
                f.severity,
                f.status,
                f.title,
                f.description,
                f.suggested_justification,
                f.confidence_score,
                f.metadata,
                f.created_by,
                f.resolved_by,
                f.resolved_at,
                f.created_at,
                d.title AS document_title,
                d.original_name AS document_file,
                div.match_key AS divergence_match_key,
                div.difference_amount AS divergence_difference_amount,
                u.name AS created_by_name
             FROM document_ai_findings f
             LEFT JOIN documents d ON d.id = f.document_id AND d.deleted_at IS NULL
             LEFT JOIN cost_mirror_divergences div ON div.id = f.divergence_id AND div.deleted_at IS NULL
             LEFT JOIN users u ON u.id = f.created_by
             WHERE f.review_id = :review_id
               AND f.deleted_at IS NULL
             ORDER BY
               CASE f.severity WHEN "alta" THEN 3 WHEN "media" THEN 2 WHEN "baixa" THEN 1 ELSE 0 END DESC,
               f.confidence_score DESC,
               f.id ASC'
        );
        $stmt->execute(['review_id' => $reviewId]);

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function documentsForPerson(int $personId, int $limit = 80): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                d.id,
                d.person_id,
                d.document_type_id,
                d.title,
                d.reference_sei,
                d.document_date,
                d.tags,
                d.notes,
                d.original_name,
                d.mime_type,
                d.file_size,
                d.storage_path,
                d.created_at,
                dt.name AS document_type_name
             FROM documents d
             INNER JOIN document_types dt ON dt.id = d.document_type_id
             WHERE d.person_id = :person_id
               AND d.deleted_at IS NULL
             ORDER BY d.created_at DESC, d.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countDocumentsByReferenceSei(int $personId, string $referenceSei, int $excludeDocumentId = 0): int
    {
        $normalized = preg_replace('/\D+/', '', $referenceSei);
        if (!is_string($normalized) || $normalized === '') {
            return 0;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total
             FROM documents d
             WHERE d.person_id = :person_id
               AND d.deleted_at IS NULL
               AND REPLACE(REPLACE(REPLACE(COALESCE(d.reference_sei, ""), ".", ""), "/", ""), "-", "") = :reference_sei
               AND d.id <> :exclude_id'
        );
        $stmt->execute([
            'person_id' => $personId,
            'reference_sei' => $normalized,
            'exclude_id' => $excludeDocumentId,
        ]);

        return (int) (($stmt->fetch()['total'] ?? 0));
    }

    /** @return array{samples: int, avg_amount: float, stddev_amount: float} */
    public function amountStatsByPersonAndType(int $personId, int $documentTypeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*) AS samples,
                COALESCE(AVG(e.extracted_amount), 0) AS avg_amount,
                COALESCE(STDDEV_POP(e.extracted_amount), 0) AS stddev_amount
             FROM document_ai_extractions e
             INNER JOIN documents d ON d.id = e.document_id
             WHERE e.person_id = :person_id
               AND d.document_type_id = :document_type_id
               AND e.deleted_at IS NULL
               AND d.deleted_at IS NULL
               AND e.extracted_amount IS NOT NULL'
        );
        $stmt->execute([
            'person_id' => $personId,
            'document_type_id' => $documentTypeId,
        ]);
        $row = $stmt->fetch();

        return [
            'samples' => (int) ($row['samples'] ?? 0),
            'avg_amount' => round((float) ($row['avg_amount'] ?? 0), 2),
            'stddev_amount' => round((float) ($row['stddev_amount'] ?? 0), 2),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function unresolvedRequiredDivergencesByPerson(int $personId, int $limit = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                d.id,
                d.person_id,
                d.match_key,
                d.divergence_type,
                d.severity,
                d.expected_amount,
                d.actual_amount,
                d.difference_amount,
                d.threshold_amount,
                d.requires_justification,
                d.is_resolved,
                d.justification_text,
                r.id AS reconciliation_id,
                r.status AS reconciliation_status,
                cm.id AS cost_mirror_id,
                cm.reference_month
             FROM cost_mirror_divergences d
             INNER JOIN cost_mirror_reconciliations r
               ON r.id = d.reconciliation_id
              AND r.deleted_at IS NULL
             INNER JOIN cost_mirrors cm
               ON cm.id = d.cost_mirror_id
              AND cm.deleted_at IS NULL
             WHERE d.person_id = :person_id
               AND d.deleted_at IS NULL
               AND d.requires_justification = 1
               AND d.is_resolved = 0
             ORDER BY
               CASE d.severity WHEN "alta" THEN 3 WHEN "media" THEN 2 WHEN "baixa" THEN 1 ELSE 0 END DESC,
               ABS(d.difference_amount) DESC,
               d.id ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function historicalJustificationsByMatchKey(string $matchKey, int $excludeDivergenceId = 0, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                d.id,
                d.person_id,
                d.match_key,
                d.divergence_type,
                d.difference_amount,
                d.justification_text,
                d.justified_at,
                u.name AS justification_by_name
             FROM cost_mirror_divergences d
             LEFT JOIN users u ON u.id = d.justification_by
             WHERE d.deleted_at IS NULL
               AND d.id <> :exclude_id
               AND d.justification_text IS NOT NULL
               AND TRIM(d.justification_text) <> ""
               AND d.match_key = :match_key
             ORDER BY d.justified_at DESC, d.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':exclude_id', $excludeDivergenceId, PDO::PARAM_INT);
        $stmt->bindValue(':match_key', $matchKey);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function historicalJustificationsByPerson(int $personId, int $excludeDivergenceId = 0, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                d.id,
                d.person_id,
                d.match_key,
                d.divergence_type,
                d.difference_amount,
                d.justification_text,
                d.justified_at,
                u.name AS justification_by_name
             FROM cost_mirror_divergences d
             LEFT JOIN users u ON u.id = d.justification_by
             WHERE d.deleted_at IS NULL
               AND d.id <> :exclude_id
               AND d.person_id = :person_id
               AND d.justification_text IS NOT NULL
               AND TRIM(d.justification_text) <> ""
             ORDER BY d.justified_at DESC, d.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':exclude_id', $excludeDivergenceId, PDO::PARAM_INT);
        $stmt->bindValue(':person_id', $personId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
