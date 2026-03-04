<?php

namespace App\Services;

use App\Repositories\CatalogBrandRepository;
use App\Support\Input;

/**
 * Service for catalog brand validation and business logic.
 */
class CatalogBrandLocalService
{
    private CatalogBrandRepository $brandRepo;

    public function __construct(CatalogBrandRepository $brandRepo)
    {
        $this->brandRepo = $brandRepo;
    }

    /**
     * Validate and prepare brand data for save.
     *
     * @param array $input Raw form input
     * @param int|null $id Brand ID if editing
     * @return array [validated data array, errors array]
     */
    public function validate(array $input, ?int $id = null): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        // Required fields
        if (empty($input['name'])) {
            $errors[] = 'Nome da marca é obrigatório.';
        }

        // Validate slug uniqueness
        $slug = $input['slug'] ?? $this->generateSlug($input['name'] ?? '');
        if (!empty($slug)) {
            $existing = $this->brandRepo->findBySlug($slug);
            if ($existing && (!$id || $existing['id'] != $id)) {
                $errors[] = "Slug '{$slug}' já está em uso.";
            }
        }

        // Validate status
        $allowedStatuses = ['ativa', 'inativa'];
        $status = $input['status'] ?? 'ativa';
        if (!in_array($status, $allowedStatuses)) {
            $errors[] = 'Status inválido.';
            $status = 'ativa';
        }

        // Build validated data array
        $data = [
            'name' => $input['name'] ?? '',
            'slug' => $slug,
            'description' => $input['description'] ?? null,
            'status' => $status,
        ];

        // Store metadata
        $metadata = [];
        if (isset($input['metadata']) && is_array($input['metadata'])) {
            $metadata = $input['metadata'];
        }
        $data['metadata'] = $metadata;

        if ($id) {
            $data['id'] = $id;
        }

        return [$data, $errors];
    }

    /**
     * Generate URL-friendly slug from name.
     *
     * @param string $name
     * @return string
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        // Remove accents
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
