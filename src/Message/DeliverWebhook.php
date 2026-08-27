<?php

declare(strict_types=1);

namespace App\Message;

final readonly class DeliverWebhook
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $endpointId,
        public string $eventType,
        public array $payload,
    ) {
        if ($this->endpointId === '') {
            throw new \InvalidArgumentException('endpointId must not be empty.');
        }

        if ($this->eventType === '') {
            throw new \InvalidArgumentException('eventType must not be empty.');
        }
    }
}
