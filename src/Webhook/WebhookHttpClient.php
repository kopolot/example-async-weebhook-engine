<?php

declare(strict_types=1);

namespace App\Webhook;

use App\Exception\WebhookDeliveryException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WebhookHttpClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly float $timeoutSeconds = 5.0,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function deliver(WebhookEndpoint $endpoint, string $eventType, array $payload): void
    {
        $body = [
            'event' => $eventType,
            'payload' => $payload,
            'sent_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
        ];

        $json = json_encode($body, JSON_THROW_ON_ERROR);
        $headers = [
            'Content-Type' => 'application/json',
            'X-Webhook-Event' => $eventType,
        ];

        if ($endpoint->secret !== '') {
            $headers['X-Webhook-Signature'] = 'sha256='.hash_hmac('sha256', $json, $endpoint->secret);
        }

        try {
            $response = $this->httpClient->request('POST', $endpoint->url, [
                'headers' => $headers,
                'body' => $json,
                'timeout' => $this->timeoutSeconds,
            ]);
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $exception) {
            throw new WebhookDeliveryException($endpoint->id, 0, $exception->getMessage(), previous: $exception);
        }

        if ($statusCode >= 500 || $statusCode === 429) {
            throw new WebhookDeliveryException($endpoint->id, $statusCode);
        }

        if ($statusCode >= 400) {
            // Client errors are not retried — the payload or URL is wrong.
            throw new WebhookDeliveryException($endpoint->id, $statusCode, 'Non-retryable client error.');
        }
    }
}
