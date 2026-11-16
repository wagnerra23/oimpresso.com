// Cenário 🟢 Ponto — 50 colaboradores batendo ENTRADA às 8h ao mesmo tempo.
//
// Rota: POST /ponto/api/marcar (auth:api, Bearer Passport — o mesmo que o app usa).
// Metas: P95 < 400 ms · 100% das marcações gravadas (resposta `sucesso: true` com NSR).
//
// ⚠️ ponto_marcacoes é APPEND-ONLY por lei (Portaria 671/2021): nada do que este teste
//   gravar pode ser apagado — nem no staging. Rode SÓ com colaboradores do tenant de
//   TESTE 98, uma vez por janela combinada. Nunca biz=4.
//
// Entrada (env): PONTO_TOKENS="tok1,tok2,..." — 1 token Passport por colaborador de teste
//   (50 para o cenário cheio). Token repetido = mesma pessoa batendo duas vezes, e o
//   antifraude do MobileMarcacaoService pode recusar: aí a métrica deixa de ser carga.
import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';
import { baseUrl, envObrigatoria, escala } from './lib/common.js';

const gravadas = new Counter('marcacoes_gravadas');
const recusadas = new Counter('marcacoes_recusadas');

const TOKENS = envObrigatoria('PONTO_TOKENS').split(',').map((t) => t.trim()).filter(Boolean);
const N = Math.min(escala(50), TOKENS.length);

export const options = {
  scenarios: {
    oito_horas: { executor: 'per-vu-iterations', vus: N, iterations: 1, maxDuration: '2m' },
  },
  thresholds: {
    http_req_duration: ['p(95)<400'],
    http_req_failed: ['rate<0.01'],
    marcacoes_recusadas: ['count==0'],
  },
};

const BASE = baseUrl();

export default function () {
  const token = TOKENS[__VU - 1];
  const corpo = {
    tipo: 'ENTRADA',
    // Coordenada fixa de teste; fora do geofence vira `revisar: true`, não recusa.
    lat: Number(__ENV.PONTO_LAT || -23.55),
    lng: Number(__ENV.PONTO_LNG || -46.63),
    accuracy: 15,
    device_uuid: `load-test-${__VU}`,
    timestamp_device: new Date().toISOString(),
  };
  const r = http.post(`${BASE}/ponto/api/marcar`, JSON.stringify(corpo), {
    headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' },
    tags: { name: 'POST /ponto/api/marcar' },
  });
  let ok = false;
  try { ok = r.status === 201 && r.json('sucesso') === true && r.json('marcacao.nsr') > 0; } catch (_) { ok = false; }
  (ok ? gravadas : recusadas).add(1);
  check(r, { 'marcação gravada com NSR': () => ok });
}
