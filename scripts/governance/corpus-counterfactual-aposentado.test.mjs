#!/usr/bin/env node
// @covers-us US-GOV-056
// Guarda da decisão [W] 2026-09-28 (US-GOV-056, opção b1): o contrafactual de corpus foi
// APOSENTADO. Este teste impede que ele volte sem uma decisão nova, que é o que a lápide
// §5 2026-09-28 em memory/licoes-rejeitadas.md proíbe.
//
// Verifica duas coisas no repo real:
//   1. os arquivos removidos não existem;
//   2. nenhum código ou config versionado (fora de memory/, que guarda o registro histórico)
//      volta a importar ou invocar o script.
// Tem controle positivo: o casador precisa achar o padrão num texto que sabidamente o contém,
// senão a varredura do item 2 pode estar cega e o verde não vale nada.
//
// Rodar: node scripts/governance/corpus-counterfactual-aposentado.test.mjs

import { existsSync, readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, resolve, relative } from 'node:path';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const SELF = relative(ROOT, fileURLToPath(import.meta.url)).replace(/\\/g, '/');

let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };

const REMOVIDOS = [
  'scripts/governance/agent-corpus-counterfactual.mjs',
  'scripts/governance/agent-corpus-counterfactual.test.mjs',
  '.claude/governance-eval/corpus-counterfactual/harness.mjs',
  '.claude/governance-eval/corpus-counterfactual/grader-multitenant.mjs',
  '.claude/governance-eval/corpus-counterfactual/README.md',
];

// O que conta como "voltou a usar": o nome do arquivo do script ou o caminho do scaffold.
// A prosa sem extensão (ex.: "agent-corpus-counterfactual saiu desta lista") não conta.
const USO_RE = /agent-corpus-counterfactual\.(?:test\.)?mjs|governance-eval\/corpus-counterfactual\//;

// ── controle positivo: o casador enxerga o que deve enxergar ─────────────────
check('controle: casa um import do script',
  USO_RE.test("import { wilsonCI } from '../scripts/governance/agent-corpus-counterfactual.mjs';"));
check('controle: casa o caminho do scaffold',
  USO_RE.test('node .claude/governance-eval/corpus-counterfactual/harness.mjs runs.json'));
check('controle negativo: não casa a prosa sem extensão',
  !USO_RE.test('agent-corpus-counterfactual saiu desta lista em 2026-09-28'));

// ── 1. os arquivos removidos seguem removidos ───────────────────────────────
for (const p of REMOVIDOS) check(`removido: ${p}`, !existsSync(resolve(ROOT, p)));

// ── 2. nenhum código/config versionado voltou a usá-lo ──────────────────────
let arquivos;
try {
  arquivos = execFileSync('git', ['ls-files', '-z'], { cwd: ROOT, encoding: 'utf8', maxBuffer: 64 << 20 })
    .split('\0').filter(Boolean);
} catch (e) {
  // Sem git não dá pra medir: falha visível, nunca verde mudo.
  console.log('[FAIL] git ls-files falhou — não foi possível varrer o repo: ' + e.message);
  process.exit(1);
}
check('controle: git ls-files devolveu o próprio teste (a varredura enxerga o repo)',
  arquivos.includes(SELF));

const ALVO_RE = /\.(?:mjs|js|cjs|ts|json|ya?ml|sh|php)$/;
const usos = arquivos
  .filter((f) => ALVO_RE.test(f) && !f.startsWith('memory/') && f !== SELF)
  .filter((f) => {
    try { return USO_RE.test(readFileSync(resolve(ROOT, f), 'utf8')); } catch { return false; }
  });
check(`nenhum código/config usa o script aposentado${usos.length ? ' — achado em: ' + usos.join(', ') : ''}`,
  usos.length === 0);

// ── 3. a lápide que justifica a remoção existe ──────────────────────────────
const lapides = readFileSync(resolve(ROOT, 'memory/licoes-rejeitadas.md'), 'utf8');
check('lápide §5 2026-09-28 presente em memory/licoes-rejeitadas.md',
  lapides.includes('### 2026-09-28 — Ressuscitar o contrafactual de corpus'));

console.log(fails ? `\n${fails} falha(s).` : '\nOK — contrafactual de corpus segue aposentado.');
process.exit(fails ? 1 : 0);
