<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Exception\CircuitOpenException;
use App\Exception\ConcurrencyLimitException;
use App\Exception\WebhookDeliveryException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * Fixed delays: 1m, 5m, 15m, 1h (matches production playbook).
 */
final class FixedDelayRetryStrategy implements RetryStrategyInterface
{
    /**
     * @param list<int> $delaysMs
     */
    public function __construct(
        private readonly array $delaysMs = [60_000, 300_000, 900_000, 3_600_000],
    ) {
        if ($this->delaysMs === []) {
            throw new \InvalidArgumentException('At least one retry delay is required.');
        }
    }

    public function isRetryable(Envelope $message, ?\Throwable $throwable = null): bool
    {
        if ($throwable instanceof WebhookDeliveryException && !$throwable->isRetryable()) {
            return false;
        }

        if (
            $throwable !== null
            && !$throwable instanceof WebhookDeliveryException
            && !$throwable instanceof CircuitOpenException
            && !$throwable instanceof ConcurrencyLimitException
        ) {
            return false;
        }

        $retries = $this->retryCount($message);

        return $retries < \count($this->delaysMs);
    }

    public function getWaitingTime(Envelope $message, ?\Throwable $throwable = null): int
    {
        $retries = $this->retryCount($message);

        return $this->delaysMs[$retries] ?? $this->delaysMs[array_key_last($this->delaysMs)];
    }

    private function retryCount(Envelope $message): int
    {
        return $message->last(RedeliveryStamp::class)?->getRetryCount() ?? 0;
    }
}
