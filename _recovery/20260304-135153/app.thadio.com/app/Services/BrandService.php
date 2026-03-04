<?php

namespace App\Services;

use App\Support\Input;
use App\Support\CatalogLookup;

class BrandService
{
    /**
     * Validate brand data for catalog_brands schema.
     * 
     * @param array $input Raw input data
     * @return array{0: array, 1: array<int, string>} [validated data, errors]
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        // Required field: name
        if (!isset($input['name']) || $input['name'] === '') {
            $errors[] = 'Nome é obrigatório.';
        } elseif (mb_strlen($input['name']) < 2) {
            $errors[] = 'Nome deve ter pelo menos 2 caracteres.';
        }

        // Generate slug from name if not provided
        $slug = '';
        if (isset($input['slug']) && $input['slug'] !== '') {
            $slug = $this->generateSlug($input['slug']);
        } elseif (isset($input['name']) && $input['name'] !== '') {
            $slug = $this->generateSlug($input['name']);
        }

        // Validate status enum
        $status = $this->normalizeEnum(
            $input['status'] ?? null,
            array_keys(CatalogLookup::taxonomyStatuses()),
            'ativa',
            'Status',
            $errors
        );

        // Build validated data array
        $validated = [
            'name' => $input['name'] ?? '',
            'slug' => $slug,
            'description' => $input['description'] ?? '',
            'status' => $status,
        ];

        // Preserve ID if updating
        if (isset($input['id']) && $input['id'] !== '') {
            $validated['id'] = (int)$input['id'];
        }

        // Handle metadata (JSON)
        $metadata = [];
        if (isset($input['metadata']) && is_array($input['metadata'])) {
            $metadata = $input['metadata'];
        }
        $validated['metadata'] = !empty($metadata) ? json_encode($metadata) : null;

        return [$validated, $errors];
    }

    /**
     * Convert validated data to array for repository (alias for backward compatibility).
     */
    public function toArray(array $validated): array
    {
        return $validated;
    }

    /**
     * Generate URL-friendly slug from text.
     */
    private function generateSlug(string $text): string
    {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');
        
        // Replace accented characters
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        
        // Replace non-alphanumeric characters with hyphens
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        
        // Remove leading/trailing hyphens
        $text = trim($text, '-');
        
        return $text;
    }

    /**
     * Normalize enum value.
     */
    private function normalizeEnum(?string $value, array $allowed, string $default, string $label, array &$errors): string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $value = strtolower($value);
        if (!in_array($value, $allowed, true)) {
            $errors[] = "{$label} inválido. Valores aceitos: " . implode(', ', $allowed);
            return $default;
        }

        return $value;
    }
}
