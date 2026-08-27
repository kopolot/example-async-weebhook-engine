<?php

declare(strict_types=1);

namespace App\Exception;

final class ConcurrencyLimitException extends \RuntimeException
{
    public function __construct(string $endpointId)
    {
        parent::__construct(sprintf('Concurrency limit reached for endpoint "%s".', $endpointId));
    }
}
