<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Customer;
use App\Models\Person;
use App\Repositories\PersonRepository;
use PDO;

class CatalogCustomerService
{
    private ?PersonSyncService $personSync = null;
    private ?PDO $pdo = null;
    private ?PersonRepository $people = null;

    public function __construct(?PersonSyncService $personSync = null, ?PDO $pdo = null)
    {
        $this->personSync = $personSync;
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(Customer $customer): array
    {
        $person = $this->mapCustomerToPerson($customer);
        if (!$person->id) {
            $person->id = $this->nextPersonId();
        }

        if ($this->people()->find($person->id)) {
            throw new \RuntimeException('Já existe pessoa com o ID informado.');
        }

        $person->lastSyncedAt = date('Y-m-d H:i:s');
        $this->people()->save($person);

        $created = $this->people()->find((int) $person->id);
        if (!$created) {
            throw new \RuntimeException('Não foi possível recuperar a pessoa após o cadastro.');
        }

        return $this->personToCompatCustomerRow($created);
    }

    /**
     * @return array<string, mixed>
     */
    public function update(int $customerId, Customer $customer): array
    {
        $person = $this->people()->find($customerId);
        if (!$person) {
            throw new \RuntimeException('Pessoa não encontrada para atualização.');
        }

        $person = $this->applyCustomerUpdate($person, $customer);
        $person->lastSyncedAt = date('Y-m-d H:i:s');
        $this->people()->save($person);

        $updated = $this->people()->find($customerId);
        if (!$updated) {
            throw new \RuntimeException('Não foi possível recuperar a pessoa após a atualização.');
        }

        return $this->personToCompatCustomerRow($updated);
    }

    /**
     * @param array<string, mixed> $billing
     * @param array<string, mixed> $shipping
     * @return array<string, mixed>
     */
    public function updateAddresses(int $customerId, array $billing, array $shipping): array
    {
        $person = $this->people()->find($customerId);
        if (!$person) {
            throw new \RuntimeException('Pessoa não encontrada para atualização de endereço.');
        }

        $metadata = $person->metadata ?? [];

        if (!empty($billing)) {
            $fullName = trim((string) ($billing['full_name'] ?? ''));
            if ($fullName !== '') {
                $person->fullName = $fullName;
            }

            $person->email = $this->preferNonEmptyString($person->email, $billing['email'] ?? null);
            $person->phone = $this->preferNonEmptyString($person->phone, $billing['phone'] ?? null);
            $person->street = $this->preferNonEmptyString($person->street, $billing['address_1'] ?? null);
            $person->street2 = $this->preferNonEmptyString($person->street2, $billing['address_2'] ?? null);
            $person->city = $this->preferNonEmptyString($person->city, $billing['city'] ?? null);
            $person->state = $this->preferNonEmptyString($person->state, $billing['state'] ?? null);
            $person->zip = $this->preferNonEmptyString($person->zip, $billing['postcode'] ?? null);
            $person->country = $this->preferNonEmptyString($person->country, $billing['country'] ?? null);
            $person->number = $this->preferNonEmptyString($person->number, $billing['number'] ?? null);
            $person->neighborhood = $this->preferNonEmptyString($person->neighborhood, $billing['neighborhood'] ?? null);
        }

        if (!empty($shipping)) {
            $metadata['shipping_first_name'] = $this->nullableString($shipping['first_name'] ?? null);
            $metadata['shipping_last_name'] = $this->nullableString($shipping['last_name'] ?? null);
            $metadata['shipping_address_1'] = $this->nullableString($shipping['address_1'] ?? null);
            $metadata['shipping_address_2'] = $this->nullableString($shipping['address_2'] ?? null);
            $metadata['shipping_city'] = $this->nullableString($shipping['city'] ?? null);
            $metadata['shipping_state'] = $this->nullableString($shipping['state'] ?? null);
            $metadata['shipping_postcode'] = $this->nullableString($shipping['postcode'] ?? null);
            $metadata['shipping_country'] = $this->nullableString($shipping['country'] ?? null);
            $metadata['shipping_number'] = $this->nullableString($shipping['number'] ?? null);
            $metadata['shipping_neighborhood'] = $this->nullableString($shipping['neighborhood'] ?? null);
        }

        $person->metadata = $this->sanitizeMetadata($metadata, true);
        $person->lastSyncedAt = date('Y-m-d H:i:s');
        $this->people()->save($person);

        $updated = $this->people()->find($customerId);
        if (!$updated) {
            throw new \RuntimeException('Não foi possível recuperar a pessoa após atualizar endereços.');
        }

        return $this->personToCompatCustomerRow($updated);
    }

    /**
     * @return array<string, mixed>
     */
    public function updatePhone(int $customerId, string $phone): array
    {
        $person = $this->people()->find($customerId);
        if (!$person) {
            throw new \RuntimeException('Pessoa não encontrada para atualização de telefone.');
        }

        $normalized = $this->nullableString($phone);
        if ($normalized !== null) {
            $person->phone = $normalized;
        }

        $person->lastSyncedAt = date('Y-m-d H:i:s');
        $this->people()->save($person);

        $updated = $this->people()->find($customerId);
        if (!$updated) {
            throw new \RuntimeException('Não foi possível recuperar a pessoa após atualizar telefone.');
        }

        return $this->personToCompatCustomerRow($updated);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateMeta(int $customerId, array $meta): array
    {
        $person = $this->people()->find($customerId);
        if (!$person) {
            throw new \RuntimeException('Pessoa não encontrada para atualização de metadados.');
        }

        $metadata = $person->metadata ?? [];
        foreach ($meta as $key => $value) {
            $cleanKey = trim((string) $key);
            if ($cleanKey === '') {
                continue;
            }

            if ($value === null || $value === '') {
                unset($metadata[$cleanKey]);
                continue;
            }

            $metadata[$cleanKey] = $value;
        }

        if (isset($meta['retrato_status'])) {
            $status = $this->nullableString($meta['retrato_status']);
            if ($status !== null) {
                $person->status = $status;
            }
        }

        if (isset($meta['retrato_deleted_at'])) {
            $deletedAt = $this->nullableString($meta['retrato_deleted_at']);
            if ($deletedAt !== null) {
                $person->status = 'inativo';
            } elseif ($person->status === 'inativo') {
                $person->status = 'ativo';
            }
        }

        $person->metadata = $this->sanitizeMetadata($metadata, true);
        $person->lastSyncedAt = date('Y-m-d H:i:s');
        $this->people()->save($person);

        $updated = $this->people()->find($customerId);
        if (!$updated) {
            throw new \RuntimeException('Não foi possível recuperar a pessoa após atualizar metadados.');
        }

        return $this->personToCompatCustomerRow($updated);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $customerId): array
    {
        $person = $this->people()->find($customerId);
        if (!$person) {
            throw new \RuntimeException('Pessoa não encontrada.');
        }

        return $this->personToCompatCustomerRow($person);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(int $customerId, bool $force = false): array
    {
        $person = $this->people()->find($customerId);
        if (!$person) {
            throw new \RuntimeException('Pessoa não encontrada para exclusão.');
        }

        $snapshot = $this->personToCompatCustomerRow($person);

        if ($force) {
            $pdo = $this->requirePdo();
            $stmtRoles = $pdo->prepare('DELETE FROM pessoas_papeis WHERE pessoa_id = :id');
            $stmtRoles->execute([':id' => $customerId]);

            $stmtPerson = $pdo->prepare('DELETE FROM pessoas WHERE id = :id');
            $stmtPerson->execute([':id' => $customerId]);

            return $snapshot;
        }

        return $this->trash($customerId, date('Y-m-d H:i:s'), 'delete-soft');
    }

    /**
     * @return array<string, mixed>
     */
    public function trash(int $customerId, string $deletedAt, ?string $deletedBy = null): array
    {
        $meta = [
            'retrato_deleted_at' => $deletedAt,
            'retrato_deleted_by' => $deletedBy,
            'retrato_status' => 'inativo',
        ];

        $row = $this->updateMeta($customerId, $meta);

        $person = $this->people()->find($customerId);
        if ($person) {
            $person->status = 'inativo';
            $person->lastSyncedAt = date('Y-m-d H:i:s');
            $this->people()->save($person);
            return $this->personToCompatCustomerRow($person);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function restore(int $customerId): array
    {
        $row = $this->updateMeta($customerId, [
            'retrato_deleted_at' => '',
            'retrato_deleted_by' => '',
            'retrato_status' => 'ativo',
        ]);

        $person = $this->people()->find($customerId);
        if ($person) {
            $person->status = 'ativo';
            $person->lastSyncedAt = date('Y-m-d H:i:s');
            $this->people()->save($person);
            return $this->personToCompatCustomerRow($person);
        }

        return $row;
    }

    private function people(): PersonRepository
    {
        if ($this->people) {
            return $this->people;
        }

        $this->people = new PersonRepository($this->requirePdo());
        return $this->people;
    }

    private function requirePdo(): PDO
    {
        if ($this->pdo) {
            return $this->pdo;
        }

        [$pdo, $error] = Database::bootstrap();
        if (!$pdo) {
            throw new \RuntimeException('Sem conexão com banco: ' . ($error ?? 'erro indefinido'));
        }

        $this->pdo = $pdo;
        return $this->pdo;
    }

    private function nextPersonId(): int
    {
        $stmt = $this->requirePdo()->query('SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM pessoas');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        return $row && isset($row['next_id']) ? (int) $row['next_id'] : 1;
    }

    private function mapCustomerToPerson(Customer $customer, ?Person $existing = null): Person
    {
        $person = $existing ?? new Person();

        if ($customer->id !== null) {
            $person->id = (int) $customer->id;
        }

        $name = trim((string) $customer->fullName);
        if ($name !== '') {
            $person->fullName = $name;
        }

        $person->email = $this->preferNonEmptyString($person->email, $customer->email);
        $person->email2 = $this->preferNonEmptyString($person->email2, $customer->email2);
        $person->phone = $this->preferNonEmptyString($person->phone, $customer->phone);
        $person->cpfCnpj = $this->preferNonEmptyString($person->cpfCnpj, $customer->cpfCnpj);
        $person->pixKey = $this->preferNonEmptyString($person->pixKey, $customer->pixKey);
        $person->instagram = $this->preferNonEmptyString($person->instagram, $customer->instagram);
        $person->street = $this->preferNonEmptyString($person->street, $customer->street);
        $person->street2 = $this->preferNonEmptyString($person->street2, $customer->street2);
        $person->number = $this->preferNonEmptyString($person->number, $customer->number);
        $person->neighborhood = $this->preferNonEmptyString($person->neighborhood, $customer->neighborhood);
        $person->city = $this->preferNonEmptyString($person->city, $customer->city);
        $person->state = $this->preferNonEmptyString($person->state, $customer->state);
        $person->zip = $this->preferNonEmptyString($person->zip, $customer->zip);
        $person->country = $this->preferNonEmptyString($person->country, $customer->country);

        $status = trim((string) $customer->status);
        if ($status !== '') {
            $person->status = $status;
        }

        $meta = $person->metadata ?? [];
        $meta['is_cliente'] = true;
        $meta['retrato_status'] = $person->status;
        $person->metadata = $this->sanitizeMetadata($meta, true);

        return $person;
    }

    private function applyCustomerUpdate(Person $existing, Customer $customer): Person
    {
        return $this->mapCustomerToPerson($customer, $existing);
    }

    /**
     * @return array<string, mixed>
     */
    private function personToCompatCustomerRow(Person $person): array
    {
        [$firstName, $lastName] = $this->splitName($person->fullName);
        $meta = $person->metadata ?? [];

        $deletedAt = $meta['retrato_deleted_at'] ?? ($meta['deleted_at'] ?? null);
        $deletedBy = $meta['retrato_deleted_by'] ?? ($meta['deleted_by'] ?? null);

        $billing = $this->filterNull([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $person->email,
            'phone' => $person->phone,
            'address_1' => $person->street,
            'address_2' => $person->street2,
            'city' => $person->city,
            'state' => $person->state,
            'postcode' => $person->zip,
            'country' => $person->country,
            'number' => $person->number,
            'neighborhood' => $person->neighborhood,
        ]);

        $shipping = $this->filterNull([
            'first_name' => $this->nullableString($meta['shipping_first_name'] ?? null),
            'last_name' => $this->nullableString($meta['shipping_last_name'] ?? null),
            'address_1' => $this->nullableString($meta['shipping_address_1'] ?? null),
            'address_2' => $this->nullableString($meta['shipping_address_2'] ?? null),
            'city' => $this->nullableString($meta['shipping_city'] ?? null),
            'state' => $this->nullableString($meta['shipping_state'] ?? null),
            'postcode' => $this->nullableString($meta['shipping_postcode'] ?? null),
            'country' => $this->nullableString($meta['shipping_country'] ?? null),
            'number' => $this->nullableString($meta['shipping_number'] ?? null),
            'neighborhood' => $this->nullableString($meta['shipping_neighborhood'] ?? null),
        ]);

        $metaData = $this->normalizeMeta($this->filterNull([
            'retrato_status' => $person->status,
            'retrato_cpf_cnpj' => $person->cpfCnpj,
            'retrato_pix_key' => $person->pixKey,
            'retrato_instagram' => $person->instagram,
            'billing_number' => $person->number,
            'billing_neighborhood' => $person->neighborhood,
            'shipping_number' => $shipping['number'] ?? null,
            'shipping_neighborhood' => $shipping['neighborhood'] ?? null,
            'retrato_deleted_at' => $deletedAt,
            'retrato_deleted_by' => $deletedBy,
        ]));

        return $this->filterNull([
            'id' => $person->id,
            'customer_id' => $person->id,
            'full_name' => $person->fullName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $person->fullName,
            'email' => $person->email,
            'email2' => $person->email2,
            'phone' => $person->phone,
            'status' => $person->status,
            'cpf_cnpj' => $person->cpfCnpj,
            'pix_key' => $person->pixKey,
            'instagram' => $person->instagram,
            'street' => $person->street,
            'street2' => $person->street2,
            'number' => $person->number,
            'neighborhood' => $person->neighborhood,
            'city' => $person->city,
            'state' => $person->state,
            'zip' => $person->zip,
            'country' => $person->country,
            'billing_country' => $person->country,
            'deleted_at' => $deletedAt,
            'deleted_by' => $deletedBy,
            'billing' => $billing,
            'shipping' => $shipping,
            'meta_data' => $metaData,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMeta(array $meta): array
    {
        $metaData = [];
        foreach ($meta as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $metaData[] = [
                'key' => $key,
                'value' => $value,
            ];
        }

        return $metaData;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $last = array_pop($parts);
        return [implode(' ', $parts), $last ?? ''];
    }

    private function preferNonEmptyString(?string $current, $incoming): ?string
    {
        $incoming = $this->nullableString($incoming);
        if ($incoming !== null) {
            return $incoming;
        }
        return $current;
    }

    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function filterNull(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if ($value === null) {
                unset($payload[$key]);
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata, bool $ensureCustomerFlag = false): array
    {
        if ($ensureCustomerFlag) {
            $metadata['is_cliente'] = true;
        }

        foreach ($metadata as $key => $value) {
            if ($value === null || $value === '') {
                unset($metadata[$key]);
            }
        }

        return $metadata;
    }
}
