<?php

declare(strict_types=1);

namespace App\Failed;

use App\Message\DeliverWebhook;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * Logs permanently failed webhook deliveries (DLQ / failure transport).
 */
final class FailedWebhookLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsEventListener(event: WorkerMessageFailedEvent::class)]
    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof DeliverWebhook) {
            return;
        }

        $this->logger->critical('Webhook delivery moved to DLQ after exhausting retries.', [
            'endpoint_id' => $message->endpointId,
            'event' => $message->eventType,
            'error' => $event->getThrowable()->getMessage(),
        ]);
    }
}
