<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\ProcessMetadataRepository;
use App\Services\ProcessMetadataService;

final class ProcessMetadataController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'q' => (string) $request->input('q', ''),
            'organ_id' => max(0, (int) $request->input('organ_id', '0')),
            'has_dou' => (string) $request->input('has_dou', ''),
            'sort' => (string) $request->input('sort', 'updated_at'),
            'dir' => (string) $request->input('dir', 'desc'),
        ];

        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        $result = $this->service()->paginate($filters, $page, $perPage);

        $this->view('process_metadata/index', [
            'title' => 'Metadados de processo',
            'items' => $result['items'],
            'filters' => [
                ...$filters,
                'per_page' => $perPage,
            ],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'pages' => $result['pages'],
            ],
            'organs' => $this->service()->activeOrgans(),
            'canManage' => $this->app->auth()->hasPermission('process_meta.manage'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('process_metadata/create', [
            'title' => 'Novo metadado formal',
            'meta' => $this->emptyMeta(),
            'people' => $this->service()->activePeople(0, 1600),
            'channelOptions' => $this->service()->channelOptions(),
        ]);
    }

    public function store(Request $request): void
    {
        $input = $request->all();
        Session::flashInput($input);

        $result = $this->service()->create(
            input: $input,
            file: is_array($_FILES['dou_attachment'] ?? null) ? $_FILES['dou_attachment'] : null,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/process-meta/create');
        }

        flash('success', 'Metadados formais cadastrados com sucesso.');
        $this->redirect('/process-meta/show?id=' . (int) $result['id']);
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Registro invalido.');
            $this->redirect('/process-meta');
        }

        $meta = $this->service()->find($id);
        if ($meta === null) {
            flash('error', 'Registro nao encontrado.');
            $this->redirect('/process-meta');
        }

        $this->view('process_metadata/show', [
            'title' => 'Detalhe de metadado formal',
            'meta' => $meta,
            'canManage' => $this->app->auth()->hasPermission('process_meta.manage'),
        ]);
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Registro invalido.');
            $this->redirect('/process-meta');
        }

        $meta = $this->service()->find($id);
        if ($meta === null) {
            flash('error', 'Registro nao encontrado.');
            $this->redirect('/process-meta');
        }

        $this->view('process_metadata/edit', [
            'title' => 'Editar metadado formal',
            'meta' => $meta,
            'people' => $this->service()->activePeople(0, 1600),
            'channelOptions' => $this->service()->channelOptions(),
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Registro invalido.');
            $this->redirect('/process-meta');
        }

        $input = $request->all();
        Session::flashInput($input);

        $result = $this->service()->update(
            id: $id,
            input: $input,
            file: is_array($_FILES['dou_attachment'] ?? null) ? $_FILES['dou_attachment'] : null,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/process-meta/edit?id=' . $id);
        }

        flash('success', 'Metadados formais atualizados com sucesso.');
        $this->redirect('/process-meta/show?id=' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Registro invalido.');
            $this->redirect('/process-meta');
        }

        $deleted = $this->service()->delete(
            id: $id,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$deleted) {
            flash('error', 'Registro nao encontrado ou ja removido.');
            $this->redirect('/process-meta');
        }

        flash('success', 'Registro removido com sucesso.');
        $this->redirect('/process-meta');
    }

    public function downloadDouAttachment(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Registro invalido para download.');
            $this->redirect('/process-meta');
        }

        $file = $this->service()->attachmentForDownload(
            id: $id,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if ($file === null) {
            flash('error', 'Anexo DOU nao encontrado.');
            $this->redirect('/process-meta/show?id=' . $id);
        }

        $path = (string) $file['path'];
        $mime = (string) $file['mime_type'];
        $name = (string) $file['original_name'];

        if (!is_file($path)) {
            flash('error', 'Arquivo de anexo nao encontrado no storage.');
            $this->redirect('/process-meta/show?id=' . $id);
        }

        if (!headers_sent()) {
            header('Content-Type: ' . ($mime !== '' ? $mime : 'application/octet-stream'));
            header('Content-Length: ' . (string) filesize($path));
            header('Content-Disposition: attachment; filename="' . rawurlencode($name !== '' ? $name : ('anexo_dou_' . $id)) . '"');
            header('X-Content-Type-Options: nosniff');
        }

        readfile($path);
        exit;
    }

    /** @return array<string, mixed> */
    private function emptyMeta(): array
    {
        return [
            'person_id' => '',
            'office_number' => '',
            'office_sent_at' => '',
            'office_channel' => 'sei',
            'office_protocol' => '',
            'dou_edition' => '',
            'dou_published_at' => '',
            'dou_link' => '',
            'mte_entry_date' => '',
            'notes' => '',
            'dou_attachment_original_name' => null,
        ];
    }

    private function service(): ProcessMetadataService
    {
        return new ProcessMetadataService(
            new ProcessMetadataRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events(),
            $this->app->config()
        );
    }
}
