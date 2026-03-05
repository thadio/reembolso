<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class OpsHealthPanelService
{
    public function __construct(private Config $config)
    {
    }

    /**
     * @return array{
     *   available: bool,
     *   status: string,
     *   generated_at: string,
     *   source: array{file: string, age_minutes: int|null},
     *   totals: array{checks_total: int, ok: int, warn: int, fail: int},
     *   checks: array<int, array<string, mixed>>,
     *   recurring_errors: array{groups: int, error_groups: int, error_entries: int, message: string},
     *   history: array<int, array<string, mixed>>,
     *   kpi_snapshot: array<string, mixed>,
     *   log_severity: array<string, mixed>,
     *   app_log: array<string, mixed>,
     *   commands: array<int, string>
     * }
     */
    public function overview(int $historyLimit = 12): array
    {
        $historyLimit = max(3, min(48, $historyLimit));

        $healthPanelDir = $this->resolvePath(
            (string) $this->config->get('ops.health_panel_snapshot_dir', ''),
            'storage/ops/health-panel'
        );
        $healthPanel = $this->latestJsonSnapshot($healthPanelDir, 'ops_health_panel_*.json');
        $healthData = is_array($healthPanel['data'] ?? null) ? $healthPanel['data'] : [];

        $checksRaw = is_array($healthData['checks'] ?? null) ? $healthData['checks'] : [];
        $checks = $this->normalizeChecks($checksRaw);
        $totalsRaw = is_array($healthData['totals'] ?? null) ? $healthData['totals'] : [];
        $totals = [
            'checks_total' => max(0, (int) ($totalsRaw['checks_total'] ?? count($checks))),
            'ok' => max(0, (int) ($totalsRaw['ok'] ?? 0)),
            'warn' => max(0, (int) ($totalsRaw['warn'] ?? 0)),
            'fail' => max(0, (int) ($totalsRaw['fail'] ?? 0)),
        ];
        $status = $this->normalizeStatus((string) ($healthData['status'] ?? ''));

        return [
            'available' => $healthData !== [],
            'status' => $status,
            'generated_at' => (string) ($healthData['generated_at'] ?? ''),
            'source' => [
                'file' => (string) ($healthPanel['file'] ?? ''),
                'age_minutes' => $this->ageMinutes($healthPanel['mtime'] ?? null),
            ],
            'totals' => $totals,
            'checks' => $checks,
            'recurring_errors' => $this->recurringErrors($checks),
            'history' => $this->healthPanelHistory($healthPanelDir, $historyLimit),
            'kpi_snapshot' => $this->kpiSnapshotSummary(),
            'log_severity' => $this->logSeveritySummary(),
            'app_log' => $this->appLogSummary(),
            'commands' => [
                './scripts/ops-health-panel.php --skip-health --output table',
                './scripts/ops-health-panel.php --skip-health --write-snapshot',
                './scripts/ops-quality-gate.php --output table',
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $checks
     *  @return array{groups: int, error_groups: int, error_entries: int, message: string}
     */
    private function recurringErrors(array $checks): array
    {
        foreach ($checks as $check) {
            if ((string) ($check['name'] ?? '') !== 'error_review') {
                continue;
            }

            $metrics = is_array($check['metrics'] ?? null) ? $check['metrics'] : [];

            return [
                'groups' => max(0, (int) ($metrics['recurring_groups'] ?? 0)),
                'error_groups' => max(0, (int) ($metrics['recurring_error_groups'] ?? 0)),
                'error_entries' => max(0, (int) ($metrics['recurring_error_entries'] ?? 0)),
                'message' => (string) ($check['message'] ?? ''),
            ];
        }

        return [
            'groups' => 0,
            'error_groups' => 0,
            'error_entries' => 0,
            'message' => '',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function healthPanelHistory(string $directory, int $limit): array
    {
        $files = $this->snapshotFiles($directory, 'ops_health_panel_*.json');
        if ($files === []) {
            return [];
        }

        $history = [];
        foreach (array_slice($files, 0, $limit) as $file) {
            $content = @file_get_contents($file);
            if (!is_string($content) || $content === '') {
                continue;
            }

            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                continue;
            }

            $totals = is_array($decoded['totals'] ?? null) ? $decoded['totals'] : [];
            $history[] = [
                'generated_at' => (string) ($decoded['generated_at'] ?? ''),
                'status' => $this->normalizeStatus((string) ($decoded['status'] ?? '')),
                'checks_total' => max(0, (int) ($totals['checks_total'] ?? 0)),
                'ok' => max(0, (int) ($totals['ok'] ?? 0)),
                'warn' => max(0, (int) ($totals['warn'] ?? 0)),
                'fail' => max(0, (int) ($totals['fail'] ?? 0)),
                'file' => $file,
            ];
        }

        return $history;
    }

    /** @return array<string, mixed> */
    private function kpiSnapshotSummary(): array
    {
        $configuredDir = (string) $this->config->get('ops.kpi_snapshot_dir', 'storage/ops/kpi_snapshots');
        $directory = $this->resolvePath($configuredDir, 'storage/ops/kpi_snapshots');
        $snapshot = $this->latestJsonSnapshot($directory, 'kpi_snapshot_*.json');
        $data = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
        $maxAgeMinutes = max(1, (int) $this->config->get('ops.kpi_snapshot_max_age_minutes', 240));
        $ageMinutes = $this->ageMinutes($snapshot['mtime'] ?? null);

        return [
            'exists' => $data !== [],
            'file' => (string) ($snapshot['file'] ?? ''),
            'captured_at' => (string) ($data['captured_at'] ?? ''),
            'age_minutes' => $ageMinutes,
            'max_age_minutes' => $maxAgeMinutes,
            'is_stale' => $ageMinutes === null ? true : $ageMinutes > $maxAgeMinutes,
        ];
    }

    /** @return array<string, mixed> */
    private function logSeveritySummary(): array
    {
        $directory = $this->resolvePath(
            (string) $this->config->get('ops.log_severity_snapshot_dir', ''),
            'storage/ops/log-severity'
        );
        $snapshot = $this->latestJsonSnapshot($directory, 'log_severity_*.json');
        $data = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
        $totals = is_array($data['totals'] ?? null) ? $data['totals'] : [];
        $messages = is_array($data['top_messages'] ?? null) ? $data['top_messages'] : [];

        return [
            'exists' => $data !== [],
            'file' => (string) ($snapshot['file'] ?? ''),
            'generated_at' => (string) ($data['generated_at'] ?? ''),
            'window_hours' => max(0, (int) ($data['window_hours'] ?? 0)),
            'totals' => [
                'entries_in_window' => max(0, (int) ($totals['entries_in_window'] ?? 0)),
                'warning' => max(0, (int) ($totals['warning'] ?? 0)),
                'error' => max(0, (int) ($totals['error'] ?? 0)),
            ],
            'top_messages' => array_slice(
                array_values(
                    array_filter(
                        $messages,
                        static fn (mixed $item): bool => is_array($item)
                    )
                ),
                0,
                8
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function appLogSummary(): array
    {
        $path = (string) $this->config->get('paths.storage_logs', BASE_PATH . '/storage/logs/app.log');
        $exists = is_file($path);
        $sizeBytes = $exists ? (int) (@filesize($path) ?: 0) : 0;
        $mtime = $exists ? @filemtime($path) : false;

        return [
            'path' => $path,
            'exists' => $exists,
            'size_bytes' => max(0, $sizeBytes),
            'updated_at' => $mtime === false ? '' : date(DATE_ATOM, (int) $mtime),
        ];
    }

    /** @param array<int, array<string, mixed>> $checksRaw
     *  @return array<int, array<string, mixed>>
     */
    private function normalizeChecks(array $checksRaw): array
    {
        $checks = [];

        foreach ($checksRaw as $check) {
            $name = (string) ($check['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $checks[] = [
                'name' => $name,
                'label' => $this->checkLabel($name),
                'status' => $this->normalizeStatus((string) ($check['status'] ?? '')),
                'message' => (string) ($check['message'] ?? ''),
                'metrics' => is_array($check['metrics'] ?? null) ? $check['metrics'] : [],
                'skipped' => ((bool) ($check['skipped'] ?? false)) === true,
            ];
        }

        return $checks;
    }

    private function checkLabel(string $name): string
    {
        return match ($name) {
            'health_endpoint' => 'Health endpoint',
            'log_severity' => 'Severidade de log',
            'error_review' => 'Erros recorrentes',
            'kpi_snapshot_freshness' => 'Frescor do snapshot KPI',
            default => ucfirst(str_replace('_', ' ', $name)),
        };
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'ok' => 'ok',
            'warn', 'warning' => 'warn',
            'fail', 'error' => 'fail',
            default => 'unknown',
        };
    }

    /** @return array{file: string, mtime: int|null, data: array<string, mixed>} */
    private function latestJsonSnapshot(string $directory, string $pattern): array
    {
        $file = $this->latestSnapshotFile($directory, $pattern);
        if ($file === null) {
            return [
                'file' => '',
                'mtime' => null,
                'data' => [],
            ];
        }

        $content = @file_get_contents($file);
        if (!is_string($content) || $content === '') {
            return [
                'file' => $file,
                'mtime' => $this->fileMtime($file),
                'data' => [],
            ];
        }

        $decoded = json_decode($content, true);

        return [
            'file' => $file,
            'mtime' => $this->fileMtime($file),
            'data' => is_array($decoded) ? $decoded : [],
        ];
    }

    /** @return array<int, string> */
    private function snapshotFiles(string $directory, string $pattern): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob(rtrim($directory, '/') . '/' . $pattern);
        if (!is_array($files) || $files === []) {
            return [];
        }

        $files = array_values(
            array_filter(
                $files,
                static fn (mixed $file): bool => is_string($file) && is_file($file)
            )
        );

        usort(
            $files,
            function (string $left, string $right): int {
                $leftMtime = $this->fileMtime($left) ?? 0;
                $rightMtime = $this->fileMtime($right) ?? 0;
                if ($leftMtime === $rightMtime) {
                    return strcmp($right, $left);
                }

                return $rightMtime <=> $leftMtime;
            }
        );

        return $files;
    }

    private function latestSnapshotFile(string $directory, string $pattern): ?string
    {
        $files = $this->snapshotFiles($directory, $pattern);

        return $files[0] ?? null;
    }

    private function fileMtime(string $file): ?int
    {
        $mtime = @filemtime($file);
        if ($mtime === false) {
            return null;
        }

        return (int) $mtime;
    }

    private function ageMinutes(?int $mtime): ?int
    {
        if ($mtime === null || $mtime <= 0) {
            return null;
        }

        $delta = time() - $mtime;
        if ($delta < 0) {
            return 0;
        }

        return (int) floor($delta / 60);
    }

    private function resolvePath(string $configured, string $defaultRelative): string
    {
        $value = trim($configured);
        if ($value === '') {
            return BASE_PATH . '/' . ltrim($defaultRelative, '/');
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        return BASE_PATH . '/' . ltrim($value, '/');
    }
}
