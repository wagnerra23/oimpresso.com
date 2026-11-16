// Cenário 🔴 Portal da Associada — link + PIN.
//
// SUBSTITUTO: a área da associada (US-ASSOC-005, onda 6 da ADR 0451) ainda não existe.
// Mede o portal do paciente do Fisio, que usa o MESMO padrão (link assinado + PIN,
// sem login, throttle por IP). Quando a onda 6 mergear, troque PORTAL_PREFIXO e o
// campo do PIN — o resto do roteiro vale igual.
//
// Fases:  rajada  — 100 acessos em 1 minuto (link enviado em massa)
//         rampa   — 0→500 usuárias em 5 min, 5 min no pico, 1 min descendo
// Metas:  P95 < 800 ms · falha < 1% · nenhuma queda de VU
//
// Entrada (env): PORTAL_TOKENS="tok1,tok2,..."  PORTAL_PIN="1234"
//   Tokens de pacientes de TESTE do tenant 98 (nunca biz=4). Ver RUNBOOK.
//
// ⚠️ throttle:60,1 por IP: de UMA máquina, acima de 60 req/min chegam 429. O 429 NÃO
//   conta como falha (é a proteção funcionando) e é contado à parte em `throttle_429`.
//   Para medir capacidade real, rode distribuído ou com o IP do runner liberado no staging.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { baseUrl, envObrigatoria, xsrfHeader, escala } from './lib/common.js';

const throttle429 = new Counter('throttle_429');
http.setResponseCallback(http.expectedStatuses({ min: 200, max: 399 }, 429));

const PREFIXO = __ENV.PORTAL_PREFIXO || '/fisio/p';

export const options = {
  scenarios: {
    rajada: {
      executor: 'ramping-arrival-rate',
      startRate: 0, timeUnit: '1m', preAllocatedVUs: escala(50), maxVUs: escala(150),
      stages: [{ target: escala(100), duration: '1m' }],
    },
    rampa: {
      executor: 'ramping-vus', startTime: '1m30s', startVUs: 0,
      stages: [
        { target: escala(500), duration: '5m' },
        { target: escala(500), duration: '5m' },
        { target: 0, duration: '1m' },
      ],
      gracefulRampDown: '30s',
    },
  },
  thresholds: {
    'http_req_duration{expected_response:true}': ['p(95)<800'],
    http_req_failed: ['rate<0.01'],
    checks: ['rate>0.99'],
  },
};

const BASE = baseUrl();
const TOKENS = envObrigatoria('PORTAL_TOKENS').split(',').map((t) => t.trim()).filter(Boolean);
const PIN = envObrigatoria('PORTAL_PIN');

export default function () {
  const token = TOKENS[(__VU + __ITER) % TOKENS.length];
  const url = `${BASE}${PREFIXO}/${token}`;

  const abrir = http.get(url, { tags: { name: 'GET portal' } });
  if (abrir.status === 429) { throttle429.add(1); sleep(1); return; }
  check(abrir, { 'portal abre (200)': (r) => r.status === 200 });

  sleep(Math.random() * 3 + 2); // tempo de digitar o PIN

  const pin = http.post(`${url}/pin`, JSON.stringify({ pin: PIN }), {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...xsrfHeader(BASE) },
    tags: { name: 'POST portal/pin' },
  });
  if (pin.status === 429) { throttle429.add(1); sleep(1); return; }
  check(pin, { 'PIN aceito (2xx)': (r) => r.status >= 200 && r.status < 300 });

  const dentro = http.get(url, { tags: { name: 'GET portal (logada)' } });
  if (dentro.status === 429) { throttle429.add(1); return; }
  check(dentro, { 'área aberta (200)': (r) => r.status === 200 });

  sleep(Math.random() * 5 + 5); // tempo de leitura
}
