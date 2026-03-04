<?php

namespace App\Services;

use App\Repositories\CatalogCategoryRepository;
use App\Support\Input;

/**
 * Service for catalog category validation and business logic.
 */
class CatalogCategoryLocalService
{
    private CatalogCategoryRepository $categoryRepo;

    public function __construct(CatalogCategoryRepository $categoryRepo)
    {
        $this->categoryRepo = $categoryRepo;
    }

    /**
     * Validate and prepare category data for save.
     *
     * @param array $input Raw form input
     * @param int|null $id Category ID if editing
     * @return array [validated data array, errors array]
     */
    public function validate(array $input, ?int $id = null): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        // Required fields
        if (empty($input['name'])) {
            $errors[] = 'Nome da categoria é obrigatório.';
        }

        // Validate slug uniqueness
        $slug = $input['slug'] ?? $this->generateSlug($input['name'] ?? '');
        if (!empty($slug)) {
            $existing = $this->categoryRepo->findBySlug($slug);
            if ($existing && (!$id || $existing['id'] != $id)) {
                $errors[] = "Slug '{$slug}' já está em uso.";
            }
        }

        // Validate parent_id (prevent self-reference and circular dependencies)
        $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;
        if ($parentId !== null) {
            if ($id && $parentId === $id) {
                $errors[] = 'Categoria não pode ser pai dela mesma.';
                $parentId = null;
            } else {
                $parent = $this->categoryRepo->find($parentId);
                if (!$parent) {
                    $errors[] = 'Categoria pai selecionada não encontrada.';
                    $parentId = null;
                }
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
            'parent_id' => $parentId,
            'position' => isset($input['position']) && $input['position'] !== '' ? (int)$input['position'] : 0,
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

    /**
     * Get top-level categories for dropdowns.
     *
     * @return array
     */
    public function getTopLevelCategories(): array
    {
        return $this->categoryRepo->list(['parent_id' => null, 'status' => 'ativa'], 'position', 'ASC');
    }

    /**
     * Get all categories (for parent selection dropdown).
     *
     * @param int|null $excludeId Category ID to exclude (prevents selecting itself as parent)
     * @return array
     */
    public function getAllCategoriesForParentSelect(?int $excludeId = null): array
    {
        $all = $this->categoryRepo->list(['status' => 'ativa'], 'name', 'ASC');
        
        if ($excludeId !== null) {
            $all = array_filter($all, function($cat) use ($excludeId) {
                return $cat['id'] != $excludeId;
            });
        }
        
        return array_values($all);
    }
}
