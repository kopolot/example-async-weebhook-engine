#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-http://localhost:8080}"
API_KEY="${API_KEY:-change-me-in-real-deployments}"

if ! command -v k6 >/dev/null 2>&1; then
  echo "k6 is required (https://k6.io/docs/get-started/installation/)" >&2
  exit 1
fi

echo "==> Running k6 against ${BASE_URL}"
exec k6 run \
  -e "BASE_URL=${BASE_URL}" \
  -e "API_KEY=${API_KEY}" \
  "${ROOT}/k6/events.js"
