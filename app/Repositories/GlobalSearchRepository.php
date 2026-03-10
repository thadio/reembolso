<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class GlobalSearchRepository
{
    private ?bool $hasPeopleCpfDigitsColumn = null;

    /** @var array<string, bool> */
    private array $fulltextIndexAvailability = [];

    public function __construct(private PDO $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function searchPeople(string $query, int $limit = 20): array
    {
        $search = '%' . $query . '%';
        $boolean = $this->booleanModeQuery($query);
        $digits = preg_replace('/\D+/', '', $query);
        $digits = is_string($digits) ? $digits : '';

        $usePeopleFulltext = $boolean !== null && $this->hasFulltextIndex('people', 'ft_people_search');
        $useOrgansFulltext = $boolean !== null && $this->hasFulltextIndex('organs', 'ft_organs_search');

        $whereParts = [];
        if ($usePeopleFulltext) {
            $whereParts[] = 'MATCH(p.name, p.sei_process_number, p.tags, p.notes) AGAINST (:q_ft_people_filter IN BOOLEAN MODE)';
        }
        if ($useOrgansFulltext) {
            $whereParts[] = 'MATCH(o.name, o.acronym, o.city, o.state, o.notes) AGAINST (:q_ft_organ_filter IN BOOLEAN MODE)';
        }

        $whereParts[] = 'p.name LIKE :q_name';
        $whereParts[] = 'p.cpf LIKE :q_cpf';
        $whereParts[] = 'p.sei_process_number LIKE :q_sei';
        $whereParts[] = 'p.tags LIKE :q_tags';
        $whereParts[] = 'o.name LIKE :q_organ';

        $useCpfDigits = $digits !== '' && $this->hasPeopleCpfDigitsColumn();
        if ($useCpfDigits) {
            $whereParts[] = 'p.cpf_digits = :q_cpf_digits_exact';
            $whereParts[] = 'p.cpf_digits LIKE :q_cpf_digits_prefix';
        }

        $relevanceExpression = '0';
        if ($usePeopleFulltext || $useOrgansFulltext) {
            $relevanceParts = [];
            if ($usePeopleFulltext) {
                $relevanceParts[] = 'IFNULL(MATCH(p.name, p.sei_process_number, p.tags, p.notes) AGAINST (:q_ft_people_score IN BOOLEAN MODE), 0)';
            }
            if ($useOrgansFulltext) {
                $relevanceParts[] = 'IFNULL(MATCH(o.name, o.acronym, o.city, o.state, o.notes) AGAINST (:q_ft_organ_score IN BOOLEAN MODE), 0)';
            }
            $relevanceExpression = implode(' + ', $relevanceParts);
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
                    o.name AS organ_name,
                    ' . $relevanceExpression . ' AS relevance
                FROM people p
                INNER JOIN organs o ON o.id = p.organ_id
                WHERE p.deleted_at IS NULL
                  AND (
                    ' . implode("\n                    OR ", $whereParts) . '
                  )
                ORDER BY '
                . (($usePeopleFulltext || $useOrgansFulltext)
                    ? 'relevance DESC, p.updated_at DESC, p.id DESC'
                    : 'p.updated_at DESC, p.id DESC') . '
                LIMIT :limit';

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':q_name', $search);
        $stmt->bindValue(':q_cpf', $search);
        $stmt->bindValue(':q_sei', $search);
        $stmt->bindValue(':q_tags', $search);
        $stmt->bindValue(':q_organ', $search);

        if ($usePeopleFulltext && $boolean !== null) {
            $stmt->bindValue(':q_ft_people_filter', $boolean);
            $stmt->bindValue(':q_ft_people_score', $boolean);
        }

        if ($useOrgansFulltext && $boolean !== null) {
            $stmt->bindValue(':q_ft_organ_filter', $boolean);
            $stmt->bindValue(':q_ft_organ_score', $boolean);
        }

        if ($useCpfDigits) {
            $stmt->bindValue(':q_cpf_digits_exact', $digits);
            $stmt->bindValue(':q_cpf_digits_prefix', $digits . '%');
        }

        $stmt->bindValue(':limit', max(1, min(120, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function searchOrgans(string $query, int $limit = 20): array
    {
        $search = '%' . $query . '%';
        $boolean = $this->booleanModeQuery($query);
        $useFulltext = $boolean !== null && $this->hasFulltextIndex('organs', 'ft_organs_search');

        $whereParts = [];
        if ($useFulltext) {
            $whereParts[] = 'MATCH(o.name, o.acronym, o.city, o.state, o.notes) AGAINST (:q_ft_filter IN BOOLEAN MODE)';
        }

        $whereParts[] = 'o.name LIKE :q_name';
        $whereParts[] = 'o.acronym LIKE :q_acronym';
        $whereParts[] = 'o.cnpj LIKE :q_cnpj';
        $whereParts[] = 'o.city LIKE :q_city';
        $whereParts[] = 'o.state LIKE :q_state';

        $sql = 'SELECT
                o.id,
                o.name,
                o.acronym,
                o.cnpj,
                o.city,
                o.state,
                o.updated_at,
                ' . ($useFulltext
                    ? 'IFNULL(MATCH(o.name, o.acronym, o.city, o.state, o.notes) AGAINST (:q_ft_score IN BOOLEAN MODE), 0)'
                    : '0') . ' AS relevance
             FROM organs o
             WHERE o.deleted_at IS NULL
               AND (
                    ' . implode("\n                    OR ", $whereParts) . '
               )
             ORDER BY '
            . ($useFulltext ? 'relevance DESC, ' : '')
            . 'o.updated_at DESC, o.id DESC
             LIMIT :limit';

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':q_name', $search);
        $stmt->bindValue(':q_acronym', $search);
        $stmt->bindValue(':q_cnpj', $search);
        $stmt->bindValue(':q_city', $search);
        $stmt->bindValue(':q_state', $search);

        if ($useFulltext && $boolean !== null) {
            $stmt->bindValue(':q_ft_filter', $boolean);
            $stmt->bindValue(':q_ft_score', $boolean);
        }

        $stmt->bindValue(':limit', max(1, min(120, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function searchProcessMetadata(string $query, int $limit = 20): array
    {
        $search = '%' . $query . '%';
        $boolean = $this->booleanModeQuery($query);
        $useFulltext = $boolean !== null && $this->hasFulltextIndex('process_metadata', 'ft_process_metadata_search');

        $whereParts = [];
        if ($useFulltext) {
            $whereParts[] = 'MATCH(pm.office_number, pm.office_protocol, pm.dou_edition, pm.dou_link, pm.notes) AGAINST (:q_ft_filter IN BOOLEAN MODE)';
        }

        $whereParts[] = 'p.name LIKE :q_person';
        $whereParts[] = 'p.sei_process_number LIKE :q_sei';
        $whereParts[] = 'o.name LIKE :q_organ';
        $whereParts[] = 'pm.office_number LIKE :q_office';
        $whereParts[] = 'pm.office_protocol LIKE :q_protocol';
        $whereParts[] = 'pm.dou_edition LIKE :q_dou';
        $whereParts[] = 'pm.dou_link LIKE :q_dou_link';

        $sql = 'SELECT
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
                o.name AS organ_name,
                ' . ($useFulltext
                    ? 'IFNULL(MATCH(pm.office_number, pm.office_protocol, pm.dou_edition, pm.dou_link, pm.notes) AGAINST (:q_ft_score IN BOOLEAN MODE), 0)'
                    : '0') . ' AS relevance
             FROM process_metadata pm
             INNER JOIN people p ON p.id = pm.person_id AND p.deleted_at IS NULL
             INNER JOIN organs o ON o.id = p.organ_id
             WHERE pm.deleted_at IS NULL
               AND (
                    ' . implode("\n                    OR ", $whereParts) . '
               )
             ORDER BY '
            . ($useFulltext ? 'relevance DESC, ' : '')
            . 'pm.updated_at DESC, pm.id DESC
             LIMIT :limit';

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':q_person', $search);
        $stmt->bindValue(':q_sei', $search);
        $stmt->bindValue(':q_organ', $search);
        $stmt->bindValue(':q_office', $search);
        $stmt->bindValue(':q_protocol', $search);
        $stmt->bindValue(':q_dou', $search);
        $stmt->bindValue(':q_dou_link', $search);

        if ($useFulltext && $boolean !== null) {
            $stmt->bindValue(':q_ft_filter', $boolean);
            $stmt->bindValue(':q_ft_score', $boolean);
        }

        $stmt->bindValue(':limit', max(1, min(120, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function searchDocuments(string $query, int $limit = 20, bool $includeSensitive = false): array
    {
        $search = '%' . $query . '%';
        $boolean = $this->booleanModeQuery($query);
        $useFulltext = $boolean !== null && $this->hasFulltextIndex('documents', 'ft_documents_search');

        $visibilityFilter = $includeSensitive
            ? ''
            : " AND COALESCE(NULLIF(d.sensitivity_level, ''), 'public') = 'public'";

        $whereParts = [];
        if ($useFulltext) {
            $whereParts[] = 'MATCH(d.title, d.reference_sei, d.tags, d.original_name, d.notes) AGAINST (:q_ft_filter IN BOOLEAN MODE)';
        }

        $whereParts[] = 'd.title LIKE :q_title';
        $whereParts[] = 'd.reference_sei LIKE :q_reference';
        $whereParts[] = 'd.tags LIKE :q_tags';
        $whereParts[] = 'd.original_name LIKE :q_file';
        $whereParts[] = 'dt.name LIKE :q_type';
        $whereParts[] = 'p.name LIKE :q_person';
        $whereParts[] = 'p.sei_process_number LIKE :q_sei';
        $whereParts[] = 'o.name LIKE :q_organ';

        $sql = 'SELECT
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
                o.name AS organ_name,
                ' . ($useFulltext
                    ? 'IFNULL(MATCH(d.title, d.reference_sei, d.tags, d.original_name, d.notes) AGAINST (:q_ft_score IN BOOLEAN MODE), 0)'
                    : '0') . ' AS relevance
             FROM documents d
             INNER JOIN document_types dt ON dt.id = d.document_type_id
             INNER JOIN people p ON p.id = d.person_id AND p.deleted_at IS NULL
             INNER JOIN organs o ON o.id = p.organ_id
             WHERE d.deleted_at IS NULL' . $visibilityFilter . '
               AND (
                    ' . implode("\n                    OR ", $whereParts) . '
               )
             ORDER BY '
            . ($useFulltext ? 'relevance DESC, ' : '')
            . 'd.created_at DESC, d.id DESC
             LIMIT :limit';

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':q_title', $search);
        $stmt->bindValue(':q_reference', $search);
        $stmt->bindValue(':q_tags', $search);
        $stmt->bindValue(':q_file', $search);
        $stmt->bindValue(':q_type', $search);
        $stmt->bindValue(':q_person', $search);
        $stmt->bindValue(':q_sei', $search);
        $stmt->bindValue(':q_organ', $search);

        if ($useFulltext && $boolean !== null) {
            $stmt->bindValue(':q_ft_filter', $boolean);
            $stmt->bindValue(':q_ft_score', $boolean);
        }

        $stmt->bindValue(':limit', max(1, min(120, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function hasPeopleCpfDigitsColumn(): bool
    {
        if ($this->hasPeopleCpfDigitsColumn !== null) {
            return $this->hasPeopleCpfDigitsColumn;
        }

        $stmt = $this->db->query(
            "SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'people'
               AND COLUMN_NAME = 'cpf_digits'"
        );

        $this->hasPeopleCpfDigitsColumn = ((int) $stmt->fetchColumn()) > 0;

        return $this->hasPeopleCpfDigitsColumn;
    }

    private function hasFulltextIndex(string $table, string $indexName): bool
    {
        $cacheKey = $table . ':' . $indexName;
        if (array_key_exists($cacheKey, $this->fulltextIndexAvailability)) {
            return $this->fulltextIndexAvailability[$cacheKey];
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND INDEX_NAME = :index_name'
        );
        $stmt->execute([
            'table_name' => $table,
            'index_name' => $indexName,
        ]);

        $this->fulltextIndexAvailability[$cacheKey] = ((int) $stmt->fetchColumn()) > 0;

        return $this->fulltextIndexAvailability[$cacheKey];
    }

    private function booleanModeQuery(string $query): ?string
    {
        $normalized = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($query)));
        if ($normalized === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $normalized);
        if (!is_array($parts)) {
            return null;
        }

        $tokens = [];
        foreach ($parts as $part) {
            if ($part === '' || mb_strlen($part) < 2) {
                continue;
            }

            $tokens[] = '+' . mb_substr($part, 0, 40) . '*';
            if (count($tokens) >= 8) {
                break;
            }
        }

        if ($tokens === []) {
            return null;
        }

        return implode(' ', $tokens);
    }
}
