import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';
const API_KEY = __ENV.API_KEY || 'change-me-in-real-deployments';

export const options = {
  scenarios: {
    events_load: {
      executor: 'constant-vus',
      vus: 10,
      duration: '30s',
      exec: 'postEvent',
    },
    unauthorized: {
      executor: 'constant-vus',
      vus: 1,
      duration: '30s',
      exec: 'missingApiKey',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{expected_response:true}': ['p(95)<1000'],
    checks: ['rate>0.99'],
  },
};

export function postEvent() {
  const res = http.post(
    `${BASE_URL}/events`,
    JSON.stringify({
      event: 'OrderPlaced',
      payload: { order_id: `k6-${__VU}-${__ITER}` },
    }),
    {
      headers: {
        'Content-Type': 'application/json',
        'X-Api-Key': API_KEY,
      },
      tags: { name: 'events_accepted' },
    },
  );

  check(res, {
    'accepted is 202': (r) => r.status === 202,
  });

  sleep(0.2);
}

export function missingApiKey() {
  const res = http.post(
    `${BASE_URL}/events`,
    JSON.stringify({
      event: 'OrderPlaced',
      payload: { order_id: 'unauthorized' },
    }),
    {
      headers: { 'Content-Type': 'application/json' },
      tags: { name: 'events_unauthorized' },
    },
  );

  check(res, {
    'missing key is 401': (r) => r.status === 401,
  });

  sleep(0.5);
}
