<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class GlobalSearchRepository
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function searchPeople(string $query, int $limit = 20): array
    {
        $search = '%' . $query . '%';
        $digits = preg_replace('/\D+/', '', $query);
        $whereCpfDigits = '';

        $stmt = null;
        if (is_string($digits) && $digits !== '') {
            $whereCpfDigits = ' OR REPLACE(REPLACE(REPLACE(REPLACE(p.cpf, ".", ""), "-", ""), "/", ""), " ", "") LIKE :q_cpf_digits';
        }

        $sql = 'SELECT
                    p.id,
                    p.name,
                    p.cpf,
                    p.status,
                    p.sei_process_number,
                    p.tags,
                    p.updated_at,
                    o.id AS organ_id,
                    o.name AS organ_name
                FROM people p
                INNER JOIN organs o ON o.id = p.organ_id
                WHERE p.deleted_at IS NULL
                  AND (
                      p.name LIKE :q_name
                      OR p.cpf LIKE :q_cpf
                      OR p.sei_process_number LIKE :q_sei
                      OR p.tags LIKE :q_tags
                      OR o.name LIKE :q_organ'
                . $whereCpfDigits .
                '
                  )
                ORDER BY p.updated_at DESC, p.id DESC
                LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':q_name', $search);
        $stmt->bindValue(':q_cpf', $search);
        $stmt->bindValue(':q_sei', $search);
        $stmt->bindValue(':q_tags', $search);
        $stmt->bindValue(':q_organ', $search);
        if ($whereCpfDigits !== '') {
            $stmt->bindValue(':q_cpf_digits', '%' . $digits . '%');
        }
        $stmt->bindValue(':limit', max(1, min(120, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function searchOrgans(string $query, int $limit = 20): array
    {
        $search = '%' . $query . '%';

        $stmt = $this->db->prepare(
            'SELECT
                o.id,
                o.name,
                o.acronym,
                o.cnpj,
                o.city,
                o.state,
                o.updated_at
             FROM organs o
             WHERE o.deleted_at IS NULL
               AND (
                    o.name LIKE :q_name
                    OR o.acronym LIKE :q_acronym
                    OR o.cnpj LIKE :q_cnpj
                    OR o.city LIKE :q_city
                    OR o.state LIKE :q_state
               )
             ORDER BY o.updated_at DESC, o.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':q_name', $search);
        $stmt->bindValue(':q_acronym', $search);
        $stmt->bindValue(':q_cnpj', $search);
        $stmt->bindValue(':q_city', $search);
        $stmt->bindValue(':q_state', $search);
        $stmt->bindValue(':limit', max(1, min(120, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function searchProcessMetadata(string $query, int $limit = 20): array
    {
        $search = '%' . $query . '%';

        $stmt = $this->db->prepare(
            'SELECT
                pm.id,
                pm.person_id,
                pm.office_number,
                pm.office_protocol,
                pm.dou_edition,
                pm.dou_published_at,
                pm.dou_link,
                pm.mte_entry_date,
                pm.updated_at,
                p.name AS person_name,
                p.sei_process_number,
                o.id AS organ_id,
                o.name AS organ_name
             FROM process_metadata pm
             INNER JOIN people p ON p.id = pm.person_id AND p.deleted_at IS NULL
             INNER JOIN organs o ON o.id = p.organ_id
             WHERE pm.deleted_at IS NULL
               AND (
                    p.name LIKE :q_person
                    OR p.sei_process_number LIKE :q_sei
                    OR o.name LIKE :q_organ
                    OR pm.office_number LIKE :q_office
                    OR pm.office_protocol LIKE :q_protocol
                    OR pm.dou_edition LIKE :q_dou
                    OR pm.dou_link LIKE :q_dou_link
               )
             ORDER BY pm.updated_at DESC, pm.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':q_person', $search);
        $stmt->bindValue(':q_sei', $search);
        $stmt->bindValue(':q_organ', $search);
        $stmt->bindValue(':q_office', $search);
        $stmt->bindValue(':q_protocol', $search);
        $stmt->bindValue(':q_dou', $search);
        $stmt->bindValue(':q_dou_link', $search);
        $stmt->bindValue(':limit', max(1, min(120, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function searchDocuments(string $query, int $limit = 20, bool $includeSensitive = false): array
    {
        $search = '%' . $query . '%';
        $visibilityFilter = $includeSensitive
            ? ''
            : " AND COALESCE(NULLIF(d.sensitivity_level, ''), 'public') = 'public'";

        $stmt = $this->db->prepare(
            'SELECT
                d.id,
                d.person_id,
                d.title,
                d.reference_sei,
                d.tags,
                d.sensitivity_level,
                d.original_name,
                d.storage_path,
                d.created_at,
                dt.name AS document_type_name,
                p.name AS person_name,
                p.sei_process_number,
                o.id AS organ_id,
                o.name AS organ_name
             FROM documents d
             INNER JOIN document_types dt ON dt.id = d.document_type_id
             INNER JOIN people p ON p.id = d.person_id AND p.deleted_at IS NULL
             INNER JOIN organs o ON o.id = p.organ_id
             WHERE d.deleted_at IS NULL' . $visibilityFilter . '
               AND (
                    d.title LIKE :q_title
                    OR d.reference_sei LIKE :q_reference
                    OR d.tags LIKE :q_tags
                    OR d.original_name LIKE :q_file
                    OR dt.name LIKE :q_type
                    OR p.name LIKE :q_person
                    OR p.sei_process_number LIKE :q_sei
                    OR o.name LIKE :q_organ
               )
             ORDER BY d.created_at DESC, d.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':q_title', $search);
        $stmt->bindValue(':q_reference', $search);
        $stmt->bindValue(':q_tags', $search);
        $stmt->bindValue(':q_file', $search);
        $stmt->bindValue(':q_type', $search);
        $stmt->bindValue(':q_person', $search);
        $stmt->bindValue(':q_sei', $search);
        $stmt->bindValue(':q_organ', $search);
        $stmt->bindValue(':limit', max(1, min(120, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
