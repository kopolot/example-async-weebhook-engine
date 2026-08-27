<?php

declare(strict_types=1);

namespace App\Tests\Integration\Redis;

use App\CircuitBreaker\RedisCircuitBreaker;
use App\Exception\CircuitOpenException;
use App\Exception\ConcurrencyLimitException;
use App\RateLimit\EndpointConcurrencyLimiter;
use App\RedisFactory;
use PHPUnit\Framework\TestCase;

abstract class RedisTestCase extends TestCase
{
    protected \Redis $redis;

    protected function setUp(): void
    {
        parent::setUp();

        if (!\extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not available.');
        }

        $hostRaw = $_SERVER['REDIS_HOST'] ?? $_ENV['REDIS_HOST'] ?? '127.0.0.1';
        $portRaw = $_SERVER['REDIS_PORT'] ?? $_ENV['REDIS_PORT'] ?? 6379;
        $host = \is_string($hostRaw) ? $hostRaw : '127.0.0.1';
        $port = \is_numeric($portRaw) ? (int) $portRaw : 6379;

        try {
            $this->redis = RedisFactory::create($host, $port);
            $this->redis->ping();
        } catch (\Throwable $exception) {
            self::markTestSkipped('Redis is not reachable: '.$exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->redis)) {
            try {
                $this->redis->flushDB();
            } catch (\Throwable) {
                // Ignore cleanup failures when the connection already dropped.
            }
        }

        parent::tearDown();
    }
}
