#!/usr/bin/env node
// @ts-check
/**
 * prototipo-readiness.mjs — "quais telas de protótipo posso aplicar SEM me preocupar?"
 *
 * Responde a pergunta do Wagner 2026-07-06 de forma DERIVADA DA MÁQUINA (determinístico,
 * zero LLM), aposentando a fila manual `prototipo-ui/TELAS_REVIEW_QUEUE.md` (parada desde
 * 2026-05-18, anterior ao trio/casos/scorecard — media score visual, não blindagem).
 *
 * "Aplicar sem se preocupar" = aplicar o visual do protótipo Cowork sem risco de quebrar
 * comportamento SILENCIOSAMENTE. A garantia mecânica disso é: a tela tem CONTRATO executável
 * (casos.md com UC) defendido por teste (casos-gate G-2) ANTES da aplicação — se o visual
 * novo quebrar um comportamento, o teste do UC quebra o CI antes do merge.
 *
 * Classifica cada tela cujo charter aponta um protótipo REAL (`related_prototype`):
 *   ✅ PRONTA      — trio completo (.tsx + charter + casos.md com ≥1 UC) + scorecard (nota).
 *                    Aplicar o visual é seguro por construção: o contrato trava o comportamento.
 *   🟡 1-CICLO     — falta casos.md (ou casos sem UC) OU scorecard. Rodar 1 ciclo screen-qa
 *                    (blindagem: casos+teste+nota) ANTES de aplicar o visual. É o trabalho do
 *                    metabolismo MV — a tela entra na fila.
 *   ⛔ SEM-ANCORA  — related_prototype é `n/a`/prosa sem fonte resolvível: não é alvo de
 *                    aplicação (nasceu no DS, não no Cowork) — informativo, não pendência.
 *
 * NÃO é gate (lei ADR 0314 — advisory; leitura). NÃO aplica nada, NÃO edita telas.
 *
 * Uso:
 *   node scripts/qa/prototipo-readiness.mjs            # relatório
 *   node scripts/qa/prototipo-readiness.mjs --json     # + memory/governance/prototipo-readiness.json
 */

import { readFileSync, readdirSync, statSync, writeFileSync, existsSync } from 'node:fs';
import { join, sep } from 'node:path';

const ROOT = process.cwd();
const PAGES_DIR = join(ROOT, 'resources', 'js', 'Pages');
const SCORECARD_DIR = join(ROOT, 'memory', 'governance', 'scorecards', 'screens');
const OUT = join(ROOT, 'memory', 'governance', 'prototipo-readiness.json');

/** Valor do campo `related_prototype:` no frontmatter do charter (1ª linha). */
export function relatedPrototype(charterText) {
  const m = charterText.match(/^related_prototype:\s*(.+)$/m);
  return m ? m[1].trim().replace(/^["']|["']$/g, '') : null;
}

/**
 * O related_prototype aponta um protótipo REAL (alvo de aplicação) ou é n/a/prosa-sem-fonte?
 * Real = contém um caminho de arquivo (.jsx/.html) OU menciona "prototipo Cowork/design-handoff"
 * com aprovação. n/a explícito = não é alvo.
 */
export function temPrototipoReal(val) {
  if (!val) return false;
  if (/^n\/a\b/i.test(val)) return false;
  if (/removido related_prototype|MIS-ANCHOR/i.test(val)) return false;
  return /\.(jsx|html)\b/i.test(val) || /prototipo\s+cowork|design-handoff|Cowork chat|\.html"/i.test(val);
}

/** Conta UCs declarados (heading `## UC-`) num casos.md. */
export function contaUCs(casosText) {
  let n = 0;
  for (const block of casosText.split(/^##\s+/m).slice(1)) if (/^UC-/i.test(block)) n++;
  return n;
}

/** Classifica prontidão a partir dos fatos booleanos (pura, testável). */
export function classifica({ prototipoReal, temTsx, temCasosComUC, temScorecard }) {
  if (!prototipoReal) return 'sem-ancora';
  if (temTsx && temCasosComUC && temScorecard) return 'pronta';
  return '1-ciclo';
}

// ── coleta ──────────────────────────────────────────────────────────────────
function walk(dir, match, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir)) {
    const full = join(dir, e);
    if (statSync(full).isDirectory()) walk(full, match, acc);
    else if (match(full)) acc.push(full);
  }
  return acc;
}

/** slug de scorecard a partir do path relativo do .tsx (mesma convenção do vital-signs/seed). */
function scorecardSlug(relTsx) {
  return relTsx.replace(/^resources[\\/]js[\\/]Pages[\\/]/, '').replace(/\.tsx$/, '')
    .replace(/[\\/]/g, '-').toLowerCase();
}

function coleta() {
  const charters = walk(PAGES_DIR, (f) => f.endsWith('.charter.md'));
  const scorecards = existsSync(SCORECARD_DIR)
    ? new Set(readdirSync(SCORECARD_DIR).filter((f) => f.endsWith('.yaml') || f.endsWith('.yml')).map((f) => f.replace(/\.(yaml|yml)$/, '')))
    : new Set();

  const out = [];
  for (const abs of charters) {
    const relCharter = abs.slice(ROOT.length + 1);
    const relTsx = relCharter.replace(/\.charter\.md$/, '.tsx');
    const absTsx = abs.replace(/\.charter\.md$/, '.tsx');
    const absCasos = abs.replace(/\.charter\.md$/, '.casos.md');
    const val = relatedPrototype(readFileSync(abs, 'utf8'));
    const prototipoReal = temPrototipoReal(val);
    const temTsx = existsSync(absTsx);
    const temCasosComUC = existsSync(absCasos) && contaUCs(readFileSync(absCasos, 'utf8')) > 0;
    const temScorecard = scorecards.has(scorecardSlug(relTsx));
    const status = classifica({ prototipoReal, temTsx, temCasosComUC, temScorecard });
    if (status === 'sem-ancora') continue; // só lista alvos de aplicação
    out.push({
      tela: relTsx.replace(/^resources[\\/]js[\\/]Pages[\\/]/, '').replace(/\.tsx$/, '').replace(/\\/g, '/'),
      status,
      prototipo: val,
      falta: status === '1-ciclo'
        ? [!temTsx && 'tsx', !temCasosComUC && 'casos.md-com-UC', !temScorecard && 'scorecard'].filter(Boolean)
        : [],
    });
  }
  return out.sort((a, b) => (a.status === b.status ? a.tela.localeCompare(b.tela) : a.status === 'pronta' ? -1 : 1));
}

function main() {
  const flags = new Set(process.argv.slice(2));
  const telas = coleta();
  const prontas = telas.filter((t) => t.status === 'pronta');
  const ciclo = telas.filter((t) => t.status === '1-ciclo');

  console.log('\n  PRONTIDÃO DE APLICAÇÃO DO PROTÓTIPO — derivado da máquina (blindagem, não score visual)\n');
  console.log(`  ✅ PRONTAS pra aplicar HOJE (trio + casos+UC + scorecard trava o comportamento): ${prontas.length}`);
  for (const t of prontas) console.log(`       ${t.tela}`);
  console.log(`\n  🟡 PRECISAM DE 1 CICLO de blindagem antes (o metabolismo MV faz): ${ciclo.length}`);
  for (const t of ciclo) console.log(`       ${t.tela.padEnd(40)} falta: ${t.falta.join(', ')}`);
  console.log(`\n  Total de telas com protótipo real: ${telas.length}\n`);

  if (flags.has('--json')) {
    writeFileSync(OUT, JSON.stringify({ prontas: prontas.map((t) => t.tela), ciclo, total: telas.length }, null, 2) + '\n');
    console.log('  ✓ memory/governance/prototipo-readiness.json\n');
  }
}

if (process.argv[1] && process.argv[1].endsWith('prototipo-readiness.mjs')) main();
