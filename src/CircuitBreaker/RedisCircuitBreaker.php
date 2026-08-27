<?php

declare(strict_types=1);

namespace App\CircuitBreaker;

use App\Exception\CircuitOpenException;

/**
 * Redis-backed circuit breaker: closed → open after N consecutive failures,
 * then half-open when the open TTL expires (probe allowed).
 */
final class RedisCircuitBreaker implements CircuitBreakerInterface
{
    private const KEY_FAILURES = 'cb:%s:failures';
    private const KEY_OPEN = 'cb:%s:open';

    public function __construct(
        private readonly \Redis $redis,
        private readonly int $failureThreshold = 50,
        private readonly int $openSeconds = 60,
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
        if ($this->redis->exists($this->key(self::KEY_OPEN, $endpointId)) === 1) {
            throw new CircuitOpenException($endpointId);
        }
    }

    public function recordSuccess(string $endpointId): void
    {
        $this->redis->del(
            $this->key(self::KEY_FAILURES, $endpointId),
            $this->key(self::KEY_OPEN, $endpointId),
        );
    }

    public function recordFailure(string $endpointId): void
    {
        $failuresKey = $this->key(self::KEY_FAILURES, $endpointId);
        $failures = (int) $this->redis->incr($failuresKey);
        $this->redis->expire($failuresKey, $this->openSeconds * 10);

        if ($failures >= $this->failureThreshold) {
            $this->redis->setex(
                $this->key(self::KEY_OPEN, $endpointId),
                $this->openSeconds,
                '1',
            );
            $this->redis->del($failuresKey);
        }
    }

    private function key(string $pattern, string $endpointId): string
    {
        return sprintf($pattern, hash('xxh128', $endpointId));
    }
}
