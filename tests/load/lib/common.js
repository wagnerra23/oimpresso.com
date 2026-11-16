// Base comum dos testes de carga k6 (tests/load/*.js).
// Runbook: memory/requisitos/Infra/RUNBOOK-load-test.md
//
// Trava de segurança: os testes só rodam contra staging. Hosts de produção
// (Hostinger) são recusados antes de qualquer requisição — carga em produção é
// DoS auto-infligido. Não existe flag para desligar esta trava.
import http from 'k6/http';
import { check, fail } from 'k6';

const HOSTS_PROIBIDOS = [
  'oimpresso.com',
  'www.oimpresso.com',
  'uniafibro.com.br',
  'www.uniafibro.com.br',
];

export function baseUrl() {
  const url = (__ENV.BASE_URL || 'https://staging.oimpresso.com').replace(/\/+$/, '');
  const host = url.replace(/^https?:\/\//, '').split('/')[0].split(':')[0].toLowerCase();
  if (HOSTS_PROIBIDOS.includes(host)) {
    fail(`BASE_URL aponta para produção (${host}). Load test só roda contra staging.`);
  }
  return url;
}

// Variável obrigatória: falha cedo com mensagem clara em vez de medir 401/422.
export function envObrigatoria(nome) {
  const v = __ENV[nome];
  if (!v) fail(`Variável ${nome} não definida. Veja o RUNBOOK-load-test.md.`);
  return v;
}

// Laravel: o cookie XSRF-TOKEN vem URL-encoded; o header X-XSRF-TOKEN espera o valor decodificado.
export function xsrfHeader(url) {
  const jar = http.cookieJar();
  const c = jar.cookiesForURL(url)['XSRF-TOKEN'];
  return c && c.length ? { 'X-XSRF-TOKEN': decodeURIComponent(c[0]) } : {};
}

// Login web do UltimatePOS (campo `username`). Usa o cookie jar do VU.
export function loginWeb(base, username, password) {
  http.get(`${base}/login`, { tags: { name: 'GET /login' } });
  const res = http.post(
    `${base}/login`,
    { username, password },
    { headers: xsrfHeader(base), redirects: 0, tags: { name: 'POST /login' } },
  );
  // Sucesso = 302 para fora do /login. 302 de volta ao /login = credencial recusada.
  const ok = check(res, {
    'login 302': (r) => r.status === 302,
    'login não volta ao /login': (r) => !String(r.headers.Location || '').endsWith('/login'),
  });
  return ok;
}

// Limiares comuns: falha de rede/5xx separada de 4xx esperado (throttle, PIN errado).
export function limiares(p95ms, extra = {}) {
  return {
    http_req_duration: [`p(95)<${p95ms}`],
    http_req_failed: ['rate<0.01'],
    checks: ['rate>0.99'],
    ...extra,
  };
}

// Fatia de carga: CARGA=calibrar reduz tudo a ~10% para a primeira passada.
export function escala(n) {
  return (__ENV.CARGA || 'cheia') === 'calibrar' ? Math.max(1, Math.ceil(n / 10)) : n;
}

export const resumo5xx = (r) => r.status < 500;
