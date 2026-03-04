<?php

namespace App\Services;

use App\Models\Carrier;
use App\Support\Input;

class CarrierService
{
    public const TYPE_OPTIONS = [
        'correios' => 'Correios',
        'transportadora' => 'Transportadora',
        'motoboy' => 'Motoboy',
        'outro' => 'Outro',
    ];

    /**
     * @return array{0: Carrier, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Nome da transportadora é obrigatório.';
        }

        $type = (string) ($input['carrier_type'] ?? 'transportadora');
        if (!array_key_exists($type, self::TYPE_OPTIONS)) {
            $errors[] = 'Tipo de transportadora inválido.';
        }

        $status = (string) ($input['status'] ?? 'ativo');
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $errors[] = 'Status inválido.';
        }

        $siteUrl = trim((string) ($input['site_url'] ?? ''));
        if ($siteUrl !== '' && filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Site da transportadora inválido.';
        }

        $trackingTemplate = trim((string) ($input['tracking_url_template'] ?? ''));
        if ($trackingTemplate !== '') {
            if (!str_contains($trackingTemplate, '{{tracking_code}}')) {
                $errors[] = 'Template de rastreio deve conter {{tracking_code}}.';
            } else {
                $sample = str_replace('{{tracking_code}}', 'ABC123', $trackingTemplate);
                if (filter_var($sample, FILTER_VALIDATE_URL) === false) {
                    $errors[] = 'Template de rastreio inválido.';
                }
            }
        }

        $carrier = Carrier::fromArray([
            'id' => $input['id'] ?? null,
            'name' => $name,
            'carrier_type' => $type,
            'site_url' => $siteUrl !== '' ? $siteUrl : null,
            'tracking_url_template' => $trackingTemplate !== '' ? $trackingTemplate : null,
            'status' => $status,
            'notes' => $input['notes'] ?? null,
        ]);

        return [$carrier, $errors];
    }

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }
}
