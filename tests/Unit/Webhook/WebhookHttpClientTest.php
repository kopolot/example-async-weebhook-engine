<?php

declare(strict_types=1);

namespace App\Tests\Unit\Webhook;

use App\Exception\WebhookDeliveryException;
use App\Webhook\WebhookEndpoint;
use App\Webhook\WebhookHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WebhookHttpClientTest extends TestCase
{
    public function testSuccessfulDeliverySendsSignedPayload(): void
    {
        $signature = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$signature): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://example.test/hooks/acme', $url);

            foreach ($options['headers'] as $header) {
                if (str_starts_with(strtolower((string) $header), 'x-webhook-signature:')) {
                    $signature = $header;
                }
            }

            return new MockResponse('ok', ['http_code' => 200]);
        });

        $http = new WebhookHttpClient($client);
        $http->deliver(
            new WebhookEndpoint('acme', 'https://example.test/hooks/acme', 'test-secret'),
            'OrderPlaced',
            ['order_id' => '42'],
        );

        self::assertIsString($signature);
        self::assertStringContainsString('sha256=', $signature);
    }

    public function testServerErrorIsRetryable(): void
    {
        $client = new MockHttpClient([
            new MockResponse('fail', ['http_code' => 500]),
        ]);

        $http = new WebhookHttpClient($client);

        try {
            $http->deliver(
                new WebhookEndpoint('acme', 'https://example.test/hooks/acme'),
                'OrderPlaced',
                [],
            );
            self::fail('Expected WebhookDeliveryException');
        } catch (WebhookDeliveryException $exception) {
            self::assertTrue($exception->isRetryable());
            self::assertSame(500, $exception->getCode());
        }
    }
}
