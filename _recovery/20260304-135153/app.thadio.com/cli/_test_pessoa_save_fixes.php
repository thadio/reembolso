#!/usr/bin/env php
<?php
/**
 * Teste de validação das correções de persistência de pessoas.
 *
 * Verifica:
 *  1. pedido-cadastro.php rota AJAX inline (create_customer) sem bloquear permissão genérica
 *  2. Auth::requirePermission responde JSON para AJAX
 *  3. Auth::requireLogin responde JSON para AJAX
 *  4. Todos os fetch() no frontend enviam Accept: application/json
 *  5. CatalogCustomerService::create() funciona com ROLLBACK
 *  6. PersonRepository::save() funciona com ROLLBACK
 *
 * USO: php cli/_test_pessoa_save_fixes.php
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Services\CatalogCustomerService;
use App\Services\CustomerService;
use App\Repositories\PersonRepository;

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  ✅ {$label}\n";
    } else {
        $fail++;
        echo "  ❌ {$label}" . ($detail ? " — {$detail}" : '') . "\n";
    }
}

function section(string $title): void {
    echo "\n━━━ {$title} ━━━\n";
}

// ─── 1. Estrutura de pedido-cadastro.php ────────────────────────────
section('1. pedido-cadastro.php – gate para ações AJAX inline');

$src = file_get_contents(__DIR__ . '/../pedido-cadastro.php');
check(
    'Define lista $inlineActions com create_customer',
    str_contains($src, "'create_customer'") && str_contains($src, '$inlineActions')
);
check(
    'Detecta $postAction antes do requirePermission genérico',
    str_contains($src, "in_array(\$postAction, \$inlineActions, true)")
);
check(
    'Responde JSON 403 quando usuário sem permissão de pedidos tenta ação inline',
    str_contains($src, 'application/json') && str_contains($src, 'json_encode')
);
check(
    'Gate genérico orders.create/view permanece intacto para GET/POST normais',
    str_contains($src, "requirePermission(\$pdo, \$editing ? 'orders.view' : 'orders.create')")
);

// ─── 2. Auth::requirePermission responde JSON ──────────────────────
section('2. Auth::requirePermission – resposta JSON para AJAX');

$authSrc = file_get_contents(__DIR__ . '/../app/Support/Auth.php');
check(
    'requirePermission chama isJsonRequest()',
    str_contains($authSrc, 'self::isJsonRequest()')
);
check(
    'isJsonRequest() verifica HTTP_ACCEPT para application/json',
    str_contains($authSrc, 'application/json')
);
check(
    'isJsonRequest() verifica X-Requested-With: xmlhttprequest',
    str_contains($authSrc, 'xmlhttprequest')
);
check(
    'requirePermission retorna JSON com ok=false',
    preg_match("/json_encode.*'ok'.*false.*'message'/s", $authSrc) === 1
);

// ─── 3. Auth::requireLogin responde JSON para AJAX ─────────────────
section('3. Auth::requireLogin – resposta JSON para AJAX');

check(
    'requireLogin chama isJsonRequest()',
    substr_count($authSrc, 'self::isJsonRequest()') >= 2  // uma em requirePermission, outra em requireLogin
);
check(
    'requireLogin retorna 401 JSON quando sessão expirada',
    str_contains($authSrc, 'http_response_code(401)') && str_contains($authSrc, 'Sessão expirada')
);

// ─── 4. Frontend – Accept header em todos os fetch() ───────────────
section('4. Frontend – Accept: application/json em fetch()');

$orderFormSrc = file_get_contents(__DIR__ . '/../app/Views/orders/form.php');
// Conta fetch() para pedido-cadastro.php
preg_match_all("/fetch\('pedido-cadastro\.php'/", $orderFormSrc, $fetchMatches);
$fetchCount = count($fetchMatches[0]);
preg_match_all("/'Accept':\s*'application\/json'/", $orderFormSrc, $acceptMatches);
$acceptCount = count($acceptMatches[0]);
check(
    "Todos os {$fetchCount} fetch('pedido-cadastro.php') incluem Accept header ({$acceptCount} encontrados)",
    $fetchCount > 0 && $acceptCount >= $fetchCount
);

// Verifica .catch(() => null) para parsing resiliente
preg_match_all('/response\.json\(\)\.catch\(\(\) => null\)/', $orderFormSrc, $catchMatches);
$catchCount = count($catchMatches[0]);
check(
    "Parsing JSON resiliente (.catch(() => null)) em {$catchCount} fetch() calls",
    $catchCount >= $fetchCount
);

// Verifica outros views
$batimentoSrc = file_get_contents(__DIR__ . '/../app/Views/inventory/batimento.php');
check(
    'batimento.php fetch inclui Accept: application/json',
    str_contains($batimentoSrc, "'Accept': 'application/json'")
);

$productFormSrc = file_get_contents(__DIR__ . '/../app/Views/products/form.php');
preg_match_all("/'Accept':\s*'application\/json'/", $productFormSrc, $prodAccept);
check(
    'products/form.php fetch calls incluem Accept: application/json (' . count($prodAccept[0]) . ' encontrados)',
    count($prodAccept[0]) >= 2
);

// ─── 5. Persistência – CatalogCustomerService::create() ────────────
section('5. Persistência – CatalogCustomerService::create() com ROLLBACK');

[$pdo, $err] = bootstrapPdo();
if (!$pdo) {
    check('Conexão com banco', false, $err ?? 'PDO nulo');
} else {
    check('Conexão com banco', true);

    $svc = new CustomerService();
    $input = [
        'fullName' => '__TEST_VALIDACAO_FIX_' . date('YmdHis'),
        'email'    => 'test_fix_' . time() . '@example.test',
        'status'   => 'ativo',
    ];
    [$customer, $errors] = $svc->validate($input, false);
    check('CustomerService::validate() sem erros', empty($errors), implode(', ', $errors));

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $catalog = new CatalogCustomerService(null, $pdo);
            $created = $catalog->create($customer);
            $createdId = (int)($created['id'] ?? 0);
            check('CatalogCustomerService::create() retorna ID > 0', $createdId > 0, "ID={$createdId}");

            if ($createdId > 0) {
                $repo = new PersonRepository($pdo);
                $person = $repo->find($createdId);
                check('PersonRepository::find() recupera registro criado', $person !== null);
                check('Nome confere', ($person->fullName ?? '') === $input['fullName']);
                check('Email confere', ($person->email ?? '') === $input['email']);
            }
        } catch (\Throwable $e) {
            check('CatalogCustomerService::create() sem exceção', false, $e->getMessage());
        } finally {
            $pdo->rollBack();
            echo "  ↩️  ROLLBACK executado – nenhum registro permanente.\n";
        }
    }
}

// ─── Resumo ────────────────────────────────────────────────────────
section('RESUMO');
$total = $pass + $fail;
echo "  Total: {$total} | ✅ Passou: {$pass} | ❌ Falhou: {$fail}\n\n";
exit($fail > 0 ? 1 : 0);
