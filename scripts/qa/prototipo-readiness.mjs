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
import { join, relative } from 'node:path';
import { ucsDeclaredInCasos } from '../lib/uc-regex.mjs';
import { pageNamespacePath, raizesDePages } from './page-path.mjs';

const ROOT = process.cwd();
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

/**
 * Conta UCs declarados (heading `## UC-`) num casos.md. DELEGA pra fonte única
 * `scripts/lib/uc-regex.mjs` — não reimplementar aqui.
 *
 * POR QUE DELEGA (2026-07-27): o `/^UC-/i` que estava aqui era PERMISSIVO demais —
 * aceitava qualquer heading começando em `UC-` (inclusive `## UC-` solto), que a lib
 * rejeita. MEDIDO no corpus (44 .casos.md): delta **0** — batia por acaso, não por
 * contrato. A direção do erro era sobre-contar; migrar preserva o número de hoje e
 * mata a chance de drift. Mesma varredura que achou o drift INVERSO (sub-contar) em
 * `screen-coverage-map.mjs::ucsFromCasos`, que perdia UC de sufixo-letra.
 */
export function contaUCs(casosText) {
  return ucsDeclaredInCasos(casosText).length;
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
  return pageNamespacePath(relTsx).replace(/\.tsx$/, '')
    .replace(/[\\/]/g, '-').toLowerCase();
}

export function coleta(root = ROOT) {
  // Desde o PR #5686 as Pages vivem em DUAS classes de raiz: núcleo e módulo nWidart.
  // Varrer só `resources/js/Pages` fazia Superadmin/Officeimpresso desaparecerem justamente
  // da fila que deveria dizer o que aplicar. A fonte única das raízes é `page-path.mjs`.
  const charters = raizesDePages(root).flatMap((pagesRoot) => walk(pagesRoot, (f) => f.endsWith('.charter.md')));
  const scorecardDir = join(root, 'memory', 'governance', 'scorecards', 'screens');
  const scorecards = existsSync(scorecardDir)
    ? new Set(readdirSync(scorecardDir).filter((f) => f.endsWith('.yaml') || f.endsWith('.yml')).map((f) => f.replace(/\.(yaml|yml)$/, '')))
    : new Set();

  const out = [];
  for (const abs of charters) {
    const relCharter = relative(root, abs).replace(/\\/g, '/');
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
    const moduleMatch = relTsx.match(/^Modules\/([^/]+)\//);
    out.push({
      // `tela` é o namespace Inertia, independente da raiz física. `arquivo` e `modulo`
      // preservam a informação necessária para aplicar a mudança no dono correto.
      tela: pageNamespacePath(relTsx).replace(/\.tsx$/, ''),
      arquivo: relTsx,
      modulo: moduleMatch ? moduleMatch[1] : 'core',
      status,
      prototipo: val,
      falta: status === '1-ciclo'
        ? [!temTsx && 'tsx', !temCasosComUC && 'casos.md-com-UC', !temScorecard && 'scorecard'].filter(Boolean)
        : [],
    });
  }
  return out.sort((a, b) => (a.status === b.status
    ? a.modulo.localeCompare(b.modulo) || a.tela.localeCompare(b.tela)
    : a.status === 'pronta' ? -1 : 1));
}

function main() {
  const flags = new Set(process.argv.slice(2));
  const telas = coleta();
  const prontas = telas.filter((t) => t.status === 'pronta');
  const ciclo = telas.filter((t) => t.status === '1-ciclo');

  console.log('\n  PRONTIDÃO DE APLICAÇÃO DO PROTÓTIPO — derivado da máquina (blindagem, não score visual)\n');
  console.log(`  ✅ PRONTAS pra aplicar HOJE (trio + casos+UC + scorecard trava o comportamento): ${prontas.length}`);
  for (const t of prontas) console.log(`       [${t.modulo}] ${t.tela}`);
  console.log(`\n  🟡 PRECISAM DE 1 CICLO de blindagem antes (o metabolismo MV faz): ${ciclo.length}`);
  for (const t of ciclo) console.log(`       [${t.modulo}] ${t.tela.padEnd(40)} falta: ${t.falta.join(', ')}`);
  console.log(`\n  Total de telas com protótipo real: ${telas.length}\n`);

  if (flags.has('--json')) {
    writeFileSync(OUT, JSON.stringify({ prontas: prontas.map((t) => t.tela), ciclo, total: telas.length }, null, 2) + '\n');
    console.log('  ✓ memory/governance/prototipo-readiness.json\n');
  }
}

if (process.argv[1] && process.argv[1].endsWith('prototipo-readiness.mjs')) main();
