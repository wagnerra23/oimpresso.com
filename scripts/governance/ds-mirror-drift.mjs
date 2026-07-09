#!/usr/bin/env node
// ds-mirror-drift.mjs — SENTINELA de drift git ↔ espelho vivo (P3).
//
// Fecha o loop de sync do DS (proposta 2026-07-08-profissionalizar-ds-sync-git-espelho; ADR de transição a ser numerada por [W]):
// git = SSOT; o projeto claude.ai/design é ESPELHO. Este guard ALERTA quando os dois separam.
//
// ── Por que um SNAPSHOT commitado, e não o espelho vivo? ──
// O CI do GitHub Actions NÃO tem login claude.ai → NÃO pode chamar `DesignSync get_file`.
// Então a checagem no CI compara o git (_generated-*.css) contra um SNAPSHOT do espelho
// versionado em scripts/design-sync/mirror-snapshot/colors_and_type.css. Esse snapshot é
// "o último estado conhecido do espelho", refrescado pelo runbook design-sync-push.md (passo 5).
// O diff contra o espelho VIVO (via DesignSync) roda local/cron — este script aceita qualquer
// arquivo via --snapshot, então serve pros dois usos (CI com snapshot; local com get_file salvo).
//
// Efeito de governança: se um PR muda um token no git e NÃO refresca o snapshot (= esqueceu de
// re-espelhar), o drift sobe acima do baseline e o guard acusa. Advisory primeiro; required só
// depois de estável (política ADR 0314: required = só Tier-0).
//
// Uso:
//   node scripts/governance/ds-mirror-drift.mjs [--enforce] [--update-baseline]
//        [--snapshot <css>] [--tokens <dir>] [--baseline <json>]
//   default snapshot = scripts/design-sync/mirror-snapshot/colors_and_type.css
//   default tokens   = resources/css/tokens
//   default baseline = scripts/design-sync/ds-mirror-drift-baseline.json
//
// Saída: relatório + exit code.
//   advisory (default): sempre exit 0; emite ::warning:: no CI se drift > baseline.
//   --enforce:          exit 1 se totalDiverge > baseline.totalDiverge.
//   --update-baseline:  regrava o baseline com o drift atual (uso humano, ao aceitar um novo piso).

import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join, resolve } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '..', '..');
const argv = process.argv.slice(2);
const flag = (name) => argv.includes(name);
const opt = (name, def) => { const i = argv.indexOf(name); return i >= 0 && argv[i + 1] ? argv[i + 1] : def; };

const SNAPSHOT = resolve(REPO, opt('--snapshot', 'scripts/design-sync/mirror-snapshot/colors_and_type.css'));
const TOKENS = resolve(REPO, opt('--tokens', 'resources/css/tokens'));
const BASELINE = resolve(REPO, opt('--baseline', 'scripts/design-sync/ds-mirror-drift-baseline.json'));
const DIFF_ENGINE = resolve(REPO, 'scripts/design-sync/ds-token-diff.mjs');
const ENFORCE = flag('--enforce');
const isCI = !!process.env.GITHUB_ACTIONS;
const warn = (msg) => console.log(isCI ? `::warning title=ds-mirror-drift::${msg}` : `⚠️  ${msg}`);

// snapshot ausente = não dá pra checar. No CI advisory isso é warning (não vermelho); no enforce, falha.
if (!existsSync(SNAPSHOT)) {
  const msg = `snapshot do espelho não encontrado: ${SNAPSHOT}. Refresque via runbook design-sync-push.md (passo 5).`;
  if (ENFORCE) { console.error(`✗ ${msg}`); process.exit(1); }
  warn(msg); process.exit(0);
}

// Reusa o motor ds-token-diff.mjs (não duplica o parser). --json → { totalDiverge, report }.
let diff;
try {
  const raw = execFileSync('node', [DIFF_ENGINE, SNAPSHOT, TOKENS, '--json'], { encoding: 'utf8' });
  diff = JSON.parse(raw);
} catch (e) {
  console.error(`✗ falha ao rodar ds-token-diff.mjs: ${e.message}`);
  process.exit(ENFORCE ? 1 : 0);
}

const perScope = Object.fromEntries(Object.entries(diff.report).map(([s, r]) => [s, r.diverge.length]));

if (flag('--update-baseline')) {
  const next = { totalDiverge: diff.totalDiverge, perScope, note: 'Piso de drift git↔espelho. Baixe com re-espelho (design-sync-push.md). Nunca suba sem decisão.', updatedFrom: 'ds-mirror-drift.mjs --update-baseline' };
  writeFileSync(BASELINE, JSON.stringify(next, null, 2) + '\n');
  console.log(`baseline regravado: totalDiverge=${diff.totalDiverge}`);
  process.exit(0);
}

const base = existsSync(BASELINE) ? JSON.parse(readFileSync(BASELINE, 'utf8')) : { totalDiverge: 0, perScope: {} };

console.log(`\n═══ ds-mirror-drift (git ↔ espelho) ═══`);
console.log(`snapshot: ${SNAPSHOT.replace(REPO + '/', '')}`);
console.log(`drift atual: ${diff.totalDiverge}  ·  baseline: ${base.totalDiverge}`);
for (const [s, n] of Object.entries(perScope)) {
  const b = base.perScope?.[s] ?? 0;
  const mark = n > b ? '✗' : n < b ? '↓' : '·';
  console.log(`  ${mark} ${s.padEnd(14)} ${n}  (baseline ${b})`);
}

if (diff.totalDiverge > base.totalDiverge) {
  const msg = `drift SUBIU: ${diff.totalDiverge} > baseline ${base.totalDiverge}. Um token do git mudou sem re-espelhar (design-sync-push.md) — ou o espelho driftou. Rode o push e refresque o snapshot.`;
  if (ENFORCE) { console.error(`\n✗ ${msg}`); process.exit(1); }
  warn(msg);
  console.log(`(advisory — não bloqueia. Promover a --enforce só depois de estável · ADR 0314.)`);
  process.exit(0);
}

if (diff.totalDiverge < base.totalDiverge) {
  warn(`drift CAIU (${diff.totalDiverge} < ${base.totalDiverge}) — bom! Trave o piso novo: node scripts/governance/ds-mirror-drift.mjs --update-baseline`);
}
console.log(`\n✓ dentro do baseline.`);
process.exit(0);
