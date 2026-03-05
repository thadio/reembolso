<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\LgpdRepository;
use App\Repositories\OfficeTemplateRepository;
use App\Services\LgpdService;
use App\Services\OfficeDocumentPdfBuilder;
use App\Services\OfficeTemplateService;

final class OfficeTemplatesController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'q' => (string) $request->input('q', ''),
            'template_type' => (string) $request->input('template_type', ''),
            'is_active' => (string) $request->input('is_active', ''),
            'sort' => (string) $request->input('sort', 'updated_at'),
            'dir' => (string) $request->input('dir', 'desc'),
        ];

        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        $result = $this->service()->paginate($filters, $page, $perPage);

        $this->view('office_templates/index', [
            'title' => 'Templates de oficio',
            'templates' => $result['items'],
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
            'typeOptions' => $this->service()->typeOptions(),
            'canManage' => $this->app->auth()->hasPermission('office_template.manage'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('office_templates/create', [
            'title' => 'Novo template de oficio',
            'template' => $this->emptyTemplate(),
            'typeOptions' => $this->service()->typeOptions(),
            'availableVariables' => $this->service()->availableVariables(),
        ]);
    }

    public function store(Request $request): void
    {
        $input = $request->all();
        Session::flashInput($input);

        $result = $this->service()->createTemplate(
            input: $input,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/office-templates/create');
        }

        flash('success', 'Template criado com sucesso.');
        $this->redirect('/office-templates/show?id=' . (int) $result['id']);
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Template invalido.');
            $this->redirect('/office-templates');
        }

        $template = $this->service()->findTemplate($id);
        if ($template === null) {
            flash('error', 'Template nao encontrado.');
            $this->redirect('/office-templates');
        }

        $canManage = $this->app->auth()->hasPermission('office_template.manage');

        $this->view('office_templates/show', [
            'title' => 'Detalhe do template',
            'template' => $template,
            'versions' => $this->service()->versions($id),
            'documents' => $this->service()->documents($id, 80),
            'people' => $canManage ? $this->service()->activePeople(800) : [],
            'typeOptions' => $this->service()->typeOptions(),
            'availableVariables' => $this->service()->availableVariables(),
            'canManage' => $canManage,
        ]);
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Template invalido.');
            $this->redirect('/office-templates');
        }

        $template = $this->service()->findTemplate($id);
        if ($template === null) {
            flash('error', 'Template nao encontrado.');
            $this->redirect('/office-templates');
        }

        $this->view('office_templates/edit', [
            'title' => 'Editar template',
            'template' => $template,
            'typeOptions' => $this->service()->typeOptions(),
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Template invalido.');
            $this->redirect('/office-templates');
        }

        $input = $request->all();
        Session::flashInput($input);

        $result = $this->service()->updateTemplate(
            id: $id,
            input: $input,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/office-templates/edit?id=' . $id);
        }

        flash('success', 'Template atualizado com sucesso.');
        $this->redirect('/office-templates/show?id=' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Template invalido.');
            $this->redirect('/office-templates');
        }

        $deleted = $this->service()->deleteTemplate(
            templateId: $id,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$deleted) {
            flash('error', 'Template nao encontrado ou ja removido.');
            $this->redirect('/office-templates');
        }

        flash('success', 'Template removido com sucesso.');
        $this->redirect('/office-templates');
    }

    public function createVersion(Request $request): void
    {
        $templateId = (int) $request->input('template_id', '0');
        if ($templateId <= 0) {
            flash('error', 'Template invalido para nova versao.');
            $this->redirect('/office-templates');
        }

        $result = $this->service()->addVersion(
            templateId: $templateId,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/office-templates/show?id=' . $templateId);
        }

        flash('success', $result['message']);
        $this->redirect('/office-templates/show?id=' . $templateId);
    }

    public function generate(Request $request): void
    {
        $templateId = (int) $request->input('template_id', '0');
        if ($templateId <= 0) {
            flash('error', 'Template invalido para geracao.');
            $this->redirect('/office-templates');
        }

        $result = $this->service()->generateDocument(
            templateId: $templateId,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/office-templates/show?id=' . $templateId);
        }

        flash('success', $result['message']);
        $this->redirect('/office-documents/show?id=' . (int) $result['id']);
    }

    public function showDocument(Request $request): void
    {
        $documentId = (int) $request->input('id', '0');
        if ($documentId <= 0) {
            flash('error', 'Documento de oficio invalido.');
            $this->redirect('/office-templates');
        }

        $document = $this->service()->findDocument($documentId);
        if ($document === null) {
            flash('error', 'Documento de oficio nao encontrado.');
            $this->redirect('/office-templates');
        }

        $this->service()->registerDocumentAccess(
            document: $document,
            channel: 'show',
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $this->view('office_templates/document_show', [
            'title' => 'Oficio gerado',
            'document' => $document,
        ]);
    }

    public function printDocument(Request $request): void
    {
        $documentId = (int) $request->input('id', '0');
        if ($documentId <= 0) {
            flash('error', 'Documento de oficio invalido.');
            $this->redirect('/office-templates');
        }

        $document = $this->service()->findDocument($documentId);
        if ($document === null) {
            flash('error', 'Documento de oficio nao encontrado.');
            $this->redirect('/office-templates');
        }

        $this->service()->registerDocumentAccess(
            document: $document,
            channel: 'print',
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $this->app->view()->render('office_templates/document_print', [
            'title' => 'Oficio',
            'document' => $document,
            'authUser' => $this->app->auth()->user(),
            'currentPath' => '/office-documents/print',
        ], 'print_layout');
    }

    public function downloadDocumentPdf(Request $request): void
    {
        $documentId = (int) $request->input('id', '0');
        if ($documentId <= 0) {
            flash('error', 'Documento de oficio invalido para PDF.');
            $this->redirect('/office-templates');
        }

        $pdf = $this->service()->documentPdf(
            documentId: $documentId,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if ($pdf === null) {
            flash('error', 'Documento de oficio nao encontrado.');
            $this->redirect('/office-templates');
        }

        $binary = (string) ($pdf['binary'] ?? '');
        $fileName = (string) ($pdf['file_name'] ?? ('oficio_' . $documentId . '.pdf'));

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
            header('Content-Length: ' . (string) strlen($binary));
        }

        echo $binary;
        exit;
    }

    /** @return array<string, mixed> */
    private function emptyTemplate(): array
    {
        return [
            'template_key' => '',
            'name' => '',
            'template_type' => 'orgao',
            'description' => '',
            'is_active' => '1',
            'subject' => '',
            'body_html' => '<p>Prezados(as),</p><p>Solicitamos providencias referentes a {{person_name}} (processo {{person_process}}).</p><p>Atenciosamente,</p>',
            'variables_json' => '',
            'notes' => '',
        ];
    }

    private function service(): OfficeTemplateService
    {
        return new OfficeTemplateService(
            new OfficeTemplateRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events(),
            new OfficeDocumentPdfBuilder(),
            new LgpdService(
                new LgpdRepository($this->app->db()),
                $this->app->audit(),
                $this->app->events()
            )
        );
    }
}
