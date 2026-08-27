<?php

declare(strict_types=1);

namespace App\Tests\Unit\RateLimit;

use App\Exception\ConcurrencyLimitException;
use App\RateLimit\InMemoryConcurrencyLimiter;
use PHPUnit\Framework\TestCase;

final class InMemoryConcurrencyLimiterTest extends TestCase
{
    public function testThrowsWhenLimitExceeded(): void
    {
        $limiter = new InMemoryConcurrencyLimiter(maxConcurrent: 1);

        $this->expectException(ConcurrencyLimitException::class);

        $limiter->run('acme', static function () use ($limiter): void {
            $limiter->run('acme', static function (): void {
            });
        });
    }

    public function testReleasesSlotAfterCallback(): void
    {
        $limiter = new InMemoryConcurrencyLimiter(maxConcurrent: 1);

        $limiter->run('acme', static fn (): string => 'ok');
        $result = $limiter->run('acme', static fn (): string => 'again');

        self::assertSame('again', $result);
    }
}
