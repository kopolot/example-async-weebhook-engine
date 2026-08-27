<?php

declare(strict_types=1);

namespace App\RateLimit;

interface ConcurrencyLimiterInterface
{
    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function run(string $endpointId, callable $callback): mixed;
}
