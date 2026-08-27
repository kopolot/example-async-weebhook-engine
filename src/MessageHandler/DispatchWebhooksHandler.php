<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DeliverWebhook;
use App\Message\DispatchWebhooks;
use App\Webhook\WebhookEndpointRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class DispatchWebhooksHandler
{
    public function __construct(
        private readonly WebhookEndpointRegistry $registry,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(DispatchWebhooks $message): void
    {
        foreach ($this->registry->all() as $endpoint) {
            $this->bus->dispatch(new DeliverWebhook(
                $endpoint->id,
                $message->eventType,
                $message->payload,
            ));
        }
    }
}
