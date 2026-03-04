<?php

namespace App\Models;

class Rule
{
    public ?int $id = null;
    public string $title = '';
    public string $content = '';
    public string $status = 'ativo';

    public static function fromArray(array $data): self
    {
        $rule = new self();
        $rule->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $rule->title = (string) ($data['title'] ?? '');
        $rule->content = (string) ($data['content'] ?? '');
        $rule->status = (string) ($data['status'] ?? 'ativo');

        return $rule;
    }

    public function toDbParams(): array
    {
        return [
            ':title' => $this->title,
            ':content' => $this->content,
            ':status' => $this->status,
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'status' => $this->status,
        ];
    }
}
