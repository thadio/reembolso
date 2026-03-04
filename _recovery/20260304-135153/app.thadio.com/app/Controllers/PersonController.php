<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\Customer;
use App\Models\Vendor;
use App\Repositories\CustomerHistoryRepository;
use App\Repositories\PersonRepository;
use App\Repositories\PersonRoleRepository;
use App\Repositories\VendorRepository;
use App\Services\CustomerService;
use App\Services\PersonSyncService;
use App\Services\CatalogCustomerService;
use App\Support\Auth;
use App\Support\Html;
use App\Support\Input;
use App\Support\Phone;
use PDO;

class PersonController
{
    private ?PDO $pdo;
    private ?string $connectionError;
    private CustomerService $service;
    private ?CatalogCustomerService $catalogCustomers = null;
    private ?CustomerHistoryRepository $history;
    private PersonRepository $people;
    private PersonRoleRepository $roles;
    private VendorRepository $vendors;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->pdo = $pdo;
        $this->connectionError = $connectionError;
        $this->service = new CustomerService();
        $this->history = $pdo ? new CustomerHistoryRepository($pdo) : null;
        $this->people = new PersonRepository($pdo);
        $this->roles = new PersonRoleRepository($pdo);
        $this->vendors = new VendorRepository($pdo);
    }

    public function index(): void
    {
        $errors = [];
        $success = '';
        $statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
        $roleFilter = isset($_GET['role']) ? trim((string) $_GET['role']) : '';
        $isTrashView = $statusFilter === 'trash';
        $searchQuery = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPageOptions = [50, 100, 200];
        $perPage = (int) ($_GET['per_page'] ?? 100);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 100;
        }
        $sortKey = trim((string) ($_GET['sort_key'] ?? $_GET['sort'] ?? 'full_name'));
        $sortDir = strtolower(trim((string) ($_GET['sort_dir'] ?? $_GET['dir'] ?? 'asc'))) === 'desc' ? 'DESC' : 'ASC';
        $allowedSort = ['id', 'full_name', 'email', 'phone', 'roles', 'status', 'city_state', 'vendor'];
        if (!in_array($sortKey, $allowedSort, true)) {
            $sortKey = 'full_name';
        }
        $restoredPersonId = 0;

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['delete_id'])) {
                $customerId = (int) $_POST['delete_id'];
                if ($customerId > 0) {
                    try {
                        $this->requireAnyPermission(['people.delete', 'customers.delete', 'vendors.delete']);
                        $this->ensureCatalogCustomers();
                        $deletedAt = date('Y-m-d H:i:s');
                        $user = Auth::user();
                        $deletedBy = $user['email'] ?? null;
                        $this->catalogCustomers->trash($customerId, $deletedAt, $deletedBy);
                        $this->logHistory($customerId, 'trash', [
                            'deleted_at' => $deletedAt,
                            'deleted_by' => $deletedBy,
                        ]);
                        $success = 'Pessoa enviada para a lixeira.';
                    } catch (\Throwable $e) {
                        $errors[] = 'Erro ao excluir pessoa: ' . $e->getMessage();
                    }
                } else {
                    $errors[] = 'Pessoa inválida para exclusão.';
                }
            } elseif (isset($_POST['restore_id'])) {
                $customerId = (int) $_POST['restore_id'];
                if ($customerId > 0) {
                    try {
                        $this->requireAnyPermission(['people.restore', 'customers.restore', 'vendors.restore']);
                        $this->ensureCatalogCustomers();
                        $this->catalogCustomers->restore($customerId);
                        $this->logHistory($customerId, 'restore');
                        $success = 'Pessoa restaurada.';
                        $restoredPersonId = $customerId;
                    } catch (\Throwable $e) {
                        $errors[] = 'Erro ao restaurar pessoa: ' . $e->getMessage();
                    }
                } else {
                    $errors[] = 'Pessoa inválida para restauração.';
                }
            } elseif (isset($_POST['force_delete_id'])) {
                $customerId = (int) $_POST['force_delete_id'];
                if ($customerId > 0) {
                    try {
                        $this->requireAnyPermission(['people.force_delete', 'customers.force_delete', 'vendors.force_delete']);
                        $this->ensureCatalogCustomers();
                        $this->catalogCustomers->delete($customerId, true);
                        $this->logHistory($customerId, 'force_delete');
                        $success = 'Pessoa excluída definitivamente.';
                    } catch (\Throwable $e) {
                        $errors[] = 'Erro ao excluir definitivamente: ' . $e->getMessage();
                    }
                } else {
                    $errors[] = 'Pessoa inválida para exclusão definitiva.';
                }
            }
        }

        // Buscar pessoas com filtros
        $filters = [];
        if ($roleFilter !== '') {
            $filters['role'] = $roleFilter;
        }
        if ($statusFilter !== '') {
            $filters['status'] = $statusFilter;
        }
        if ($searchQuery !== '') {
            $filters['search'] = $searchQuery;
        }
        foreach (['id', 'full_name', 'email', 'phone', 'roles', 'status', 'city_state', 'vendor'] as $columnKey) {
            $param = 'filter_' . $columnKey;
            if (!isset($_GET[$param])) {
                continue;
            }
            $raw = trim((string) $_GET[$param]);
            if ($raw === '') {
                continue;
            }
            $filters[$param] = $raw;
        }

        $totalRows = 0;
        $totalPages = 1;
        $rows = [];
        try {
            $totalRows = $this->people->countForList($filters);
            $totalPages = max(1, (int) ceil($totalRows / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $personRows = $this->people->paginateForList($filters, $perPage, $offset, $sortKey, $sortDir);

            // Converter para formato esperado pela view
            $rows = array_map(function (array $row): array {
            $roles = [];

            if ((int) ($row['is_cliente'] ?? 0) === 1) {
                $roles[] = 'cliente';
            }
            if ((int) ($row['is_fornecedor'] ?? 0) === 1) {
                $roles[] = 'fornecedor';
            }
            if ((int) ($row['is_usuario_retratoapp'] ?? 0) === 1) {
                $roles[] = 'usuario_retratoapp';
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'full_name' => (string) ($row['full_name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'email2' => (string) ($row['email2'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'roles' => $roles,
                'status' => (string) ($row['status'] ?? ''),
                'city' => (string) ($row['city'] ?? ''),
                'state' => (string) ($row['state'] ?? ''),
                'vendor_code' => (string) ($row['vendor_code'] ?? ''),
            ];
            }, $personRows);
        } catch (\Throwable $e) {
            $errors[] = 'Erro ao carregar pessoas: ' . $e->getMessage();
            $rows = [];
            $totalRows = 0;
            $totalPages = 1;
        }

        $roleOptions = $this->roleOptions();

        View::render('pessoas/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'statusFilter' => $statusFilter,
            'roleFilter' => $roleFilter,
            'roleOptions' => $roleOptions,
            'isTrashView' => $isTrashView,
            'searchQuery' => $searchQuery,
            'filters' => $filters,
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Pessoas',
        ]);
    }

    public function form(): void
    {
        $errors = [];
        $success = '';
        $editing = false;
        $formData = $this->emptyForm();
        $history = [];
        $isTrashed = false;

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $roleOptions = $this->roleOptions();
        $requestedRole = $this->requestedRole();

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            $editingId = (int) $_GET['id'];
            if ($editingId <= 0) {
                $errors[] = 'ID inválido para edição.';
            } else {
                $this->requireAnyPermission(['people.edit', 'customers.edit', 'vendors.edit']);
                try {
                    $this->ensureCatalogCustomers();
                    $customer = $this->catalogCustomers->get($editingId);
                    $formData = $this->customerToForm($customer);
                    $person = $this->people->find($editingId);
                    $vendorRow = $this->vendors->findByPersonId($editingId);
                    $roleRows = $this->roles->listByPerson($editingId);
                    $hasVendorRole = $this->hasVendorRoleForPerson($roleRows, $vendorRow);
                    $formData = $this->mergePersonData($formData, $person, $vendorRow, $hasVendorRole);
                    $formData['roles'] = $this->resolveRoles($editingId, $person, $roleRows, $vendorRow);
                    $editing = true;
                    $isTrashed = !empty($customer['deleted_at']);
                } catch (\Throwable $e) {
                    $errors[] = 'Pessoa não encontrada.';
                }
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            $editingId = $editing ? (int) $_POST['id'] : 0;
            $this->requireAnyPermission([$editing ? 'people.edit' : 'people.create', $editing ? 'customers.edit' : 'customers.create', $editing ? 'vendors.edit' : 'vendors.create']);

            [$customer, $validationErrors] = $this->service->validate($_POST, $editing);
            $errors = array_merge($errors, $validationErrors);

            $selectedRoles = $this->normalizeRoles($_POST['roles'] ?? [], $requestedRole);

            if (empty($errors)) {
                try {
                    $this->ensureCatalogCustomers();
                    if ($editing) {
                        if ($editingId <= 0) {
                            throw new \RuntimeException('ID inválido para edição.');
                        }
                        $submittedEmail = $customer->email;
                        $currentCustomer = null;
                        try {
                            $currentCustomer = $this->catalogCustomers->get($editingId);
                        } catch (\Throwable) {
                            $currentCustomer = null;
                        }
                        if ($currentCustomer && $submittedEmail) {
                            $currentEmail = trim((string) ($currentCustomer['email'] ?? ''));
                            if ($currentEmail !== '' && strcasecmp($currentEmail, $submittedEmail) === 0) {
                                $customer->email = null;
                            }
                        }
                        $this->catalogCustomers->update($editingId, $customer);
                        $historyPayload = $this->buildUpdateHistoryPayload($currentCustomer, $customer, $submittedEmail);
                        $this->logHistory($editingId, 'update', $historyPayload);
                        $success = 'Pessoa atualizada no sistema local.';
                    } else {
                        $created = $this->catalogCustomers->create($customer);
                        $editingId = (int) ($created['id'] ?? 0);
                        if ($editingId <= 0) {
                            throw new \RuntimeException('sistema local não retornou o ID da pessoa.');
                        }
                        $this->logHistory($editingId, 'create', $this->historyPayload($customer));
                        $success = 'Pessoa criada no sistema local.';
                        $editing = true;
                    }

                    if ($editingId > 0) {
                        try {
                            $customerRow = $this->catalogCustomers->get($editingId);
                            $this->syncPerson($customerRow, $selectedRoles);
                            $formData = $this->customerToForm($customerRow);
                            $person = $this->people->find($editingId);
                            $vendorRow = $this->vendors->findByPersonId($editingId);
                            $roleRows = $this->roles->listByPerson($editingId);
                            $hasVendorRole = $this->hasVendorRoleForPerson($roleRows, $vendorRow);
                            $formData = $this->mergePersonData($formData, $person, $vendorRow, $hasVendorRole);
                            $formData['roles'] = $this->resolveRoles($editingId, $person, $roleRows, $vendorRow);
                            $isTrashed = !empty($customerRow['deleted_at']);
                        } catch (\Throwable) {
                            // Não bloqueia salvamento quando a leitura de retorno falha.
                        }
                    }

                    if ($editingId > 0) {
                        $this->roles->replaceForContext($editingId, $selectedRoles, 'people');
                    }

                    if ($editingId > 0 && in_array('fornecedor', $selectedRoles, true)) {
                        $vendor = $this->syncVendorLegacy($editingId, $_POST, $customer);
                        if ($vendor) {
                            $this->syncVendorMetadata($editingId, $vendor, $_POST);
                        }
                    }

                    if (empty($formData['id'])) {
                        $formData = array_merge($this->emptyForm(), [
                            'id' => (string) $editingId,
                            'fullName' => $customer->fullName,
                            'email' => $customer->email ?? ($_POST['email'] ?? ''),
                            'email2' => $customer->email2 ?? ($_POST['email2'] ?? ''),
                            'phone' => $customer->phone ?? '',
                            'status' => $customer->status,
                            'cpfCnpj' => $customer->cpfCnpj ?? '',
                            'pixKey' => $customer->pixKey ?? '',
                            'instagram' => $customer->instagram ?? '',
                            'street' => $customer->street ?? '',
                            'street2' => $customer->street2 ?? '',
                            'number' => $customer->number ?? '',
                            'neighborhood' => $customer->neighborhood ?? '',
                            'city' => $customer->city ?? '',
                            'state' => $customer->state ?? '',
                            'zip' => $customer->zip ?? '',
                            'country' => $customer->country ?? '',
                            'roles' => $selectedRoles,
                        ]);
                    }
                } catch (\Throwable $e) {
                    $errors[] = 'Erro ao salvar pessoa: ' . $e->getMessage();
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
                $formData['roles'] = $selectedRoles;
            }
        }

        if (!$editing && $requestedRole) {
            $formData['roles'] = $this->normalizeRoles($formData['roles'] ?? [], $requestedRole);
        }

        if ($editing && !empty($formData['id'])) {
            $history = $this->history ? $this->history->listByCustomer((int) $formData['id']) : [];
            $history = $this->decorateHistory($history);
        }

        View::render('pessoas/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'history' => $history,
            'isTrashed' => $isTrashed,
            'roleOptions' => $roleOptions,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar pessoa' : 'Nova pessoa',
        ]);
    }

    private function ensureCatalogCustomers(): void
    {
        if (!$this->catalogCustomers) {
            $this->catalogCustomers = new CatalogCustomerService(null, $this->pdo);
        }
    }

    private function requireAnyPermission(array $permissions): void
    {
        foreach ($permissions as $permission) {
            if (Auth::can($permission)) {
                return;
            }
        }
        if (!empty($permissions)) {
            Auth::requirePermission($permissions[0], $this->pdo);
        }
    }

    private function logHistory(int $customerId, string $action, array $payload = []): void
    {
        if ($this->history) {
            $this->history->log($customerId, $action, $payload);
        }
    }

    private function historyPayload(Customer $customer): array
    {
        return [
            'full_name' => $customer->fullName,
            'email' => $customer->email,
            'status' => $customer->status,
        ];
    }

    private function buildUpdateHistoryPayload(?array $beforeRow, Customer $customer, ?string $submittedEmail): array
    {
        if (!$beforeRow) {
            return [];
        }

        $before = $this->historyDataFromRow($beforeRow);
        $after = $this->historyDataFromCustomer($customer, $submittedEmail);
        $changes = [];

        foreach ($this->historyFieldLabels() as $field => $label) {
            $beforeValue = $this->normalizeHistoryValue($before[$field] ?? '');
            $afterValue = $this->normalizeHistoryValue($after[$field] ?? '');
            if ($beforeValue !== $afterValue) {
                $changes[$field] = [
                    'before' => $beforeValue,
                    'after' => $afterValue,
                ];
            }
        }

        return ['changes' => $changes];
    }

    private function historyDataFromRow(array $customerRow): array
    {
        $form = $this->customerToForm($customerRow);
        return $this->filterHistoryData($form);
    }

    private function historyDataFromCustomer(Customer $customer, ?string $emailOverride): array
    {
        $data = [
            'fullName' => $customer->fullName,
            'email' => $emailOverride ?? $customer->email ?? '',
            'email2' => $customer->email2 ?? '',
            'phone' => Phone::normalizeBrazilCell($customer->phone ?? '') ?? ($customer->phone ?? ''),
            'status' => $customer->status ?? '',
            'cpfCnpj' => $customer->cpfCnpj ?? '',
            'pixKey' => $customer->pixKey ?? '',
            'instagram' => $customer->instagram ?? '',
            'street' => $customer->street ?? '',
            'street2' => $customer->street2 ?? '',
            'number' => $customer->number ?? '',
            'neighborhood' => $customer->neighborhood ?? '',
            'city' => $customer->city ?? '',
            'state' => $customer->state ?? '',
            'zip' => $customer->zip ?? '',
            'country' => $customer->country ?? '',
        ];

        return $this->filterHistoryData($data);
    }

    private function filterHistoryData(array $data): array
    {
        $filtered = [];
        foreach ($this->historyFieldLabels() as $field => $label) {
            $filtered[$field] = $data[$field] ?? '';
        }
        return $filtered;
    }

    private function normalizeHistoryValue($value): string
    {
        return trim((string) $value);
    }

    private function decodeHistoryPayload($payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function historyFieldLabels(): array
    {
        return [
            'fullName' => 'Nome completo',
            'email' => 'E-mail',
            'email2' => 'E-mail secundário',
            'phone' => 'Telefone',
            'status' => 'Status',
            'cpfCnpj' => 'CPF/CNPJ',
            'pixKey' => 'Chave PIX',
            'instagram' => 'Instagram',
            'street' => 'Rua',
            'street2' => 'Complemento',
            'number' => 'Número',
            'neighborhood' => 'Bairro',
            'city' => 'Cidade',
            'state' => 'Estado',
            'zip' => 'CEP',
            'country' => 'País',
        ];
    }

    private function decorateHistory(array $rows): array
    {
        $labels = [
            'create' => 'Cadastro',
            'update' => 'Atualização',
            'trash' => 'Lixeira',
            'restore' => 'Restaurado',
            'force_delete' => 'Exclusão definitiva',
        ];
        $fieldLabels = $this->historyFieldLabels();

        foreach ($rows as &$row) {
            $action = (string) ($row['action'] ?? '');
            $row['action_label'] = $labels[$action] ?? $action;
            $payload = $this->decodeHistoryPayload($row['payload'] ?? null);
            if ($action === 'update') {
                $changes = [];
                $payloadChanges = $payload['changes'] ?? [];
                if (is_array($payloadChanges)) {
                    foreach ($payloadChanges as $field => $change) {
                        if (!is_array($change)) {
                            continue;
                        }
                        $changes[] = [
                            'label' => $fieldLabels[$field] ?? $field,
                            'before' => (string) ($change['before'] ?? ''),
                            'after' => (string) ($change['after'] ?? ''),
                        ];
                    }
                }
                $row['changes'] = $changes;
            }
        }
        unset($row);

        return $rows;
    }

    private function customerToForm(array $customer): array
    {
        return [
            'id' => (string) ($customer['customer_id'] ?? $customer['id'] ?? ''),
            'fullName' => $this->resolveFullName($customer),
            'email' => (string) ($customer['email'] ?? ''),
            'email2' => (string) ($customer['email2'] ?? ''),
            'phone' => Phone::normalizeBrazilCell($customer['phone'] ?? '') ?? (string) ($customer['phone'] ?? ''),
            'status' => (string) ($customer['status'] ?? 'ativo'),
            'cpfCnpj' => (string) ($customer['cpf_cnpj'] ?? ''),
            'pixKey' => (string) ($customer['pix_key'] ?? ''),
            'instagram' => (string) ($customer['instagram'] ?? ''),
            'street' => (string) ($customer['street'] ?? ''),
            'street2' => (string) ($customer['street2'] ?? ''),
            'number' => (string) ($customer['number'] ?? ''),
            'neighborhood' => (string) ($customer['neighborhood'] ?? ''),
            'city' => (string) ($customer['city'] ?? ''),
            'state' => (string) ($customer['state'] ?? ''),
            'zip' => (string) ($customer['zip'] ?? ''),
            'country' => (string) ($customer['billing_country'] ?? $customer['country'] ?? ''),
            'roles' => ['cliente'],
            'vendorCommissionRate' => '',
        ];
    }

    private function mergePersonData(array $form, ?\App\Models\Person $person, ?Vendor $vendor, bool $includeVendorInfo): array
    {
        if ($person) {
            if ($includeVendorInfo) {
                $meta = $person->metadata ?? [];
                $form['vendorCommissionRate'] = (string) ($meta['vendor_commission_rate'] ?? $form['vendorCommissionRate']);
            }
        }
        if ($includeVendorInfo && $vendor) {
            $form['vendorCommissionRate'] = $vendor->commissionRate !== null ? (string) $vendor->commissionRate : $form['vendorCommissionRate'];
        }

        return $form;
    }

    /**
     * @param array<int, array<string, mixed>> $roleRows
     * @param Vendor|array|null $vendorRow
     */
    private function hasVendorRoleForPerson(array $roleRows, Vendor|array|null $vendorRow): bool
    {
        if ($vendorRow instanceof Vendor || (is_array($vendorRow) && !empty($vendorRow))) {
            return true;
        }
        foreach ($roleRows as $row) {
            $role = is_array($row) ? (string) ($row['role'] ?? '') : (string) ($row->role ?? '');
            if (in_array($role, ['fornecedor', 'consignante'], true)) {
                return true;
            }
        }
        return false;
    }

    private function resolveFullName(array $customer): string
    {
        // Priorizar full_name (campo principal na tabela pessoas)
        $fullName = trim((string) ($customer['full_name'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        // Legacy WooCommerce fallback: first_name + last_name
        $first = trim((string) ($customer['first_name'] ?? ''));
        $last = trim((string) ($customer['last_name'] ?? ''));
        $legacy = trim($first . ' ' . $last);
        if ($legacy !== '') {
            return $legacy;
        }

        $display = trim((string) ($customer['display_name'] ?? ''));
        if ($display !== '') {
            return $display;
        }

        return (string) ($customer['email'] ?? '');
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'fullName' => '',
            'email' => '',
            'email2' => '',
            'phone' => '',
            'status' => 'ativo',
            'cpfCnpj' => '',
            'pixKey' => '',
            'instagram' => '',
            'street' => '',
            'street2' => '',
            'number' => '',
            'neighborhood' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'country' => '',
            'roles' => [],
            'vendorCommissionRate' => '',
        ];
    }

    private function syncPerson(array $customerRow, array $roles): void
    {
        if (!$this->pdo) {
            return;
        }
        try {
            $sync = new PersonSyncService($this->pdo);
            $sync->syncFromExternalCustomerRow($customerRow, ['cliente'], 'external');
        } catch (\Throwable $e) {
            error_log('Falha ao sincronizar pessoa: ' . $e->getMessage());
        }
    }

    /**
     * @param array<int, array<string, mixed>> $roleRows
     * @param Vendor|array|null $vendorRow
     * @return string[]
     */
    private function resolveRoles(int $personId, ?\App\Models\Person $person, array $roleRows, Vendor|array|null $vendorRow): array
    {
        $roles = ['cliente'];
        $hasVendor = $vendorRow instanceof Vendor || (is_array($vendorRow) && !empty($vendorRow));
        if ($hasVendor) {
            $roles[] = 'fornecedor';
        }
        foreach ($roleRows as $row) {
            $role = is_array($row) ? (string) ($row['role'] ?? '') : '';
            if ($role !== '') {
                $roles[] = $role;
            }
        }
        $roles = array_values(array_unique($roles));
        sort($roles);
        return $roles;
    }

    private function normalizeRoles($raw, ?string $requestedRole): array
    {
        $roles = [];
        if (is_array($raw)) {
            foreach ($raw as $role) {
                $role = trim((string) $role);
                if ($role !== '') {
                    $roles[] = $role;
                }
            }
        }
        if ($requestedRole) {
            $roles[] = $requestedRole;
        }
        if (!in_array('cliente', $roles, true)) {
            $roles[] = 'cliente';
        }
        $roles = array_values(array_unique($roles));
        sort($roles);
        return $roles;
    }

    private function roleOptions(): array
    {
        return [
            'cliente' => 'Cliente',
            'fornecedor' => 'Fornecedor',
            'usuario_retratoapp' => 'Usuário Retratoapp',
        ];
    }

    private function requestedRole(): ?string
    {
        $role = isset($_GET['role']) ? trim((string) $_GET['role']) : '';
        if ($role === '') {
            $role = isset($_POST['role']) ? trim((string) $_POST['role']) : '';
        }
        return $role !== '' ? $role : null;
    }

    private function syncVendorLegacy(int $personId, array $input, Customer $customer): ?Vendor
    {
        // Repositório de fornecedor agora é somente leitura (compat view em cima de pessoas).
        // Mantemos esta montagem apenas para metadados de compatibilidade.
        $vendor = new Vendor();
        $vendor->id = $personId;
        $vendor->idVendor = $personId;
        $vendor->fullName = $customer->fullName;
        $vendor->email = $customer->email ?? null;
        $vendor->phone = $customer->phone;
        $vendor->instagram = $customer->instagram;
        $vendor->cpfCnpj = $customer->cpfCnpj;
        $vendor->pixKey = $customer->pixKey;
        $vendor->commissionRate = Input::parseNumber($input['vendorCommissionRate'] ?? null);
        $vendor->street = $customer->street;
        $vendor->street2 = $customer->street2;
        $vendor->number = $customer->number;
        $vendor->neighborhood = $customer->neighborhood;
        $vendor->city = $customer->city;
        $vendor->state = $customer->state;
        $vendor->zip = $customer->zip;
        $vendor->pais = $customer->country;
        return $vendor;
    }

    private function syncVendorMetadata(int $personId, Vendor $vendor, array $input): void
    {
        if (!$this->pdo) {
            return;
        }
        $sync = new PersonSyncService($this->pdo);
        $sync->enrichMetadata($personId, [
            'is_fornecedor' => true,
            'vendor_id' => $vendor->id ?? $personId,
            'vendor_code' => $vendor->idVendor ?? $personId,
            'vendor_commission_rate' => $input['vendorCommissionRate'] ?? $vendor->commissionRate,
        ]);
    }
}
