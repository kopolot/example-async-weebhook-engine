<?php

declare(strict_types=1);

namespace App\RateLimit;

use App\Exception\ConcurrencyLimitException;

/**
 * Limits in-flight webhook deliveries per endpoint via an atomic Redis semaphore.
 */
final class EndpointConcurrencyLimiter implements ConcurrencyLimiterInterface
{
    private const ACQUIRE_LUA = <<<'LUA'
local current = redis.call('INCR', KEYS[1])
redis.call('EXPIRE', KEYS[1], ARGV[1])
if current > tonumber(ARGV[2]) then
    redis.call('DECR', KEYS[1])
    return 0
end
return 1
LUA;

    private const RELEASE_LUA = <<<'LUA'
local current = tonumber(redis.call('GET', KEYS[1]) or '0')
if current <= 0 then
    redis.call('DEL', KEYS[1])
    return 0
end
return redis.call('DECR', KEYS[1])
LUA;

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
        $acquired = (int) $this->redis->eval(
            self::ACQUIRE_LUA,
            [$key, (string) $this->slotTtlSeconds, (string) $this->maxConcurrent],
            1,
        );

        if ($acquired !== 1) {
            throw new ConcurrencyLimitException($endpointId);
        }

        try {
            return $callback();
        } finally {
            $this->redis->eval(self::RELEASE_LUA, [$key], 1);
        }
    }

    private function key(string $endpointId): string
    {
        return 'webhook:concurrency:'.hash('xxh128', $endpointId);
    }
}
