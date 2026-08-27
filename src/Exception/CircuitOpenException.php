<?php

declare(strict_types=1);

namespace App\Exception;

final class CircuitOpenException extends \RuntimeException
{
    public function __construct(string $endpointId)
    {
        parent::__construct(sprintf('Circuit open for endpoint "%s"; delivery deferred.', $endpointId));
    }
}
