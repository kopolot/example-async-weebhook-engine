<?php

declare(strict_types=1);

namespace App\Webhook;

final readonly class WebhookEndpoint
{
    public function __construct(
        public string $id,
        public string $url,
        public string $secret = '',
    ) {
        if ($this->id === '') {
            throw new \InvalidArgumentException('Endpoint id must not be empty.');
        }

        if ($this->url === '' || filter_var($this->url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException(sprintf('Invalid webhook URL for endpoint "%s".', $this->id));
        }
    }
}
