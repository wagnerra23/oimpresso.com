#!/usr/bin/env node
// ds-mirror-drift.mjs — SENTINELA de drift git ↔ espelho vivo (P3).
//
// Fecha o loop de sync do DS (proposta 2026-07-08-profissionalizar-ds-sync-git-espelho; ADR de transição a ser numerada por [W]):
// git = SSOT; o projeto claude.ai/design é ESPELHO. Este guard ALERTA quando os dois separam.
//
// ── Por que um SNAPSHOT commitado, e não o espelho vivo? ──
// O CI do GitHub Actions NÃO tem login claude.ai → NÃO pode chamar `DesignSync get_file`.
// Então a checagem no CI compara o git (_generated-*.css) contra um SNAPSHOT do espelho
// versionado em prototipo-ui/design-system/colors_and_type.css. Esse snapshot é
// "o último estado conhecido do espelho", refrescado pelo runbook design-sync-push.md (passo 5).
// O diff contra o espelho VIVO (via DesignSync) roda local/cron — este script aceita qualquer
// arquivo via --snapshot, então serve pros dois usos (CI com snapshot; local com get_file salvo).
//
// Efeito de governança: se um PR muda um token no git e NÃO refresca o snapshot (= esqueceu de
// re-espelhar), qualquer divergência é acusada. Advisory primeiro; required só
// depois de estável (política ADR 0314: required = só Tier-0).
//
// Uso:
//   node scripts/governance/ds-mirror-drift.mjs [--enforce]
//        [--snapshot <css>] [--tokens <dir>]
//   default snapshot = prototipo-ui/design-system/colors_and_type.css
//   default tokens   = resources/css/tokens
//
// Saída: relatório + exit code.
//   advisory (default): sempre exit 0; emite ::warning:: no CI se drift > 0.
//   --enforce:          exit 1 = drift REAL (totalDiverge > 0). exit 2 = NÃO CONSEGUIU
//                       MEDIR (snapshot ausente · tokens/ ausente · motor de diff falhou).
//                       Não-medição nunca sai com o código de drift — é o pré-requisito nomeado
//                       na lápide §5 2026-08-14. O workflow e o medidor de mordidas passaram
//                       a usar --enforce após a ratificação da ADR 0410 em 2026-09-21.
// Não existe baseline: drift conhecido continua sendo drift até o espelho voltar a zero.

import { existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join, resolve } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '..', '..');
const argv = process.argv.slice(2);
const flag = (name) => argv.includes(name);
const opt = (name, def) => { const i = argv.indexOf(name); return i >= 0 && argv[i + 1] ? argv[i + 1] : def; };

const SNAPSHOT = resolve(REPO, opt('--snapshot', 'prototipo-ui/design-system/colors_and_type.css'));
const TOKENS = resolve(REPO, opt('--tokens', 'resources/css/tokens'));
const DIFF_ENGINE = resolve(REPO, 'scripts/design-sync/ds-token-diff.mjs');
const ENFORCE = flag('--enforce');
const isCI = !!process.env.GITHUB_ACTIONS;
const warn = (msg) => console.log(isCI ? `::warning title=ds-mirror-drift::${msg}` : `⚠️  ${msg}`);

if (flag('--update-baseline') || argv.includes('--baseline')) {
  console.error('✗ baseline removido: este sentinela exige drift zero; sincronize o espelho em vez de aceitar divergência.');
  process.exit(2);
}

// snapshot ausente = não dá pra checar. No CI advisory isso é warning (não vermelho); no enforce,
// exit 2 (não-medi ≠ drift — lápide §5 2026-08-14).
if (!existsSync(SNAPSHOT)) {
  const msg = `snapshot do espelho não encontrado: ${SNAPSHOT}. Refresque via runbook design-sync-push.md (passo 5).`;
  if (ENFORCE) { console.error(`✗ NÃO MEDIDO — ${msg}`); process.exit(2); }
  warn(msg); process.exit(0);
}

// tokens/ ausente = o LADO GIT não foi medido. Sem esta checagem o motor devolve diverge=0
// (todo token do snapshot vira designOnly) e o enforce sairia VERDE FALSO — medido 2026-09-01
// (`ds-token-diff.mjs <snap> <dir-inexistente> --json` → totalDiverge 0, rc=0).
if (!existsSync(TOKENS)) {
  const msg = `dir de tokens não encontrado: ${TOKENS}. Nada foi medido do lado git.`;
  if (ENFORCE) { console.error(`✗ NÃO MEDIDO — ${msg}`); process.exit(2); }
  warn(msg); process.exit(0);
}

// Reusa o motor ds-token-diff.mjs (não duplica o parser). --json → { totalDiverge, report }.
let diff;
try {
  const raw = execFileSync('node', [DIFF_ENGINE, SNAPSHOT, TOKENS, '--json'], { encoding: 'utf8' });
  diff = JSON.parse(raw);
} catch (e) {
  console.error(`✗ NÃO MEDIDO — falha ao rodar ds-token-diff.mjs: ${e.message}`);
  process.exit(ENFORCE ? 2 : 0);
}

const perScope = Object.fromEntries(Object.entries(diff.report).map(([s, r]) => [s, r.diverge.length]));

console.log(`\n═══ ds-mirror-drift (git ↔ espelho) ═══`);
console.log(`snapshot: ${SNAPSHOT.replace(REPO + '/', '')}`);
console.log(`drift atual: ${diff.totalDiverge}  ·  exigido: 0`);
for (const [s, n] of Object.entries(perScope)) {
  const mark = n > 0 ? '✗' : '·';
  console.log(`  ${mark} ${s.padEnd(14)} ${n}`);
}

if (diff.totalDiverge > 0) {
  const msg = `drift detectado: ${diff.totalDiverge} divergência(s). Um token do git mudou sem re-espelhar (design-sync-push.md) — ou o espelho driftou. Rode o push e refresque o snapshot.`;
  if (ENFORCE) { console.error(`\n✗ ${msg}`); process.exit(1); }
  warn(msg);
  console.log(`(advisory — não bloqueia. Promover a --enforce só depois de estável · ADR 0314.)`);
  process.exit(0);
}

console.log(`\n✓ drift zero.`);
process.exit(0);
