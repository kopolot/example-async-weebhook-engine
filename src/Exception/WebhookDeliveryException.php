<?php

declare(strict_types=1);

namespace App\Exception;

final class WebhookDeliveryException extends \RuntimeException
{
    public function __construct(
        string $endpointId,
        int $statusCode,
        string $detail = '',
        ?\Throwable $previous = null,
    ) {
        $message = sprintf('Webhook delivery to "%s" failed with HTTP %d.', $endpointId, $statusCode);
        if ($detail !== '') {
            $message .= ' '.$detail;
        }

        parent::__construct($message, $statusCode, $previous);
    }

    public function isRetryable(): bool
    {
        $code = $this->getCode();

        return $code === 0 || $code === 429 || $code >= 500;
    }
}
