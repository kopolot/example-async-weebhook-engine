<?php

declare(strict_types=1);

namespace App\CircuitBreaker;

interface CircuitBreakerInterface
{
    public function assertAvailable(string $endpointId): void;

    public function recordSuccess(string $endpointId): void;

    public function recordFailure(string $endpointId): void;
}
