<?php

namespace App\Services;

class PersonSyncScheduler
{
    private PersonSyncService $sync;
    private string $stateFile;
    private int $batchSize;
    private int $limit;
    private bool $dryRun = false;

    public function __construct(PersonSyncService $sync, $legacySource = null, string $stateFile = '', int $batchSize = 400, int $limit = 0)
    {
        $this->sync = $sync;
        $this->stateFile = $stateFile !== '' ? $stateFile : __DIR__ . '/../../scripts/person-sync-state.json';
        $this->batchSize = $batchSize > 0 ? $batchSize : 400;
        $this->limit = max(0, $limit);
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    public function maybeRun(int $minIntervalSeconds = 300): int
    {
        return 0;
    }

    public function run(?string $updatedAfter = null): int
    {
        return 0;
    }

    private function readState(): array
    {
        if (!is_file($this->stateFile)) {
            return [];
        }
        $raw = @file_get_contents($this->stateFile);
        if ($raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function writeState(?string $updatedAfter = null): void
    {
        $data = [
            'last_run_at' => date('Y-m-d H:i:s'),
            'last_updated_after_used' => $updatedAfter ?: date('Y-m-d H:i:s'),
        ];
        $dir = dirname($this->stateFile);
        if ($dir !== '' && !is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($this->stateFile, json_encode($data, JSON_PRETTY_PRINT));
    }
}
