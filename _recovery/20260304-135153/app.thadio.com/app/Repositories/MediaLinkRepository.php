<?php

namespace App\Repositories;

use PDO;

class MediaLinkRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela media_links: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS media_links (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          media_file_id BIGINT UNSIGNED NOT NULL,
          entity_type VARCHAR(60) NOT NULL,
          entity_id BIGINT UNSIGNED NOT NULL,
          role VARCHAR(40) NULL,
          sort_order INT UNSIGNED NOT NULL DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_media_links_media (media_file_id),
          INDEX idx_media_links_entity (entity_type, entity_id),
          INDEX idx_media_links_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
