<?php

namespace App\Support;

/**
 * Lookup helper for product/category/brand statuses and visibility options.
 * Eliminates dependency on external platforms for status dropdowns.
 */
class CatalogLookup
{
    /**
     * Product status options.
     *
     * @return array
     */
    public static function productStatuses(): array
    {
        return [
            'draft' => 'Rascunho',
            'disponivel' => 'Disponível',
            'reservado' => 'Reservado',
            'esgotado' => 'Esgotado',
            'baixado' => 'Baixado',
            'archived' => 'Arquivado',
        ];
    }

    /**
     * Product visibility options.
     *
     * @return array
     */
    public static function productVisibility(): array
    {
        return [
            'public' => 'Público (catálogo + busca)',
            'catalog' => 'Apenas catálogo',
            'search' => 'Apenas busca',
            'hidden' => 'Oculto',
        ];
    }

    /**
     * Category/brand status options.
     *
     * @return array
     */
    public static function taxonomyStatuses(): array
    {
        return [
            'ativa' => 'Ativa',
            'inativa' => 'Inativa',
        ];
    }

    /**
     * Inventory source/origin options.
     *
     * @return array
     */
    public static function inventorySources(): array
    {
        return [
            'compra' => 'Compra',
            'consignacao' => 'Consignação',
            'doacao' => 'Doação',
        ];
    }

    /**
     * Get human-readable label for a source value.
     */
    public static function getSourceLabel(string $value): string
    {
        $sources = self::inventorySources();
        return $sources[$value] ?? $value;
    }

    /**
     * Inventory item status options.
     *
     * @return array
     */
    public static function inventoryStatuses(): array
    {
        return [
            'disponivel' => 'Disponível',
            'reservado' => 'Reservado',
            'esgotado' => 'Esgotado',
            'baixado' => 'Baixado',
        ];
    }

    /**
     * Item condition grades.
     *
     * @return array
     */
    public static function conditionGrades(): array
    {
        return [
            'novo' => 'Novo',
            'usado' => 'Usado',
            'usado_com_detalhes' => 'Usado com detalhes',
        ];
    }

    /**
     * Order status options.
     *
     * @return array
     */
    public static function orderStatuses(): array
    {
        return [
            'draft' => 'Rascunho',
            'pending' => 'Pendente',
            'processing' => 'Processando',
            'shipped' => 'Enviado',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
        ];
    }

    /**
     * Payment status options.
     *
     * @return array
     */
    public static function paymentStatuses(): array
    {
        return [
            'pending' => 'Pendente',
            'paid' => 'Pago',
            'failed' => 'Falhou',
        ];
    }

    /**
     * Fulfillment status options.
     *
     * @return array
     */
    public static function fulfillmentStatuses(): array
    {
        return [
            'pending' => 'Pendente',
            'ready' => 'Pronto',
            'shipped' => 'Enviado',
            'delivered' => 'Entregue',
        ];
    }

    /**
     * Get status label by value.
     *
     * @param string $type 'product', 'taxonomy', 'order', etc.
     * @param string $value
     * @return string
     */
    public static function getStatusLabel(string $type, string $value): string
    {
        if ($type === 'inventory' && $value === 'vendido') {
            $value = 'esgotado';
        }

        $map = match ($type) {
            'product' => self::productStatuses(),
            'taxonomy' => self::taxonomyStatuses(),
            'order' => self::orderStatuses(),
            'payment' => self::paymentStatuses(),
            'fulfillment' => self::fulfillmentStatuses(),
            'inventory' => self::inventoryStatuses(),
            default => [],
        };

        return $map[$value] ?? $value;
    }

    /**
     * Get visibility label.
     *
     * @param string $value
     * @return string
     */
    public static function getVisibilityLabel(string $value): string
    {
        $map = self::productVisibility();
        return $map[$value] ?? $value;
    }

    /**
     * Get taxonomy (brand/category) status label.
     *
     * @param string $value
     * @return string
     */
    public static function getTaxonomyStatusLabel(string $value): string
    {
        return self::getStatusLabel('taxonomy', $value);
    }

    /**
     * Get product status label.
     *
     * @param string $value
     * @return string
     */
    public static function getProductStatusLabel(string $value): string
    {
        $legacyMap = [
            'active' => 'disponivel',
            'publish' => 'disponivel',
            'pending' => 'draft',
            'trash' => 'archived',
            'sold' => 'esgotado',
            'vendido' => 'esgotado',
        ];
        $normalized = $legacyMap[$value] ?? $value;

        return self::getStatusLabel('product', $normalized);
    }
}
