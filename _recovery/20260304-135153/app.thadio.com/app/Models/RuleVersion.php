<?php

namespace App\Models;

class RuleVersion
{
    public ?int $id = null;
    public ?int $ruleId = null;
    public string $title = '';
    public string $content = '';
    public string $status = 'ativo';
    public ?string $observations = null;
    public ?int $createdById = null;
    public ?string $createdByName = null;
    public ?string $createdAt = null;

    public static function fromArray(array $data): self
    {
        $version = new self();
        $version->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $version->ruleId = isset($data['regra_id']) && $data['regra_id'] !== '' ? (int) $data['regra_id'] : null;
        $version->title = (string) ($data['title'] ?? '');
        $version->content = (string) ($data['content'] ?? '');
        $version->status = (string) ($data['status'] ?? 'ativo');
        $version->observations = $data['observacoes'] ?? $data['observations'] ?? null;
        $version->createdById = isset($data['created_by_id']) && $data['created_by_id'] !== '' ? (int) $data['created_by_id'] : null;
        $version->createdByName = $data['created_by_name'] ?? $data['createdByName'] ?? null;
        $version->createdAt = $data['created_at'] ?? null;
        return $version;
    }

    public function toDbParams(): array
    {
        return [
            ':regra_id' => $this->ruleId,
            ':title' => $this->title,
            ':content' => $this->content,
            ':status' => $this->status,
            ':observacoes' => $this->observations,
            ':created_by_id' => $this->createdById,
            ':created_by_name' => $this->createdByName,
        ];
    }
}
