<?php

namespace App\Models;

use App\Support\Input;

class Vendor
{
    public ?int $id = null;
    public ?int $idVendor = null;
    public string $fullName = '';
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $instagram = null;
    public ?string $cpfCnpj = null;
    public ?string $pixKey = null;
    public ?float $commissionRate = null;
    public ?string $street = null;
    public ?string $street2 = null;
    public ?string $number = null;
    public ?string $neighborhood = null;
    public ?string $city = null;
    public ?string $state = null;
    public ?string $zip = null;
    public ?string $pais = null;

    public static function fromArray(array $data): self
    {
        $vendor = new self();
        $vendor->id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        if (isset($data['id_vendor']) && $data['id_vendor'] !== '') {
            $vendor->idVendor = (int) $data['id_vendor'];
        } elseif (isset($data['idVendor']) && $data['idVendor'] !== '') {
            $vendor->idVendor = (int) $data['idVendor'];
        }
        $vendor->fullName = (string) ($data['full_name'] ?? $data['fullName'] ?? '');
        $vendor->email = self::nullableString($data['email'] ?? $data['contact_email'] ?? null);
        $vendor->phone = self::nullableString($data['phone'] ?? null);
        $vendor->instagram = self::nullableString($data['instagram'] ?? null);
        $vendor->cpfCnpj = self::nullableString($data['cpf_cnpj'] ?? $data['cpfCnpj'] ?? null);
        $vendor->pixKey = self::nullableString($data['pix_key'] ?? $data['pixKey'] ?? null);
        $vendor->commissionRate = self::nullableFloat($data['commission_rate'] ?? $data['commissionRate'] ?? null);
        $vendor->street = self::nullableString($data['street'] ?? null);
        $vendor->street2 = self::nullableString($data['street2'] ?? $data['address_2'] ?? null);
        $vendor->number = self::nullableString($data['number'] ?? $data['billing_number'] ?? null);
        $vendor->neighborhood = self::nullableString($data['neighborhood'] ?? $data['billing_neighborhood'] ?? null);
        $vendor->city = self::nullableString($data['city'] ?? null);
        $vendor->state = self::nullableString($data['state'] ?? null);
        $vendor->zip = self::nullableString($data['zip'] ?? null);
        $vendor->pais = self::nullableString($data['pais'] ?? null);
        return $vendor;
    }

    /**
     * Prepara array para bind no PDO.
     */
    public function toDbParams(): array
    {
        return [
            ':id_vendor' => $this->idVendor,
            ':full_name' => $this->fullName,
            ':email' => $this->email,
            ':phone' => $this->phone,
            ':instagram' => $this->instagram,
            ':cpf_cnpj' => $this->cpfCnpj,
            ':pix_key' => $this->pixKey,
            ':commission_rate' => $this->commissionRate,
            ':street' => $this->street,
            ':street2' => $this->street2,
            ':number' => $this->number,
            ':neighborhood' => $this->neighborhood,
            ':city' => $this->city,
            ':state' => $this->state,
            ':zip' => $this->zip,
            ':pais' => $this->pais,
        ];
    }

    private static function nullableString($value): ?string
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return (string) $value;
    }

    private static function nullableFloat($value): ?float
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return Input::parseNumber($value);
    }
}
