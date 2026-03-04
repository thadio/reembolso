<?php

namespace App\Services;

use App\Repositories\ConsignmentPeriodLockRepository;
use PDO;

/**
 * Gerencia fechamento/abertura de períodos de consignação.
 */
class ConsignmentPeriodLockService
{
    private ?PDO $pdo;
    private ConsignmentPeriodLockRepository $locks;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->locks = new ConsignmentPeriodLockRepository($pdo);
    }

    /**
     * Check if a period (YYYY-MM) is locked.
     */
    public function isLocked(string $yearMonth): bool
    {
        return $this->locks->isLocked($yearMonth);
    }

    /**
     * Check if a date falls in a locked period.
     */
    public function isDateInLockedPeriod(?string $dateTime): bool
    {
        if (!$dateTime) {
            return false;
        }
        $yearMonth = substr($dateTime, 0, 7);
        return $this->isLocked($yearMonth);
    }

    /**
     * Lock a period.
     */
    public function lock(string $yearMonth, int $userId, ?string $notes = null): int
    {
        return $this->locks->lock($yearMonth, $userId, $notes);
    }

    /**
     * Unlock a period (requires admin_override).
     */
    public function unlock(string $yearMonth, int $userId, string $reason): void
    {
        $this->locks->unlock($yearMonth, $userId, $reason);
    }

    /**
     * List all period locks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAll(): array
    {
        return $this->locks->listAll();
    }
}
