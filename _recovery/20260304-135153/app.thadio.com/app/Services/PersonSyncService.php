<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\PersonRepository;
use App\Repositories\PersonRoleRepository;
use App\Support\Phone;
use PDO;

class PersonSyncService
{
    private PersonRepository $people;
    private PersonRoleRepository $roles;

    public function __construct(?PDO $pdo)
    {
        $this->people = new PersonRepository($pdo);
        $this->roles = new PersonRoleRepository($pdo);
    }

    public function syncFromExternalCustomerRow(array $row, array $roles = [], string $context = 'external'): ?Person
    {
        $customerId = (int) ($row['customer_id'] ?? $row['id'] ?? 0);
        if ($customerId <= 0) {
            return null;
        }

        $person = new Person();
        $person->id = $customerId;
        $person->fullName = $this->resolveFullName($row);
        $person->email = $this->nullableString($row['email'] ?? null);
        $person->email2 = $this->nullableString($row['email2'] ?? null);
        $person->phone = $this->normalizePhone($row['phone'] ?? null);
        $person->cpfCnpj = $this->nullableString($row['cpf_cnpj'] ?? null);
        $person->pixKey = $this->nullableString($row['pix_key'] ?? null);
        $person->instagram = $this->nullableString($row['instagram'] ?? null);
        $person->street = $this->nullableString($row['street'] ?? null);
        $person->street2 = $this->nullableString($row['street2'] ?? null);
        $person->number = $this->nullableString($row['number'] ?? null);
        $person->neighborhood = $this->nullableString($row['neighborhood'] ?? null);
        $person->city = $this->nullableString($row['city'] ?? null);
        $person->state = $this->nullableString($row['state'] ?? null);
        $person->zip = $this->nullableString($row['zip'] ?? null);
        $person->country = $this->nullableString($row['billing_country'] ?? $row['country'] ?? null);
        $person->status = (string) ($row['status'] ?? 'ativo');
        $person->metadata = $this->buildMetadataFromCatalogRow($row);
        $person->lastSyncedAt = date('Y-m-d H:i:s');

        $existing = $this->people->find($customerId);
        $merged = $this->mergePeople($existing, $person, true);
        $this->people->save($merged);

        foreach ($roles as $role) {
            $this->roles->assign($customerId, $role, $context);
        }

        return $merged;
    }

    public function syncFromVendor(Vendor $vendor): ?Person
    {
        $personId = (int) ($vendor->id ?? 0);
        if ($personId <= 0) {
            return null;
        }

        $person = null;
        if (!$person) {
            $person = $this->people->find($personId) ?? new Person();
            $person->id = $personId;
            if ($person->fullName === '') {
                $person->fullName = (string) ($vendor->fullName ?? '');
            }
            if ($person->email === null || $person->email === '') {
                $person->email = $vendor->email ?: null;
            }
            if ($person->phone === null || $person->phone === '') {
                $person->phone = $this->normalizePhone($vendor->phone ?? null);
            }
            $person->lastSyncedAt = date('Y-m-d H:i:s');
            $this->people->save($person);
        }

        $this->roles->assign($personId, 'fornecedor', 'vendor');
        $this->enrichMetadata($personId, [
            'vendor_id' => $vendor->id,
            'vendor_commission_rate' => $vendor->commissionRate,
        ]);

        return $this->people->find($personId);
    }

    public function syncFromAppUser(User $user): ?Person
    {
        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return null;
        }

        $person = $this->people->findByEmail($email);
        if (!$person) {
            return null;
        }

        $this->roles->assign($person->id ?? 0, 'usuario_retratoapp', 'retratoapp');
        $this->enrichMetadata($person->id ?? 0, [
            'retrato_user_role' => $user->role,
            'retrato_user_status' => $user->status,
        ]);

        return $person;
    }

    public function ensurePersonByExternalId(int $customerId): ?Person
    {
        if ($customerId <= 0) {
            return null;
        }
        $existing = $this->people->find($customerId);
        return $existing ?: null;
    }

    public function assignRole(int $personId, string $role, string $context, ?array $payload = null): void
    {
        $this->roles->assign($personId, $role, $context, $payload);
    }

    public function enrichMetadata(int $personId, array $metadata): void
    {
        if ($personId <= 0) {
            return;
        }
        $person = $this->people->find($personId);
        if (!$person) {
            return;
        }
        $current = $person->metadata ?? [];
        foreach ($metadata as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $current[$key] = $value;
        }
        $person->metadata = $current;
        $person->lastSyncedAt = date('Y-m-d H:i:s');
        $this->people->save($person);
    }

    private function mergePeople(?Person $existing, Person $incoming, bool $preferIncomingName): Person
    {
        if (!$existing) {
            return $incoming;
        }

        $merged = $existing;

        if ($preferIncomingName && $incoming->fullName !== '') {
            $merged->fullName = $incoming->fullName;
        }
        $merged->email = $this->coalesceString($incoming->email, $existing->email);
        $merged->email2 = $this->coalesceString($incoming->email2, $existing->email2);
        $merged->phone = $this->coalesceString($incoming->phone, $existing->phone);
        $merged->cpfCnpj = $this->coalesceString($incoming->cpfCnpj, $existing->cpfCnpj);
        $merged->pixKey = $this->coalesceString($incoming->pixKey, $existing->pixKey);
        $merged->instagram = $this->coalesceString($incoming->instagram, $existing->instagram);
        $merged->country = $this->coalesceString($incoming->country, $existing->country);
        $merged->state = $this->coalesceString($incoming->state, $existing->state);
        $merged->city = $this->coalesceString($incoming->city, $existing->city);
        $merged->neighborhood = $this->coalesceString($incoming->neighborhood, $existing->neighborhood);
        $merged->number = $this->coalesceString($incoming->number, $existing->number);
        $merged->street = $this->coalesceString($incoming->street, $existing->street);
        $merged->street2 = $this->coalesceString($incoming->street2, $existing->street2);
        $merged->zip = $this->coalesceString($incoming->zip, $existing->zip);
        $merged->status = $incoming->status !== '' ? $incoming->status : $existing->status;

        $merged->metadata = $this->mergeMetadata($existing->metadata, $incoming->metadata);
        $merged->lastSyncedAt = $incoming->lastSyncedAt ?? $existing->lastSyncedAt;

        return $merged;
    }

    /**
     * @param array<string, mixed>|null $current
     * @param array<string, mixed>|null $incoming
     * @return array<string, mixed>|null
     */
    private function mergeMetadata(?array $current, ?array $incoming): ?array
    {
        if ($current === null && $incoming === null) {
            return null;
        }
        $merged = $current ?? [];
        if ($incoming) {
            foreach ($incoming as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $merged[$key] = $value;
            }
        }
        return $merged;
    }

    private function resolveFullName(array $row): string
    {
        // Priorizar full_name (campo principal na tabela pessoas)
        $fullName = trim((string) ($row['full_name'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        // Legacy WooCommerce fallback: first_name + last_name
        $first = trim((string) ($row['first_name'] ?? ''));
        $last = trim((string) ($row['last_name'] ?? ''));
        $full = trim($first . ' ' . $last);
        if ($full !== '') {
            return $full;
        }
        $display = trim((string) ($row['display_name'] ?? ''));
        if ($display !== '') {
            return $display;
        }
        return (string) ($row['email'] ?? '');
    }

    private function buildMetadataFromCatalogRow(array $row): array
    {
        $meta = [];
        $fields = [
            'deleted_at',
            'deleted_by',
            'shipping_first_name',
            'shipping_last_name',
            'shipping_address_1',
            'shipping_address_2',
            'shipping_number',
            'shipping_neighborhood',
            'shipping_city',
            'shipping_state',
            'shipping_postcode',
            'shipping_country',
        ];
        foreach ($fields as $field) {
            if (!empty($row[$field])) {
                $meta[$field] = $row[$field];
            }
        }
        return $meta;
    }

    private function normalizePhone($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Phone::normalizeBrazilCell((string) $value) ?? (string) $value;
    }

    private function nullableString($value): ?string
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return (string) $value;
    }

    private function coalesceString(?string $incoming, ?string $current): ?string
    {
        if ($incoming !== null && $incoming !== '') {
            return $incoming;
        }
        return $current;
    }
}
