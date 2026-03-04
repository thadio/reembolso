<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OfficeTemplateRepository;

final class OfficeTemplateService
{
    private const ALLOWED_TYPES = ['orgao', 'mgi', 'cobranca', 'resposta', 'outro'];

    public function __construct(
        private OfficeTemplateRepository $templates,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        return $this->templates->paginateTemplates($filters, $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function findTemplate(int $id): ?array
    {
        return $this->templates->findTemplateById($id);
    }

    /** @return array<int, array<string, mixed>> */
    public function versions(int $templateId): array
    {
        return $this->templates->versionsByTemplate($templateId);
    }

    /** @return array<int, array<string, mixed>> */
    public function documents(int $templateId, int $limit = 80): array
    {
        return $this->templates->documentsByTemplate($templateId, $limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function activePeople(int $limit = 600): array
    {
        return $this->templates->activePeople($limit);
    }

    /** @return array<string, mixed>|null */
    public function findDocument(int $id): ?array
    {
        return $this->templates->findDocumentById($id);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function typeOptions(): array
    {
        return [
            ['value' => 'orgao', 'label' => 'Orgao'],
            ['value' => 'mgi', 'label' => 'MGI'],
            ['value' => 'cobranca', 'label' => 'Cobranca'],
            ['value' => 'resposta', 'label' => 'Resposta'],
            ['value' => 'outro', 'label' => 'Outro'],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public function availableVariables(): array
    {
        return [
            ['key' => 'today', 'label' => 'Data atual', 'description' => 'Data atual no formato dd/mm/aaaa'],
            ['key' => 'person_name', 'label' => 'Nome da pessoa', 'description' => 'Nome completo da pessoa vinculada'],
            ['key' => 'person_cpf', 'label' => 'CPF', 'description' => 'CPF da pessoa (se existir)'],
            ['key' => 'person_email', 'label' => 'Email', 'description' => 'Email da pessoa'],
            ['key' => 'person_phone', 'label' => 'Telefone', 'description' => 'Telefone da pessoa'],
            ['key' => 'person_status', 'label' => 'Status pipeline', 'description' => 'Status atual da movimentacao da pessoa'],
            ['key' => 'person_process', 'label' => 'Processo SEI', 'description' => 'Numero do processo SEI da pessoa'],
            ['key' => 'organ_name', 'label' => 'Orgao', 'description' => 'Nome do orgao de origem'],
            ['key' => 'cost_plan_label', 'label' => 'Plano de custos', 'description' => 'Rotulo da versao ativa de custos'],
            ['key' => 'cost_monthly_total', 'label' => 'Custo mensal', 'description' => 'Total mensal equivalente em BRL'],
            ['key' => 'cost_annual_total', 'label' => 'Custo anual', 'description' => 'Total anualizado em BRL'],
            ['key' => 'cdo_number', 'label' => 'Numero CDO', 'description' => 'Numero do CDO mais recente vinculado'],
            ['key' => 'cdo_status', 'label' => 'Status CDO', 'description' => 'Status atual do CDO'],
            ['key' => 'cdo_allocated_amount', 'label' => 'Valor CDO', 'description' => 'Valor alocado no CDO para a pessoa'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>, id?: int}
     */
    public function createTemplate(array $input, int $userId, string $ip, string $userAgent): array
    {
        $templateValidation = $this->validateTemplateMetadata($input, null);
        $versionValidation = $this->validateVersionInput($input);

        $errors = array_merge($templateValidation['errors'], $versionValidation['errors']);
        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'data' => array_merge($templateValidation['data'], $versionValidation['data']),
            ];
        }

        $templateData = $templateValidation['data'];
        $templateData['created_by'] = $userId > 0 ? $userId : null;

        $versionData = $versionValidation['data'];
        $versionData['version_number'] = 1;
        $versionData['is_active'] = 1;
        $versionData['created_by'] = $userId > 0 ? $userId : null;

        try {
            $this->templates->beginTransaction();

            $templateId = $this->templates->createTemplate($templateData);

            $versionData['template_id'] = $templateId;
            $versionId = $this->templates->createVersion($versionData);

            $this->audit->log(
                entity: 'office_template',
                entityId: $templateId,
                action: 'create',
                beforeData: null,
                afterData: $templateData,
                metadata: [
                    'version_id' => $versionId,
                    'version_number' => 1,
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'office_template',
                type: 'office_template.created',
                payload: [
                    'template_key' => $templateData['template_key'],
                    'template_type' => $templateData['template_type'],
                    'version_number' => 1,
                ],
                entityId: $templateId,
                userId: $userId
            );

            $this->templates->commit();
        } catch (\Throwable $exception) {
            $this->templates->rollBack();

            return [
                'ok' => false,
                'errors' => ['Falha ao criar template. Tente novamente.'],
                'data' => array_merge($templateValidation['data'], $versionValidation['data']),
            ];
        }

        return [
            'ok' => true,
            'errors' => [],
            'data' => array_merge($templateData, $versionData),
            'id' => $templateId,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, errors: array<int, string>, data: array<string, mixed>}
     */
    public function updateTemplate(int $id, array $input, int $userId, string $ip, string $userAgent): array
    {
        $before = $this->templates->findTemplateById($id);
        if ($before === null) {
            return [
                'ok' => false,
                'errors' => ['Template nao encontrado.'],
                'data' => [],
            ];
        }

        $validation = $this->validateTemplateMetadata($input, $id);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $validation['errors'],
                'data' => $validation['data'],
            ];
        }

        $this->templates->updateTemplate($id, $validation['data']);
        $after = $this->templates->findTemplateById($id);

        $this->audit->log(
            entity: 'office_template',
            entityId: $id,
            action: 'update',
            beforeData: $before,
            afterData: $after ?? $validation['data'],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'office_template',
            type: 'office_template.updated',
            payload: [
                'template_key' => (string) ($validation['data']['template_key'] ?? ''),
                'template_type' => (string) ($validation['data']['template_type'] ?? ''),
            ],
            entityId: $id,
            userId: $userId
        );

        return [
            'ok' => true,
            'errors' => [],
            'data' => $validation['data'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>}
     */
    public function addVersion(int $templateId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $template = $this->templates->findTemplateById($templateId);
        if ($template === null) {
            return [
                'ok' => false,
                'message' => 'Template nao encontrado.',
                'errors' => ['Template nao encontrado.'],
            ];
        }

        $validation = $this->validateVersionInput($input);
        if ($validation['errors'] !== []) {
            return [
                'ok' => false,
                'message' => 'Nao foi possivel criar nova versao.',
                'errors' => $validation['errors'],
            ];
        }

        $versionData = $validation['data'];
        $versionData['template_id'] = $templateId;
        $versionData['version_number'] = $this->templates->nextVersionNumber($templateId);
        $versionData['is_active'] = 1;
        $versionData['created_by'] = $userId > 0 ? $userId : null;

        try {
            $this->templates->beginTransaction();

            $this->templates->deactivateVersions($templateId);
            $versionId = $this->templates->createVersion($versionData);

            $this->audit->log(
                entity: 'office_template_version',
                entityId: $versionId,
                action: 'create',
                beforeData: null,
                afterData: $versionData,
                metadata: [
                    'template_key' => (string) ($template['template_key'] ?? ''),
                ],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'office_template',
                type: 'office_template.version_created',
                payload: [
                    'template_key' => (string) ($template['template_key'] ?? ''),
                    'version_id' => $versionId,
                    'version_number' => (int) $versionData['version_number'],
                ],
                entityId: $templateId,
                userId: $userId
            );

            $this->templates->commit();
        } catch (\Throwable $exception) {
            $this->templates->rollBack();

            return [
                'ok' => false,
                'message' => 'Nao foi possivel criar nova versao.',
                'errors' => ['Falha ao persistir versao do template.'],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Nova versao publicada com sucesso.',
            'errors' => [],
        ];
    }

    public function deleteTemplate(int $templateId, int $userId, string $ip, string $userAgent): bool
    {
        $before = $this->templates->findTemplateById($templateId);
        if ($before === null) {
            return false;
        }

        try {
            $this->templates->beginTransaction();

            $this->templates->softDeleteDocumentsByTemplate($templateId);
            $this->templates->softDeleteVersionsByTemplate($templateId);
            $this->templates->softDeleteTemplate($templateId);

            $this->audit->log(
                entity: 'office_template',
                entityId: $templateId,
                action: 'delete',
                beforeData: $before,
                afterData: null,
                metadata: null,
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'office_template',
                type: 'office_template.deleted',
                payload: [
                    'template_key' => (string) ($before['template_key'] ?? ''),
                ],
                entityId: $templateId,
                userId: $userId
            );

            $this->templates->commit();
        } catch (\Throwable $exception) {
            $this->templates->rollBack();

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string, errors: array<int, string>, id?: int}
     */
    public function generateDocument(int $templateId, array $input, int $userId, string $ip, string $userAgent): array
    {
        $template = $this->templates->findTemplateById($templateId);
        if ($template === null) {
            return [
                'ok' => false,
                'message' => 'Template nao encontrado.',
                'errors' => ['Template nao encontrado.'],
            ];
        }

        $personId = (int) ($input['person_id'] ?? 0);
        if ($personId <= 0) {
            return [
                'ok' => false,
                'message' => 'Pessoa invalida para geracao.',
                'errors' => ['Pessoa e obrigatoria para gerar oficio.'],
            ];
        }

        $person = $this->templates->findPersonById($personId);
        if ($person === null) {
            return [
                'ok' => false,
                'message' => 'Pessoa nao encontrada.',
                'errors' => ['Pessoa informada nao foi encontrada.'],
            ];
        }

        $versionIdInput = (int) ($input['version_id'] ?? 0);
        $version = $versionIdInput > 0
            ? $this->templates->findVersionById($versionIdInput, $templateId)
            : $this->templates->findVersionById((int) ($template['active_version_id'] ?? 0), $templateId);

        if ($version === null) {
            return [
                'ok' => false,
                'message' => 'Versao do template nao encontrada.',
                'errors' => ['Nao ha versao valida do template para geracao.'],
            ];
        }

        $cost = $this->templates->latestCostSummaryByPerson($personId);
        $cdo = $this->templates->latestCdoByPerson($personId);
        $context = $this->buildContext($person, $cost, $cdo);

        $renderedSubject = $this->renderTemplate((string) ($version['subject'] ?? ''), $context, false);
        $renderedHtml = $this->renderTemplate((string) ($version['body_html'] ?? ''), $context, true);

        $documentData = [
            'template_id' => (int) ($template['id'] ?? 0),
            'template_version_id' => (int) ($version['id'] ?? 0),
            'person_id' => $personId,
            'organ_id' => (int) ($person['organ_id'] ?? 0),
            'rendered_subject' => $renderedSubject,
            'rendered_html' => $renderedHtml,
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'generated_by' => $userId > 0 ? $userId : null,
        ];

        $documentId = $this->templates->createDocument($documentData);

        $this->audit->log(
            entity: 'office_document',
            entityId: $documentId,
            action: 'generate',
            beforeData: null,
            afterData: [
                'template_id' => $documentData['template_id'],
                'template_version_id' => $documentData['template_version_id'],
                'person_id' => $personId,
                'rendered_subject' => $renderedSubject,
            ],
            metadata: null,
            userId: $userId,
            ip: $ip,
            userAgent: $userAgent
        );

        $this->events->recordEvent(
            entity: 'office_template',
            type: 'office_document.generated',
            payload: [
                'document_id' => $documentId,
                'template_key' => (string) ($template['template_key'] ?? ''),
                'template_version' => (int) ($version['version_number'] ?? 0),
                'person_id' => $personId,
            ],
            entityId: $templateId,
            userId: $userId
        );

        return [
            'ok' => true,
            'message' => 'Oficio gerado com sucesso.',
            'errors' => [],
            'id' => $documentId,
        ];
    }

    /**
     * @param array<string, mixed> $person
     * @param array<string, mixed>|null $cost
     * @param array<string, mixed>|null $cdo
     * @return array<string, string>
     */
    private function buildContext(array $person, ?array $cost, ?array $cdo): array
    {
        return [
            'today' => date('d/m/Y'),
            'person_name' => (string) ($person['name'] ?? ''),
            'person_cpf' => (string) ($person['cpf'] ?? ''),
            'person_email' => (string) ($person['email'] ?? ''),
            'person_phone' => (string) ($person['phone'] ?? ''),
            'person_status' => (string) ($person['status'] ?? ''),
            'person_process' => (string) ($person['sei_process_number'] ?? ''),
            'organ_name' => (string) ($person['organ_name'] ?? ''),
            'cost_plan_label' => $cost === null ? '' : (string) ($cost['label'] ?? ''),
            'cost_monthly_total' => $this->money($cost === null ? 0.0 : (float) ($cost['monthly_total'] ?? 0)),
            'cost_annual_total' => $this->money($cost === null ? 0.0 : (float) ($cost['annualized_total'] ?? 0)),
            'cdo_number' => $cdo === null ? '' : (string) ($cdo['number'] ?? ''),
            'cdo_status' => $cdo === null ? '' : (string) ($cdo['status'] ?? ''),
            'cdo_allocated_amount' => $this->money($cdo === null ? 0.0 : (float) ($cdo['allocated_amount'] ?? 0)),
        ];
    }

    /**
     * @param array<string, string> $context
     */
    private function renderTemplate(string $template, array $context, bool $htmlMode): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            static function (array $matches) use ($context, $htmlMode): string {
                $key = (string) ($matches[1] ?? '');
                $value = (string) ($context[$key] ?? '');

                if ($htmlMode) {
                    return e($value);
                }

                return $value;
            },
            $template
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, data: array<string, mixed>}
     */
    private function validateTemplateMetadata(array $input, ?int $ignoreId): array
    {
        $templateKeyRaw = mb_strtolower((string) ($input['template_key'] ?? ''));
        $templateKeyRaw = trim(str_replace(' ', '_', $templateKeyRaw));
        $name = $this->clean($input['name'] ?? null);
        $templateType = mb_strtolower((string) ($input['template_type'] ?? 'orgao'));
        $description = $this->clean($input['description'] ?? null);
        $isActive = (string) ($input['is_active'] ?? '1') === '0' ? 0 : 1;

        $errors = [];

        if ($templateKeyRaw === '' || preg_match('/^[a-z0-9_-]{3,80}$/', $templateKeyRaw) !== 1) {
            $errors[] = 'Chave do template invalida. Use 3-80 caracteres [a-z0-9_-].';
        }

        if ($templateKeyRaw !== '' && $this->templates->templateKeyExists($templateKeyRaw, $ignoreId)) {
            $errors[] = 'Ja existe template com esta chave.';
        }

        if ($name === null || mb_strlen($name) < 3) {
            $errors[] = 'Nome do template e obrigatorio (minimo 3 caracteres).';
        }

        if (!in_array($templateType, self::ALLOWED_TYPES, true)) {
            $errors[] = 'Tipo de template invalido.';
        }

        $data = [
            'template_key' => mb_substr($templateKeyRaw, 0, 80),
            'name' => $name === null ? '' : mb_substr($name, 0, 120),
            'template_type' => $templateType,
            'description' => $description === null ? null : mb_substr($description, 0, 255),
            'is_active' => $isActive,
        ];

        return [
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<int, string>, data: array<string, mixed>}
     */
    private function validateVersionInput(array $input): array
    {
        $subject = $this->clean($input['subject'] ?? null);
        $bodyHtml = trim((string) ($input['body_html'] ?? ''));
        $variablesRaw = trim((string) ($input['variables_json'] ?? ''));
        $notes = $this->clean($input['notes'] ?? null);

        $errors = [];

        if ($subject === null || mb_strlen($subject) < 3) {
            $errors[] = 'Assunto do template e obrigatorio (minimo 3 caracteres).';
        }

        if ($bodyHtml === '' || mb_strlen($bodyHtml) < 10) {
            $errors[] = 'Corpo HTML do template e obrigatorio.';
        }

        $variablesJson = null;
        if ($variablesRaw !== '') {
            $decoded = json_decode($variablesRaw, true);
            if (!is_array($decoded)) {
                $errors[] = 'JSON de variaveis invalido.';
            } else {
                $variablesJson = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        } else {
            $keys = array_map(
                static fn (array $row): string => (string) ($row['key'] ?? ''),
                $this->availableVariables()
            );
            $variablesJson = json_encode($keys, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $data = [
            'subject' => $subject === null ? '' : mb_substr($subject, 0, 190),
            'body_html' => $bodyHtml,
            'variables_json' => $variablesJson,
            'notes' => $notes,
        ];

        return [
            'errors' => $errors,
            'data' => $data,
        ];
    }

    private function clean(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}
