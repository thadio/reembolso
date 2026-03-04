<?php

namespace App\Services;

use App\Repositories\CatalogProductRepository;
use App\Repositories\CatalogBrandRepository;
use App\Repositories\CatalogCategoryRepository;
use App\Support\Input;

/**
 * Service for catalog product validation and business logic.
 * Replaces external platform dependencies with internal catalog operations.
 */
class CatalogProductLocalService
{
    private CatalogProductRepository $productRepo;
    private CatalogBrandRepository $brandRepo;
    private CatalogCategoryRepository $categoryRepo;

    public function __construct(
        CatalogProductRepository $productRepo,
        CatalogBrandRepository $brandRepo,
        CatalogCategoryRepository $categoryRepo
    ) {
        $this->productRepo = $productRepo;
        $this->brandRepo = $brandRepo;
        $this->categoryRepo = $categoryRepo;
    }

    /**
     * Validate and prepare product data for save.
     *
     * @param array $input Raw form input
     * @param int|null $id Product ID if editing
     * @return array [validated data array, errors array]
     */
    public function validate(array $input, ?int $id = null): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        // Required fields
        $required = [
            'name' => 'Nome do produto',
            'price' => 'Preço',
        ];

        foreach ($required as $field => $label) {
            if (empty($input[$field])) {
                $errors[] = "{$label} é obrigatório.";
            }
        }

        // Validate numeric fields
        if (isset($input['price']) && !is_numeric($input['price'])) {
            $errors[] = 'Preço deve ser um valor numérico.';
        }

        if (isset($input['cost']) && $input['cost'] !== '' && !is_numeric($input['cost'])) {
            $errors[] = 'Custo deve ser um valor numérico.';
        }

        if (isset($input['weight']) && $input['weight'] !== '' && !is_numeric($input['weight'])) {
            $errors[] = 'Peso deve ser um valor numérico.';
        }

        // Validate SKU uniqueness
        if (!empty($input['sku'])) {
            $existing = $this->productRepo->findBySku($input['sku']);
            if ($existing && (!$id || $existing['id'] != $id)) {
                $errors[] = "SKU '{$input['sku']}' já está em uso.";
            }
        }

        // Validate foreign keys
        if (!empty($input['brand_id'])) {
            $brand = $this->brandRepo->find((int)$input['brand_id']);
            if (!$brand) {
                $errors[] = 'Marca selecionada não encontrada.';
            }
        }

        if (!empty($input['category_id'])) {
            $category = $this->categoryRepo->find((int)$input['category_id']);
            if (!$category) {
                $errors[] = 'Categoria selecionada não encontrada.';
            }
        }

        // Validate status (aceita legado e mapeia para modelo unificado)
        $statusRaw = strtolower((string) ($input['status'] ?? 'draft'));
        $statusMap = [
            'draft' => 'draft',
            'active' => 'disponivel',
            'disponivel' => 'disponivel',
            'reservado' => 'reservado',
            'esgotado' => 'esgotado',
            'archived' => 'archived',
            'baixado' => 'baixado',
        ];
        if (!isset($statusMap[$statusRaw])) {
            $errors[] = 'Status inválido.';
            $statusRaw = 'draft';
        }
        $status = $statusMap[$statusRaw];

        // Validate visibility
        $allowedVisibility = ['public', 'catalog', 'search', 'hidden'];
        $visibility = $input['visibility'] ?? 'public';
        if (!in_array($visibility, $allowedVisibility)) {
            $errors[] = 'Visibilidade inválida.';
            $visibility = 'public';
        }

        $quantity = isset($input['quantity']) && $input['quantity'] !== ''
            ? max(0, (int) $input['quantity'])
            : 1;
        if ($status === 'disponivel' && $quantity <= 0) {
            $status = 'esgotado';
        }
        if ($status === 'esgotado' && $quantity > 0) {
            $status = 'disponivel';
        }

        $source = strtolower(trim((string) ($input['source'] ?? 'compra')));
        if (!in_array($source, ['compra', 'consignacao', 'consignacao_quitada', 'doacao'], true)) {
            $errors[] = 'Origem inválida.';
            $source = 'compra';
        }

        // Build validated data array
        $data = [
            'name' => $input['name'] ?? '',
            'short_description' => $input['short_description'] ?? null,
            'description' => $input['description'] ?? null,
            'sku' => $input['sku'] ?? null,
            'slug' => $input['slug'] ?? $this->generateSlug($input['name'] ?? ''),
            'brand_id' => !empty($input['brand_id']) ? (int)$input['brand_id'] : null,
            'category_id' => !empty($input['category_id']) ? (int)$input['category_id'] : null,
            'price' => isset($input['price']) && $input['price'] !== '' ? (float)$input['price'] : null,
            'cost' => isset($input['cost']) && $input['cost'] !== '' ? (float)$input['cost'] : null,
            'suggested_price' => isset($input['suggested_price']) && $input['suggested_price'] !== '' ? (float)$input['suggested_price'] : null,
            'weight' => isset($input['weight']) && $input['weight'] !== '' ? (float)$input['weight'] : null,
            'status' => $status,
            'quantity' => $quantity,
            'source' => $source,
            'visibility' => $visibility,
        ];

        // Validate slug uniqueness after generation
        if (!empty($data['slug'])) {
            $sql = "SELECT sku FROM products WHERE slug = :slug" . ($id ? " AND sku != :id" : "");
            $stmt = $this->productRepo->getPdo()->prepare($sql);
            $params = [':slug' => $data['slug']];
            if ($id) {
                $params[':id'] = $id;
            }
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $errors[] = "Slug '{$data['slug']}' já está em uso.";
            }
        }

        // Calculate margin if cost and price exist
        if ($data['cost'] !== null && $data['price'] !== null && $data['price'] > 0) {
            $data['margin'] = ($data['price'] - $data['cost']) / $data['price'];
        } else {
            $data['margin'] = null;
        }

        // Auto-generate SKU if not provided
        if (empty($data['sku']) && empty($errors)) {
            $data['sku'] = $this->productRepo->nextSku();
        }

        // Store metadata (flexible fields)
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
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Get available brands for dropdowns.
     *
     * @return array
     */
    public function getActiveBrands(): array
    {
        return $this->brandRepo->list(['status' => 'ativa'], 'name', 'ASC');
    }

    /**
     * Get available categories for dropdowns.
     *
     * @return array
     */
    public function getActiveCategories(): array
    {
        return $this->categoryRepo->list(['status' => 'ativa'], 'name', 'ASC');
    }
}
