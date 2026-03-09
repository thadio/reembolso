<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\OrganAuditRepository;
use App\Repositories\OrganRepository;
use App\Services\OrganAuditService;
use App\Services\OrganService;

final class OrgansController extends Controller
{
    public function index(Request $request): void
    {
        $query = (string) $request->input('q', '');
        $sort = (string) $request->input('sort', 'name');
        $dir = (string) $request->input('dir', 'asc');
        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        $result = $this->service()->paginate($query, $sort, $dir, $page, $perPage);

        $this->view('organs/index', [
            'title' => 'Órgãos',
            'organs' => $result['items'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'pages' => $result['pages'],
            ],
            'filters' => [
                'q' => $query,
                'sort' => $sort,
                'dir' => $dir,
                'per_page' => $perPage,
            ],
            'canManage' => $this->app->auth()->hasPermission('organs.manage'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('organs/create', [
            'title' => 'Novo Órgão',
            'organ' => $this->emptyOrgan(),
        ]);
    }

    public function store(Request $request): void
    {
        $input = $request->all();
        Session::flashInput($input);

        $result = $this->service()->create(
            $input,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/organs/create');
        }

        flash('success', 'Órgão cadastrado com sucesso.');
        $this->redirect('/organs/show?id=' . (int) $result['id']);
    }

    public function importCsv(Request $request): void
    {
        $validateOnly = (string) $request->input('validate_only', '0') === '1';
        $file = is_array($_FILES['csv_file'] ?? null) ? $_FILES['csv_file'] : null;

        $result = $this->service()->importCsv(
            file: $file,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            validateOnly: $validateOnly
        );

        if (!$result['ok']) {
            $errors = $result['errors'] ?? [];
            if (count($errors) > 8) {
                $extra = count($errors) - 8;
                $errors = array_slice($errors, 0, 8);
                $errors[] = sprintf('... e mais %d erro(s).', $extra);
            }

            flash('error', implode(' ', $errors));
            $this->redirect('/organs');
        }

        flash('success', (string) ($result['message'] ?? 'Importação concluída.'));
        $this->redirect('/organs');
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        $auditPage = max(1, (int) $request->input('audit_page', '1'));
        if ($id <= 0) {
            flash('error', 'Órgão inválido.');
            $this->redirect('/organs');
        }

        $organ = $this->service()->find($id);
        if ($organ === null) {
            flash('error', 'Órgão não encontrado.');
            $this->redirect('/organs');
        }

        $canViewAudit = $this->app->auth()->hasPermission('audit.view');
        $auditFilters = [
            'entity' => (string) $request->input('audit_entity', ''),
            'action' => (string) $request->input('audit_action', ''),
            'q' => (string) $request->input('audit_q', ''),
            'from_date' => (string) $request->input('audit_from', ''),
            'to_date' => (string) $request->input('audit_to', ''),
        ];

        $audit = [
            'items' => [],
            'pagination' => [
                'total' => 0,
                'page' => 1,
                'per_page' => 10,
                'pages' => 1,
            ],
            'filters' => [
                'entity' => '',
                'action' => '',
                'q' => '',
                'from_date' => '',
                'to_date' => '',
            ],
            'options' => [
                'entities' => [],
                'actions' => [],
            ],
        ];

        if ($canViewAudit) {
            $audit = $this->organAuditService()->profileData($id, $auditFilters, $auditPage, 10);
        }

        $this->view('organs/show', [
            'title' => 'Detalhe do Órgão',
            'organ' => $organ,
            'canManage' => $this->app->auth()->hasPermission('organs.manage'),
            'canViewAudit' => $canViewAudit,
            'audit' => $audit,
        ]);
    }

    public function exportAudit(Request $request): void
    {
        $organId = (int) $request->input('organ_id', '0');
        if ($organId <= 0) {
            flash('error', 'Órgão inválido para exportação de auditoria.');
            $this->redirect('/organs');
        }

        $organ = $this->service()->find($organId);
        if ($organ === null) {
            flash('error', 'Órgão não encontrado.');
            $this->redirect('/organs');
        }

        $filters = [
            'entity' => (string) $request->input('audit_entity', ''),
            'action' => (string) $request->input('audit_action', ''),
            'q' => (string) $request->input('audit_q', ''),
            'from_date' => (string) $request->input('audit_from', ''),
            'to_date' => (string) $request->input('audit_to', ''),
        ];

        $export = $this->organAuditService()->exportRows($organId, $filters, 3000);
        $rows = $export['rows'];

        $fileName = sprintf('auditoria-orgao-%d-%s.csv', $organId, date('Ymd_His'));

        header('Content-Type: text/csv; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            exit;
        }

        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'data_hora',
            'entidade',
            'entidade_id',
            'acao',
            'usuario',
            'ip',
            'user_agent',
            'before_data',
            'after_data',
            'metadata',
        ]);

        $normalizePayload = static function (mixed $payload): string {
            if (!is_string($payload) || trim($payload) === '') {
                return '';
            }

            $decoded = json_decode($payload, true);
            if (!is_array($decoded)) {
                return $payload;
            }

            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return is_string($encoded) ? $encoded : $payload;
        };

        foreach ($rows as $row) {
            fputcsv($output, [
                (string) ($row['created_at'] ?? ''),
                (string) ($row['entity'] ?? ''),
                isset($row['entity_id']) ? (string) $row['entity_id'] : '',
                (string) ($row['action'] ?? ''),
                (string) ($row['user_name'] ?? ''),
                (string) ($row['ip'] ?? ''),
                (string) ($row['user_agent'] ?? ''),
                $normalizePayload($row['before_data'] ?? null),
                $normalizePayload($row['after_data'] ?? null),
                $normalizePayload($row['metadata'] ?? null),
            ]);
        }

        fclose($output);
        exit;
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Órgão inválido.');
            $this->redirect('/organs');
        }

        $organ = $this->service()->find($id);
        if ($organ === null) {
            flash('error', 'Órgão não encontrado.');
            $this->redirect('/organs');
        }

        $this->view('organs/edit', [
            'title' => 'Editar Órgão',
            'organ' => $organ,
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Órgão inválido.');
            $this->redirect('/organs');
        }

        $input = $request->all();
        Session::flashInput($input);

        $result = $this->service()->update(
            $id,
            $input,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/organs/edit?id=' . $id);
        }

        flash('success', 'Órgão atualizado com sucesso.');
        $this->redirect('/organs/show?id=' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Órgão inválido.');
            $this->redirect('/organs');
        }

        $deleted = $this->service()->delete(
            $id,
            (int) ($this->app->auth()->id() ?? 0),
            $request->ip(),
            $request->userAgent()
        );

        if (!$deleted) {
            flash('error', 'Órgão não encontrado ou já removido.');
            $this->redirect('/organs');
        }

        flash('success', 'Órgão removido com sucesso.');
        $this->redirect('/organs');
    }

    /** @return array<string, mixed> */
    private function emptyOrgan(): array
    {
        return [
            'name' => '',
            'acronym' => '',
            'cnpj' => '',
            'company_nire' => '',
            'organ_type' => '',
            'company_dependency_type' => '',
            'government_level' => '',
            'government_branch' => '',
            'supervising_organ' => '',
            'federative_entity' => '',
            'contact_name' => '',
            'contact_email' => '',
            'contact_phone' => '',
            'address_line' => '',
            'city' => '',
            'state' => '',
            'zip_code' => '',
            'notes' => '',
            'source_name' => '',
            'source_url' => '',
            'company_objective' => '',
            'capital_information' => '',
            'creation_act' => '',
            'internal_regulations' => '',
            'subsidiaries' => '',
            'official_website' => '',
        ];
    }

    private function service(): OrganService
    {
        return new OrganService(
            new OrganRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events()
        );
    }

    private function organAuditService(): OrganAuditService
    {
        return new OrganAuditService(
            new OrganAuditRepository($this->app->db())
        );
    }
}
