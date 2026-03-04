<?php

namespace App\Services;

use App\Models\Product;
use App\Support\Input;
use App\Repositories\ProductRepository;

/**
 * ProductService - Validações do Modelo Unificado
 *
 * Responsável por validar e normalizar dados de produtos segundo o novo modelo:
 * - SKU numérico como identificador único
 * - quantity gerencia disponibilidade (INT >= 0)
 * - status com transições automáticas (draft → disponivel → esgotado)
 * - source: apenas 3 valores (doacao, compra, consignacao)
 * - condition_grade: apenas 3 valores (novo, usado, usado_com_detalhes)
 */
class ProductService
{
    private ?ProductRepository $repository = null;

    public function __construct(?ProductRepository $repository = null)
    {
        $this->repository = $repository;
    }

    /**
     * Validate product data for unified model.
     * 
     * @param array $input Raw input data
     * @param int|null $sku SKU when updating (to merge with existing data)
     * @return array{0: Product, 1: array<int, string>} [validated model, errors]
     */
    public function validate(array $input, ?int $sku = null): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        // If updating, merge with existing product data
        $existingProduct = null;
        if ($sku !== null && $this->repository !== null) {
            $existingProduct = $this->repository->find($sku);
            if ($existingProduct) {
                // Preserve existing values for fields not provided in input
                $input = array_merge($existingProduct->toArray(), $input);
            }
        }

        // ===== CAMPO SKU (opcional em criação, numérico quando informado) =====
        $skuValue = null;
        if (isset($input['sku']) && $input['sku'] !== '') {
            if (!is_numeric($input['sku'])) {
                $errors[] = 'SKU deve ser numérico.';
            } else {
                $skuValue = (int) $input['sku'];
                if ($skuValue <= 0) {
                    $errors[] = 'SKU inválido.';
                }
            }
        } elseif ($sku !== null) {
            // Update: preserva SKU existente
            $skuValue = $sku;
        }

        // ===== CAMPOS OBRIGATÓRIOS =====
        if (!isset($input['name']) || $input['name'] === '') {
            $errors[] = 'Nome é obrigatório.';
        }
        if (!isset($input['price']) || $input['price'] === '') {
            $errors[] = 'Preço é obrigatório.';
        }

        // ===== CAMPO QUANTITY (obrigatório, INT >= 0) =====
        $quantity = 0;
        if (isset($input['quantity'])) {
            if (!is_numeric($input['quantity'])) {
                $errors[] = 'Quantity deve ser numérico.';
            } else {
                $quantity = (int)$input['quantity'];
                if ($quantity < 0) {
                    $errors[] = 'Quantity não pode ser negativo.';
                }
            }
        } elseif ($existingProduct === null) {
            // Criação: default 1 (produto único)
            $quantity = 1;
        }

        // ===== CAMPO STATUS (ENUM com 6 valores) =====
        $allowedStatuses = ['draft', 'disponivel', 'reservado', 'esgotado', 'baixado', 'archived'];
        $statusInputRaw = strtolower(trim((string) ($input['status'] ?? ($input['post_status'] ?? ''))));
        $statusLegacyMap = [
            'publish' => 'disponivel',
            'active' => 'disponivel',
            'instock' => 'disponivel',
            'pending' => 'draft',
            'private' => 'archived',
            'sold' => 'esgotado',
            'vendido' => 'esgotado',
            'outofstock' => 'esgotado',
        ];
        if (isset($statusLegacyMap[$statusInputRaw])) {
            $statusInputRaw = $statusLegacyMap[$statusInputRaw];
        }
        $status = $this->normalizeEnum(
            $statusInputRaw !== '' ? $statusInputRaw : null,
            $allowedStatuses,
            'draft',
            'Status',
            $errors
        );

        // REGRA DE NEGÓCIO: Status vs Quantity
        if ($status === 'disponivel' && $quantity <= 0) {
            $errors[] = "Produto 'disponivel' deve ter quantity > 0.";
        }
        if ($status === 'esgotado' && $quantity > 0) {
            $errors[] = "Produto 'esgotado' deve ter quantity = 0.";
        }

        // ===== CAMPO SOURCE (ENUM com 3 valores) =====
        $allowedSources = ['doacao', 'compra', 'consignacao', 'consignacao_quitada'];
        $sourceInput = strtolower(trim((string) ($input['source'] ?? '')));
        if ($sourceInput === 'garimpo') {
            $sourceInput = 'compra';
        }
        $source = $this->normalizeEnum(
            $sourceInput !== '' ? $sourceInput : null,
            $allowedSources,
            'compra',
            'Source',
            $errors
        );

        $supplierId = isset($input['supplier_pessoa_id']) && $input['supplier_pessoa_id'] !== ''
            ? (int)$input['supplier_pessoa_id']
            : (isset($input['supplier']) && $input['supplier'] !== '' ? (int)$input['supplier'] : null);

        // REGRA DE NEGÓCIO: Consignação requer supplier
        if ($source === 'consignacao') {
            if ($supplierId === null || $supplierId <= 0) {
                $errors[] = "Produto de consignação requer fornecedor (supplier_pessoa_id).";
            }
        }

        $consignmentRaw = $input['percentual_consignacao']
            ?? ($input['percentualConsignacao'] ?? ($input['consignment_percent'] ?? null));
        $consignmentPercent = null;
        if ($source === 'consignacao') {
            if ($consignmentRaw === null || $consignmentRaw === '') {
                $consignmentPercent = 40.0;
            } else {
                $consignmentPercent = $this->parseDecimal($consignmentRaw);
            }
            if ($consignmentPercent === null || $consignmentPercent < 0 || $consignmentPercent > 100) {
                $errors[] = 'Percentual de consignação inválido (0 a 100).';
            }
        } elseif ($consignmentRaw !== null && $consignmentRaw !== '') {
            $consignmentPercent = $this->parseDecimal($consignmentRaw);
            if ($consignmentPercent === null) {
                $errors[] = 'Percentual de consignação inválido (0 a 100).';
            } elseif ($consignmentPercent < 0 || $consignmentPercent > 100) {
                $errors[] = 'Percentual de consignação inválido (0 a 100).';
            }
        }

        // ===== CAMPO CONDITION_GRADE (ENUM com 3 valores) =====
        $allowedGrades = ['novo', 'usado', 'usado_com_detalhes'];
        $conditionInput = $this->normalizeConditionGradeInput($input['condition_grade'] ?? null);
        $conditionGrade = $this->normalizeEnum(
            $conditionInput,
            $allowedGrades,
            'usado',
            'Condition Grade',
            $errors
        );

        // ===== CAMPOS NUMÉRICOS =====
        $price = isset($input['price']) ? $this->parseDecimal($input['price']) : null;
        $cost = isset($input['cost']) ? $this->parseDecimal($input['cost']) : null;
        $suggestedPrice = isset($input['suggested_price']) ? $this->parseDecimal($input['suggested_price']) : null;
        $weight = isset($input['weight']) ? $this->parseDecimal($input['weight']) : null;

        if ($price === null || $price < 0) {
            $errors[] = 'Preço inválido.';
        }
        if (isset($input['weight']) && $input['weight'] !== '' && $weight === null) {
            $errors[] = 'Peso inválido.';
        }
        if ($weight !== null && ($weight < 0 || $weight > 99999.999)) {
            $errors[] = 'Peso fora do limite permitido (0 a 99.999,999 kg).';
        }

        // ===== CAMPOS ID (FKs) =====
        $collectionId = isset($input['collection_id']) && $input['collection_id'] !== ''
            ? (int)$input['collection_id']
            : null;
        $batchId = isset($input['batch_id']) && $input['batch_id'] !== ''
            ? (int)$input['batch_id']
            : null;
        $lastOrderId = isset($input['last_order_id']) && $input['last_order_id'] !== ''
            ? (int)$input['last_order_id']
            : null;

        // ===== CALCULAR MARGIN =====
        $margin = null;
        if ($price !== null && $cost !== null && $cost > 0) {
            $margin = ($price - $cost) / $cost;
        }

        // ===== BUILD VALIDATED DATA =====
        $validated = [
            'name' => $input['name'] ?? '',
            'short_description' => $input['short_description'] ?? '',
            'description' => $input['description'] ?? '',
            'price' => $price,
            'cost' => $cost,
            'suggested_price' => $suggestedPrice,
            'profit_margin' => $margin,
            'quantity' => $quantity,
            'status' => $status,
            'source' => $source,
            'percentual_consignacao' => $consignmentPercent,
            'condition_grade' => $conditionGrade,
            'visibility' => $this->normalizeVisibility((string) ($input['visibility'] ?? ($input['catalogVisibility'] ?? 'public'))),
            'collection_id' => $collectionId,
            'supplier_pessoa_id' => $supplierId,
            'batch_id' => $batchId,
            'last_order_id' => $lastOrderId,
            'weight' => $weight,
        ];
        if ($skuValue !== null && $skuValue > 0) {
            $validated['sku'] = $skuValue;
        }

        // ===== CAMPOS OPCIONAIS =====
        if (isset($input['slug']) && $input['slug'] !== '') {
            $validated['slug'] = $this->generateSlug($input['slug']);
        } elseif (isset($input['name']) && $input['name'] !== '') {
            $validated['slug'] = $this->generateSlug($input['name']);
        }

        if (isset($input['barcode']) && $input['barcode'] !== '') {
            $validated['barcode'] = $input['barcode'];
        }

        if (isset($input['images']) && $input['images'] !== '') {
            $validated['images'] = $input['images'];
        }

        if (isset($input['size']) && $input['size'] !== '') {
            $validated['size'] = $input['size'];
        }

        if (isset($input['color']) && $input['color'] !== '') {
            $validated['color'] = $input['color'];
        }

        if (isset($input['last_sold_at']) && $input['last_sold_at'] !== '') {
            $validated['last_sold_at'] = $input['last_sold_at'];
        }

        // ===== METADATA (JSON) =====
        $metadata = [];
        if (isset($input['metadata'])) {
            if (is_string($input['metadata'])) {
                $decoded = json_decode($input['metadata'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $metadata = $decoded;
                }
            } elseif (is_array($input['metadata'])) {
                $metadata = $input['metadata'];
            }
        }
        $validated['metadata'] = $metadata;

        $product = Product::fromArray($validated);
        if ($skuValue !== null && $skuValue > 0) {
            $product->sku = $skuValue;
        }

        return [$product, $errors];
    }

    /**
     * Convert validated data to array for repository (alias for backward compatibility).
     */
    public function toArray($validated): array
    {
        if ($validated instanceof Product) {
            return $validated->toArray();
        }

        if (!is_array($validated)) {
            return [];
        }

        return $validated;
    }

    /**
     * Validate status transition rules.
     * 
     * Allowed transitions:
     * - draft → disponivel (quando quantity > 0)
     * - disponivel → reservado (reserva temporária)
     * - reservado → disponivel (cancelamento)
     * - disponivel → esgotado (quando quantity = 0)
     * - esgotado → disponivel (quando quantity > 0)
     * - qualquer → baixado (baixa definitiva)
     * - qualquer → archived (arquivamento)
     * 
     * @param string $currentStatus
     * @param string $newStatus
     * @param int $quantity
     * @return array{0: bool, 1: string|null} [isValid, errorMessage]
     */
    public function validateStatusTransition(string $currentStatus, string $newStatus, int $quantity): array
    {
        // Mesma status: sempre válido
        if ($currentStatus === $newStatus) {
            return [true, null];
        }

        // Transições sempre permitidas
        $alwaysAllowed = ['baixado', 'archived'];
        if (in_array($newStatus, $alwaysAllowed, true)) {
            return [true, null];
        }

        // Validar transições específicas
        $transitions = [
            'draft' => ['disponivel'],
            'disponivel' => ['reservado', 'esgotado'],
            'reservado' => ['disponivel', 'esgotado'],
            'esgotado' => ['disponivel'],
            'baixado' => [], // terminal (não permite voltar)
            'archived' => [], // terminal (não permite voltar)
        ];

        $allowedNext = $transitions[$currentStatus] ?? [];
        if (!in_array($newStatus, $allowedNext, true)) {
            return [false, "Transição {$currentStatus} → {$newStatus} não permitida."];
        }

        // Validar consistência com quantity
        if ($newStatus === 'disponivel' && $quantity <= 0) {
            return [false, "Não é possível marcar como 'disponivel' com quantity = 0."];
        }
        if ($newStatus === 'esgotado' && $quantity > 0) {
            return [false, "Não é possível marcar como 'esgotado' com quantity > 0."];
        }

        return [true, null];
    }

    /**
     * Normalize ENUM value with validation.
     */
    private function normalizeEnum(?string $value, array $allowed, string $default, string $label, array &$errors): string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        // Case-insensitive comparison for user input
        $valueLower = strtolower($value);
        foreach ($allowed as $option) {
            if (strtolower($option) === $valueLower) {
                return $option; // Return with correct case
            }
        }

        $errors[] = "{$label} inválido. Valores aceitos: " . implode(', ', $allowed);
        return $default;
    }

    /**
     * Parse decimal value (supports Brazilian format: 1.234,56).
     */
    private function parseDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        return Input::parseNumber($value);
    }

    private function normalizeVisibility(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === 'visible') {
            return 'public';
        }
        return in_array($value, ['public', 'catalog', 'search', 'hidden'], true) ? $value : 'public';
    }

    private function normalizeConditionGradeInput($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim((string) $value));
        return match ($normalized) {
            'a' => 'novo',
            'b', 'seminovo', 'bom' => 'usado',
            'c', 'regular', 'defeituoso' => 'usado_com_detalhes',
            default => $normalized,
        };
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
}
