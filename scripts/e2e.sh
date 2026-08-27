#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

COMPOSE=(docker compose -f docker-compose.yml -f docker-compose.e2e.yml)
BASE_URL="${BASE_URL:-http://localhost:8080}"
RECEIVER_URL="${RECEIVER_URL:-http://localhost:8090}"
API_KEY="${API_KEY:-change-me-in-real-deployments}"
TIMEOUT_SECONDS="${TIMEOUT_SECONDS:-60}"

cleanup() {
  "${COMPOSE[@]}" down --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "==> Building and starting e2e stack"
"${COMPOSE[@]}" up -d --build

echo "==> Waiting for API (${BASE_URL})"
deadline=$((SECONDS + TIMEOUT_SECONDS))
until curl -sS -o /dev/null -w '%{http_code}' -X POST "${BASE_URL}/events" \
  -H 'Content-Type: application/json' \
  -d '{}' | grep -qx '401'; do
  if (( SECONDS >= deadline )); then
    echo "API did not become ready in time" >&2
    "${COMPOSE[@]}" logs --tail=80 >&2 || true
    exit 1
  fi
  sleep 1
done

echo "==> Waiting for receiver (${RECEIVER_URL})"
deadline=$((SECONDS + TIMEOUT_SECONDS))
until curl -sS "${RECEIVER_URL}/health" | grep -q '"status":"ok"'; do
  if (( SECONDS >= deadline )); then
    echo "Receiver did not become ready in time" >&2
    "${COMPOSE[@]}" logs receiver --tail=80 >&2 || true
    exit 1
  fi
  sleep 1
done

echo "==> Clearing receiver inbox"
curl -sS -X DELETE "${RECEIVER_URL}/received" >/dev/null

echo "==> Posting OrderPlaced event"
status="$(curl -sS -o /tmp/e2e-events-response.json -w '%{http_code}' -X POST "${BASE_URL}/events" \
  -H 'Content-Type: application/json' \
  -H "X-Api-Key: ${API_KEY}" \
  -d '{"event":"OrderPlaced","payload":{"order_id":"e2e-42"}}')"

if [[ "${status}" != "202" ]]; then
  echo "Expected 202 from /events, got ${status}" >&2
  cat /tmp/e2e-events-response.json >&2 || true
  exit 1
fi

echo "==> Waiting for 2 webhook deliveries"
deadline=$((SECONDS + TIMEOUT_SECONDS))
while true; do
  payload="$(curl -sS "${RECEIVER_URL}/received")"
  count="$(php -r '$d=json_decode(stream_get_contents(STDIN), true); echo is_array($d)&&isset($d["count"])?(int)$d["count"]:0;' <<<"${payload}")"
  if [[ "${count}" -ge 2 ]]; then
    echo "${payload}" | php -r '
      $d = json_decode(stream_get_contents(STDIN), true);
      if (!is_array($d) || !isset($d["items"]) || !is_array($d["items"])) {
          fwrite(STDERR, "Invalid receiver payload\n");
          exit(1);
      }
      $ids = [];
      foreach ($d["items"] as $item) {
          if (!is_array($item)) {
              continue;
          }
          $ids[] = (string) ($item["endpoint_id"] ?? "");
          $body = json_decode((string) ($item["body"] ?? ""), true);
          if (!is_array($body) || ($body["event"] ?? null) !== "OrderPlaced") {
              fwrite(STDERR, "Missing OrderPlaced in delivery body\n");
              exit(1);
          }
      }
      sort($ids);
      if ($ids !== ["acme", "beta"]) {
          fwrite(STDERR, "Expected acme+beta deliveries, got: ".implode(",", $ids)."\n");
          exit(1);
      }
      echo "Deliveries OK: acme + beta with OrderPlaced\n";
    '
    echo "==> E2E passed"
    exit 0
  fi
  if (( SECONDS >= deadline )); then
    echo "Timed out waiting for webhook deliveries. Last payload:" >&2
    echo "${payload}" >&2
    "${COMPOSE[@]}" logs worker --tail=100 >&2 || true
    exit 1
  fi
  sleep 1
done
