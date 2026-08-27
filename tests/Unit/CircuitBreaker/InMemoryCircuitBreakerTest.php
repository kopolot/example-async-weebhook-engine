<?php

declare(strict_types=1);

namespace App\Tests\Unit\CircuitBreaker;

use App\CircuitBreaker\InMemoryCircuitBreaker;
use App\Exception\CircuitOpenException;
use PHPUnit\Framework\TestCase;

final class InMemoryCircuitBreakerTest extends TestCase
{
    public function testOpensAfterFailureThreshold(): void
    {
        $breaker = new InMemoryCircuitBreaker(failureThreshold: 3, openSeconds: 60);

        $breaker->recordFailure('acme');
        $breaker->recordFailure('acme');
        self::assertSame(2, $breaker->failureCount('acme'));
        self::assertFalse($breaker->isOpen('acme'));

        $breaker->recordFailure('acme');
        self::assertTrue($breaker->isOpen('acme'));
        self::assertSame(0, $breaker->failureCount('acme'));

        $this->expectException(CircuitOpenException::class);
        $breaker->assertAvailable('acme');
    }

    public function testSuccessResetsFailures(): void
    {
        $breaker = new InMemoryCircuitBreaker(failureThreshold: 3, openSeconds: 60);

        $breaker->recordFailure('acme');
        $breaker->recordFailure('acme');
        $breaker->recordSuccess('acme');

        self::assertSame(0, $breaker->failureCount('acme'));
        $breaker->assertAvailable('acme');
    }

    public function testHalfOpenAfterOpenWindowExpires(): void
    {
        $clock = new class {
            public int $now = 1_000_000;
        };

        $breaker = new InMemoryCircuitBreaker(
            failureThreshold: 1,
            openSeconds: 10,
            clock: fn (): int => $clock->now,
        );

        $breaker->recordFailure('acme');
        self::assertTrue($breaker->isOpen('acme'));

        $clock->now = 1_000_011;
        $breaker->assertAvailable('acme');
        self::assertFalse($breaker->isOpen('acme'));
    }
}
