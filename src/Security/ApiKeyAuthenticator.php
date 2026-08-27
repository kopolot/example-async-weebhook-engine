<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final class ApiKeyAuthenticator
{
    public function __construct(
        private readonly string $apiKey,
    ) {
        if ($this->apiKey === '') {
            throw new \InvalidArgumentException('API_KEY must not be empty.');
        }
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get('_route') !== 'events_create') {
            return;
        }

        $provided = (string) $request->headers->get('X-Api-Key', '');
        if (!hash_equals($this->apiKey, $provided)) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Unauthorized.'],
                Response::HTTP_UNAUTHORIZED,
            ));
        }
    }
}
