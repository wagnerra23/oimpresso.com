#!/usr/bin/env node
// TESTE DE REGRESSÃO — guard de base fresca vs origin/main (incidente 2026-05-31).
//
// Pergunta que responde (Wagner: "isso nunca pode acontecer ... não pode depender de mim"):
//   "Um checkout STALE dispara o choque AUTOMÁTICO, sem depender de ninguém notar?"
//
// Prova que a decisão é MECÂNICA (função pura `buildBanner`) e que o contrato
// "nunca bloqueia / escape valve" do hook está intacto. Sem rede, sem mock de git.
//
// Rodar: node .claude/hooks/git-base-freshness-guard.test.mjs
// Exit 0 = todos passam. Exit 1 = regressão.

import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildBanner } from './git-base-freshness-guard.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const HOOK = join(__dirname, 'git-base-freshness-guard.mjs');

let fails = 0;
function check(name, cond) {
  console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}`);
  if (!cond) fails++;
}

// 1. Base FRESCA (behind 0) → SILÊNCIO (zero fricção).
check('behind=0 → silêncio', buildBanner({ behind: 0, ahead: 0, branch: 'main' }) === '');
check('behind negativo/NaN → silêncio', buildBanner({ behind: NaN, ahead: 0, branch: 'main' }) === '');

// 2. Base STALE (o caso real: feat/staging-ct100 −46) → CHOQUE alto e acionável.
const b = buildBanner({ behind: 46, ahead: 12, branch: 'feat/staging-ct100' });
check('stale → não-vazio', b.length > 0);
check('stale → grita "BASE STALE"', /BASE STALE/.test(b));
check('stale → cita o branch e o número', b.includes('feat/staging-ct100') && b.includes('46'));
check('stale → manda usar git show origin/main', /git show origin\/main:/.test(b));
check('stale → proíbe validar working tree', /N[ÃA]O valide canon contra o working tree/i.test(b));
check('stale → aponta a lei (§10.4)', /§?10\.4/.test(b));

// 3. Contrato do HOOK: escape valve + nunca bloqueia (exit 0, sem rede).
const off = spawnSync('node', [HOOK], {
  input: '{"hook_event_name":"SessionStart","source":"startup"}',
  env: { ...process.env, OIMPRESSO_BASE_GUARD_OFF: '1' },
  encoding: 'utf8',
});
check('escape valve OFF → exit 0', off.status === 0);
check('escape valve OFF → stdout vazio', (off.stdout || '') === '');

console.log('');
if (fails === 0) {
  console.log('[PASS] guard de base fresca é MECÂNICO — choque automático em stale, silêncio em fresco. (10/10)');
  process.exit(0);
} else {
  console.log(`[FAIL] ${fails} caso(s) — o guard NÃO está garantido. NÃO mergear.`);
  process.exit(1);
}
