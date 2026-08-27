<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\CircuitBreaker\CircuitBreakerInterface;
use App\Exception\WebhookDeliveryException;
use App\Message\DeliverWebhook;
use App\RateLimit\ConcurrencyLimiterInterface;
use App\Webhook\WebhookEndpointRegistry;
use App\Webhook\WebhookHttpClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeliverWebhookHandler
{
    public function __construct(
        private readonly WebhookEndpointRegistry $registry,
        private readonly CircuitBreakerInterface $circuitBreaker,
        private readonly ConcurrencyLimiterInterface $concurrencyLimiter,
        private readonly WebhookHttpClient $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeliverWebhook $message): void
    {
        $endpoint = $this->registry->get($message->endpointId);

        $this->circuitBreaker->assertAvailable($endpoint->id);

        try {
            $this->concurrencyLimiter->run($endpoint->id, function () use ($endpoint, $message): void {
                $this->httpClient->deliver($endpoint, $message->eventType, $message->payload);
            });
            $this->circuitBreaker->recordSuccess($endpoint->id);
            $this->logger->info('Webhook delivered.', [
                'endpoint_id' => $endpoint->id,
                'event' => $message->eventType,
            ]);
        } catch (WebhookDeliveryException $exception) {
            if (!$exception->isRetryable()) {
                $this->logger->warning('Dropping non-retryable webhook delivery.', [
                    'endpoint_id' => $endpoint->id,
                    'event' => $message->eventType,
                    'error' => $exception->getMessage(),
                ]);

                return;
            }

            $this->circuitBreaker->recordFailure($endpoint->id);

            throw $exception;
        }
    }
}
