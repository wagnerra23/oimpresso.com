// Cenário 🟡 oimpresso.com — login + Financeiro (Visão Unificada).
//
// 20 usuários simultâneos entram e abrem a Visão Unificada do Financeiro
// (`/financeiro` redireciona 301 para `/financeiro/unificado`).
// Metas: P95 < 600 ms · zero 500.
//
// Entrada (env): LOGIN_USUARIOS="user1:senha1,user2:senha2,..."
//   Usuários de TESTE do seed (tenant 98 ou biz=1). NUNCA usuário do biz=4 (ROTA LIVRE).
//   Um usuário por VU evita a sessão de um derrubar a do outro.
import http from 'k6/http';
import { check, sleep, fail } from 'k6';
import { Counter } from 'k6/metrics';
import { baseUrl, envObrigatoria, loginWeb, limiares, escala } from './lib/common.js';

const erros5xx = new Counter('erros_5xx');

export const options = {
  scenarios: {
    vinte: {
      executor: 'ramping-vus', startVUs: 0,
      stages: [
        { target: escala(20), duration: '1m' },
        { target: escala(20), duration: '5m' },
        { target: 0, duration: '30s' },
      ],
    },
  },
  thresholds: limiares(600, { erros_5xx: ['count==0'] }),
};

const BASE = baseUrl();
const USUARIOS = envObrigatoria('LOGIN_USUARIOS').split(',').map((p) => p.trim().split(':'));

export default function () {
  // Login uma vez por VU; as iterações seguintes reaproveitam a sessão.
  if (__ITER === 0) {
    const [u, s] = USUARIOS[(__VU - 1) % USUARIOS.length];
    if (!loginWeb(BASE, u, s)) fail(`login recusado para o VU ${__VU}`);
  }
  const r = http.get(`${BASE}/financeiro/unificado`, {
    tags: { name: 'GET /financeiro/unificado' },
  });
  if (r.status >= 500) erros5xx.add(1);
  check(r, { 'financeiro 200': (x) => x.status === 200 });
  sleep(Math.random() * 10 + 10); // usuário lendo a tela
}
