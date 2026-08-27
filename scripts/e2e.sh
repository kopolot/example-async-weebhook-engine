#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

COMPOSE=(docker compose -f docker-compose.yml -f docker-compose.e2e.yml)
BASE_URL="${BASE_URL:-http://localhost:8080}"
RECEIVER_URL="${RECEIVER_URL:-http://localhost:8090}"
API_KEY="${API_KEY:-change-me-in-real-deployments}"
TIMEOUT_SECONDS="${TIMEOUT_SECONDS:-90}"
MANAGED_STACK="${MANAGED_STACK:-0}"

cleanup() {
  if [[ "${MANAGED_STACK}" != "1" ]]; then
    "${COMPOSE[@]}" down --remove-orphans >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

if [[ "${MANAGED_STACK}" != "1" ]]; then
  echo "==> Building and starting e2e stack"
  "${COMPOSE[@]}" up -d --build
fi

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

echo "==> Waiting for webhook deliveries to acme and beta"
deadline=$((SECONDS + TIMEOUT_SECONDS))
while true; do
  payload="$(curl -sS "${RECEIVER_URL}/received")"
  if echo "${payload}" | php -r '
      $d = json_decode(stream_get_contents(STDIN), true);
      if (!is_array($d) || !isset($d["items"]) || !is_array($d["items"])) {
          exit(2);
      }
      $ids = [];
      foreach ($d["items"] as $item) {
          if (!is_array($item)) {
              continue;
          }
          $id = (string) ($item["endpoint_id"] ?? "");
          if ($id === "") {
              continue;
          }
          $body = json_decode((string) ($item["body"] ?? ""), true);
          if (!is_array($body) || ($body["event"] ?? null) !== "OrderPlaced") {
              fwrite(STDERR, "Missing OrderPlaced in delivery body\n");
              exit(1);
          }
          $ids[$id] = true;
      }
      if (isset($ids["acme"], $ids["beta"])) {
          echo "Deliveries OK: acme + beta with OrderPlaced\n";
          exit(0);
      }
      exit(2);
    '; then
    echo "==> E2E passed"
    exit 0
  fi
  status=$?
  if [[ "${status}" -eq 1 ]]; then
    exit 1
  fi
  if (( SECONDS >= deadline )); then
    echo "Timed out waiting for webhook deliveries. Last payload:" >&2
    echo "${payload}" >&2
    "${COMPOSE[@]}" logs worker app receiver --tail=120 >&2 || true
    exit 1
  fi
  sleep 1
done
