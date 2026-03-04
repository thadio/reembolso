<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\FinanceEntry;
use App\Repositories\BankAccountRepository;
use App\Repositories\FinanceEntryRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\VoucherIdentificationPatternRepository;
use App\Repositories\VoucherAccountRepository;
use App\Repositories\VoucherCreditEntryRepository;
use App\Repositories\PersonRepository;
use App\Services\PersonSyncService;
use App\Services\VoucherAccountService;
use App\Support\Auth;
use App\Support\Html;
use App\Support\Input;
use PDO;
use PDOException;

class VoucherAccountController
{
    private const EVENT_LABELS = [
        'return' => 'Devolução',
        'return_cancel' => 'Cancelamento de devolução',
        'order_cancel' => 'Cancelamento do pedido',
        'order_trash' => 'Pedido na lixeira',
        'order_delete' => 'Pedido excluído',
        'payment_refund' => 'Estorno de pagamento',
        'payment_failed' => 'Pagamento falhou',
        'payout' => 'Pagamento PIX',
    ];

    private VoucherAccountRepository $repository;
    private VoucherAccountService $service;
    private PersonSyncService $personSync;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->repository = new VoucherAccountRepository($pdo);
        $this->service = new VoucherAccountService();
        $this->personSync = new PersonSyncService($pdo);
        $this->connectionError = $connectionError;
    }

    public function index(): void
    {
        $errors = [];
        $success = '';
        $statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
        $isTrashView = $statusFilter === 'trash';
        $searchQuery = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPageOptions = [50, 100, 200];
        $perPage = (int) ($_GET['per_page'] ?? 100);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 100;
        }
        $sortKey = trim((string) ($_GET['sort_key'] ?? $_GET['sort'] ?? 'created_at'));
        $sortDir = strtolower(trim((string) ($_GET['sort_dir'] ?? $_GET['dir'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';
        $allowedSort = ['id', 'customer', 'type', 'code', 'balance', 'status', 'description', 'created_at'];
        if (!in_array($sortKey, $allowedSort, true)) {
            $sortKey = 'created_at';
        }
        $columnFilterKeys = [
            'filter_id',
            'filter_customer',
            'filter_type',
            'filter_code',
            'filter_balance',
            'filter_status',
            'filter_description',
        ];
        $columnFilters = [];

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['delete_id'])) {
                try {
                    Auth::requirePermission('voucher_accounts.delete', $this->repository->getPdo());
                    $deletedAt = date('Y-m-d H:i:s');
                    $user = Auth::user();
                    $deletedBy = $user['email'] ?? null;
                    $this->repository->trash((int) $_POST['delete_id'], $deletedAt, $deletedBy);
                    $success = 'Cupom/credito enviado para a lixeira.';
                } catch (PDOException $e) {
                    $errors[] = 'Erro ao excluir cupom/credito: ' . $e->getMessage();
                }
            } elseif (isset($_POST['restore_id'])) {
                try {
                    Auth::requirePermission('voucher_accounts.restore', $this->repository->getPdo());
                    $this->repository->restore((int) $_POST['restore_id']);
                    $success = 'Cupom/credito restaurado.';
                } catch (PDOException $e) {
                    $errors[] = 'Erro ao restaurar cupom/credito: ' . $e->getMessage();
                }
            } elseif (isset($_POST['force_delete_id'])) {
                try {
                    Auth::requirePermission('voucher_accounts.force_delete', $this->repository->getPdo());
                    $this->repository->forceDelete((int) $_POST['force_delete_id']);
                    $success = 'Cupom/credito excluido definitivamente.';
                } catch (PDOException $e) {
                    $errors[] = 'Erro ao excluir definitivamente: ' . $e->getMessage();
                }
            }
        }

        $filters = [];
        if ($statusFilter !== '') {
            $filters['status'] = $statusFilter;
        }
        if ($searchQuery !== '') {
            $filters['search'] = $searchQuery;
        }
        foreach ($columnFilterKeys as $key) {
            if (!isset($_GET[$key])) {
                continue;
            }
            $raw = trim((string) $_GET[$key]);
            if ($raw === '') {
                continue;
            }
            $filters[$key] = $raw;
            $columnFilters[$key] = $raw;
        }

        $totalRows = 0;
        $totalPages = 1;
        $rows = [];
        try {
            $totalRows = $this->repository->countForList($filters);
            $totalPages = max(1, (int) ceil($totalRows / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;
            $rows = $this->repository->paginateForList($filters, $perPage, $offset, $sortKey, $sortDir);
            $rows = $this->applyPersonDisplayToRows($rows);
            $rows = $this->applyVendorDisplayToRows($rows);
        } catch (\Throwable $e) {
            $errors[] = 'Erro ao carregar cupons/creditos: ' . $e->getMessage();
            $rows = [];
            $totalRows = 0;
            $totalPages = 1;
        }

        View::render('voucher_accounts/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'statusFilter' => $statusFilter,
            'isTrashView' => $isTrashView,
            'searchQuery' => $searchQuery,
            'filters' => $columnFilters,
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'typeOptions' => VoucherAccountService::typeOptions(),
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => 'Cupons e creditos',
        ]);
    }

    public function form(): void
    {
        $errors = [];
        $success = '';
        $editing = false;
        $formData = $this->emptyForm();
        $vendorInfo = null;
        $voucherStatement = [
            'entries' => [],
            'opening_balance' => null,
            'current_balance' => null,
            'total_usage' => 0.0,
            'error' => null,
        ];
        $handledPayout = false;
        $account = null;

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $customerOptions = $this->listCustomerOptions();
        $bankAccountOptions = [];
        if ($this->repository->getPdo()) {
            $bankAccountRepo = new BankAccountRepository($this->repository->getPdo());
            $bankAccountOptions = $bankAccountRepo->active();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payout_action'])) {
            Auth::requirePermission('voucher_accounts.payout', $this->repository->getPdo());
            $handledPayout = true;
            $payoutResult = $this->handlePixPayout($_POST);
            $errors = array_merge($errors, $payoutResult['errors'] ?? []);
            $success = $payoutResult['success'] ?? '';
            $account = $payoutResult['account'] ?? null;
            if ($account) {
                $editing = true;
                $displayCustomer = $this->resolveVoucherCustomerDisplay($account);
                if (!empty($displayCustomer['name'])) {
                    $account->customerName = $displayCustomer['name'];
                }
                if (!empty($displayCustomer['email'])) {
                    $account->customerEmail = $displayCustomer['email'];
                }
                $formData = $this->accountToForm($account);
                $voucherStatement = $this->buildVoucherStatement($account);
                $vendorInfo = $this->resolveVendorInfo($account);
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        if (!$handledPayout && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
            Auth::requirePermission('voucher_accounts.view', $this->repository->getPdo());
            $editing = true;

            // Auto-correção: sincronizar customer_name/email com pessoas
            // antes de exibir, garantindo dados sempre atualizados.
            $this->repository->syncCustomerDataForAccount((int) $_GET['id']);

            $account = $this->repository->find((int) $_GET['id']);
            if ($account) {
                $displayCustomer = $this->resolveVoucherCustomerDisplay($account);
                if (!empty($displayCustomer['name'])) {
                    $account->customerName = $displayCustomer['name'];
                }
                if (!empty($displayCustomer['email'])) {
                    $account->customerEmail = $displayCustomer['email'];
                }
                $formData = $this->accountToForm($account);
                $voucherStatement = $this->buildVoucherStatement($account);
                $vendorInfo = $this->resolveVendorInfo($account);
            } else {
                $errors[] = 'Cupom/credito nao encontrado.';
                $editing = false;
            }
        }

        if (!$handledPayout && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $editing = isset($_POST['id']) && $_POST['id'] !== '';
            Auth::requirePermission($editing ? 'voucher_accounts.edit' : 'voucher_accounts.create', $this->repository->getPdo());
            [$account, $errors] = $this->service->validate($_POST);
            $editing = (bool) ($account->id ?? false);

            // PROTEÇÃO: impedir troca de pessoa_id em cupom com movimentações.
            // Uma vez que existem lançamentos no extrato, a titularidade não pode mudar
            // pois isso criaria divergência entre o dono do cupom e o histórico financeiro.
            if (empty($errors) && $editing && $account->id) {
                $existing = $this->repository->find((int) $account->id, true);
                if ($existing) {
                    $oldPersonId = (int) ($existing->personId ?? 0);
                    $newPersonId = (int) ($account->personId ?? 0);
                    if ($oldPersonId > 0 && $newPersonId > 0 && $oldPersonId !== $newPersonId) {
                        if ($this->repository->hasMovements((int) $account->id)) {
                            $errors[] = 'Nao e possivel alterar a pessoa (ID) deste cupom pois ja existem movimentacoes no extrato. '
                                . 'Para transferir, crie um novo cupom para a pessoa desejada.';
                        }
                    }
                }
            }

            if (empty($errors)) {
                $personId = (int) ($account->personId ?? 0);
                $pdo = $this->repository->getPdo();
                if ($personId <= 0) {
                    $errors[] = 'Pessoa informada e invalida.';
                } elseif (!$pdo) {
                    $errors[] = 'Sem conexão com banco para validar pessoa.';
                } else {
                    $peopleRepo = new PersonRepository($pdo);
                    $person = $peopleRepo->find($personId);
                    if (!$person) {
                        $errors[] = 'Pessoa informada e invalida.';
                    } else {
                        $account->personId = $personId;
                        $this->personSync->assignRole($personId, 'cliente', 'voucher_accounts');

                        // REGRA DE OURO: pessoa_id é a ÚNICA fonte da verdade.
                        // customer_name/customer_email são snapshots de cache
                        // derivados SEMPRE de pessoas (tabela canônica).
                        // Nunca usar Vendor como fonte alternativa de nome — 
                        // vw_fornecedores_compat é a mesma tabela pessoas filtrada.
                        $account->customerName = trim((string) ($person->fullName ?? ''));
                        $account->customerEmail = trim((string) ($person->email ?? '')) ?: null;
                    }
                }
            }
            if (empty($errors) && !$editing && (string) ($account->type ?? '') === 'credito') {
                $personId = (int) ($account->personId ?? 0);
                if ($personId > 0 && $this->personHasRole($personId, 'fornecedor')) {
                    $existingCredit = $this->repository->findActiveCreditByPerson($personId);
                    if ($existingCredit && (int) ($existingCredit->id ?? 0) > 0) {
                        $errors[] = 'Esta fornecedora ja possui ledger de credito ativo (ID '
                            . (int) $existingCredit->id
                            . '). Use o ledger existente.';
                    }
                }
            }
            if (empty($errors)) {
                try {
                    $this->repository->save($account);
                    $success = $editing ? 'Cupom/credito atualizado com sucesso.' : 'Cupom/credito salvo com sucesso.';
                    if ($account->id) {
                        $reloaded = $this->repository->find((int) $account->id, true);
                        if ($reloaded) {
                            $account = $reloaded;
                        }
                    }
                    $formData = $this->accountToForm($account);
                    $voucherStatement = $this->buildVoucherStatement($account);
                    $vendorInfo = $this->resolveVendorInfo($account);
                } catch (PDOException $e) {
                    if ((int) $e->getCode() === 23000) {
                        $errors[] = 'Codigo do cupom ja existe. Use um valor unico.';
                    } else {
                        $errors[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                }
            } else {
                $formData = array_merge($this->emptyForm(), $_POST);
            }
        }

        $identificationOptions = [];
        if ($this->repository->getPdo()) {
            $patternRepo = new VoucherIdentificationPatternRepository($this->repository->getPdo());
            $identificationOptions = $patternRepo->active();
        }

        $currentLabel = trim((string) ($formData['label'] ?? ''));
        if ($currentLabel !== '') {
            $alreadyListed = false;
            foreach ($identificationOptions as $option) {
                if (trim((string) ($option['label'] ?? '')) === $currentLabel) {
                    $alreadyListed = true;
                    break;
                }
            }
            if (!$alreadyListed) {
                $identificationOptions[] = [
                    'id' => 0,
                    'label' => $currentLabel,
                    'description' => null,
                    'status' => 'inativo',
                ];
            }
        }

        View::render('voucher_accounts/form', [
            'formData' => $formData,
            'errors' => $errors,
            'success' => $success,
            'editing' => $editing,
            'customerOptions' => $customerOptions,
            'identificationOptions' => $identificationOptions,
            'typeOptions' => VoucherAccountService::typeOptions(),
            'voucherStatement' => $voucherStatement,
            'vendorInfo' => $vendorInfo,
            'bankAccountOptions' => $bankAccountOptions,
            'esc' => [Html::class, 'esc'],
        ], [
            'title' => $editing ? 'Editar cupom/credito' : 'Novo cupom/credito',
        ]);
    }

    public function statementPrint(): void
    {
        $errors = [];
        $account = null;
        $statement = [
            'entries' => [],
            'opening_balance' => 0.0,
            'current_balance' => 0.0,
            'total_usage' => 0.0,
            'error' => null,
        ];

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        Auth::requirePermission('voucher_accounts.view', $this->repository->getPdo());

        $accountId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($accountId <= 0) {
            $errors[] = 'Cupom/credito nao encontrado.';
        } else {
            $account = $this->repository->find($accountId);
            if (!$account) {
                $errors[] = 'Cupom/credito nao encontrado.';
            }
        }

        $periodStart = $this->normalizeStatementDate($_GET['start'] ?? null);
        $periodEnd = $this->normalizeStatementDate($_GET['end'] ?? null);
        if ($periodStart && $periodEnd && $periodStart > $periodEnd) {
            $errors[] = 'Periodo invalido. A data inicial deve ser menor ou igual a data final.';
            [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
        }

        if ($account) {
            $displayCustomer = $this->resolveVoucherCustomerDisplay($account);
            if (!empty($displayCustomer['name'])) {
                $account->customerName = $displayCustomer['name'];
            }
            if (!empty($displayCustomer['email'])) {
                $account->customerEmail = $displayCustomer['email'];
            }
        }

        if ($account) {
            $statement = $this->buildVoucherStatementForPrint($account, $periodStart, $periodEnd);
        }

        View::render('voucher_accounts/statement_print', [
            'account' => $account,
            'statement' => $statement,
            'errors' => $errors,
            'period' => [
                'start' => $periodStart,
                'end' => $periodEnd,
            ],
            'esc' => [Html::class, 'esc'],
        ], [
            'layout' => __DIR__ . '/../Views/print-layout.php',
            'title' => 'Extrato de cupom/credito',
        ]);
    }

    private function listCustomerOptions(): array
    {
        $pdo = $this->repository->getPdo();
        if (!$pdo) {
            return [];
        }

        $sql = "SELECT p.id, p.full_name, p.email
                FROM pessoas p
                WHERE EXISTS (
                    SELECT 1
                    FROM pessoas_papeis pp
                    WHERE pp.pessoa_id = p.id
                      AND pp.role = 'cliente'
                )
                ORDER BY p.full_name ASC, p.id ASC
                ";
        $stmt = $pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];
        $options = [];
        foreach ($rows as $row) {
            $personId = (int) ($row['id'] ?? 0);
            if ($personId <= 0) {
                continue;
            }
            $name = trim((string) ($row['full_name'] ?? ''));
            if ($name === '') {
                $name = 'Cliente #' . $personId;
            }
            $options[] = [
                'id' => $personId,
                'name' => $name,
                'email' => $row['email'] ?? '',
            ];
        }
        return $options;
    }

    /**
     * DESATIVADO — applyPersonDisplayToRows já resolve nome/email
     * a partir de pessoas (fonte canônica). Sobrescrever com dados
     * da view de fornecedor criava divergência de nomes.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyVendorDisplayToRows(array $rows): array
    {
        // Não sobrescrever customer_name/customer_email com dados do Vendor.
        // A fonte da verdade é SEMPRE pessoas (via applyPersonDisplayToRows).
        return $rows;
    }

    /**
     * Resolve o nome e email canônicos do dono do cupom.
     *
     * REGRA DE OURO: pessoa_id é a ÚNICA fonte da verdade.
     * A tabela `pessoas` é SEMPRE a fonte primária de nome e email.
     * Dados de snapshot (customer_name/customer_email gravados no banco)
     * são usados APENAS como último fallback caso a pessoa não exista mais.
     *
     * Isso garante que TODAS as telas (hint, seção PIX, extrato, listagem)
     * mostrem os MESMOS dados para o mesmo cupom.
     *
     * @return array{name: ?string, email: ?string}
     */
    private function resolveVoucherCustomerDisplay($account): array
    {
        $personId = (int) ($account->personId ?? 0);
        $pdo = $this->repository->getPdo();

        // 1) Fonte da verdade: tabela pessoas (via pessoa_id)
        if ($pdo && $personId > 0) {
            $peopleRepo = new PersonRepository($pdo);
            $person = $peopleRepo->find($personId);
            if ($person) {
                $name = trim((string) ($person->fullName ?? ''));
                $email = trim((string) ($person->email ?? ''));
                if ($name !== '' || $email !== '') {
                    return [
                        'name' => $name !== '' ? $name : null,
                        'email' => $email !== '' ? $email : null,
                    ];
                }
            }
        }

        // 2) Fallback: snapshot gravado no banco (customer_name/customer_email)
        $name = trim((string) ($account->customerName ?? ''));
        $email = trim((string) ($account->customerEmail ?? ''));

        if ($name !== '' || $email !== '') {
            return [
                'name' => $name !== '' ? $name : null,
                'email' => $email !== '' ? $email : null,
            ];
        }

        return ['name' => null, 'email' => null];
    }

    /**
     * Resolve dados do fornecedor/dono para a seção de pagamento PIX.
     *
     * REGRA DE OURO: pessoa_id é a ÚNICA fonte da verdade.
     * A tabela `pessoas` é SEMPRE a fonte primária.
     * A view de fornecedor NÃO sobrescreve os dados da pessoa.
     *
     * @return array{vendor_pessoa_id: int, name: ?string, email: ?string, pix_key: ?string}|null
     */
    private function resolveVendorInfo($account): ?array
    {
        $pdo = $this->repository->getPdo();
        $personId = (int) ($account->personId ?? 0);
        if (!$pdo || !$account || $personId <= 0) {
            return null;
        }

        $peopleRepo = new PersonRepository($pdo);
        $person = $peopleRepo->find($personId);
        if (!$person) {
            return null;
        }

        // Fonte da verdade: tabela pessoas
        $name = trim((string) ($person->fullName ?? ''));
        $email = trim((string) ($person->email ?? ''));
        $pixKey = trim((string) ($person->pixKey ?? ''));

        return [
            'vendor_pessoa_id' => $personId,
            'name' => $name !== '' ? $name : null,
            'email' => $email !== '' ? $email : null,
            'pix_key' => $pixKey !== '' ? $pixKey : null,
        ];
    }

    private function personHasRole(int $personId, string $role): bool
    {
        $pdo = $this->repository->getPdo();
        if (!$pdo || $personId <= 0 || trim($role) === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM pessoas_papeis
             WHERE pessoa_id = :pid
               AND role = :role
             LIMIT 1'
        );
        $stmt->execute([
            ':pid' => $personId,
            ':role' => $role,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array{account: ?object, success: string, errors: array<int, string>}
     */
    private function handlePixPayout(array $input): array
    {
        $errors = [];
        $success = '';
        $account = null;

        $voucherId = isset($input['id']) ? (int) $input['id'] : (int) ($input['voucher_id'] ?? 0);
        if ($voucherId <= 0) {
            $errors[] = 'Cupom/credito invalido.';
            return ['account' => null, 'success' => '', 'errors' => $errors];
        }

        $account = $this->repository->find($voucherId);
        if (!$account) {
            $errors[] = 'Cupom/credito nao encontrado.';
            return ['account' => null, 'success' => '', 'errors' => $errors];
        }

        // Block PIX payout for consignment-scoped vouchers — must use Consignação > Pagamentos
        if ((string) ($account->scope ?? '') === 'consignacao') {
            $errors[] = 'Pagamentos de consignação devem ser feitos pela aba Consignação > Pagamentos.';
            return ['account' => $account, 'success' => '', 'errors' => $errors];
        }

        if ((string) ($account->type ?? '') !== 'credito') {
            $errors[] = 'Somente creditos de consignacao permitem pagamento PIX.';
        }

        $amount = Input::parseNumber($input['payout_amount'] ?? null);
        if ($amount === null || $amount <= 0) {
            $errors[] = 'Valor do pagamento deve ser maior que zero.';
        }

        $availableBalance = isset($account->balance) ? (float) $account->balance : 0.0;
        if ($amount !== null && $amount > $availableBalance + 0.0001) {
            $errors[] = 'Valor do pagamento excede o saldo disponivel.';
        }

        $eventAt = $this->normalizeDateTimeLocal($input['payout_paid_at'] ?? null) ?? date('Y-m-d H:i:s');
        $notes = trim((string) ($input['payout_notes'] ?? ''));
        $pixKeyInput = trim((string) ($input['pix_key'] ?? ''));
        $originBankAccountId = isset($input['payout_bank_account_id']) ? (int) $input['payout_bank_account_id'] : 0;

        $pdo = $this->repository->getPdo();
        if (!$pdo) {
            $errors[] = 'Sem conexão com banco para registrar pagamento.';
            return ['account' => $account, 'success' => '', 'errors' => $errors];
        }

        $bankAccountRepo = new BankAccountRepository($pdo);
        $activeBankAccounts = $bankAccountRepo->active();
        $bankAccountMap = [];
        foreach ($activeBankAccounts as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $bankAccountMap[$id] = $row;
            }
        }
        if ($originBankAccountId <= 0 || !isset($bankAccountMap[$originBankAccountId])) {
            $errors[] = 'Selecione a conta de origem do PIX.';
        }
        $originBankAccount = $bankAccountMap[$originBankAccountId] ?? null;

        $vendorPersonId = (int) ($account->personId ?? 0);
        $peopleRepo = new PersonRepository($pdo);
        $person = $vendorPersonId > 0 ? $peopleRepo->find($vendorPersonId) : null;
        if (!$person) {
            $errors[] = 'Pessoa vinculada ao cupom/credito nao encontrada.';
        }

        // PROTEÇÃO: sincronizar customer_name/email antes do payout para
        // garantir que os dados exibidos e gravados sejam consistentes.
        if ($person) {
            $this->repository->syncCustomerDataForAccount((int) $account->id);
        }

        $pixKey = $pixKeyInput !== '' ? $pixKeyInput : ($person && $person->pixKey ? $person->pixKey : '');

        if (!empty($errors)) {
            return ['account' => $account, 'success' => '', 'errors' => $errors];
        }

        $creditRepo = new VoucherCreditEntryRepository($pdo);
        $paymentMethodRepo = new PaymentMethodRepository($pdo);
        $financeRepo = new FinanceEntryRepository($pdo);
        $pixPaymentMethodId = $this->resolvePixPaymentMethodId($paymentMethodRepo);
        $entryData = [
            'voucher_account_id' => (int) $account->id,
            'vendor_pessoa_id' => $vendorPersonId > 0 ? $vendorPersonId : null,
            'order_id' => 0,
            'order_item_id' => 0,
            'product_id' => null,
            'variation_id' => null,
            'sku' => null,
            'product_name' => 'Pagamento PIX fornecedor',
            'quantity' => 1,
            'unit_price' => null,
            'line_total' => null,
            'percent' => null,
            'credit_amount' => $amount,
            'sold_at' => null,
            'buyer_name' => null,
            'buyer_email' => null,
            'type' => 'debito',
            'event_type' => 'payout',
            'event_id' => $this->generatePayoutEventId((int) $account->id),
            'event_label' => 'Pagamento PIX',
            'event_notes' => $this->buildPayoutNotes($pixKey, $notes),
            'event_at' => $eventAt,
        ];

        $bankLabel = '';
        if ($originBankAccount) {
            $bankName = trim((string) ($originBankAccount['bank_name'] ?? ''));
            $accountLabel = trim((string) ($originBankAccount['label'] ?? ''));
            $bankLabel = $bankName !== '' && $accountLabel !== ''
                ? $bankName . ' · ' . $accountLabel
                : ($accountLabel !== '' ? $accountLabel : ('Conta #' . $originBankAccountId));
        }

        $vendorName = trim((string) ($person->fullName ?? ''));
        $financeDescription = 'Pagamento PIX fornecedor';
        if ($vendorName !== '') {
            $financeDescription .= ' - ' . $vendorName;
        }
        $financeDescription .= ' (crédito #' . (int) $account->id . ')';

        $financeNotesPayload = [
            'tag' => '[AUTO_VOUCHER_PAYOUT]',
            'voucher_account_id' => (int) $account->id,
            'vendor_pessoa_id' => $vendorPersonId > 0 ? $vendorPersonId : null,
            'pix_key' => $pixKey !== '' ? $pixKey : null,
            'origin_bank_account_id' => $originBankAccountId,
            'origin_bank_account_label' => $bankLabel,
            'payout_notes' => $notes !== '' ? $notes : null,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        $financeNotes = '[AUTO_VOUCHER_PAYOUT] ' . json_encode($financeNotesPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $startedTransaction = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $ok = $creditRepo->insert($entryData);
            if (!$ok) {
                throw new \RuntimeException('Pagamento ja registrado para este cupom/credito.');
            }

            $this->repository->debitBalance((int) $account->id, $amount);

            $financeEntry = FinanceEntry::fromArray([
                'type' => 'pagar',
                'description' => $financeDescription,
                'supplier_pessoa_id' => $vendorPersonId > 0 ? $vendorPersonId : null,
                'amount' => $amount,
                'due_date' => substr($eventAt, 0, 10),
                'status' => 'pago',
                'paid_at' => $eventAt,
                'paid_amount' => $amount,
                'bank_account_id' => $originBankAccountId,
                'payment_method_id' => $pixPaymentMethodId,
                'notes' => $financeNotes,
            ]);
            $financeRepo->save($financeEntry);

            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Erro ao registrar pagamento: ' . $e->getMessage();
            return ['account' => $account, 'success' => '', 'errors' => $errors];
        }

        $account = $this->repository->find((int) $account->id, true) ?? $account;
        $success = 'Pagamento PIX registrado, saldo atualizado e lançamento financeiro criado.';

        return ['account' => $account, 'success' => $success, 'errors' => $errors];
    }

    private function normalizeDateTimeLocal(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = str_replace('T', ' ', $value);
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function generatePayoutEventId(int $voucherId): int
    {
        $base = (int) round(microtime(true) * 1000);
        return $base > 0 ? $base : max(1, $voucherId);
    }

    private function buildPayoutNotes(string $pixKey, string $notes): string
    {
        $parts = [];
        if ($pixKey !== '') {
            $parts[] = 'PIX ' . $this->trimNotes($pixKey, 120);
        }
        if ($notes !== '') {
            $parts[] = $this->trimNotes($notes, 160);
        }
        return implode(' | ', $parts);
    }

    private function resolvePixPaymentMethodId(PaymentMethodRepository $repository): ?int
    {
        $methods = $repository->active();
        if (empty($methods)) {
            return null;
        }

        foreach ($methods as $method) {
            $methodId = (int) ($method['id'] ?? 0);
            $methodType = strtolower(trim((string) ($method['type'] ?? '')));
            if ($methodId > 0 && $methodType === 'pix') {
                return $methodId;
            }
        }

        foreach ($methods as $method) {
            $methodId = (int) ($method['id'] ?? 0);
            $name = strtolower(trim((string) ($method['name'] ?? '')));
            if ($methodId > 0 && str_contains($name, 'pix')) {
                return $methodId;
            }
        }

        return null;
    }

    private function accountToForm($account): array
    {
        return [
            'id' => $account->id ?? '',
            'pessoa_id' => $account->personId ?? '',
            'customer_name' => $account->customerName ?? '',
            'customer_email' => $account->customerEmail ?? '',
            'label' => $account->label ?? '',
            'type' => $account->type ?? 'credito',
            'code' => $account->code ?? '',
            'balance' => isset($account->balance) ? number_format((float) $account->balance, 2, '.', '') : '0.00',
            'status' => $account->status ?? 'ativo',
            'description' => $account->description ?? '',
        ];
    }

    private function emptyForm(): array
    {
        return [
            'id' => '',
            'pessoa_id' => '',
            'label' => '',
            'type' => 'credito',
            'code' => '',
            'balance' => '0.00',
            'status' => 'ativo',
            'description' => '',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyPersonDisplayToRows(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }
        $pdo = $this->repository->getPdo();
        if (!$pdo) {
            return $rows;
        }

        $personIds = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['pessoa_id'] ?? 0);
            if ($pid > 0) {
                $personIds[$pid] = true;
            }
        }
        if (empty($personIds)) {
            return $rows;
        }

    $peopleRepo = new PersonRepository($pdo);
        $peopleMap = $peopleRepo->findByIds(array_keys($personIds));
        if (empty($peopleMap)) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $pid = (int) ($row['pessoa_id'] ?? 0);
            if ($pid <= 0 || !isset($peopleMap[$pid])) {
                continue;
            }
            $person = $peopleMap[$pid];
            // SEMPRE usar dados de pessoas como fonte da verdade,
            // sobrescrevendo qualquer snapshot desatualizado no banco.
            if ($person->fullName !== '') {
                $row['customer_name'] = $person->fullName;
            }
            if ($person->email) {
                $row['customer_email'] = $person->email;
            }
        }
        unset($row);

        return $rows;
    }

    private function buildVoucherStatement($account): array
    {
        $statement = [
            'entries' => [],
            'opening_balance' => null,
            'current_balance' => isset($account->balance) ? (float) $account->balance : 0.0,
            'total_usage' => 0.0,
            'error' => null,
        ];

        if (!$account || !$account->id) {
            return $statement;
        }

        $entries = [];
        $totalUsage = 0.0;

        $voucherIds = $this->resolveVoucherIdsForAccount($account);

        if ($this->repository->getPdo()) {
            try {
                $creditRepo = new VoucherCreditEntryRepository($this->repository->getPdo());
                $creditRows = [];
                if (!empty($voucherIds)) {
                    if (count($voucherIds) === 1) {
                        $creditRows = $creditRepo->listByVoucher($voucherIds[0]);
                    } else {
                        $creditRows = $creditRepo->listByVoucherIds($voucherIds);
                    }
                }
                foreach ($creditRows as $row) {
                    $amount = (float) ($row['credit_amount'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }
                    $isDebit = $this->isDebitEntry($row);
                    $entries[] = [
                        'date' => $this->resolveMovementDate($row),
                        'type' => $isDebit ? 'debito' : 'credito',
                        'description' => $this->formatMovementDescription($row, $isDebit),
                        'amount' => $isDebit ? -$amount : $amount,
                        'order_id' => isset($row['order_id']) ? (int) $row['order_id'] : null,
                    ];
                }
                if (empty($creditRows)) {
                    $fallbackRows = $this->resolveLegacyCreditEntries($account);
                    foreach ($fallbackRows as $row) {
                        $entries[] = $row;
                    }
                }
            } catch (\Throwable $e) {
                if (!$statement['error']) {
                    $statement['error'] = 'Erro ao carregar creditos consignados: ' . $e->getMessage();
                }
            }
        }

        usort($entries, function (array $a, array $b): int {
            $timeA = isset($a['date']) ? strtotime((string) $a['date']) : 0;
            $timeB = isset($b['date']) ? strtotime((string) $b['date']) : 0;
            if ($timeA === $timeB) {
                $typeA = ($a['type'] ?? '') === 'credito' ? 0 : 1;
                $typeB = ($b['type'] ?? '') === 'credito' ? 0 : 1;
                if ($typeA === $typeB) {
                    return ((int) ($a['order_id'] ?? 0)) <=> ((int) ($b['order_id'] ?? 0));
                }
                return $typeA <=> $typeB;
            }
            return $timeA <=> $timeB;
        });

        $running = 0.0;
        foreach ($entries as &$entry) {
            $running += (float) ($entry['amount'] ?? 0);
            $entry['balance'] = $running;
        }
        unset($entry);

        $storedBalance = isset($account->balance) ? (float) $account->balance : $running;
        $delta = $storedBalance - $running;
        if (abs($delta) > 0.01) {
            $running += $delta;
            $entries[] = [
                'date' => $account->updatedAt ?? $account->createdAt ?? date('Y-m-d H:i:s'),
                'type' => $delta >= 0 ? 'credito' : 'debito',
                'description' => 'Ajuste de saldo',
                'amount' => $delta,
                'balance' => $running,
                'order_id' => null,
            ];
        }

        $statement['entries'] = $entries;
        $statement['opening_balance'] = 0.0;
        $statement['current_balance'] = $running;
        $statement['total_usage'] = $totalUsage;

        return $statement;
    }

    /**
     * @return int[]
     */
    private function resolveVoucherIdsForAccount($account): array
    {
        $voucherIds = [];
        $accountId = (int) ($account->id ?? 0);
        if ($accountId > 0) {
            $voucherIds[] = $accountId;
        }

        $personId = (int) ($account->personId ?? 0);
        if ($personId > 0) {
            $rows = $this->repository->listByPerson($personId, true);
            $voucherIds = [];
            foreach ($rows as $row) {
                if ((string) ($row['type'] ?? '') !== 'credito') {
                    continue;
                }
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $voucherIds[] = $id;
                }
            }
        }

        $voucherIds = array_values(array_unique(array_filter($voucherIds, function (int $id): bool {
            return $id > 0;
        })));

        return $voucherIds;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveLegacyCreditEntries($account): array
    {
        $personId = (int) ($account->personId ?? 0);
        if ($personId <= 0) {
            return [];
        }

        $rows = $this->repository->listByPerson($personId, true);
        $entries = [];
        foreach ($rows as $row) {
            if ((string) ($row['type'] ?? '') !== 'credito') {
                continue;
            }
            $description = (string) ($row['description'] ?? '');
            if ($description === '') {
                continue;
            }

            $orderId = null;
            if (preg_match('/pedido\\s*#?\\s*(\\d+)/i', $description, $match)) {
                $orderId = (int) $match[1];
            }
            $soldAt = null;
            if (preg_match('/Data da venda:\\s*(\\d{4}-\\d{2}-\\d{2})/i', $description, $match)) {
                $soldAt = $match[1];
            }

            $lines = preg_split('/\\r?\\n/', $description);
            if (!$lines) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || stripos($line, 'SKU') === false) {
                    continue;
                }
                $line = ltrim($line, "- \t");
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) < 2) {
                    continue;
                }

                $sku = trim(str_ireplace('SKU', '', $parts[0]));
                $productName = $parts[1] ?? '';
                $qty = $this->parseIntValue($parts[2] ?? '', 1);
                $unitPrice = $this->parseMoneyValue($parts[3] ?? '');
                $lineTotal = $this->parseMoneyValue($parts[4] ?? '');
                $credit = $this->parseMoneyValue($parts[5] ?? '');
                $percent = $this->parsePercentValue($parts[5] ?? '');

                if ($credit <= 0) {
                    continue;
                }

                $entries[] = [
                    'date' => $soldAt ?? ($row['created_at'] ?? null),
                    'type' => 'credito',
                    'description' => $this->formatLegacyDescription($orderId, $sku, $productName, $soldAt, $qty, $unitPrice, $lineTotal, $credit, $percent),
                    'amount' => $credit,
                    'order_id' => $orderId,
                    'sku' => $sku,
                    'product_name' => $productName,
                ];
            }
        }

        return $entries;
    }

    private function formatLegacyDescription(
        ?int $orderId,
        string $sku,
        string $productName,
        ?string $soldAt,
        int $qty,
        ?float $unitPrice,
        ?float $lineTotal,
        float $credit,
        ?float $percent
    ): string {
        $parts = [];
        if ($orderId) {
            $parts[] = 'Pedido #' . $orderId;
        }
        if ($sku !== '') {
            $parts[] = 'SKU ' . $sku;
        }
        if ($productName !== '') {
            $parts[] = $productName;
        }
        if ($soldAt) {
            $parts[] = 'Venda ' . $soldAt;
        }
        if ($unitPrice !== null && $unitPrice > 0) {
            $parts[] = 'Valor produto R$ ' . $this->formatMoneyLabel($unitPrice);
        }
        if ($qty > 1) {
            $parts[] = 'Qtd ' . $qty;
        }
        if ($lineTotal !== null && $lineTotal > 0) {
            $parts[] = 'Total R$ ' . $this->formatMoneyLabel($lineTotal);
        }
        $percentLabel = $percent !== null ? number_format($percent, 2, ',', '.') . '%' : '-';
        $parts[] = 'Crédito R$ ' . $this->formatMoneyLabel($credit) . ' (' . $percentLabel . ')';

        return implode(' | ', $parts);
    }

    private function parseMoneyValue(string $value): float
    {
        if (!preg_match('/([0-9][0-9\\.,]*)/', $value, $matches)) {
            return 0.0;
        }
        $raw = str_replace('.', '', $matches[1]);
        $raw = str_replace(',', '.', $raw);
        return (float) $raw;
    }

    private function parsePercentValue(string $value): ?float
    {
        if (!preg_match('/([0-9][0-9\\.,]*)\\s*%/', $value, $matches)) {
            return null;
        }
        $raw = str_replace('.', '', $matches[1]);
        $raw = str_replace(',', '.', $raw);
        return (float) $raw;
    }

    private function parseIntValue(string $value, int $default = 1): int
    {
        if (!preg_match('/\\d+/', $value, $matches)) {
            return $default;
        }
        return (int) $matches[0];
    }

    private function formatCreditDescription(array $entry): string
    {
        $sku = trim((string) ($entry['sku'] ?? ''));
        $productName = trim((string) ($entry['product_name'] ?? ''));
        $soldAt = $entry['sold_at'] ?? null;
        $orderId = (int) ($entry['order_id'] ?? 0);
        $buyerName = trim((string) ($entry['buyer_name'] ?? ''));
        $buyerEmail = trim((string) ($entry['buyer_email'] ?? ''));
        $qty = (int) ($entry['quantity'] ?? 1);
        $unitPrice = (float) ($entry['unit_price'] ?? 0);
        $lineTotal = (float) ($entry['line_total'] ?? 0);
        $credit = (float) ($entry['credit_amount'] ?? 0);
        $percent = $entry['percent'] !== null ? (float) $entry['percent'] : null;

        if ($percent === null && $lineTotal > 0 && $credit > 0) {
            $percent = round(($credit / $lineTotal) * 100, 2);
        }

        $parts = [];
        if ($orderId > 0) {
            $parts[] = 'Pedido #' . $orderId;
        }
        if ($sku !== '') {
            $parts[] = 'SKU ' . $sku;
        }
        if ($productName !== '') {
            $parts[] = $productName;
        }
        if ($soldAt) {
            $parts[] = 'Venda ' . substr((string) $soldAt, 0, 10);
        }
        $buyerLabel = $buyerName !== '' ? $buyerName : 'Cliente';
        if ($buyerEmail !== '') {
            $buyerLabel .= ' <' . $buyerEmail . '>';
        }
        $parts[] = 'Cliente: ' . $buyerLabel;
        if ($unitPrice > 0) {
            $parts[] = 'Valor produto R$ ' . $this->formatMoneyLabel($unitPrice);
        }
        if ($qty > 1) {
            $parts[] = 'Qtd ' . $qty;
        }
        if ($lineTotal > 0) {
            $parts[] = 'Total R$ ' . $this->formatMoneyLabel($lineTotal);
        }
        $percentLabel = $percent !== null ? number_format($percent, 2, ',', '.') . '%' : '-';
        $parts[] = 'Crédito R$ ' . $this->formatMoneyLabel($credit) . ' (' . $percentLabel . ')';

        return implode(' | ', $parts);
    }

    private function isDebitEntry(array $entry): bool
    {
        $type = strtolower((string) ($entry['type'] ?? ''));
        return $type !== '' && $type !== 'credito';
    }

    private function resolveMovementDate(array $entry): ?string
    {
        return $entry['event_at'] ?? $entry['sold_at'] ?? $entry['created_at'] ?? null;
    }

    private function formatMovementDescription(array $entry, bool $isDebit): string
    {
        $eventType = trim((string) ($entry['event_type'] ?? ''));
        if ($eventType === '' || $eventType === 'sale') {
            return $this->formatCreditDescription($entry);
        }

        return $this->formatEventDescription($entry, $isDebit);
    }

    private function formatEventDescription(array $entry, bool $isDebit): string
    {
        $eventType = trim((string) ($entry['event_type'] ?? ''));
        $eventId = (int) ($entry['event_id'] ?? 0);
        $eventLabel = $this->resolveEventLabelFromEntry($entry);
        $orderId = (int) ($entry['order_id'] ?? 0);
        $sku = trim((string) ($entry['sku'] ?? ''));
        $productName = trim((string) ($entry['product_name'] ?? ''));
        $qty = (int) ($entry['quantity'] ?? 1);
        $unitPrice = (float) ($entry['unit_price'] ?? 0);
        $lineTotal = (float) ($entry['line_total'] ?? 0);
        $amount = (float) ($entry['credit_amount'] ?? 0);
        $percent = $entry['percent'] !== null ? (float) $entry['percent'] : null;
        $soldAt = $entry['sold_at'] ?? null;
        $notes = trim((string) ($entry['event_notes'] ?? ''));

        if ($percent === null && $lineTotal > 0 && $amount > 0) {
            $percent = round(($amount / $lineTotal) * 100, 2);
        }

        $parts = [];
        if ($eventLabel !== '') {
            $parts[] = $eventLabel;
        } elseif ($eventType !== '') {
            $parts[] = ucfirst($eventType);
        }
        if ($eventId > 0 && in_array($eventType, ['return', 'return_cancel'], true) && stripos($eventLabel, (string) $eventId) === false) {
            $parts[] = 'Devolução #' . $eventId;
        }
        if ($orderId > 0 && stripos($eventLabel, 'Pedido') === false) {
            $parts[] = 'Pedido #' . $orderId;
        }
        if ($sku !== '') {
            $parts[] = 'SKU ' . $sku;
        }
        if ($productName !== '') {
            $parts[] = $productName;
        }
        if ($unitPrice > 0) {
            $parts[] = 'Valor produto R$ ' . $this->formatMoneyLabel($unitPrice);
        }
        if ($qty > 1) {
            $parts[] = 'Qtd ' . $qty;
        }
        if ($lineTotal > 0) {
            $parts[] = 'Total R$ ' . $this->formatMoneyLabel($lineTotal);
        }
        $percentLabel = $percent !== null ? number_format($percent, 2, ',', '.') . '%' : '-';
        $amountLabel = $isDebit ? 'Abatimento' : 'Crédito';
        $parts[] = $amountLabel . ' R$ ' . $this->formatMoneyLabel($amount) . ' (' . $percentLabel . ')';
        if ($soldAt) {
            $parts[] = 'Venda ' . substr((string) $soldAt, 0, 10);
        }
        if ($notes !== '') {
            $parts[] = 'Obs: ' . $this->trimNotes($notes);
        }

        return implode(' | ', $parts);
    }

    private function resolveEventLabelFromEntry(array $entry): string
    {
        $label = trim((string) ($entry['event_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        $eventType = trim((string) ($entry['event_type'] ?? ''));
        if ($eventType === '') {
            return '';
        }
        $base = self::EVENT_LABELS[$eventType] ?? '';
        if ($base === '') {
            return '';
        }
        $eventId = (int) ($entry['event_id'] ?? 0);
        if ($eventId > 0 && in_array($eventType, ['return', 'return_cancel'], true)) {
            return $base . ' #' . $eventId;
        }
        return $base;
    }

    private function buildVoucherStatementForPrint($account, ?string $periodStart, ?string $periodEnd): array
    {
        $statement = [
            'entries' => [],
            'opening_balance' => 0.0,
            'current_balance' => 0.0,
            'total_usage' => 0.0,
            'error' => null,
        ];

        if (!$account || !$account->id) {
            return $statement;
        }

        $entries = [];
        $totalUsage = 0.0;
        $voucherIds = $this->resolveVoucherIdsForAccount($account);

        if ($this->repository->getPdo()) {
            try {
                $creditRepo = new VoucherCreditEntryRepository($this->repository->getPdo());
                $creditRows = [];
                if (!empty($voucherIds)) {
                    if (count($voucherIds) === 1) {
                        $creditRows = $creditRepo->listByVoucher($voucherIds[0]);
                    } else {
                        $creditRows = $creditRepo->listByVoucherIds($voucherIds);
                    }
                }
                foreach ($creditRows as $row) {
                    $amount = (float) ($row['credit_amount'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }
                    $isDebit = $this->isDebitEntry($row);
                    $entries[] = [
                        'date' => $this->resolveMovementDate($row),
                        'type' => $isDebit ? 'debito' : 'credito',
                        'description' => $this->formatPrintMovementDescription($row),
                        'amount' => $isDebit ? -$amount : $amount,
                        'order_id' => isset($row['order_id']) ? (int) $row['order_id'] : null,
                    ];
                }
                if (empty($creditRows)) {
                    $fallbackRows = $this->resolveLegacyCreditEntries($account);
                    foreach ($fallbackRows as $row) {
                        $amount = (float) ($row['amount'] ?? 0);
                        if ($amount <= 0) {
                            continue;
                        }
                        $entries[] = [
                            'date' => $row['date'] ?? null,
                            'type' => 'credito',
                            'description' => $this->formatPrintSkuDescription(
                                (string) ($row['sku'] ?? ''),
                                (string) ($row['product_name'] ?? '')
                            ),
                            'amount' => $amount,
                            'order_id' => isset($row['order_id']) ? (int) $row['order_id'] : null,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                if (!$statement['error']) {
                    $statement['error'] = 'Erro ao carregar creditos consignados: ' . $e->getMessage();
                }
            }
        }

        usort($entries, function (array $a, array $b): int {
            $timeA = isset($a['date']) ? strtotime((string) $a['date']) : 0;
            $timeB = isset($b['date']) ? strtotime((string) $b['date']) : 0;
            if ($timeA === $timeB) {
                $typeA = ($a['type'] ?? '') === 'credito' ? 0 : 1;
                $typeB = ($b['type'] ?? '') === 'credito' ? 0 : 1;
                if ($typeA === $typeB) {
                    return ((int) ($a['order_id'] ?? 0)) <=> ((int) ($b['order_id'] ?? 0));
                }
                return $typeA <=> $typeB;
            }
            return $timeA <=> $timeB;
        });

        $startTs = $this->normalizeStatementTimestamp($periodStart, true);
        $endTs = $this->normalizeStatementTimestamp($periodEnd, false);

        $opening = 0.0;
        $filtered = [];
        foreach ($entries as $entry) {
            $ts = $this->entryTimestamp($entry);
            if ($startTs !== null && $ts !== 0 && $ts < $startTs) {
                $opening += (float) ($entry['amount'] ?? 0);
                continue;
            }
            if ($endTs !== null && $ts !== 0 && $ts > $endTs) {
                continue;
            }
            $filtered[] = $entry;
        }

        $running = $opening;
        foreach ($filtered as &$entry) {
            $running += (float) ($entry['amount'] ?? 0);
            $entry['balance'] = $running;
        }
        unset($entry);

        $statement['entries'] = $filtered;
        $statement['opening_balance'] = $opening;
        $statement['current_balance'] = $running;
        $statement['total_usage'] = $totalUsage;

        return $statement;
    }

    private function formatPrintSkuDescription(string $sku, string $productName): string
    {
        $sku = trim($sku);
        $productName = trim($productName);
        if ($sku !== '' && $productName !== '') {
            return 'SKU ' . $sku . ' - ' . $productName;
        }
        if ($sku !== '') {
            return 'SKU ' . $sku;
        }
        if ($productName !== '') {
            return $productName;
        }
        return 'Produto consignado';
    }

    private function formatPrintMovementDescription(array $entry): string
    {
        $eventType = trim((string) ($entry['event_type'] ?? ''));
        if ($eventType !== '' && $eventType !== 'sale') {
            $label = $this->resolveEventLabelFromEntry($entry);
            $sku = trim((string) ($entry['sku'] ?? ''));
            $productName = trim((string) ($entry['product_name'] ?? ''));
            $itemLabel = $this->formatPrintSkuDescription($sku, $productName);
            if ($label !== '') {
                return $label . ' - ' . $itemLabel;
            }
            return $itemLabel;
        }

        return $this->formatPrintSkuDescription(
            (string) ($entry['sku'] ?? ''),
            (string) ($entry['product_name'] ?? '')
        );
    }

    private function normalizeStatementDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }
        return date('Y-m-d', $timestamp);
    }

    private function normalizeStatementTimestamp(?string $value, bool $startOfDay): ?int
    {
        if (!$value) {
            return null;
        }
        $suffix = $startOfDay ? '00:00:00' : '23:59:59';
        $timestamp = strtotime($value . ' ' . $suffix);
        return $timestamp === false ? null : $timestamp;
    }

    private function entryTimestamp(array $entry): int
    {
        $value = $entry['date'] ?? null;
        if (!$value) {
            return 0;
        }
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? 0 : $timestamp;
    }

    private function formatMoneyLabel(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private function trimNotes(string $value, int $limit = 160): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value) <= $limit) {
                return $value;
            }
            return mb_substr($value, 0, $limit - 3) . '...';
        }
        if (strlen($value) <= $limit) {
            return $value;
        }
        return substr($value, 0, $limit - 3) . '...';
    }
}
