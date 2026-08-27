<?php

declare(strict_types=1);

namespace App\Tests\Integration\Redis;

use App\CircuitBreaker\RedisCircuitBreaker;
use App\Exception\CircuitOpenException;

final class RedisCircuitBreakerTest extends RedisTestCase
{
    public function testOpensAfterFailureThresholdAndBlocksTraffic(): void
    {
        $breaker = new RedisCircuitBreaker($this->redis, failureThreshold: 3, openSeconds: 60);
        $endpoint = 'acme-open-'.uniqid('', true);

        $breaker->recordFailure($endpoint);
        $breaker->recordFailure($endpoint);
        $breaker->assertAvailable($endpoint);
        $breaker->recordSuccess($endpoint);

        $breaker->recordFailure($endpoint);
        $breaker->recordFailure($endpoint);
        $breaker->recordFailure($endpoint);

        $this->expectException(CircuitOpenException::class);
        $breaker->assertAvailable($endpoint);
    }

    public function testHalfOpenAllowsOnlyOneProbe(): void
    {
        $breaker = new RedisCircuitBreaker($this->redis, failureThreshold: 1, openSeconds: 1);
        $endpoint = 'acme-probe-'.uniqid('', true);

        $breaker->recordFailure($endpoint);

        try {
            $breaker->assertAvailable($endpoint);
            self::fail('Circuit should be open.');
        } catch (CircuitOpenException) {
        }

        usleep(1_100_000);

        $breaker->assertAvailable($endpoint);

        $this->expectException(CircuitOpenException::class);
        $breaker->assertAvailable($endpoint);
    }
}
