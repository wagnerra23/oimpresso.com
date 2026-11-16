// Cenário 🔴 Site público da Uniafibro.
//
// Hoje o site é UMA página (UniafibroSiteController@home, sem banco); qualquer outro
// caminho redireciona para a home. `/direitos`, as páginas internas e o PDF chegam
// com os PRs #10908+. Até lá rode só com a home (padrão). Depois, sem mudar o script:
//   PAGINAS="/direitos,/direitos/a,/direitos/b,/direitos/c"  PDF="/direitos/cartilha.pdf"
//
// Jornada: home → páginas → 1 PDF.
// Fases:  base — 50 visitantes/min por 5 min · pico — sobe a 300/min, 5 min, desce
// Metas:  P95 < 1,2 s · zero 5xx
//
// O staging responde pelo host do site via header Host (Route::domain casa pelo Host).
// ⚠️ throttle:60,1 por IP: de uma máquina só, acima de 60 req/min chegam 429. Contados
//   à parte em `throttle_429`; NÃO são capacidade. Ver RUNBOOK §Throttle.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { baseUrl, escala } from './lib/common.js';

const throttle429 = new Counter('throttle_429');
const erros5xx = new Counter('erros_5xx');
http.setResponseCallback(http.expectedStatuses({ min: 200, max: 399 }, 429));

export const options = {
  scenarios: {
    base: {
      executor: 'constant-arrival-rate', rate: escala(50), timeUnit: '1m',
      duration: '5m', preAllocatedVUs: escala(20), maxVUs: escala(100),
    },
    pico: {
      executor: 'ramping-arrival-rate', startTime: '5m30s', startRate: escala(50), timeUnit: '1m',
      preAllocatedVUs: escala(50), maxVUs: escala(400),
      stages: [
        { target: escala(300), duration: '2m' },
        { target: escala(300), duration: '5m' },
        { target: escala(50), duration: '1m' },
      ],
    },
  },
  thresholds: {
    'http_req_duration{expected_response:true}': ['p(95)<1200'],
    erros_5xx: ['count==0'],
    checks: ['rate>0.99'],
  },
};

const BASE = baseUrl();
const HOST = __ENV.SITE_HOST || 'uniafibro.com.br';
const PAGINAS = (__ENV.PAGINAS || '').split(',').map((p) => p.trim()).filter(Boolean);
const PDF = __ENV.PDF || '';
const params = (name) => ({ headers: { Host: HOST }, redirects: 0, tags: { name } });

function abrir(caminho, nome) {
  const r = http.get(`${BASE}${caminho}`, params(nome));
  if (r.status === 429) { throttle429.add(1); return null; }
  if (r.status >= 500) erros5xx.add(1);
  check(r, { [`${nome} 2xx`]: (x) => x.status >= 200 && x.status < 300 });
  return r;
}

export default function () {
  if (!abrir('/', 'home')) return;
  sleep(Math.random() * 3 + 2);
  for (const p of PAGINAS) {
    if (!abrir(p, 'pagina')) return;
    sleep(Math.random() * 4 + 3);
  }
  if (PDF) abrir(PDF, 'pdf');
}
