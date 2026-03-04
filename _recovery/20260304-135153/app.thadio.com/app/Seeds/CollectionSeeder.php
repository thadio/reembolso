<?php

namespace App\Seeds;

use App\Repositories\CollectionRepository;

class CollectionSeeder
{
    public static function seed(CollectionRepository $repository): void
    {
        $pdo = $repository->getPdo();
        if (!$pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS colecoes (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(200) NOT NULL,
          main_menu_image TEXT NULL,
          external_id VARCHAR(120) NULL,
          slug VARCHAR(200) NULL,
          page_url VARCHAR(255) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_colecoes_external_id (external_id),
          UNIQUE KEY uniq_colecoes_slug (slug),
          INDEX idx_colecoes_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM colecoes");
        $count = $stmt ? (int) $stmt->fetchColumn() : 0;
        if ($count > 0) {
            return;
        }

        $sql = "INSERT INTO colecoes (name, main_menu_image, external_id, slug, page_url)
                VALUES (:name, :main_menu_image, :external_id, :slug, :page_url)";
        $insert = $pdo->prepare($sql);

        foreach (self::defaultCollections() as $row) {
            $insert->execute([
                ':name' => $row['name'],
                ':main_menu_image' => $row['main_menu_image'],
                ':external_id' => $row['external_id'],
                ':slug' => $row['slug'],
                ':page_url' => $row['page_url'],
            ]);
        }
    }

    public static function defaultCollections(): array
    {
        return [
            [
                'name' => 'AA_EM_CADASTRAMENTO',
                'main_menu_image' => null,
                'external_id' => '59c78aaf-48d1-2d57-7303-0a6942f5e5ca',
                'slug' => 'aa_em_cadastramento',
                'page_url' => '/category/aa_em_cadastramento',
            ],
            [
                'name' => 'Acervo de Locação',
                'main_menu_image' => null,
                'external_id' => 'a0712278-da2e-5a4f-3630-403afc00fac1',
                'slug' => 'copa-do-mundo',
                'page_url' => '/category/copa-do-mundo',
            ],
            [
                'name' => 'All Products',
                'main_menu_image' => null,
                'external_id' => '00000000-00000000-000000000001',
                'slug' => 'all-products',
                'page_url' => '/category/all-products',
            ],
            [
                'name' => 'Autoral',
                'main_menu_image' => null,
                'external_id' => 'bfe21bb8-23b3-3741-d83d-3f077560ac04',
                'slug' => 'upcycling',
                'page_url' => '/category/upcycling',
            ],
            [
                'name' => 'Blazers, coletes & casacos',
                'main_menu_image' => null,
                'external_id' => '63fab3f3-a55e-bc4a-376d-522ef092d3de',
                'slug' => 'blazers-coletes-casacos',
                'page_url' => '/category/blazers-coletes-casacos',
            ],
            [
                'name' => 'Blusas & camisas',
                'main_menu_image' => null,
                'external_id' => '04614054-a964-16f4-83df-144838dfdf02',
                'slug' => 'blusas-camisas',
                'page_url' => '/category/blusas-camisas',
            ],
            [
                'name' => 'Calçados, bolsas & acessórios',
                'main_menu_image' => null,
                'external_id' => '0eeb21fb-e393-644a-7558-a3537fcc34ab',
                'slug' => 'calçados-bolsas-acessórios',
                'page_url' => '/category/calçados-bolsas-acessórios',
            ],
            [
                'name' => 'Conjuntos, vestidos & macacões',
                'main_menu_image' => null,
                'external_id' => '95641dd0-4e17-7b27-1f50-f8d9b7b67f0a',
                'slug' => 'conjuntos-vestidos-macacões',
                'page_url' => '/category/conjuntos-vestidos-macacões',
            ],
            [
                'name' => 'Festa',
                'main_menu_image' => null,
                'external_id' => '1bea56fd-bbd3-2d8c-c0a8-59490fbe6cd4',
                'slug' => 'festa',
                'page_url' => '/category/festa',
            ],
            [
                'name' => 'Fitness, praia & lingerie',
                'main_menu_image' => null,
                'external_id' => 'a73762b4-759b-488d-b7c6-5668d2f58d41',
                'slug' => 'fitness-praia-lingerie',
                'page_url' => '/category/fitness-praia-lingerie',
            ],
            [
                'name' => 'Inverno',
                'main_menu_image' => null,
                'external_id' => '886a843d-f650-52fc-a8d9-a9a4bc0d2d13',
                'slug' => 'inverno',
                'page_url' => '/category/inverno',
            ],
            [
                'name' => 'Joias',
                'main_menu_image' => null,
                'external_id' => 'ba0a5bbd-8e43-7e20-7d87-5d46f23c6719',
                'slug' => 'joias',
                'page_url' => '/category/joias',
            ],
            [
                'name' => 'Luxo',
                'main_menu_image' => null,
                'external_id' => '31283fb5-9cc8-e92f-cc70-284a2d84c99b',
                'slug' => 'luxo',
                'page_url' => '/category/luxo',
            ],
            [
                'name' => 'Novidades',
                'main_menu_image' => null,
                'external_id' => '495dbaa8-6d3e-b1d5-96f8-c3ae3dcd6c37',
                'slug' => 'lançamentos-da-semana',
                'page_url' => '/category/lançamentos-da-semana',
            ],
            [
                'name' => 'Shorts, calças & saias',
                'main_menu_image' => null,
                'external_id' => '34ab5ea5-8d89-78a9-2592-a3c3a19341f1',
                'slug' => 'shorts-calças-saias',
                'page_url' => '/category/shorts-calças-saias',
            ],
            [
                'name' => 'Vi no Insta',
                'main_menu_image' => null,
                'external_id' => '797459b7-0ff6-d000-7d03-a1564577992a',
                'slug' => 'feed-insta',
                'page_url' => '/category/feed-insta',
            ],
            [
                'name' => 'Vintage',
                'main_menu_image' => null,
                'external_id' => '1ccf393e-5bfd-f7f3-80e9-71ceca25af6',
                'slug' => 'vintage',
                'page_url' => '/category/vintage',
            ],
        ];
    }
}
