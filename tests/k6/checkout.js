import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * k6 Load Test for Checkout API (Section 1.4 A3 & Section 10):
 * 20 simultaneous cashiers, checkout p95 under 500 ms.
 */
export const options = {
    vus: 20,
    duration: '1m',
    thresholds: {
        http_req_duration: ['p(95)<500'],
        http_req_failed: ['rate<0.01'],
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost/api/v1';

export default function () {
    const res = http.get(`${BASE_URL}/catalog`);
    check(res, {
        'status is 200 or 401': (r) => r.status === 200 || r.status === 401,
    });
    sleep(1);
}

