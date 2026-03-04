<?php

namespace App\Services;

use App\Support\Input;

class ProductWriteOffService
{
    /**
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        return $this->validateSingleItem($input);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    public function validateItems(array $items): array
    {
        $errors = [];
        $normalized = [];
        foreach ($items as $index => $item) {
            [$data, $itemErrors] = $this->validateSingleItem($item);
            if ($itemErrors) {
                foreach ($itemErrors as $error) {
                    $errors[] = 'Item #' . ($index + 1) . ': ' . $error;
                }
                continue;
            }
            $normalized[] = $data;
        }
        return [$normalized, $errors];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validateSingleItem(array $input): array
    {
        $errors = [];
        $data = Input::trimStrings($input);

        $destination = $this->normalizeEnum($data['destination'] ?? null, $this->destinationOptions());
        $reason = $this->normalizeEnum($data['reason'] ?? null, $this->reasonOptions());
        $quantity = isset($data['quantity']) ? (int) $data['quantity'] : 1;
        if ($quantity <= 0) {
            $errors[] = 'Quantidade deve ser maior que zero.';
        }

        $productSku = isset($data['product_sku']) ? (int) $data['product_sku'] : null;
        $sku = isset($data['sku']) ? trim((string) $data['sku']) : '';
        
        if (!$productSku && $sku === '') {
            $errors[] = 'Informe o SKU do produto.';
        }

        if ($destination === 'nao_localizado') {
            $reason = 'perdido';
        }

        $normalized = [
            'product_sku' => $productSku,
            'sku' => $sku,
            'destination' => $destination,
            'reason' => $reason,
            'quantity' => $quantity,
            'notes' => $data['notes'] ?? null,
        ];

        return [$normalized, $errors];
    }

    /**
     * @return array<string, string>
     */
    public function destinationOptions(): array
    {
        return [
            'nao_localizado' => 'Não localizado',
            'doacao' => 'Envio para doação',
            'devolucao_fornecedor' => 'Devolução para fornecedor',
            'lixo' => 'Lixo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function reasonOptions(): array
    {
        return [
            'perdido' => 'Produto perdido (não localizado)',
            'sem_venda' => 'Muito tempo sem vender',
            'avaria' => 'Avarias',
            'solicitacao_fornecedor' => 'Solicitação do fornecedor',
        ];
    }

    private function normalizeEnum(?string $value, array $allowed): string
    {
        if ($value === null) {
            return array_key_first($allowed);
        }
        $value = strtolower(trim($value));
        return array_key_exists($value, $allowed) ? $value : array_key_first($allowed);
    }
}
