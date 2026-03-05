<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentIntelligenceRepository;

final class DocumentIntelligenceService
{
    public function __construct(
        private DocumentIntelligenceRepository $intelligence,
        private AuditService $audit,
        private EventService $events
    ) {
    }

    /**
     * @return array{
     *   review: array<string, mixed>|null,
     *   extractions: array<int, array<string, mixed>>,
     *   findings: array<int, array<string, mixed>>,
     *   summary: array<string, int>
     * }
     */
    public function profileData(int $personId): array
    {
        $review = $this->intelligence->latestReviewByPerson($personId);
        if ($review === null) {
            return [
                'review' => null,
                'extractions' => [],
                'findings' => [],
                'summary' => [
                    'documents_total' => 0,
                    'extractions_total' => 0,
                    'inconsistencies_total' => 0,
                    'suggestions_total' => 0,
                    'high_severity_total' => 0,
                ],
            ];
        }

        $reviewId = (int) ($review['id'] ?? 0);
        $extractions = $reviewId > 0 ? $this->intelligence->extractionsByReview($reviewId) : [];
        $findings = $reviewId > 0 ? $this->intelligence->findingsByReview($reviewId) : [];

        $highSeverityTotal = 0;
        foreach ($findings as $row) {
            if ((string) ($row['severity'] ?? '') === 'alta') {
                $highSeverityTotal++;
            }
        }

        return [
            'review' => $review,
            'extractions' => $extractions,
            'findings' => $findings,
            'summary' => [
                'documents_total' => (int) ($review['documents_total'] ?? 0),
                'extractions_total' => (int) ($review['extractions_total'] ?? 0),
                'inconsistencies_total' => (int) ($review['inconsistencies_total'] ?? 0),
                'suggestions_total' => (int) ($review['suggestions_total'] ?? 0),
                'high_severity_total' => $highSeverityTotal,
            ],
        ];
    }

    /** @return array{ok: bool, message: string, errors: array<int, string>, review_id?: int} */
    public function runPersonReview(
        int $personId,
        ?string $expectedSei,
        int $userId,
        string $ip,
        string $userAgent
    ): array {
        if ($personId <= 0) {
            return [
                'ok' => false,
                'message' => 'Pessoa invalida para conferencia assistida.',
                'errors' => ['Pessoa invalida para conferencia assistida.'],
            ];
        }

        $documents = $this->intelligence->documentsForPerson($personId, 100);
        if ($documents === []) {
            return [
                'ok' => false,
                'message' => 'Nao ha documentos suficientes para executar a conferencia assistida.',
                'errors' => ['Nenhum documento encontrado para esta pessoa.'],
            ];
        }

        $reviewId = 0;
        $extractionsTotal = 0;
        $inconsistenciesTotal = 0;
        $suggestionsTotal = 0;

        try {
            $this->intelligence->beginTransaction();

            $reviewId = $this->intelligence->createReview([
                'person_id' => $personId,
                'trigger_source' => 'manual',
                'status' => 'processando',
                'documents_total' => count($documents),
                'extractions_total' => 0,
                'inconsistencies_total' => 0,
                'suggestions_total' => 0,
                'summary' => null,
                'executed_by' => $userId > 0 ? $userId : null,
            ]);

            if ($reviewId <= 0) {
                throw new \RuntimeException('Falha ao iniciar review de inteligencia documental.');
            }

            $statsCache = [];
            foreach ($documents as $document) {
                $documentId = (int) ($document['id'] ?? 0);
                if ($documentId <= 0) {
                    continue;
                }

                $documentTypeId = (int) ($document['document_type_id'] ?? 0);
                if (!isset($statsCache[$documentTypeId])) {
                    $statsCache[$documentTypeId] = $this->intelligence->amountStatsByPersonAndType($personId, $documentTypeId);
                }

                $extraction = $this->extractFromDocument($document);
                $payload = json_encode($extraction['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $this->intelligence->createExtraction([
                    'review_id' => $reviewId,
                    'person_id' => $personId,
                    'document_id' => $documentId,
                    'source_scope' => 'metadata',
                    'extracted_sei' => $extraction['extracted_sei'],
                    'extracted_cpf' => $extraction['extracted_cpf'],
                    'extracted_competence' => $extraction['extracted_competence'],
                    'extracted_amount' => $extraction['extracted_amount'],
                    'confidence_score' => $extraction['confidence_score'],
                    'raw_excerpt' => $extraction['raw_excerpt'],
                    'extracted_payload' => is_string($payload) ? $payload : null,
                ]);

                $extractionsTotal++;

                $findings = $this->buildInconsistencyFindings(
                    personId: $personId,
                    expectedSei: $expectedSei,
                    document: $document,
                    extraction: $extraction,
                    amountStats: is_array($statsCache[$documentTypeId] ?? null)
                        ? $statsCache[$documentTypeId]
                        : ['samples' => 0, 'avg_amount' => 0.0, 'stddev_amount' => 0.0]
                );

                foreach ($findings as $finding) {
                    $metadataJson = json_encode($finding['metadata'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    $this->intelligence->createFinding([
                        'review_id' => $reviewId,
                        'person_id' => $personId,
                        'document_id' => $documentId,
                        'divergence_id' => null,
                        'finding_type' => 'inconsistency',
                        'rule_key' => $finding['rule_key'],
                        'severity' => $finding['severity'],
                        'status' => 'aberta',
                        'title' => $finding['title'],
                        'description' => $finding['description'],
                        'suggested_justification' => null,
                        'confidence_score' => $finding['confidence_score'],
                        'metadata' => is_string($metadataJson) ? $metadataJson : null,
                        'created_by' => $userId > 0 ? $userId : null,
                    ]);
                    $inconsistenciesTotal++;
                }
            }

            $pendingDivergences = $this->intelligence->unresolvedRequiredDivergencesByPerson($personId, 40);
            foreach ($pendingDivergences as $divergence) {
                $suggestion = $this->buildDivergenceSuggestion($personId, $divergence);
                $metadataJson = json_encode($suggestion['metadata'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $this->intelligence->createFinding([
                    'review_id' => $reviewId,
                    'person_id' => $personId,
                    'document_id' => null,
                    'divergence_id' => (int) ($divergence['id'] ?? 0),
                    'finding_type' => 'suggestion',
                    'rule_key' => $suggestion['rule_key'],
                    'severity' => $suggestion['severity'],
                    'status' => 'aberta',
                    'title' => $suggestion['title'],
                    'description' => $suggestion['description'],
                    'suggested_justification' => $suggestion['suggested_justification'],
                    'confidence_score' => $suggestion['confidence_score'],
                    'metadata' => is_string($metadataJson) ? $metadataJson : null,
                    'created_by' => $userId > 0 ? $userId : null,
                ]);
                $suggestionsTotal++;
            }

            $summary = [
                'documents_total' => count($documents),
                'extractions_total' => $extractionsTotal,
                'inconsistencies_total' => $inconsistenciesTotal,
                'suggestions_total' => $suggestionsTotal,
            ];

            $summaryJson = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $this->intelligence->updateReviewSummary($reviewId, [
                'status' => 'concluido',
                'documents_total' => $summary['documents_total'],
                'extractions_total' => $summary['extractions_total'],
                'inconsistencies_total' => $summary['inconsistencies_total'],
                'suggestions_total' => $summary['suggestions_total'],
                'summary' => is_string($summaryJson) ? $summaryJson : null,
            ]);

            $this->audit->log(
                entity: 'document_ai_review',
                entityId: $reviewId,
                action: 'run',
                beforeData: null,
                afterData: [
                    'person_id' => $personId,
                    'documents_total' => $summary['documents_total'],
                    'extractions_total' => $summary['extractions_total'],
                    'inconsistencies_total' => $summary['inconsistencies_total'],
                    'suggestions_total' => $summary['suggestions_total'],
                ],
                metadata: ['trigger_source' => 'manual'],
                userId: $userId,
                ip: $ip,
                userAgent: $userAgent
            );

            $this->events->recordEvent(
                entity: 'person',
                type: 'person.document_ai_review_ran',
                payload: [
                    'person_id' => $personId,
                    'review_id' => $reviewId,
                    'documents_total' => $summary['documents_total'],
                    'inconsistencies_total' => $summary['inconsistencies_total'],
                    'suggestions_total' => $summary['suggestions_total'],
                ],
                entityId: $personId,
                userId: $userId
            );

            $this->intelligence->commit();
        } catch (\Throwable $exception) {
            $this->intelligence->rollBack();

            return [
                'ok' => false,
                'message' => 'Falha ao executar conferencia assistida de documentos.',
                'errors' => ['Nao foi possivel concluir a conferencia assistida no momento.'],
            ];
        }

        return [
            'ok' => true,
            'message' => sprintf(
                'Conferencia assistida concluida: %d extracao(oes), %d inconsistencia(s) e %d sugestao(oes).',
                $extractionsTotal,
                $inconsistenciesTotal,
                $suggestionsTotal
            ),
            'errors' => [],
            'review_id' => $reviewId,
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @return array{
     *   extracted_sei: string|null,
     *   extracted_cpf: string|null,
     *   extracted_competence: string|null,
     *   extracted_amount: float|null,
     *   confidence_score: float,
     *   raw_excerpt: string,
     *   payload: array<string, mixed>
     * }
     */
    private function extractFromDocument(array $document): array
    {
        $chunks = [
            (string) ($document['title'] ?? ''),
            (string) ($document['reference_sei'] ?? ''),
            (string) ($document['tags'] ?? ''),
            (string) ($document['notes'] ?? ''),
            (string) ($document['original_name'] ?? ''),
        ];

        $corpus = trim(implode("\n", array_filter(array_map(
            static fn (string $value): string => trim($value),
            $chunks
        ), static fn (string $value): bool => $value !== '')));

        $seiCandidates = $this->extractSeiCandidates($corpus);
        $cpfCandidates = $this->extractCpfCandidates($corpus);
        $competence = $this->extractCompetence($corpus, $document['document_date'] ?? null);
        $amountCandidates = $this->extractAmountCandidates($corpus);

        $extractedSei = trim((string) ($document['reference_sei'] ?? ''));
        if ($extractedSei === '' && $seiCandidates !== []) {
            $extractedSei = $seiCandidates[0];
        }

        $extractedCpf = $cpfCandidates !== [] ? $cpfCandidates[0] : null;
        $extractedAmount = $amountCandidates !== [] ? $amountCandidates[0] : null;

        $confidence = 20.0;
        if ($extractedSei !== '') {
            $confidence += 30.0;
        }
        if ($extractedCpf !== null) {
            $confidence += 10.0;
        }
        if ($competence !== null) {
            $confidence += 20.0;
        }
        if ($extractedAmount !== null) {
            $confidence += 25.0;
        }

        $confidence = max(0.0, min(99.0, $confidence));

        return [
            'extracted_sei' => $this->clean($extractedSei),
            'extracted_cpf' => $extractedCpf,
            'extracted_competence' => $competence,
            'extracted_amount' => $extractedAmount,
            'confidence_score' => $confidence,
            'raw_excerpt' => mb_substr($corpus, 0, 500),
            'payload' => [
                'sei_candidates' => $seiCandidates,
                'cpf_candidates' => $cpfCandidates,
                'amount_candidates' => $amountCandidates,
                'competence' => $competence,
                'source_fields' => [
                    'title' => (string) ($document['title'] ?? ''),
                    'reference_sei' => (string) ($document['reference_sei'] ?? ''),
                    'tags' => (string) ($document['tags'] ?? ''),
                    'notes' => (string) ($document['notes'] ?? ''),
                    'original_name' => (string) ($document['original_name'] ?? ''),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $extraction
     * @param array{samples: int, avg_amount: float, stddev_amount: float} $amountStats
     * @return array<int, array<string, mixed>>
     */
    private function buildInconsistencyFindings(
        int $personId,
        ?string $expectedSei,
        array $document,
        array $extraction,
        array $amountStats
    ): array {
        $findings = [];

        $documentId = (int) ($document['id'] ?? 0);
        $documentType = (string) ($document['document_type_name'] ?? 'Documento');
        $documentLabel = trim((string) ($document['title'] ?? ''));
        if ($documentLabel === '') {
            $documentLabel = (string) ($document['original_name'] ?? ('Documento #' . $documentId));
        }

        $expectedSeiNormalized = $this->normalizeSei($expectedSei);
        $extractedSeiNormalized = $this->normalizeSei((string) ($extraction['extracted_sei'] ?? ''));

        if ($expectedSeiNormalized !== '' && $extractedSeiNormalized !== '' && $expectedSeiNormalized !== $extractedSeiNormalized) {
            $findings[] = [
                'rule_key' => 'sei_mismatch',
                'severity' => 'alta',
                'title' => 'Referencia SEI divergente do processo principal',
                'description' => sprintf(
                    'Documento "%s" (%s) traz referencia SEI diferente da pessoa. Esperado: %s. Extraido: %s.',
                    $documentLabel,
                    $documentType,
                    (string) $expectedSei,
                    (string) ($extraction['extracted_sei'] ?? '-')
                ),
                'confidence_score' => 94.0,
                'metadata' => [
                    'expected_sei' => $expectedSei,
                    'extracted_sei' => $extraction['extracted_sei'] ?? null,
                ],
            ];
        }

        if ($extractedSeiNormalized === '') {
            $findings[] = [
                'rule_key' => 'missing_sei_reference',
                'severity' => 'media',
                'title' => 'Documento sem referencia SEI identificavel',
                'description' => sprintf(
                    'Documento "%s" (%s) nao apresentou referencia SEI clara para conferencia automatica.',
                    $documentLabel,
                    $documentType
                ),
                'confidence_score' => 79.0,
                'metadata' => [],
            ];
        }

        if (($extraction['extracted_competence'] ?? null) === null) {
            $findings[] = [
                'rule_key' => 'missing_competence',
                'severity' => 'baixa',
                'title' => 'Competencia nao identificada no documento',
                'description' => sprintf(
                    'Documento "%s" (%s) sem competencia identificada (MM/AAAA) para conferencia temporal.',
                    $documentLabel,
                    $documentType
                ),
                'confidence_score' => 72.0,
                'metadata' => [],
            ];
        }

        if ($extractedSeiNormalized !== '' && $documentId > 0) {
            $duplicates = $this->intelligence->countDocumentsByReferenceSei(
                personId: $personId,
                referenceSei: (string) ($extraction['extracted_sei'] ?? ''),
                excludeDocumentId: $documentId
            );

            if ($duplicates > 0) {
                $findings[] = [
                    'rule_key' => 'duplicate_sei_reference',
                    'severity' => 'media',
                    'title' => 'Referencia SEI repetida em multiplos documentos',
                    'description' => sprintf(
                        'Documento "%s" compartilha a referencia SEI com %d outro(s) documento(s) da pessoa.',
                        $documentLabel,
                        $duplicates
                    ),
                    'confidence_score' => 81.0,
                    'metadata' => [
                        'duplicates' => $duplicates,
                        'extracted_sei' => $extraction['extracted_sei'] ?? null,
                    ],
                ];
            }
        }

        $amount = $this->toFloat($extraction['extracted_amount'] ?? null);
        if ($amount > 0 && (int) ($amountStats['samples'] ?? 0) >= 5) {
            $avg = (float) ($amountStats['avg_amount'] ?? 0);
            $stddev = (float) ($amountStats['stddev_amount'] ?? 0);

            if ($stddev > 0) {
                $zScore = abs($amount - $avg) / $stddev;
                if ($zScore >= 2.5) {
                    $severity = $zScore >= 3.5 ? 'alta' : 'media';
                    $confidence = max(70.0, min(98.0, 70.0 + ($zScore * 6.0)));

                    $findings[] = [
                        'rule_key' => 'amount_statistical_anomaly',
                        'severity' => $severity,
                        'title' => 'Valor extraido fora do padrao historico',
                        'description' => sprintf(
                            'Documento "%s" indica R$ %s; historico do tipo apresenta media R$ %s (desvio R$ %s, z-score %.2f).',
                            $documentLabel,
                            number_format($amount, 2, ',', '.'),
                            number_format($avg, 2, ',', '.'),
                            number_format($stddev, 2, ',', '.'),
                            $zScore
                        ),
                        'confidence_score' => $confidence,
                        'metadata' => [
                            'amount' => $amount,
                            'avg' => $avg,
                            'stddev' => $stddev,
                            'z_score' => round($zScore, 2),
                            'samples' => (int) ($amountStats['samples'] ?? 0),
                        ],
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * @param array<string, mixed> $divergence
     * @return array<string, mixed>
     */
    private function buildDivergenceSuggestion(int $personId, array $divergence): array
    {
        $divergenceId = (int) ($divergence['id'] ?? 0);
        $matchKey = trim((string) ($divergence['match_key'] ?? 'Item')); // chave semantica da divergencia

        $history = [];
        if ($matchKey !== '') {
            $history = $this->intelligence->historicalJustificationsByMatchKey($matchKey, $divergenceId, 5);
        }

        if ($history === []) {
            $history = $this->intelligence->historicalJustificationsByPerson($personId, $divergenceId, 5);
        }

        if ($history !== []) {
            $chosen = trim((string) ($history[0]['justification_text'] ?? ''));
            $chosen = mb_substr($chosen, 0, 600);
            $recurrence = count($history);

            $description = sprintf(
                'Sugestao baseada em %d justificativa(s) historica(s) de divergencias semelhantes para este contexto.',
                $recurrence
            );

            return [
                'rule_key' => 'recurring_divergence_justification',
                'severity' => (string) ($divergence['severity'] ?? 'media'),
                'title' => 'Sugestao automatica de justificativa recorrente',
                'description' => $description,
                'suggested_justification' => $chosen !== ''
                    ? $chosen
                    : $this->fallbackSuggestionText($divergence),
                'confidence_score' => max(60.0, min(96.0, 62.0 + ($recurrence * 7.0))),
                'metadata' => [
                    'history_count' => $recurrence,
                    'divergence_id' => $divergenceId,
                ],
            ];
        }

        return [
            'rule_key' => 'divergence_template_justification',
            'severity' => (string) ($divergence['severity'] ?? 'media'),
            'title' => 'Sugestao automatica de justificativa (template)',
            'description' => 'Sem historico recorrente suficiente. Sugestao gerada por regras com base nos valores da divergencia.',
            'suggested_justification' => $this->fallbackSuggestionText($divergence),
            'confidence_score' => 58.0,
            'metadata' => [
                'history_count' => 0,
                'divergence_id' => $divergenceId,
            ],
        ];
    }

    /** @return array<int, string> */
    private function extractSeiCandidates(string $corpus): array
    {
        if ($corpus === '') {
            return [];
        }

        preg_match_all('/\b\d{5}\.\d{6}\/\d{4}\-\d{2}\b/u', $corpus, $matches);
        $values = is_array($matches[0] ?? null) ? $matches[0] : [];

        return array_values(array_unique(array_map(static fn (string $value): string => trim($value), $values)));
    }

    /** @return array<int, string> */
    private function extractCpfCandidates(string $corpus): array
    {
        if ($corpus === '') {
            return [];
        }

        preg_match_all('/\b\d{3}\.?\d{3}\.?\d{3}\-?\d{2}\b/u', $corpus, $matches);
        $rawValues = is_array($matches[0] ?? null) ? $matches[0] : [];

        $normalized = [];
        foreach ($rawValues as $value) {
            $digits = preg_replace('/\D+/', '', (string) $value);
            if (!is_string($digits) || strlen($digits) !== 11) {
                continue;
            }

            $normalized[] = substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
        }

        return array_values(array_unique($normalized));
    }

    private function extractCompetence(string $corpus, mixed $documentDate): ?string
    {
        $documentDateValue = is_string($documentDate) ? trim($documentDate) : '';
        if ($documentDateValue !== '') {
            $timestamp = strtotime($documentDateValue);
            if ($timestamp !== false) {
                return date('Y-m-01', $timestamp);
            }
        }

        if ($corpus === '') {
            return null;
        }

        if (preg_match('/\b(0?[1-9]|1[0-2])[\/\-](20\d{2}|19\d{2})\b/u', $corpus, $match) === 1) {
            $month = str_pad((string) ((int) ($match[1] ?? 0)), 2, '0', STR_PAD_LEFT);
            $year = (string) ($match[2] ?? '');
            if ($year !== '') {
                return $year . '-' . $month . '-01';
            }
        }

        $monthNames = [
            'janeiro' => '01',
            'fevereiro' => '02',
            'marco' => '03',
            'abril' => '04',
            'maio' => '05',
            'junho' => '06',
            'julho' => '07',
            'agosto' => '08',
            'setembro' => '09',
            'outubro' => '10',
            'novembro' => '11',
            'dezembro' => '12',
        ];

        $lower = mb_strtolower($this->removeAccents($corpus));
        foreach ($monthNames as $name => $month) {
            if (preg_match('/\b' . preg_quote($name, '/') . '\s+de\s+(19\d{2}|20\d{2})\b/u', $lower, $match) === 1) {
                $year = (string) ($match[1] ?? '');
                if ($year !== '') {
                    return $year . '-' . $month . '-01';
                }
            }
        }

        return null;
    }

    /** @return array<int, float> */
    private function extractAmountCandidates(string $corpus): array
    {
        if ($corpus === '') {
            return [];
        }

        $candidates = [];

        preg_match_all('/R\$\s*([0-9\.]+,[0-9]{2})/u', $corpus, $currencyMatches);
        $currencyValues = is_array($currencyMatches[1] ?? null) ? $currencyMatches[1] : [];
        foreach ($currencyValues as $value) {
            $parsed = $this->parseMoney($value);
            if ($parsed !== null && $parsed > 0) {
                $candidates[] = $parsed;
            }
        }

        if ($candidates === []) {
            preg_match_all('/\b([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})\b/u', $corpus, $matches);
            $rawValues = is_array($matches[1] ?? null) ? $matches[1] : [];
            foreach ($rawValues as $value) {
                $parsed = $this->parseMoney($value);
                if ($parsed !== null && $parsed > 0) {
                    $candidates[] = $parsed;
                }
            }
        }

        $unique = [];
        foreach ($candidates as $value) {
            $key = number_format($value, 2, '.', '');
            $unique[$key] = $value;
        }

        return array_values($unique);
    }

    private function parseMoney(string $value): ?float
    {
        $normalized = preg_replace('/[^0-9,\.\-]/', '', $value);
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private function normalizeSei(?string $value): string
    {
        $clean = trim((string) $value);
        if ($clean === '') {
            return '';
        }

        return preg_replace('/\D+/', '', $clean) ?? '';
    }

    /** @param array<string, mixed> $divergence */
    private function fallbackSuggestionText(array $divergence): string
    {
        $matchKey = trim((string) ($divergence['match_key'] ?? 'item')); 
        $expected = number_format($this->toFloat($divergence['expected_amount'] ?? null), 2, ',', '.');
        $actual = number_format($this->toFloat($divergence['actual_amount'] ?? null), 2, ',', '.');
        $difference = number_format($this->toFloat($divergence['difference_amount'] ?? null), 2, ',', '.');
        $referenceMonth = (string) ($divergence['reference_month'] ?? 'competencia analisada');

        return sprintf(
            'Divergencia recorrente no item %s para a competencia %s. Valor previsto de R$ %s e valor apurado de R$ %s (diferenca de R$ %s). Justifica-se por variacao operacional pontual, com revisao de comprovantes e ajuste registrado na trilha de conciliacao.',
            $matchKey,
            $referenceMonth,
            $expected,
            $actual,
            $difference
        );
    }

    private function removeAccents(string $value): string
    {
        $search = ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç'];
        $replace = ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c'];

        return str_replace($search, $replace, $value);
    }

    private function clean(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function toFloat(mixed $value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return 0.0;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return 0.0;
        }

        $normalized = str_replace(' ', '', $normalized);
        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}
