<?php

namespace App\Services;

use App\Models\Collection;
use App\Support\Input;

class CollectionService
{
    /**
     * @return array{0: Collection, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $required = [
            'name' => 'Nome',
            'externalId' => 'ID',
            'slug' => 'Slug',
            'pageUrl' => 'Page Url',
        ];

        foreach ($required as $field => $label) {
            $value = $input[$field] ?? ($input[strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $field))] ?? '');
            if ($value === '') {
                $errors[] = "{$label} é obrigatório.";
            }
        }

        if (!empty($input['pageUrl']) && strpos($input['pageUrl'], '/') !== 0 && strpos($input['page_url'] ?? '', '/') !== 0) {
            $errors[] = 'Page Url deve começar com "/".';
        }

        $collection = Collection::fromArray($input);

        return [$collection, $errors];
    }
}
