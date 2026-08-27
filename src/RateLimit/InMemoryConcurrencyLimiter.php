<?php

declare(strict_types=1);

namespace App\RateLimit;

use App\Exception\ConcurrencyLimitException;

/**
 * In-memory semaphore for tests.
 */
final class InMemoryConcurrencyLimiter implements ConcurrencyLimiterInterface
{
    /** @var array<string, int> */
    private array $inflight = [];

    public function __construct(
        private readonly int $maxConcurrent = 2,
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
        $this->inflight[$endpointId] = ($this->inflight[$endpointId] ?? 0) + 1;

        if ($this->inflight[$endpointId] > $this->maxConcurrent) {
            --$this->inflight[$endpointId];
            throw new ConcurrencyLimitException($endpointId);
        }

        try {
            return $callback();
        } finally {
            --$this->inflight[$endpointId];
        }
    }
}
