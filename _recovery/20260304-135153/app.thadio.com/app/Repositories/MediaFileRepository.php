<?php

namespace App\Repositories;

use PDO;

class MediaFileRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \shouldRunSchemaMigrations()) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                error_log('Falha ao preparar tabela media_files: ' . $e->getMessage());
            }
        }
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS media_files (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          file_name VARCHAR(200) NOT NULL,
          file_path VARCHAR(255) NOT NULL,
          mime_type VARCHAR(80) NULL,
          file_size INT UNSIGNED NULL,
          width INT UNSIGNED NULL,
          height INT UNSIGNED NULL,
          hash_sha1 VARCHAR(40) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_media_files_mime (mime_type),
          INDEX idx_media_files_hash (hash_sha1),
          INDEX idx_media_files_name (file_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
}
