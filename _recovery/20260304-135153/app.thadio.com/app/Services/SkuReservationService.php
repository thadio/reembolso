<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use App\Repositories\SkuReservationRepository;

class SkuReservationService
{
    private SkuReservationRepository $repo;
    private ProductRepository $products;

    public function __construct(SkuReservationRepository $repo, ProductRepository $products)
    {
        $this->repo = $repo;
        $this->products = $products;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function ensureReservation(
        string $context,
        string $contextKey,
        ?int $userId,
        ?string $sessionId
    ): ?array {
        if (!$this->repo->hasConnection() || $contextKey === '') {
            return null;
        }
        $existing = $this->repo->listByContextKey($context, $contextKey, $sessionId);
        if (!empty($existing)) {
            return $existing[0];
        }
        $created = $this->reserveMany(1, $context, $contextKey, $userId, $sessionId);
        return $created[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function reserveNewOne(
        string $context,
        string $contextKey,
        ?int $userId,
        ?string $sessionId
    ): ?array {
        if (!$this->repo->hasConnection() || $contextKey === '') {
            return null;
        }
        $this->repo->cleanupExpired($this->ttlMinutes());
        $candidate = $this->resolveStartCandidate();
        $attempts = 0;
        $maxAttempts = 200;

        while ($attempts < $maxAttempts) {
            $sku = (string) $candidate;
            if ($this->products->skuExists($sku)) {
                $candidate++;
                $attempts++;
                continue;
            }
            try {
                $row = $this->repo->insertReservation($sku, $context, $contextKey, $sessionId, $userId);
                if ($row) {
                    return $row;
                }
            } catch (\Throwable $e) {
                if ($this->isDuplicateKeyError($e)) {
                    $candidate++;
                    $attempts++;
                    continue;
                }
                throw $e;
            }
            $candidate++;
            $attempts++;
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function reserveMany(
        int $count,
        string $context,
        string $contextKey,
        ?int $userId,
        ?string $sessionId
    ): array {
        if ($count <= 0 || !$this->repo->hasConnection() || $contextKey === '') {
            return [];
        }
        $this->repo->cleanupExpired($this->ttlMinutes());
        $existing = $this->repo->listByContextKey($context, $contextKey, $sessionId);
        if (count($existing) >= $count) {
            return array_slice($existing, 0, $count);
        }

        $reservations = $existing;
        $candidate = $this->resolveStartCandidate();
        $attempts = 0;
        $maxAttempts = max(200, $count * 50);

        while (count($reservations) < $count && $attempts < $maxAttempts) {
            $sku = (string) $candidate;
            if ($this->products->skuExists($sku)) {
                $candidate++;
                $attempts++;
                continue;
            }
            try {
                $row = $this->repo->insertReservation($sku, $context, $contextKey, $sessionId, $userId);
                if ($row) {
                    $reservations[] = $row;
                }
            } catch (\Throwable $e) {
                if ($this->isDuplicateKeyError($e)) {
                    $candidate++;
                    $attempts++;
                    continue;
                }
                throw $e;
            }
            $candidate++;
            $attempts++;
        }

        return $reservations;
    }

    public function consumeReservation(int $id, string $context, string $contextKey, ?string $sessionId): ?string
    {
        if ($id <= 0 || $contextKey === '' || !$this->repo->hasConnection()) {
            return null;
        }
        return $this->repo->consumeById($id, $context, $contextKey, $sessionId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listReservations(string $context, string $contextKey, ?string $sessionId): array
    {
        if ($contextKey === '' || !$this->repo->hasConnection()) {
            return [];
        }
        return $this->repo->listByContextKey($context, $contextKey, $sessionId);
    }

    public function releaseContext(string $context, string $contextKey, ?string $sessionId): int
    {
        if ($contextKey === '' || !$this->repo->hasConnection()) {
            return 0;
        }
        return $this->repo->releaseByContextKey($context, $contextKey, $sessionId);
    }

    private function resolveStartCandidate(): int
    {
        $minSku = $this->skuNumericStart();
        $maxReserved = $this->repo->maxReservedNumericSku();
        $maxProductSku = $this->products->maxNumericSku();
        return max($minSku, $maxReserved + 1, $maxProductSku + 1);
    }

    private function skuNumericStart(): int
    {
        $raw = getenv('SKU_NUMERIC_START');
        if ($raw !== false && $raw !== '') {
            $value = (int) $raw;
            if ($value > 0) {
                return $value;
            }
        }
        return 19100;
    }

    private function ttlMinutes(): int
    {
        $raw = getenv('SKU_RESERVATION_TTL_MINUTES');
        if ($raw !== false && $raw !== '') {
            $value = (int) $raw;
            if ($value > 0) {
                return $value;
            }
        }
        return 240;
    }

    private function isDuplicateKeyError(\Throwable $e): bool
    {
        $code = (string) $e->getCode();
        if ($code === '23000') {
            return true;
        }
        return stripos($e->getMessage(), 'duplicate') !== false;
    }
}
