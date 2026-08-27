<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messenger;

use App\Exception\CircuitOpenException;
use App\Exception\WebhookDeliveryException;
use App\Messenger\FixedDelayRetryStrategy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class FixedDelayRetryStrategyTest extends TestCase
{
    public function testRetryDelaysMatchPlaybook(): void
    {
        $strategy = new FixedDelayRetryStrategy();
        $envelope = new Envelope(new \stdClass());

        self::assertTrue($strategy->isRetryable($envelope, new CircuitOpenException('acme')));
        self::assertSame(60_000, $strategy->getWaitingTime($envelope));

        $envelope = $envelope->with(new RedeliveryStamp(1));
        self::assertSame(300_000, $strategy->getWaitingTime($envelope));

        $envelope = $envelope->with(new RedeliveryStamp(2));
        self::assertSame(900_000, $strategy->getWaitingTime($envelope));

        $envelope = $envelope->with(new RedeliveryStamp(3));
        self::assertSame(3_600_000, $strategy->getWaitingTime($envelope));

        $envelope = $envelope->with(new RedeliveryStamp(4));
        self::assertFalse($strategy->isRetryable($envelope, new CircuitOpenException('acme')));
    }

    public function testNonRetryableClientErrorsAreNotRetried(): void
    {
        $strategy = new FixedDelayRetryStrategy();
        $envelope = new Envelope(new \stdClass());

        self::assertFalse($strategy->isRetryable(
            $envelope,
            new WebhookDeliveryException('acme', 400, 'Non-retryable client error.'),
        ));
    }

    public function testServerErrorsAreRetried(): void
    {
        $strategy = new FixedDelayRetryStrategy();
        $envelope = new Envelope(new \stdClass());

        self::assertTrue($strategy->isRetryable(
            $envelope,
            new WebhookDeliveryException('acme', 503),
        ));
    }
}
