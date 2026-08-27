<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\CircuitBreaker\InMemoryCircuitBreaker;
use App\Exception\WebhookDeliveryException;
use App\Message\DeliverWebhook;
use App\MessageHandler\DeliverWebhookHandler;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class EventToWebhookFlowTest extends WebTestCase
{
    public function testAcceptedEventIsDeliveredThroughMessenger(): void
    {
        $requests = [];
        $mockHttp = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'body' => $options['body'] ?? '',
            ];

            return new MockResponse('{"ok":true}', ['http_code' => 200]);
        });

        $client = static::createClient();
        self::getContainer()->set(HttpClientInterface::class, $mockHttp);

        $client->request(
            'POST',
            '/events',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_API_KEY' => 'test-api-key',
            ],
            content: json_encode([
                'event' => 'OrderPlaced',
                'payload' => ['order_id' => '42'],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(202);
        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('https://example.test/hooks/acme', $requests[0]['url']);
        self::assertStringContainsString('OrderPlaced', (string) $requests[0]['body']);
    }

    public function testRetryableFailuresOpenCircuit(): void
    {
        $mockHttp = new MockHttpClient([
            new MockResponse('down', ['http_code' => 503]),
            new MockResponse('down', ['http_code' => 503]),
            new MockResponse('down', ['http_code' => 503]),
        ]);

        self::bootKernel();
        self::getContainer()->set(HttpClientInterface::class, $mockHttp);

        /** @var DeliverWebhookHandler $handler */
        $handler = self::getContainer()->get(DeliverWebhookHandler::class);
        /** @var InMemoryCircuitBreaker $breaker */
        $breaker = self::getContainer()->get(InMemoryCircuitBreaker::class);

        $message = new DeliverWebhook('acme', 'OrderPlaced', ['order_id' => '99']);

        for ($i = 0; $i < 3; ++$i) {
            try {
                $handler($message);
                self::fail('Expected WebhookDeliveryException');
            } catch (WebhookDeliveryException) {
            }
        }

        self::assertTrue($breaker->isOpen('acme'));
    }

    public function testInvalidPayloadReturnsBadRequest(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/events',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_API_KEY' => 'test-api-key',
            ],
            content: '{"event":""}',
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testMissingApiKeyReturnsUnauthorized(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/events',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'event' => 'OrderPlaced',
                'payload' => ['order_id' => '42'],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }
}
