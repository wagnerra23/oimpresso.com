import { existsSync } from 'node:fs';
import { test, expect } from '@playwright/test';

// Área da associada — limite de pedidos por IP (item 19 do plano E2E).
//
// A rota pública usa `throttle:60,1`. Para visitante sem login o Laravel conta por domínio + IP,
// então o estouro daqui bloquearia por um minuto os outros testes da área que rodam do mesmo
// runner. Por isso este arquivo roda num projeto próprio (`portal-associada-limite`), que só
// começa depois de todos os outros (playwright.config.ts). Só no CI, nunca contra o site real.

const FIXTURE = 'storage/app/e2e/portal-associada.json';

test.use({ storageState: { cookies: [], origins: [] } });
test.skip(!existsSync(FIXTURE), 'aguardando PR #10880 (área da associada) — sem link de teste semeado');

// FIXME até duas coisas mudarem (medido em 2026-11-16, runs 43979249077 e 43981023244):
//  1. O e2e-gate usa CACHE_DRIVER=array. O `artisan serve` atende cada pedido num processo novo, o
//     contador do throttle zera a cada um e a rajada inteira volta 200 (70 de 70).
//  2. Trocar para cache em arquivo faz o throttle valer, mas derruba o item 3 (PDF da Uniafibro):
//     o grupo do domínio tem throttle:60,1 e a rota do PDF throttle:10,1, e para visitante os dois
//     contam na MESMA chave (domínio + IP, ThrottleRequests::resolveRequestSignature). Toda página
//     vista gasta do limite do PDF. Conserto no app, fora deste PR: dar prefixo próprio à rota do
//     PDF (`throttle:10,1,pdf`). Depois dele, trocar o cache do e2e-gate e tirar este fixme.
test.fixme('19 · rajada de pedidos do mesmo IP recebe 429', async ({ request }) => {
  const status: number[] = [];
  for (let i = 0; i < 70; i++) {
    status.push((await request.get('/associacoes/m/qualquer-coisa', { maxRedirects: 0 })).status());
  }

  // O primeiro passa (link inválido é página normal); depois do limite, só 429 até o fim.
  const primeiro429 = status.indexOf(429);
  expect(status[0], `sequência: ${status.join(',')}`).toBe(200);
  expect(primeiro429, `sequência: ${status.join(',')}`).toBeGreaterThan(0);
  expect(primeiro429).toBeLessThanOrEqual(61);
  expect(status.slice(primeiro429).every((s) => s === 429), `sequência: ${status.join(',')}`).toBe(true);
});
