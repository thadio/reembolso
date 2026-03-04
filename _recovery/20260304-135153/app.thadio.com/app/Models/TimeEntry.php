<?php

namespace App\Models;

class TimeEntry
{
    public ?int $id = null;
    public ?int $userId = null;
    public string $type = 'entrada';
    public string $recordedAt = '';
    public string $status = 'pendente';
    public ?int $approvedBy = null;
    public ?string $approvedAt = null;
    public ?string $note = null;

    public static function fromArray(array $data): self
    {
        $entry = new self();
        $entry->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $entry->userId = isset($data['user_id']) && $data['user_id'] !== '' ? (int) $data['user_id'] : null;
        $entry->type = (string) ($data['tipo'] ?? $data['type'] ?? 'entrada');
        $entry->recordedAt = (string) ($data['registrado_em'] ?? $data['recordedAt'] ?? '');
        $entry->status = (string) ($data['status'] ?? 'pendente');
        $entry->approvedBy = isset($data['aprovado_por']) && $data['aprovado_por'] !== ''
            ? (int) $data['aprovado_por']
            : (isset($data['approvedBy']) && $data['approvedBy'] !== '' ? (int) $data['approvedBy'] : null);
        $entry->approvedAt = self::nullable($data['aprovado_em'] ?? $data['approvedAt'] ?? null);
        $entry->note = self::nullable($data['observacao'] ?? $data['note'] ?? null);
        return $entry;
    }

    public function toDbParams(): array
    {
        return [
            ':user_id' => $this->userId,
            ':tipo' => $this->type,
            ':registrado_em' => $this->recordedAt,
            ':status' => $this->status,
            ':observacao' => $this->note,
        ];
    }

    private static function nullable($value): ?string
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return (string) $value;
    }
}
