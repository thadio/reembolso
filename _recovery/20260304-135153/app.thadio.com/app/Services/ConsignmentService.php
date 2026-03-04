<?php

namespace App\Services;

use App\Support\Input;
use PDO;
use Exception;

/**
 * ConsignmentService
 * 
 * Serviço para validação e regras de negócio de consignações.
 */
class ConsignmentService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Valida dados de entrada para criação/atualização de consignação.
     *
     * @param array $input Dados brutos do formulário/request
     * @return array Dados validados e normalizados
     * @throws Exception Se validação falhar
     */
    public function validate(array $input): array
    {
        // Trim strings
        $input = Input::trimStrings($input);

        $errors = [];
        $validated = [];

        // 1. supplier_pessoa_id (obrigatório, deve ser fornecedor)
        if (empty($input['supplier_pessoa_id'])) {
            $errors[] = 'Fornecedor é obrigatório.';
        } else {
            $supplierId = filter_var($input['supplier_pessoa_id'], FILTER_VALIDATE_INT);
            if ($supplierId === false || $supplierId <= 0) {
                $errors[] = 'ID do fornecedor inválido.';
            } else {
                // Verificar se pessoa existe e tem papel de fornecedor
                $stmt = $this->pdo->prepare("
                    SELECT p.id 
                    FROM pessoas p
                    INNER JOIN pessoas_papeis pp ON p.id = pp.pessoa_id
                    WHERE p.id = :id AND pp.role = 'fornecedor'
                    LIMIT 1
                ");
                $stmt->execute(['id' => $supplierId]);
                
                if (!$stmt->fetch()) {
                    $errors[] = 'Fornecedor não encontrado ou pessoa não tem papel de fornecedor.';
                } else {
                    $validated['supplier_pessoa_id'] = $supplierId;
                }
            }
        }

        // 2. percent_default (obrigatório, decimal 0-100)
        if (!isset($input['percent_default']) || $input['percent_default'] === '') {
            $errors[] = 'Percentual padrão é obrigatório.';
        } else {
            $percent = filter_var($input['percent_default'], FILTER_VALIDATE_FLOAT);
            if ($percent === false || $percent < 0 || $percent > 100) {
                $errors[] = 'Percentual padrão deve ser um número entre 0 e 100.';
            } else {
                $validated['percent_default'] = $percent;
            }
        }

        // 3. status (obrigatório, enum)
        if (empty($input['status'])) {
            $validated['status'] = 'aberta'; // Padrão
        } else {
            $validStatuses = ['aberta', 'fechada', 'pendente', 'liquidada'];
            if (!in_array($input['status'], $validStatuses, true)) {
                $errors[] = 'Status inválido. Valores permitidos: ' . implode(', ', $validStatuses);
            } else {
                $validated['status'] = $input['status'];
            }
        }

        // 4. received_at (obrigatório, datetime)
        if (empty($input['received_at'])) {
            $validated['received_at'] = date('Y-m-d H:i:s'); // Padrão: agora
        } else {
            // Tentar parsear datetime
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $input['received_at']);
            if (!$date) {
                // Tentar formato sem hora
                $date = \DateTime::createFromFormat('Y-m-d', $input['received_at']);
                if ($date) {
                    $validated['received_at'] = $date->format('Y-m-d H:i:s');
                } else {
                    $errors[] = 'Data de recebimento inválida. Use formato: YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS';
                }
            } else {
                $validated['received_at'] = $input['received_at'];
            }
        }

        // 5. closed_at (opcional, datetime, >= received_at)
        if (!empty($input['closed_at'])) {
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $input['closed_at']);
            if (!$date) {
                $date = \DateTime::createFromFormat('Y-m-d', $input['closed_at']);
                if ($date) {
                    $validated['closed_at'] = $date->format('Y-m-d H:i:s');
                } else {
                    $errors[] = 'Data de fechamento inválida. Use formato: YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS';
                }
            } else {
                $validated['closed_at'] = $input['closed_at'];
            }

            // Validar que closed_at >= received_at
            if (isset($validated['closed_at']) && isset($validated['received_at'])) {
                if (strtotime($validated['closed_at']) < strtotime($validated['received_at'])) {
                    $errors[] = 'Data de fechamento não pode ser anterior à data de recebimento.';
                }
            }
        } else {
            $validated['closed_at'] = null;
        }

        // 6. notes (opcional)
        $validated['notes'] = !empty($input['notes']) ? $input['notes'] : null;

        // 7. id (para updates)
        if (isset($input['id']) && is_numeric($input['id'])) {
            $validated['id'] = (int)$input['id'];
        }

        // 8. items (array de itens - validar se fornecido)
        if (isset($input['items']) && is_array($input['items'])) {
            $validatedItems = [];
            
            foreach ($input['items'] as $index => $item) {
                $itemErrors = [];
                $validatedItem = [];

                // product_sku (obrigatório)
                $productSku = $item['product_sku'] ?? null;
                if (empty($productSku)) {
                    $itemErrors[] = "Item #{$index}: SKU do produto é obrigatório.";
                } else {
                    $itemId = filter_var($productSku, FILTER_VALIDATE_INT);
                    if ($itemId === false || $itemId <= 0) {
                        $itemErrors[] = "Item #{$index}: SKU do produto inválido.";
                    } else {
                        // MODELO UNIFICADO: referencia products.sku
                        $stmt = $this->pdo->prepare("SELECT sku FROM products WHERE sku = :id LIMIT 1");
                        $stmt->execute(['id' => $itemId]);
                        if (!$stmt->fetch()) {
                            $itemErrors[] = "Item #{$index}: Produto não encontrado.";
                        } else {
                            $validatedItem['product_sku'] = $itemId;
                        }
                    }
                }

                // quantity (obrigatório, int >= 1)
                if (!isset($item['quantity']) || $item['quantity'] === '') {
                    $itemErrors[] = "Item #{$index}: Quantidade é obrigatória.";
                } else {
                    $qty = filter_var($item['quantity'], FILTER_VALIDATE_INT);
                    if ($qty === false || $qty < 1) {
                        $itemErrors[] = "Item #{$index}: Quantidade deve ser um número maior ou igual a 1.";
                    } else {
                        $validatedItem['quantity'] = $qty;
                    }
                }

                // percent_override (obrigatório, decimal 0-100)
                if (!isset($item['percent_override']) || $item['percent_override'] === '') {
                    $itemErrors[] = "Item #{$index}: Percentual é obrigatório.";
                } else {
                    $percent = filter_var($item['percent_override'], FILTER_VALIDATE_FLOAT);
                    if ($percent === false || $percent < 0 || $percent > 100) {
                        $itemErrors[] = "Item #{$index}: Percentual deve ser um número entre 0 e 100.";
                    } else {
                        $validatedItem['percent_override'] = $percent;
                    }
                }

                // Se houver erros de item, adicionar aos erros gerais
                if (!empty($itemErrors)) {
                    $errors = array_merge($errors, $itemErrors);
                } else {
                    $validatedItems[] = $validatedItem;
                }
            }

            if (!empty($validatedItems)) {
                $validated['items'] = $validatedItems;
            }
        }

        // Se houver erros, lançar exceção
        if (!empty($errors)) {
            throw new Exception('Erros de validação: ' . implode(' | ', $errors));
        }

        return $validated;
    }

    /**
     * Valida se uma consignação pode ser fechada
     * 
     * @param int $consignmentId
     * @return bool
     * @throws Exception Se não puder ser fechada
     */
    public function canClose(int $consignmentId): bool
    {
        $stmt = $this->pdo->prepare("SELECT status FROM consignments WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $consignmentId]);
        $consignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$consignment) {
            throw new Exception('Consignação não encontrada.');
        }

        if ($consignment['status'] === 'fechada') {
            throw new Exception('Consignação já está fechada.');
        }

        // Verificar se tem itens
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM consignment_items WHERE consignment_id = :id");
        $stmt->execute(['id' => $consignmentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ((int)$result['count'] === 0) {
            throw new Exception('Consignação não pode ser fechada sem itens.');
        }

        return true;
    }

    /**
     * Valida se uma consignação pode ser editada
     * 
     * @param int $consignmentId
     * @return bool
     * @throws Exception Se não puder ser editada
     */
    public function canEdit(int $consignmentId): bool
    {
        $stmt = $this->pdo->prepare("SELECT status FROM consignments WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $consignmentId]);
        $consignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$consignment) {
            throw new Exception('Consignação não encontrada.');
        }

        if (in_array($consignment['status'], ['fechada', 'liquidada'], true)) {
            throw new Exception('Consignação fechada ou liquidada não pode ser editada.');
        }

        return true;
    }
}
