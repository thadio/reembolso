<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\TimeEntry;
use App\Repositories\TimeEntryRepository;
use App\Repositories\UserRepository;
use App\Support\Auth;
use App\Support\Html;
use PDO;
use PDOException;

class TimeClockController
{
    private TimeEntryRepository $entries;
    private UserRepository $users;
    private ?string $connectionError;

    public function __construct(?PDO $pdo, ?string $connectionError = null)
    {
        $this->entries = new TimeEntryRepository($pdo);
        $this->users = new UserRepository($pdo);
        $this->connectionError = $connectionError;
    }

    public function index(): void
    {
        $errors = [];
        $success = '';
        $searchQuery = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPageOptions = [50, 100, 200];
        $perPage = (int) ($_GET['per_page'] ?? 100);
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 100;
        }
        $sortKey = trim((string) ($_GET['sort_key'] ?? $_GET['sort'] ?? 'registrado_em'));
        $sortDir = strtolower(trim((string) ($_GET['sort_dir'] ?? $_GET['dir'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';
        $allowedSort = ['registrado_em', 'full_name', 'tipo', 'status', 'aprovado_por_nome', 'observacao'];
        if (!in_array($sortKey, $allowedSort, true)) {
            $sortKey = 'registrado_em';
        }
        $columnFilterKeys = [
            'filter_registrado_em',
            'filter_full_name',
            'filter_tipo',
            'filter_status',
            'filter_aprovado_por_nome',
            'filter_observacao',
        ];
        $columnFilters = [];

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        $currentUser = Auth::user();
        if (!$currentUser) {
            Auth::requireLogin($this->entries->getPdo());
            $currentUser = Auth::user();
        }

        $canApprove = Auth::can('timeclock.approve');
        $canReport = Auth::can('timeclock.report');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['register_type'])) {
                try {
                    Auth::requirePermission('timeclock.create', $this->entries->getPdo());
                    $type = strtolower(trim((string) $_POST['register_type']));
                    if (!in_array($type, ['entrada', 'saida'], true)) {
                        $errors[] = 'Tipo de registro inválido.';
                    } else {
                        $lastEntry = $this->entries->lastForUser((int) $currentUser['id']);
                        $allowedTypes = $this->allowedTypes($lastEntry);
                        if (!in_array($type, $allowedTypes, true)) {
                            $errors[] = 'Sequência inválida. Registre o próximo ponto sugerido.';
                        } else {
                            $note = trim((string) ($_POST['observacao'] ?? ''));
                            $entry = new TimeEntry();
                            $entry->userId = (int) $currentUser['id'];
                            $entry->type = $type;
                            $entry->recordedAt = date('Y-m-d H:i:s');
                            $entry->status = 'pendente';
                            $entry->note = $note !== '' ? $note : null;
                            $this->entries->create($entry);
                            $success = 'Ponto registrado com sucesso.';
                        }
                    }
                } catch (PDOException | \RuntimeException $e) {
                    $errors[] = 'Erro ao registrar ponto: ' . $e->getMessage();
                }
            } elseif (isset($_POST['approve_id']) || isset($_POST['reject_id'])) {
                try {
                    Auth::requirePermission('timeclock.approve', $this->entries->getPdo());
                    $id = (int) ($_POST['approve_id'] ?? $_POST['reject_id']);
                    $status = isset($_POST['approve_id']) ? 'aprovado' : 'rejeitado';
                    $this->entries->updateStatus($id, $status, (int) $currentUser['id']);
                    $success = $status === 'aprovado'
                        ? 'Registro aprovado.'
                        : 'Registro rejeitado.';
                } catch (PDOException | \RuntimeException $e) {
                    $errors[] = 'Erro ao atualizar status: ' . $e->getMessage();
                }
            }
        }

        $statusFilter = trim((string) ($_GET['status'] ?? ''));
        $startFilter = trim((string) ($_GET['start'] ?? ''));
        $endFilter = trim((string) ($_GET['end'] ?? ''));
        $userFilter = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

        $filters = [];
        if ($statusFilter !== '') {
            $filters['status'] = $statusFilter;
        }
        if ($startFilter !== '') {
            $filters['start'] = $this->normalizeDate($startFilter, true);
        }
        if ($endFilter !== '') {
            $filters['end'] = $this->normalizeDate($endFilter, false);
        }

        if ($canApprove || $canReport) {
            if ($userFilter) {
                $filters['user_id'] = $userFilter;
            }
        } else {
            $filters['user_id'] = (int) $currentUser['id'];
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

        $totalRows = $this->entries->countForList($filters);
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $rows = $this->entries->paginateForList($filters, $perPage, $offset, $sortKey, $sortDir);
        $lastEntry = $this->entries->lastForUser((int) $currentUser['id']);
        $allowedTypes = $this->allowedTypes($lastEntry);

        $userOptions = ($canApprove || $canReport) ? $this->users->list() : [];

        View::render('timeclock/list', [
            'rows' => $rows,
            'errors' => $errors,
            'success' => $success,
            'esc' => [Html::class, 'esc'],
            'currentUser' => $currentUser,
            'canApprove' => $canApprove,
            'canReport' => $canReport,
            'allowedTypes' => $allowedTypes,
            'lastEntry' => $lastEntry,
            'filters' => [
                'status' => $statusFilter,
                'start' => $startFilter,
                'end' => $endFilter,
                'user_id' => $userFilter,
            ],
            'searchQuery' => $searchQuery,
            'columnFilters' => $columnFilters,
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'userOptions' => $userOptions,
        ], [
            'title' => 'Gestão de ponto',
        ]);
    }

    public function report(): void
    {
        $errors = [];

        if ($this->connectionError) {
            $errors[] = 'Erro ao conectar ao banco: ' . $this->connectionError;
        }

        Auth::requirePermission('timeclock.report', $this->entries->getPdo());

        $statusFilter = trim((string) ($_GET['status'] ?? 'aprovado'));
        $startFilter = trim((string) ($_GET['start'] ?? ''));
        $endFilter = trim((string) ($_GET['end'] ?? ''));
        $userFilter = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

        $filters = [];
        if ($statusFilter !== '') {
            $filters['status'] = $statusFilter;
        }
        if ($startFilter !== '') {
            $filters['start'] = $this->normalizeDate($startFilter, true);
        }
        if ($endFilter !== '') {
            $filters['end'] = $this->normalizeDate($endFilter, false);
        }
        if ($userFilter) {
            $filters['user_id'] = $userFilter;
        }

        $rows = $this->entries->list($filters);
        $grouped = [];

        foreach ($rows as $row) {
            $userId = (int) $row['user_id'];
            $date = substr((string) $row['registrado_em'], 0, 10);
            $grouped[$userId]['user_name'] = $row['full_name'] ?? ('Usuário #' . $userId);
            $grouped[$userId]['days'][$date][] = $row;
        }

        $summaryRows = [];
        $userTotals = [];

        foreach ($grouped as $userId => $data) {
            foreach ($data['days'] ?? [] as $date => $entries) {
                usort($entries, function ($a, $b) {
                    return strcmp((string) $a['registrado_em'], (string) $b['registrado_em']);
                });

                $totalSeconds = 0;
                $openAt = null;
                $firstEntry = null;
                $lastExit = null;
                $entryCount = 0;
                $exitCount = 0;

                foreach ($entries as $entry) {
                    $ts = strtotime((string) $entry['registrado_em']);
                    if (($entry['tipo'] ?? '') === 'entrada') {
                        $entryCount++;
                        if ($firstEntry === null || $ts < $firstEntry) {
                            $firstEntry = $ts;
                        }
                        if ($openAt === null) {
                            $openAt = $ts;
                        }
                    } else {
                        $exitCount++;
                        if ($lastExit === null || $ts > $lastExit) {
                            $lastExit = $ts;
                        }
                        if ($openAt !== null && $ts >= $openAt) {
                            $totalSeconds += ($ts - $openAt);
                            $openAt = null;
                        }
                    }
                }

                $userTotals[$userId] = ($userTotals[$userId] ?? 0) + $totalSeconds;

                $summaryRows[] = [
                    'user_id' => $userId,
                    'user_name' => $data['user_name'] ?? ('Usuário #' . $userId),
                    'date' => $date,
                    'first_entry' => $firstEntry ? date('H:i', $firstEntry) : '-',
                    'last_exit' => $lastExit ? date('H:i', $lastExit) : '-',
                    'entry_count' => $entryCount,
                    'exit_count' => $exitCount,
                    'total_seconds' => $totalSeconds,
                    'total_hours' => $this->formatDuration($totalSeconds),
                ];
            }
        }

        usort($summaryRows, function ($a, $b) {
            $userCmp = strcmp((string) $a['user_name'], (string) $b['user_name']);
            if ($userCmp !== 0) {
                return $userCmp;
            }
            return strcmp((string) $a['date'], (string) $b['date']);
        });

        $userOptions = $this->users->list();

        View::render('timeclock/report', [
            'rows' => $summaryRows,
            'errors' => $errors,
            'esc' => [Html::class, 'esc'],
            'filters' => [
                'status' => $statusFilter,
                'start' => $startFilter,
                'end' => $endFilter,
                'user_id' => $userFilter,
            ],
            'userOptions' => $userOptions,
            'userTotals' => $userTotals,
            'formatDuration' => function (int $seconds): string {
                return $this->formatDuration($seconds);
            },
        ], [
            'title' => 'Relatório de ponto',
        ]);
    }

    private function allowedTypes(?array $lastEntry): array
    {
        if (!$lastEntry || ($lastEntry['status'] ?? '') === 'rejeitado') {
            return ['entrada'];
        }

        if (($lastEntry['tipo'] ?? '') === 'entrada') {
            return ['saida'];
        }

        return ['entrada'];
    }

    private function normalizeDate(string $date, bool $isStart): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date . ($isStart ? ' 00:00:00' : ' 23:59:59');
        }

        return $date;
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
