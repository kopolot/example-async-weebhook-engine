<?php

declare(strict_types=1);

namespace App\Tests\Integration\Redis;

use App\Exception\ConcurrencyLimitException;
use App\RateLimit\EndpointConcurrencyLimiter;

final class RedisConcurrencyLimiterTest extends RedisTestCase
{
    public function testRejectsWhenMaxConcurrentExceeded(): void
    {
        $limiter = new EndpointConcurrencyLimiter($this->redis, maxConcurrent: 1, slotTtlSeconds: 30);
        $endpoint = 'acme-limit-'.uniqid('', true);

        $this->expectException(ConcurrencyLimitException::class);

        $limiter->run($endpoint, function () use ($limiter, $endpoint): void {
            $limiter->run($endpoint, static function (): void {
            });
        });
    }

    public function testReleasesSlotAfterCallback(): void
    {
        $limiter = new EndpointConcurrencyLimiter($this->redis, maxConcurrent: 1, slotTtlSeconds: 30);
        $endpoint = 'acme-release-'.uniqid('', true);

        $limiter->run($endpoint, static fn (): string => 'ok');
        $result = $limiter->run($endpoint, static fn (): string => 'again');

        self::assertSame('again', $result);
    }
}
