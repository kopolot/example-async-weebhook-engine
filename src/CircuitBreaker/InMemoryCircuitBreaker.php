<?php

declare(strict_types=1);

namespace App\CircuitBreaker;

use App\Exception\CircuitOpenException;

/**
 * In-memory circuit breaker for tests and environments without Redis.
 */
final class InMemoryCircuitBreaker implements CircuitBreakerInterface
{
    /** @var array<string, int> */
    private array $failures = [];

    /** @var array<string, int> unix timestamp when open window ends */
    private array $openUntil = [];

    public function __construct(
        private readonly int $failureThreshold = 50,
        private readonly int $openSeconds = 60,
        private readonly ?\Closure $clock = null,
    ) {
        if ($this->failureThreshold < 1) {
            throw new \InvalidArgumentException('failureThreshold must be >= 1.');
        }

        if ($this->openSeconds < 1) {
            throw new \InvalidArgumentException('openSeconds must be >= 1.');
        }
    }

    public function assertAvailable(string $endpointId): void
    {
        $openUntil = $this->openUntil[$endpointId] ?? 0;
        if ($openUntil > $this->now()) {
            throw new CircuitOpenException($endpointId);
        }

        unset($this->openUntil[$endpointId]);
    }

    public function recordSuccess(string $endpointId): void
    {
        unset($this->failures[$endpointId], $this->openUntil[$endpointId]);
    }

    public function recordFailure(string $endpointId): void
    {
        $this->failures[$endpointId] = ($this->failures[$endpointId] ?? 0) + 1;

        if ($this->failures[$endpointId] >= $this->failureThreshold) {
            $this->openUntil[$endpointId] = $this->now() + $this->openSeconds;
            unset($this->failures[$endpointId]);
        }
    }

    public function failureCount(string $endpointId): int
    {
        return $this->failures[$endpointId] ?? 0;
    }

    public function isOpen(string $endpointId): bool
    {
        return ($this->openUntil[$endpointId] ?? 0) > $this->now();
    }

    private function now(): int
    {
        if ($this->clock !== null) {
            return (int) ($this->clock)();
        }

        return time();
    }
}
