<?php

declare(strict_types=1);

$storage = getenv('RECEIVER_STORAGE') ?: '/tmp/webhook-received.json';

/**
 * @return list<array<string, mixed>>
 */
function loadInbox(string $storage): array
{
    if (!is_file($storage)) {
        return [];
    }

    $raw = file_get_contents($storage);
    if ($raw === false || $raw === '') {
        return [];
    }

    try {
        /** @var mixed $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }

    return \is_array($decoded) ? array_values($decoded) : [];
}

/**
 * @param list<array<string, mixed>> $items
 */
function saveInbox(string $storage, array $items): void
{
    $dir = \dirname($storage);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents(
        $storage,
        json_encode($items, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
        LOCK_EX,
    );
}

function jsonResponse(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_THROW_ON_ERROR);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($method === 'GET' && $path === '/health') {
    jsonResponse(['status' => 'ok']);
    exit;
}

if ($method === 'GET' && $path === '/received') {
    jsonResponse(['count' => \count(loadInbox($storage)), 'items' => loadInbox($storage)]);
    exit;
}

if ($method === 'DELETE' && $path === '/received') {
    saveInbox($storage, []);
    jsonResponse(['status' => 'cleared']);
    exit;
}

if ($method === 'POST' && preg_match('#^/hook/([a-zA-Z0-9_-]+)$#', $path, $matches) === 1) {
    $body = file_get_contents('php://input') ?: '';
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_') && \is_string($value)) {
            $headers[$key] = $value;
        }
    }

    $items = loadInbox($storage);
    $items[] = [
        'endpoint_id' => $matches[1],
        'received_at' => gmdate('c'),
        'headers' => $headers,
        'body' => $body,
    ];
    saveInbox($storage, $items);

    jsonResponse(['status' => 'accepted', 'endpoint_id' => $matches[1]], 200);
    exit;
}

jsonResponse(['error' => 'Not found.'], 404);
