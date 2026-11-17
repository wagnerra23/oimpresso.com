// Cenário 🟡 Copiloto (Jana) — 10 conversas paralelas × 5 mensagens.
//
// ⚠️ CHAMA O LLM DE VERDADE e gasta tokens (orçamento autorizado por [W] 2026-11-15).
//   Cada iteração = 1 conversa nova + 5 mensagens = 5 chamadas ao modelo.
//   Com 10 VUs e 1 iteração cada: 50 chamadas por execução. Não aumente sem combinar.
//
// Metas: P95 < 3 s por mensagem · tokens dentro do orçamento.
//   O k6 só vê latência. Tokens são gravados no servidor junto com cada resposta;
//   a conferência é por consulta no staging depois da execução (RUNBOOK §Jana).
//
// Entrada (env): LOGIN_USUARIOS="user:senha,..." com permissão `jana.chat`
//   (tenant 98 ou biz=1 — nunca biz=4). Rota: POST /ia/conversas/{id}/mensagens
//   (throttle 60/min por usuário — 5 mensagens por conversa ficam muito abaixo).
import http from 'k6/http';
import { check, sleep, fail } from 'k6';
import { Trend, Counter } from 'k6/metrics';
import { baseUrl, envObrigatoria, loginWeb, xsrfHeader, escala } from './lib/common.js';

const tempoMensagem = new Trend('jana_mensagem_ms', true);
const erros5xx = new Counter('erros_5xx');

export const options = {
  scenarios: {
    conversas: { executor: 'per-vu-iterations', vus: escala(10), iterations: 1, maxDuration: '10m' },
  },
  thresholds: {
    jana_mensagem_ms: ['p(95)<3000'],
    http_req_failed: ['rate<0.01'],
    erros_5xx: ['count==0'],
    checks: ['rate>0.99'],
  },
};

const BASE = baseUrl();
const USUARIOS = envObrigatoria('LOGIN_USUARIOS').split(',').map((p) => p.trim().split(':'));
const PERGUNTAS = [
  'Quanto vendemos este mês?',
  'Quais produtos mais venderam na última semana?',
  'Tem algum título a receber vencido?',
  'Compare as vendas deste mês com o mês passado.',
  'Resuma em três tópicos o que você me respondeu.',
];

export default function () {
  const [u, s] = USUARIOS[(__VU - 1) % USUARIOS.length];
  if (!loginWeb(BASE, u, s)) fail(`login recusado para o VU ${__VU}`);

  const nova = http.post(`${BASE}/ia/conversas`, { titulo: `load-test VU ${__VU}` }, {
    headers: xsrfHeader(BASE), redirects: 0, tags: { name: 'POST /ia/conversas' },
  });
  const m = String(nova.headers.Location || '').match(/\/ia\/conversas\/(\d+)/);
  if (!check(nova, { 'conversa criada (302 com id)': () => !!m })) return;
  const id = m[1];

  for (const pergunta of PERGUNTAS) {
    const r = http.post(`${BASE}/ia/conversas/${id}/mensagens`, { content: pergunta }, {
      headers: xsrfHeader(BASE), redirects: 0, timeout: '60s',
      tags: { name: 'POST /ia/conversas/{id}/mensagens' },
    });
    tempoMensagem.add(r.timings.duration);
    if (r.status >= 500) erros5xx.add(1);
    check(r, { 'mensagem aceita (302 back)': (x) => x.status === 302 });
    sleep(Math.random() * 4 + 4); // usuário lendo a resposta antes da próxima
  }
}
