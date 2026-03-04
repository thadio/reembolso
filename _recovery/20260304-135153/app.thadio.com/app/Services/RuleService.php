<?php

namespace App\Services;

use App\Models\Rule;
use App\Support\Input;

class RuleService
{
    /**
     * @return array{0: Rule, 1: array<int, string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $input = Input::trimStrings($input);

        $title = (string) ($input['title'] ?? '');
        if ($title === '') {
            $errors[] = 'Título é obrigatório.';
        }

        $content = (string) ($input['content'] ?? '');
        if (trim($content) === '') {
            $errors[] = 'Conteúdo é obrigatório.';
        }

        $status = $input['status'] ?? 'ativo';
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            $errors[] = 'Status inválido.';
        }

        $rule = Rule::fromArray([
            'id' => $input['id'] ?? null,
            'title' => $title,
            'content' => $content,
            'status' => $status,
        ]);

        return [$rule, $errors];
    }
}
