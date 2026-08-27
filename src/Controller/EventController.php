<?php

declare(strict_types=1);

namespace App\Controller;

use App\Message\DispatchWebhooks;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class EventController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('/events', name: 'events_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            /** @var mixed $data */
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        if (!\is_array($data)) {
            return new JsonResponse(['error' => 'JSON body must be an object.'], Response::HTTP_BAD_REQUEST);
        }

        $eventType = $data['event'] ?? null;
        $payload = $data['payload'] ?? null;

        if (!\is_string($eventType) || $eventType === '') {
            return new JsonResponse(['error' => 'Field "event" must be a non-empty string.'], Response::HTTP_BAD_REQUEST);
        }

        if (!\is_array($payload)) {
            return new JsonResponse(['error' => 'Field "payload" must be an object.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var array<string, mixed> $payload */
        $this->bus->dispatch(new DispatchWebhooks($eventType, $payload));

        return new JsonResponse([
            'status' => 'accepted',
            'event' => $eventType,
        ], Response::HTTP_ACCEPTED);
    }
}
