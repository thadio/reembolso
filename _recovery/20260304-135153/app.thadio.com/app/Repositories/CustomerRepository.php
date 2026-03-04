<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Repositories\PeopleCompatViewRepository;
use PDO;

use App\Support\AuditableTrait;
class CustomerRepository
{
    use AuditableTrait;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        if ($this->pdo && \function_exists('shouldRunSchemaMigrations') && \shouldRunSchemaMigrations()) {
            // Passa a usar views de compatibilidade baseadas em `pessoas`.
            PeopleCompatViewRepository::ensure($this->pdo);
        }
    }

    public function list(): array
    {
        if (!$this->pdo) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT id, full_name, email, phone, status, cpf_cnpj, city, state FROM vw_clientes_compat ORDER BY updated_at DESC");
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function find(int $id): ?Customer
    {
        if (!$this->pdo) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM vw_clientes_compat WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? Customer::fromArray($row) : null;
    }

    public function nextId(): int
    {
        if (!$this->pdo) {
            return 1;
        }
        $stmt = $this->pdo->query("SELECT COALESCE(MAX(id) + 1, 1) AS next_id FROM pessoas");
        $row = $stmt ? $stmt->fetch() : null;
        return $row && isset($row['next_id']) ? (int) $row['next_id'] : 1;
    }

    public function save(Customer $customer): void
    {
        throw new \RuntimeException('CustomerRepository agora é somente leitura. Use PersonController/PersonSyncService para criar/atualizar pessoas.');
    }

    public function delete(int $id): void
    {
        throw new \RuntimeException('CustomerRepository agora é somente leitura. Use o módulo Pessoas para excluir.');
    }
    
    /**
     * Find customer by user ID (compatibilidade com código catálogo legado)
     * Substitui: leitura legada por ID de usuário.
     */
    public function findByUserId(int $userId): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        
        // user_id na view corresponde ao id da tabela pessoas
        $stmt = $this->pdo->prepare("SELECT * FROM vw_clientes_compat WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * Find customer by email
     * Substitui: leitura legada por email.
     */
    public function findByEmail(string $email): ?array
    {
        if (!$this->pdo || trim($email) === '') {
            return null;
        }
        
        $stmt = $this->pdo->prepare(
            "SELECT * FROM vw_clientes_compat WHERE email = :email OR email2 = :email2 LIMIT 1"
        );
        $stmt->execute([':email' => trim($email), ':email2' => trim($email)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * Find customer as array (não como Model)
     * Substitui: leitura legada por ID de cliente.
     */
    public function findAsArray(int $id): ?array
    {
        if (!$this->pdo) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM vw_clientes_compat WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * List customers for select/dropdown
     * Substitui: listagem de clientes via catálogo legado
     */
    public function listForSelect(): array
    {
        if (!$this->pdo) {
            return [];
        }
        
        $sql = "SELECT id, full_name, email, phone, cpf_cnpj 
                FROM vw_clientes_compat 
                WHERE status = 'ativo'
                ORDER BY full_name ASC";
        
        $stmt = $this->pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
    
    /**
     * Search customers (por nome, email, telefone)
     * Returns ALL matching records – no silent truncation.
     * Callers that need pagination should use limit/offset explicitly.
     */
    public function search(string $query, int $limit = 0): array
    {
        if (!$this->pdo || trim($query) === '') {
            return [];
        }
        
        $sql = "SELECT * FROM vw_clientes_compat 
                WHERE full_name LIKE :query 
                   OR email LIKE :query 
                   OR phone LIKE :query
                   OR cpf_cnpj LIKE :query
                ORDER BY full_name ASC";
        
        $params = [':query' => '%' . $query . '%'];

        if ($limit > 0) {
            $sql .= " LIMIT :limit";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        if ($limit > 0) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create customer via PersonRepository
     * Wrapper para facilitar migração
     */
    public function create(array $data): int
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Sem conexão com banco.');
        }
        
        // Delega para PersonRepository (fonte de verdade)
        $personRepo = new PersonRepository($this->pdo);
        
        // Converte dados de cliente para Person
        $person = new \App\Models\Person();
        $person->id = $data['id'] ?? null;
        $person->fullName = $data['full_name'] ?? $data['name'] ?? '';
        $person->email = $data['email'] ?? null;
        $person->phone = $data['phone'] ?? null;
        $person->cpfCnpj = $data['cpf_cnpj'] ?? null;
        $person->street = $data['address_1'] ?? $data['street'] ?? null;
        $person->street2 = $data['address_2'] ?? $data['street2'] ?? null;
        $person->city = $data['city'] ?? null;
        $person->state = $data['state'] ?? null;
        $person->zip = $data['postcode'] ?? $data['zip'] ?? null;
        $person->country = $data['country'] ?? 'BR';
        $person->status = $data['status'] ?? 'ativo';
        
        // Adiciona tipo 'cliente' ao metadata
        $metadata = $data['metadata'] ?? [];
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?? [];
        }
        $tipos = $metadata['tipos'] ?? [];
        if (!in_array('cliente', $tipos)) {
            $tipos[] = 'cliente';
        }
        $metadata['tipos'] = $tipos;
        $person->metadata = $metadata;
        
        if (!$person->id) {
            // Gerar próximo ID
            $stmt = $this->pdo->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM pessoas");
            $person->id = (int)$stmt->fetchColumn();
        }
        
        $personRepo->save($person);
        
        return $person->id;
    }
    
    /**
     * Update customer via PersonRepository
     */
    public function update(int $id, array $data): bool
    {
        if (!$this->pdo) {
            return false;
        }
        
        $personRepo = new PersonRepository($this->pdo);
        $person = $personRepo->find($id);
        
        if (!$person) {
            return false;
        }
        
        // Atualiza campos
        if (isset($data['full_name'])) {
            $person->fullName = $data['full_name'];
        }
        if (isset($data['email'])) {
            $person->email = $data['email'];
        }
        if (isset($data['phone'])) {
            $person->phone = $data['phone'];
        }
        if (isset($data['cpf_cnpj'])) {
            $person->cpfCnpj = $data['cpf_cnpj'];
        }
        if (isset($data['address_1']) || isset($data['street'])) {
            $person->street = $data['address_1'] ?? $data['street'];
        }
        if (isset($data['city'])) {
            $person->city = $data['city'];
        }
        if (isset($data['state'])) {
            $person->state = $data['state'];
        }
        if (isset($data['postcode']) || isset($data['zip'])) {
            $person->zip = $data['postcode'] ?? $data['zip'];
        }
        
        $personRepo->save($person);
        
        return true;
    }
    
    /**
     * Soft delete via PersonRepository
     */
    public function trash(int $id, string $deletedAt, int $deletedBy): bool
    {
        if (!$this->pdo) {
            return false;
        }
        
        $personRepo = new PersonRepository($this->pdo);
        $person = $personRepo->find($id);
        
        if (!$person) {
            return false;
        }
        
        $person->status = 'inativo';
        $metadata = $person->metadata ?? [];
        $metadata['deleted_at'] = $deletedAt;
        $metadata['deleted_by'] = $deletedBy;
        $person->metadata = $metadata;
        
        $personRepo->save($person);
        
        return true;
    }
    
    /**
     * Restore customer
     */
    public function restore(int $id): bool
    {
        if (!$this->pdo) {
            return false;
        }
        
        $personRepo = new PersonRepository($this->pdo);
        $person = $personRepo->find($id);
        
        if (!$person) {
            return false;
        }
        
        $person->status = 'ativo';
        $metadata = $person->metadata ?? [];
        unset($metadata['deleted_at'], $metadata['deleted_by']);
        $person->metadata = $metadata;
        
        $personRepo->save($person);
        
        return true;
    }
    
    /**
     * Permanent delete
     */
    public function permanentDelete(int $id): bool
    {
        if (!$this->pdo) {
            return false;
        }
        
        $sql = "DELETE FROM pessoas WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
