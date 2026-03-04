<?php

namespace App\Models;

class Customer
{
    public ?int $id = null;
    public string $fullName = '';
    public ?string $email = null;
    public ?string $email2 = null;
    public ?string $phone = null;
    public string $status = 'ativo';
    public ?string $cpfCnpj = null;
    public ?string $pixKey = null;
    public ?string $instagram = null;
    public ?string $street = null;
    public ?string $street2 = null;
    public ?string $number = null;
    public ?string $neighborhood = null;
    public ?string $city = null;
    public ?string $state = null;
    public ?string $zip = null;
    public ?string $country = null;

    public static function fromArray(array $data): self
    {
        $customer = new self();
        $customer->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        $customer->fullName = (string) ($data['full_name'] ?? $data['fullName'] ?? '');
        $customer->email = self::nullable($data['email'] ?? null);
        $customer->email2 = self::nullable($data['email2'] ?? null);
        $customer->phone = self::nullable($data['phone'] ?? null);
        $customer->status = (string) ($data['status'] ?? 'ativo');
        $customer->cpfCnpj = self::nullable($data['cpf_cnpj'] ?? $data['cpfCnpj'] ?? null);
        $customer->pixKey = self::nullable($data['pix_key'] ?? $data['pixKey'] ?? null);
        $customer->instagram = self::nullable($data['instagram'] ?? null);
        $customer->street = self::nullable($data['street'] ?? null);
        $customer->street2 = self::nullable($data['street2'] ?? $data['address_2'] ?? null);
        $customer->number = self::nullable($data['number'] ?? $data['billing_number'] ?? null);
        $customer->neighborhood = self::nullable($data['neighborhood'] ?? $data['billing_neighborhood'] ?? null);
        $customer->city = self::nullable($data['city'] ?? null);
        $customer->state = self::nullable($data['state'] ?? null);
        $customer->zip = self::nullable($data['zip'] ?? null);
        $customer->country = self::nullable($data['country'] ?? null);
        return $customer;
    }

    public function toDbParams(): array
    {
        return [
            ':full_name' => $this->fullName,
            ':email' => $this->email,
            ':email2' => $this->email2,
            ':phone' => $this->phone,
            ':status' => $this->status,
            ':cpf_cnpj' => $this->cpfCnpj,
            ':pix_key' => $this->pixKey,
            ':instagram' => $this->instagram,
            ':street' => $this->street,
            ':street2' => $this->street2,
            ':number' => $this->number,
            ':neighborhood' => $this->neighborhood,
            ':city' => $this->city,
            ':state' => $this->state,
            ':zip' => $this->zip,
            ':country' => $this->country,
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
