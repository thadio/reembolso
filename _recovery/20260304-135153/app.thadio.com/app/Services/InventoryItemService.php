<?php

namespace App\Services;

use App\Support\Input;
use App\Support\CatalogLookup;
use Exception;

class InventoryItemService
{
    /**
     * Valida dados de um item de inventário
     * 
     * @param array $input Dados brutos do formulário
     * @return array Dados validados e normalizados
     * @throws Exception Se validação falhar
     */
    public function validate(array $input): array
    {
        // Trim strings
        $input = Input::trimStrings($input);
        
        $errors = [];
        $validated = [];
        
        // 1. inventory_code (internal_code) - obrigatório, único será validado no repository
        if (empty($input['internal_code'])) {
            $errors[] = 'Código do item é obrigatório.';
        } else {
            $validated['internal_code'] = $input['internal_code'];
        }
        
        // 2. product_sku - opcional
        $productSkuInput = $input['product_sku'] ?? null;
        if (isset($productSkuInput) && $productSkuInput !== '' && $productSkuInput !== null) {
            if (!is_numeric($productSkuInput)) {
                $errors[] = 'SKU do produto inválido.';
            } else {
                $validated['product_sku'] = (int)$productSkuInput;
            }
        } else {
            $validated['product_sku'] = null;
        }
        
        // 3. sku - será gerado automaticamente se vazio, mas deve ser numérico se fornecido
        if (!empty($input['sku'])) {
            if (!is_numeric($input['sku']) || $input['sku'] < 0) {
                $errors[] = 'SKU deve ser um número inteiro positivo.';
            } else {
                $validated['sku'] = (int)$input['sku'];
            }
        }
        // Nota: Se vazio, será gerado no Controller via getNextSku()
        
        // 4. title_override - opcional
        $validated['title_override'] = !empty($input['title_override']) ? $input['title_override'] : null;
        
        // 5. condition (condition_grade) - opcional mas validado se fornecido
        if (isset($input['condition_grade']) && $input['condition_grade'] !== '' && $input['condition_grade'] !== null) {
            $validConditions = array_keys(CatalogLookup::conditionGrades());
            if (!in_array($input['condition_grade'], $validConditions)) {
                $errors[] = 'Condição inválida. Valores permitidos: ' . implode(', ', $validConditions);
            } else {
                $validated['condition_grade'] = $input['condition_grade'];
            }
        } else {
            $validated['condition_grade'] = null;
        }
        
        // 6. size - opcional
        $validated['size'] = !empty($input['size']) ? $input['size'] : null;
        
        // 7. color - opcional
        $validated['color'] = !empty($input['color']) ? $input['color'] : null;
        
        // 8. gender - opcional
        $validated['gender'] = !empty($input['gender']) ? $input['gender'] : null;
        
        // 9. source - obrigatório, enum
        if (empty($input['source'])) {
            $errors[] = 'Origem é obrigatória.';
        } else {
            $validSources = array_keys(CatalogLookup::inventorySources());
            if (!in_array($input['source'], $validSources, true)) {
                $errors[] = 'Origem inválida. Valores permitidos: ' . implode(', ', $validSources);
            } else {
                $validated['source'] = $input['source'];
            }
        }
        
        // 10. acquisition_cost - obrigatório, decimal >= 0
        if (!isset($input['acquisition_cost']) || $input['acquisition_cost'] === '' || $input['acquisition_cost'] === null) {
            $errors[] = 'Custo de aquisição é obrigatório.';
        } else {
            $cost = (float)$input['acquisition_cost'];
            if ($cost < 0) {
                $errors[] = 'Custo de aquisição não pode ser negativo.';
            } else {
                $validated['acquisition_cost'] = $cost;
            }
        }
        
        // 11. consignment_percent (percentual_consignacao) - opcional, mas obrigatório se source='consignacao'
        if (isset($validated['source']) && $validated['source'] === 'consignacao') {
            if (!isset($input['consignment_percent']) || $input['consignment_percent'] === '' || $input['consignment_percent'] === null) {
                $errors[] = 'Percentual de consignação é obrigatório quando origem é "consignação".';
            } else {
                $percent = (float)$input['consignment_percent'];
                if ($percent < 0 || $percent > 100) {
                    $errors[] = 'Percentual de consignação deve estar entre 0 e 100.';
                } else {
                    $validated['consignment_percent'] = $percent;
                }
            }
        } else {
            // Se não for consignação, pode ser null
            if (isset($input['consignment_percent']) && $input['consignment_percent'] !== '' && $input['consignment_percent'] !== null) {
                $percent = (float)$input['consignment_percent'];
                if ($percent < 0 || $percent > 100) {
                    $errors[] = 'Percentual de consignação deve estar entre 0 e 100.';
                } else {
                    $validated['consignment_percent'] = $percent;
                }
            } else {
                $validated['consignment_percent'] = null;
            }
        }
        
        // 12. price_listed (published_price) - opcional, decimal >= 0
        if (isset($input['price_listed']) && $input['price_listed'] !== '' && $input['price_listed'] !== null) {
            $price = (float)$input['price_listed'];
            if ($price < 0) {
                $errors[] = 'Preço publicado não pode ser negativo.';
            } else {
                $validated['price_listed'] = $price;
            }
        } else {
            $validated['price_listed'] = null;
        }
        
        // 13. status - obrigatório, enum, padrão 'disponivel'
        if (empty($input['status'])) {
            $validated['status'] = 'disponivel'; // Padrão
        } else {
            $validStatuses = array_keys(CatalogLookup::inventoryStatuses());
            if (!in_array($input['status'], $validStatuses, true)) {
                $errors[] = 'Status inválido. Valores permitidos: ' . implode(', ', $validStatuses);
            } else {
                $validated['status'] = $input['status'];
            }
        }
        
        // 14. supplier_pessoa_id - opcional
        if (isset($input['supplier_pessoa_id']) && $input['supplier_pessoa_id'] !== '' && $input['supplier_pessoa_id'] !== null) {
            if (!is_numeric($input['supplier_pessoa_id'])) {
                $errors[] = 'ID do fornecedor inválido.';
            } else {
                $validated['supplier_pessoa_id'] = (int)$input['supplier_pessoa_id'];
            }
        } else {
            $validated['supplier_pessoa_id'] = null;
        }
        
        // 15. consignment_id - opcional
        if (isset($input['consignment_id']) && $input['consignment_id'] !== '' && $input['consignment_id'] !== null) {
            if (!is_numeric($input['consignment_id'])) {
                $errors[] = 'ID da consignação inválido.';
            } else {
                $validated['consignment_id'] = (int)$input['consignment_id'];
            }
        } else {
            $validated['consignment_id'] = null;
        }
        
        // 16. entered_at - opcional, datetime, padrão now()
        if (!empty($input['entered_at'])) {
            // Validar formato de data
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $input['entered_at']);
            if (!$date) {
                // Tentar formato sem hora
                $date = \DateTime::createFromFormat('Y-m-d', $input['entered_at']);
                if ($date) {
                    // Forçar hora para 00:00:00
                    $date->setTime(0, 0, 0);
                    $validated['entered_at'] = $date->format('Y-m-d H:i:s');
                } else {
                    $errors[] = 'Data de entrada inválida. Use formato: YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS';
                }
            } else {
                $validated['entered_at'] = $input['entered_at'];
            }
        } else {
            $validated['entered_at'] = date('Y-m-d H:i:s'); // Padrão: agora
        }
        
        // 17. notes - opcional
        $validated['notes'] = !empty($input['notes']) ? $input['notes'] : null;
        
        // 18. id - para updates
        if (isset($input['id']) && is_numeric($input['id'])) {
            $validated['id'] = (int)$input['id'];
        }
        
        // Se houver erros, lançar exceção
        if (!empty($errors)) {
            throw new Exception('Erros de validação: ' . implode(' | ', $errors));
        }
        
        return $validated;
    }
    
    /**
     * Valida unicidade do código interno
     * 
     * @param string $code Código a validar
     * @param int|null $excludeId ID a excluir da verificação (para updates)
     * @param \PDO $pdo Conexão PDO
     * @return bool
     * @throws Exception Se código já existe
     */
    public function validateUniqueCode(string $code, ?int $excludeId, \PDO $pdo): bool
    {
        $sql = "SELECT sku FROM products
                WHERE JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.internal_code')) = :code";
        
        if ($excludeId) {
            $sql .= " AND sku != :exclude_id";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':code', $code);
        
        if ($excludeId) {
            $stmt->bindValue(':exclude_id', $excludeId, \PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("Código '{$code}' já existe. Use um código único.");
        }
        
        return true;
    }

    /**
     * Valida unicidade do SKU
     * 
     * @param int $sku SKU a validar
     * @param int|null $excludeId ID a excluir da verificação (para updates)
     * @param \PDO $pdo Conexão PDO
     * @return bool
     * @throws Exception Se SKU já existe
     */
    public function validateUniqueSku(int $sku, ?int $excludeId, \PDO $pdo): bool
    {
        $sql = "SELECT sku FROM products WHERE sku = :sku";
        
        if ($excludeId) {
            $sql .= " AND sku != :exclude_id";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':sku', $sku, \PDO::PARAM_INT);
        
        if ($excludeId) {
            $stmt->bindValue(':exclude_id', $excludeId, \PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("SKU '{$sku}' já existe. Use um SKU único ou deixe em branco para gerar automaticamente.");
        }
        
        return true;
    }
}
