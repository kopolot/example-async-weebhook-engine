<?php

declare(strict_types=1);

namespace App\RateLimit;

use App\Exception\ConcurrencyLimitException;

/**
 * Limits in-flight webhook deliveries per endpoint via a Redis semaphore.
 */
final class EndpointConcurrencyLimiter implements ConcurrencyLimiterInterface
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly int $maxConcurrent = 2,
        private readonly int $slotTtlSeconds = 30,
    ) {
        if ($this->maxConcurrent < 1) {
            throw new \InvalidArgumentException('maxConcurrent must be >= 1.');
        }
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function run(string $endpointId, callable $callback): mixed
    {
        $key = $this->key($endpointId);
        $count = (int) $this->redis->incr($key);
        $this->redis->expire($key, $this->slotTtlSeconds);

        if ($count > $this->maxConcurrent) {
            $this->redis->decr($key);
            throw new ConcurrencyLimitException($endpointId);
        }

        try {
            return $callback();
        } finally {
            $this->redis->decr($key);
        }
    }

    private function key(string $endpointId): string
    {
        return 'webhook:concurrency:'.hash('xxh128', $endpointId);
    }
}
