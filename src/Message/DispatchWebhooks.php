<?php

declare(strict_types=1);

namespace App\Message;

final readonly class DispatchWebhooks
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $eventType,
        public array $payload,
    ) {
        if ($this->eventType === '') {
            throw new \InvalidArgumentException('eventType must not be empty.');
        }
    }
}
