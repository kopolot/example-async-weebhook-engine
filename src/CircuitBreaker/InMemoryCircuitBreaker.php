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

    /** @var array<string, true> */
    private array $needsProbe = [];

    /** @var array<string, true> */
    private array $probes = [];

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

        if (isset($this->probes[$endpointId])) {
            throw new CircuitOpenException($endpointId);
        }

        if (!isset($this->needsProbe[$endpointId])) {
            return;
        }

        $this->probes[$endpointId] = true;
        unset($this->needsProbe[$endpointId]);
    }

    public function recordSuccess(string $endpointId): void
    {
        unset(
            $this->failures[$endpointId],
            $this->openUntil[$endpointId],
            $this->needsProbe[$endpointId],
            $this->probes[$endpointId],
        );
    }

    public function recordFailure(string $endpointId): void
    {
        unset($this->probes[$endpointId]);
        $this->failures[$endpointId] = ($this->failures[$endpointId] ?? 0) + 1;

        if ($this->failures[$endpointId] >= $this->failureThreshold) {
            $this->openUntil[$endpointId] = $this->now() + $this->openSeconds;
            $this->needsProbe[$endpointId] = true;
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

    public function hasProbe(string $endpointId): bool
    {
        return isset($this->probes[$endpointId]);
    }

    private function now(): int
    {
        if ($this->clock !== null) {
            return (int) ($this->clock)();
        }

        return time();
    }
}
