<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Repositories\InvoiceRepository;
use App\Repositories\LgpdRepository;
use App\Services\InvoiceService;
use App\Services\LgpdService;

final class InvoicesController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'q' => (string) $request->input('q', ''),
            'status' => (string) $request->input('status', ''),
            'organ_id' => max(0, (int) $request->input('organ_id', '0')),
            'reference_month' => (string) $request->input('reference_month', ''),
            'sort' => (string) $request->input('sort', 'due_date'),
            'dir' => (string) $request->input('dir', 'desc'),
        ];

        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(5, min(50, (int) $request->input('per_page', '10')));

        $result = $this->service()->paginate($filters, $page, $perPage);

        $this->view('invoices/index', [
            'title' => 'Boletos',
            'invoices' => $result['items'],
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
            'statusOptions' => $this->service()->statusOptions(),
            'organs' => $this->service()->activeOrgans(),
            'canManage' => $this->app->auth()->hasPermission('invoice.manage'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('invoices/create', [
            'title' => 'Novo boleto',
            'invoice' => $this->emptyInvoice(),
            'statusOptions' => $this->service()->statusOptions(),
            'organs' => $this->service()->activeOrgans(),
        ]);
    }

    public function store(Request $request): void
    {
        $input = $request->all();
        Session::flashInput($input);

        $result = $this->service()->create(
            input: $input,
            file: is_array($_FILES['invoice_pdf'] ?? null) ? $_FILES['invoice_pdf'] : null,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/invoices/create');
        }

        flash('success', 'Boleto cadastrado com sucesso.');
        $this->redirect('/invoices/show?id=' . (int) $result['id']);
    }

    public function show(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Boleto invalido.');
            $this->redirect('/invoices');
        }

        $invoice = $this->service()->find($id);
        if ($invoice === null) {
            flash('error', 'Boleto nao encontrado.');
            $this->redirect('/invoices');
        }

        $canManage = $this->app->auth()->hasPermission('invoice.manage');

        $this->view('invoices/show', [
            'title' => 'Detalhe do boleto',
            'invoice' => $invoice,
            'links' => $this->service()->links($id),
            'payments' => $this->service()->payments($id),
            'availablePeople' => $canManage ? $this->service()->availablePeople($id, 500) : [],
            'canManage' => $canManage,
        ]);
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Boleto invalido.');
            $this->redirect('/invoices');
        }

        $invoice = $this->service()->find($id);
        if ($invoice === null) {
            flash('error', 'Boleto nao encontrado.');
            $this->redirect('/invoices');
        }

        $this->view('invoices/edit', [
            'title' => 'Editar boleto',
            'invoice' => $invoice,
            'statusOptions' => $this->service()->statusOptions(),
            'organs' => $this->service()->activeOrgans(),
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Boleto invalido.');
            $this->redirect('/invoices');
        }

        $input = $request->all();
        Session::flashInput($input);

        $result = $this->service()->update(
            id: $id,
            input: $input,
            file: is_array($_FILES['invoice_pdf'] ?? null) ? $_FILES['invoice_pdf'] : null,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/invoices/edit?id=' . $id);
        }

        flash('success', 'Boleto atualizado com sucesso.');
        $this->redirect('/invoices/show?id=' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->input('id', '0');
        if ($id <= 0) {
            flash('error', 'Boleto invalido.');
            $this->redirect('/invoices');
        }

        $deleted = $this->service()->delete(
            id: $id,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$deleted) {
            flash('error', 'Boleto nao encontrado ou ja removido.');
            $this->redirect('/invoices');
        }

        flash('success', 'Boleto removido com sucesso.');
        $this->redirect('/invoices');
    }

    public function linkPerson(Request $request): void
    {
        $invoiceId = (int) $request->input('invoice_id', '0');
        if ($invoiceId <= 0) {
            flash('error', 'Boleto invalido para vinculo.');
            $this->redirect('/invoices');
        }

        $result = $this->service()->linkPerson(
            invoiceId: $invoiceId,
            input: $request->all(),
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/invoices/show?id=' . $invoiceId);
        }

        flash('success', $result['message']);
        $this->redirect('/invoices/show?id=' . $invoiceId);
    }

    public function unlinkPerson(Request $request): void
    {
        $invoiceId = (int) $request->input('invoice_id', '0');
        $linkId = (int) $request->input('link_id', '0');

        if ($invoiceId <= 0 || $linkId <= 0) {
            flash('error', 'Dados invalidos para remocao de vinculo.');
            $this->redirect('/invoices');
        }

        $result = $this->service()->unlinkPerson(
            invoiceId: $invoiceId,
            linkId: $linkId,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/invoices/show?id=' . $invoiceId);
        }

        flash('success', $result['message']);
        $this->redirect('/invoices/show?id=' . $invoiceId);
    }

    public function storePayment(Request $request): void
    {
        $invoiceId = (int) $request->input('invoice_id', '0');
        if ($invoiceId <= 0) {
            flash('error', 'Boleto invalido para registrar pagamento.');
            $this->redirect('/invoices');
        }

        $result = $this->service()->registerPayment(
            invoiceId: $invoiceId,
            input: $request->all(),
            file: is_array($_FILES['payment_proof'] ?? null) ? $_FILES['payment_proof'] : null,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$result['ok']) {
            flash('error', implode(' ', $result['errors']));
            $this->redirect('/invoices/show?id=' . $invoiceId);
        }

        flash('success', $result['message']);
        $this->redirect('/invoices/show?id=' . $invoiceId);
    }

    public function downloadPaymentProof(Request $request): void
    {
        $paymentId = (int) $request->input('id', '0');
        $invoiceIdRaw = (int) $request->input('invoice_id', '0');
        $invoiceId = $invoiceIdRaw > 0 ? $invoiceIdRaw : null;
        if ($paymentId <= 0) {
            flash('error', 'Comprovante de pagamento invalido.');
            $this->redirect($invoiceIdRaw > 0 ? '/invoices/show?id=' . $invoiceIdRaw : '/invoices');
        }

        $file = $this->service()->paymentProofForDownload(
            paymentId: $paymentId,
            invoiceId: $invoiceId,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if ($file === null) {
            flash('error', 'Comprovante de pagamento nao encontrado.');
            $this->redirect($invoiceIdRaw > 0 ? '/invoices/show?id=' . $invoiceIdRaw : '/invoices');
        }

        $path = (string) $file['path'];
        $mimeType = (string) $file['mime_type'];
        $fileName = (string) $file['original_name'];

        if (!is_file($path)) {
            flash('error', 'Arquivo de comprovante nao encontrado no storage.');
            $this->redirect($invoiceIdRaw > 0 ? '/invoices/show?id=' . $invoiceIdRaw : '/invoices');
        }

        if (!headers_sent()) {
            header('Content-Type: ' . ($mimeType !== '' ? $mimeType : 'application/octet-stream'));
            header('Content-Length: ' . (string) filesize($path));
            header('Content-Disposition: attachment; filename="' . rawurlencode($fileName !== '' ? $fileName : ('comprovante_' . $paymentId)) . '"');
            header('X-Content-Type-Options: nosniff');
        }

        readfile($path);
        exit;
    }

    public function downloadPdf(Request $request): void
    {
        $invoiceId = (int) $request->input('id', '0');
        if ($invoiceId <= 0) {
            flash('error', 'Boleto invalido para download.');
            $this->redirect('/invoices');
        }

        $file = $this->service()->pdfForDownload(
            invoiceId: $invoiceId,
            userId: (int) ($this->app->auth()->id() ?? 0),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        if ($file === null) {
            flash('error', 'PDF do boleto nao encontrado.');
            $this->redirect('/invoices/show?id=' . $invoiceId);
        }

        $path = (string) $file['path'];
        $mimeType = (string) $file['mime_type'];
        $fileName = (string) $file['original_name'];

        if (!is_file($path)) {
            flash('error', 'Arquivo do boleto nao encontrado no storage.');
            $this->redirect('/invoices/show?id=' . $invoiceId);
        }

        if (!headers_sent()) {
            header('Content-Type: ' . ($mimeType !== '' ? $mimeType : 'application/pdf'));
            header('Content-Length: ' . (string) filesize($path));
            header('Content-Disposition: attachment; filename="' . rawurlencode($fileName !== '' ? $fileName : ('boleto_' . $invoiceId . '.pdf')) . '"');
            header('X-Content-Type-Options: nosniff');
        }

        readfile($path);
        exit;
    }

    /** @return array<string, mixed> */
    private function emptyInvoice(): array
    {
        return [
            'organ_id' => '',
            'invoice_number' => '',
            'title' => '',
            'reference_month' => date('Y-m'),
            'issue_date' => '',
            'due_date' => '',
            'total_amount' => '',
            'status' => 'aberto',
            'digitable_line' => '',
            'reference_code' => '',
            'notes' => '',
            'pdf_original_name' => null,
        ];
    }

    private function service(): InvoiceService
    {
        return new InvoiceService(
            new InvoiceRepository($this->app->db()),
            $this->app->audit(),
            $this->app->events(),
            $this->app->config(),
            new LgpdService(
                new LgpdRepository($this->app->db()),
                $this->app->audit(),
                $this->app->events()
            )
        );
    }
}
