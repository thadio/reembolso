<?php

namespace App\Repositories;

use App\Models\Rule;
use App\Models\RuleVersion;
use PDO;

class RuleVersionRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            $this->ensureTable();
        }
    }

    /**
     * @return RuleVersion[]
     */
    public function allForRule(int $ruleId): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT * FROM regras_versoes WHERE regra_id = :regra_id ORDER BY created_at DESC");
        $stmt->execute([':regra_id' => $ruleId]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return [];
        }
        return array_map(fn (array $row) => RuleVersion::fromArray($row), $rows);
    }

    public function countForRule(int $ruleId): int
    {
        if (!$this->pdo) {
            return 0;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM regras_versoes WHERE regra_id = :regra_id");
        $stmt->execute([':regra_id' => $ruleId]);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?RuleVersion
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM regras_versoes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? RuleVersion::fromArray($row) : null;
    }

    public function saveVersion(Rule $rule, ?string $observations, ?array $user): void
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }
        if (!$rule->id) {
            throw new \RuntimeException('Regra precisa ter ID antes de gravar a versão.');
        }
        $version = new RuleVersion();
        $version->ruleId = $rule->id;
        $version->title = $rule->title;
        $version->content = $rule->content;
        $version->status = $rule->status;
        $version->observations = $observations;
        $version->createdById = isset($user['id']) ? (int) $user['id'] : null;
        $version->createdByName = $user['name'] ?? null;

        $stmt = $this->pdo->prepare(
            "INSERT INTO regras_versoes (
                regra_id,
                title,
                content,
                status,
                observacoes,
                created_by_id,
                created_by_name
            ) VALUES (
                :regra_id,
                :title,
                :content,
                :status,
                :observacoes,
                :created_by_id,
                :created_by_name
            )"
        );

        $stmt->execute($version->toDbParams());
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS regras_versoes (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          regra_id INT UNSIGNED NOT NULL,
          title VARCHAR(200) NOT NULL,
          content TEXT NOT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'ativo',
          observacoes TEXT DEFAULT NULL,
          created_by_id INT UNSIGNED DEFAULT NULL,
          created_by_name VARCHAR(200) DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (regra_id) REFERENCES regras(id) ON DELETE CASCADE,
          INDEX idx_regras_versoes_regra_id (regra_id),
          INDEX idx_regras_versoes_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $this->pdo->exec($sql);
    }
}
