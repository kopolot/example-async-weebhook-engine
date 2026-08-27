<?php

declare(strict_types=1);

if (class_exists(\Redis::class, false)) {
    return;
}

/**
 * Minimal Redis stub for static analysis / local runs without ext-redis.
 */
class Redis
{
    public function connect(string $host, int $port = 6379, float $timeout = 0.0): bool
    {
        return true;
    }

    public function exists(mixed $key): int|bool
    {
        return 0;
    }

    public function incr(string $key): int|false
    {
        return 1;
    }

    public function decr(string $key): int|false
    {
        return 0;
    }

    public function expire(string $key, int $ttl): bool
    {
        return true;
    }

    public function setex(string $key, int $ttl, mixed $value): bool
    {
        return true;
    }

    /**
     * @param array<int, string> $args
     */
    public function eval(string $script, array $args = [], int $numKeys = 0): mixed
    {
        return 1;
    }

    public function set(string $key, mixed $value, mixed $options = null): bool|string|\Redis
    {
        return true;
    }

    public function ping(mixed $message = null): bool|string|\Redis
    {
        return true;
    }

    public function flushDB(mixed $sync = null): bool
    {
        return true;
    }

    /**
     * @param string|array<int, string> ...$keys
     */
    public function del(string|array ...$keys): int|false
    {
        return 0;
    }
}
