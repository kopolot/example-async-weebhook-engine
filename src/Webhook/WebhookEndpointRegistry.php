<?php

declare(strict_types=1);

namespace App\Webhook;

/**
 * @phpstan-type EndpointConfig array{id: string, url: string, secret?: string}
 */
final class WebhookEndpointRegistry
{
    /** @var array<string, WebhookEndpoint> */
    private array $endpoints = [];

    /**
     * @param list<EndpointConfig> $configs
     */
    public function __construct(array $configs)
    {
        foreach ($configs as $config) {
            $endpoint = new WebhookEndpoint(
                $config['id'],
                $config['url'],
                $config['secret'] ?? '',
            );
            $this->endpoints[$endpoint->id] = $endpoint;
        }
    }

    /**
     * @return list<WebhookEndpoint>
     */
    public function all(): array
    {
        return array_values($this->endpoints);
    }

    public function get(string $id): WebhookEndpoint
    {
        if (!isset($this->endpoints[$id])) {
            throw new \InvalidArgumentException(sprintf('Unknown webhook endpoint "%s".', $id));
        }

        return $this->endpoints[$id];
    }
}
