<?php

namespace App\Models;

class User
{
    public ?int $id = null;
    public string $fullName = '';
    public string $email = '';
    public ?string $phone = null;
    public string $role = 'colaborador';
    public string $status = 'ativo';
    public ?string $passwordHash = null;
    public ?int $profileId = null;
    public ?string $verificationToken = null;
    public ?string $verificationExpiresAt = null;
    public ?string $verifiedAt = null;
    public ?string $resetToken = null;
    public ?string $resetExpiresAt = null;
    public ?string $resetRequestedAt = null;

    public static function fromArray(array $data): self
    {
        $user = new self();
        $user->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $user->fullName = (string) ($data['full_name'] ?? $data['fullName'] ?? '');
        $user->email = (string) ($data['email'] ?? '');
        $user->phone = self::nullable($data['phone'] ?? null);
        $user->role = (string) ($data['role'] ?? 'colaborador');
        $user->status = (string) ($data['status'] ?? 'ativo');
        $user->passwordHash = $data['password_hash'] ?? $data['passwordHash'] ?? null;
        $user->profileId = isset($data['profile_id']) && $data['profile_id'] !== '' ? (int) $data['profile_id'] : null;
        $user->verificationToken = self::nullable($data['verification_token'] ?? $data['verificationToken'] ?? null);
        $user->verificationExpiresAt = self::nullable($data['verification_expires_at'] ?? $data['verificationExpiresAt'] ?? null);
        $user->verifiedAt = self::nullable($data['verified_at'] ?? $data['verifiedAt'] ?? null);
        $user->resetToken = self::nullable($data['reset_token'] ?? $data['resetToken'] ?? null);
        $user->resetExpiresAt = self::nullable($data['reset_expires_at'] ?? $data['resetExpiresAt'] ?? null);
        $user->resetRequestedAt = self::nullable($data['reset_requested_at'] ?? $data['resetRequestedAt'] ?? null);
        return $user;
    }

    public function toDbParams(bool $includeVerification = false): array
    {
        $params = [
            ':full_name' => $this->fullName,
            ':email' => $this->email,
            ':phone' => $this->phone,
            ':role' => $this->role,
            ':status' => $this->status,
            ':password_hash' => $this->passwordHash,
            ':profile_id' => $this->profileId,
        ];

        if ($includeVerification) {
            $params[':verification_token'] = $this->verificationToken;
            $params[':verification_expires_at'] = $this->verificationExpiresAt;
            $params[':verified_at'] = $this->verifiedAt;
        }

        return $params;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'status' => $this->status,
            'password_hash' => $this->passwordHash,
            'profile_id' => $this->profileId,
            'verification_token' => $this->verificationToken,
            'verification_expires_at' => $this->verificationExpiresAt,
            'verified_at' => $this->verifiedAt,
            'reset_token' => $this->resetToken,
            'reset_expires_at' => $this->resetExpiresAt,
            'reset_requested_at' => $this->resetRequestedAt,
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
