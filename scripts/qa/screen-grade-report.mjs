#!/usr/bin/env node
// @ts-check
/**
 * screen-grade-report.mjs — a FOTO LADO A LADO: UX × comportamento por tela.
 *
 * Onda 0b do programa de ondas ([ADR 0320] — régua de correção por módulo). Fecha o buraco
 * que deixava `/perfil` "ok" no visual e o cálculo indefeso: `screen-grade` media SÓ UX; o
 * `.casos.md` media comportamento mas era ORTOGONAL e não entrava em nenhuma foto. Aqui as
 * duas leituras aparecem JUNTAS — sem FUNDIR (fundir confundiria "bonita" com "funciona"):
 *
 *   - UX          → do scorecard (memory/governance/scorecards/screens/*.yaml): `nota` + `nivel`.
 *                   Nota LLM-as-judge, cacheada + ratcheteada (SCREEN-GRADE-METODO §7).
 *   - Comportamento → DERIVADO AO VIVO do <Tela>.casos.md ao lado do .tsx (fonte da verdade,
 *                   mesmo uc-regex do casos-coverage-guard). Não pode mentir por YAML velho:
 *                   se o `cobertura_uc` gravado no scorecard divergir do vivo → marca `⚠ drift`.
 *   - D1 cálculo  → do bloco `d1_calculo` do scorecard (SCREEN-GRADE-METODO §3-bis).
 *
 * READ-ONLY — não grava nada, não é gate (não muda exit code por conteúdo; só erro de uso).
 * A fonte da verdade de cobertura continua sendo scripts/casos-coverage-guard.mjs (ADR 0264);
 * este script é a VISTA consolidada por tela, não um segundo juiz.
 *
 * Uso:
 *   node scripts/qa/screen-grade-report.mjs                 # tabela (só telas com casos.md)
 *   node scripts/qa/screen-grade-report.mjs --all           # inclui telas sem trio (comportamento "—")
 *   node scripts/qa/screen-grade-report.mjs --screen Sells/Create   # uma tela
 *   node scripts/qa/screen-grade-report.mjs --json          # saída pra máquina
 *
 * Refs: ADR 0320 (programa de ondas) · ADR 0264 (trio/casos executável) · SCREEN-GRADE-METODO.md.
 */

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { ucHeadRe } from '../lib/uc-regex.mjs';

const ROOT = process.cwd();
const SCORECARD_DIR = join(ROOT, 'memory', 'governance', 'scorecards', 'screens');
const PAGES_REL = 'resources/js/Pages';

// ---------------------------------------------------------------------------
// Leitura leve de campos do scorecard YAML (mesma heurística do screen-grades-ratchet:
// regex por linha — sem dependência de parser YAML). Só os campos que a foto usa.
// ---------------------------------------------------------------------------
// Sufixo tolerante a comentário YAML de fim de linha (`  # ...`): `"?valor"? <comment>? $`.
// Sem isso, `nivel: "🔴"   # indefeso` não casaria e a coluna vira "—" silenciosamente.
const TAIL = '"?([^"\\n#]*?)"?\\s*(?:#.*)?$';

/** @param {string} text @param {string} key — escalar top-level (âncora `^key`, sem indent). */
function yamlScalar(text, key) {
  const m = text.match(new RegExp(`^${key}\\s*:\\s*${TAIL}`, 'm'));
  return m ? m[1].trim() : null;
}
/** `cobertura_uc:` pode estar aninhado sob casos_coverage; casa em qualquer indent. */
export function storedCobertura(text) {
  const m = text.match(new RegExp(`^\\s*cobertura_uc\\s*:\\s*${TAIL}`, 'm'));
  return m && m[1] ? m[1].trim() : null;
}
/** `nivel:` do bloco d1_calculo (glyph 🔴/🟡/🟢) ou null se aplica:false / ausente. */
export function d1FromScorecard(text) {
  const m = text.match(new RegExp(`^d1_calculo\\s*:[\\s\\S]*?^\\s*nivel\\s*:\\s*${TAIL}`, 'm'));
  if (!m) return null;
  const aplica = text.match(/^d1_calculo\s*:[\s\S]*?^\s*aplica\s*:\s*(\w+)/m);
  if (aplica && aplica[1] === 'false') return 'n/a';
  return m[1] ? m[1].trim() : null;
}

// ---------------------------------------------------------------------------
// Comportamento DERIVADO AO VIVO do <Tela>.casos.md (fonte da verdade).
// Espelha casos-coverage-guard: declara UC só no heading "## UC-XX", lê o 1º glyph de Status.
// ---------------------------------------------------------------------------
const STATUS_MAP = { '✅': 'green', '❌': 'broken', '🧪': 'testing', '⬜': 'unverified' };

/** 1º glyph de status de um bloco de UC (idêntico ao declaredStatus do guard). */
export function firstStatusGlyph(block) {
  const m = block.match(/Status\s*[:：]\s*([^\n]*)/);
  if (!m) return null;
  const g = m[1].match(/[✅❌🧪⬜]/u);
  return g ? STATUS_MAP[g[0]] : 'other';
}

/**
 * Deriva a cobertura de comportamento de um texto casos.md.
 * cobertura_uc = % de UCs DECLARADOS com Status ✅ (prova verde). Contrato: SCREEN-GRADE-METODO §5/§8.
 * @param {string} casosText
 */
export function deriveBehavior(casosText) {
  const counts = { green: 0, testing: 0, unverified: 0, broken: 0, other: 0 };
  let declared = 0;
  for (const block of casosText.split(/^##\s+/m).slice(1)) {
    const head = block.match(ucHeadRe());
    if (!head) continue; // heading que não é UC (ex: "## Backlog", "## Como rodar")
    declared += 1;
    const st = firstStatusGlyph(block) || 'other';
    counts[st] = (counts[st] || 0) + 1;
  }
  // Candidatos de backlog (sem UC-id, sem teste) — visíveis, não contam como cobertura.
  const backlog = (casosText.match(/^\s*-\s*\*\*\[BACKLOG\]/gm) || []).length;
  const cobertura = declared > 0 ? `${Math.round((counts.green / declared) * 100)}%` : 'n/a';
  return { declared, ...counts, backlog, cobertura };
}

/** Resolve o caminho do casos.md de um scorecard (via `path:` ou `screen:`). */
function casosPathFor(scorecardText) {
  const path = yamlScalar(scorecardText, 'path'); // resources/js/Pages/Sells/Create.tsx
  if (path && path.endsWith('.tsx')) return path.replace(/\.tsx$/, '.casos.md');
  const screen = yamlScalar(scorecardText, 'screen'); // Sells/Create
  if (screen) return `${PAGES_REL}/${screen}.casos.md`;
  return null;
}

// ---------------------------------------------------------------------------
// Monta as linhas da foto.
// ---------------------------------------------------------------------------
function buildRows() {
  if (!existsSync(SCORECARD_DIR)) {
    console.error(`✗ ${SCORECARD_DIR} não existe — rode screen-grade-seed.mjs primeiro.`);
    process.exit(2);
  }
  const files = readdirSync(SCORECARD_DIR).filter((f) => f.endsWith('.yaml') || f.endsWith('.yml'));
  const rows = [];
  for (const f of files) {
    const text = readFileSync(join(SCORECARD_DIR, f), 'utf8');
    const screen = yamlScalar(text, 'screen') || f.replace(/\.ya?ml$/, '');
    const nota = yamlScalar(text, 'nota');
    const nivel = yamlScalar(text, 'nivel') || '';
    const d1 = d1FromScorecard(text);

    const casosRel = casosPathFor(text);
    let behavior = null;
    let drift = false;
    if (casosRel && existsSync(resolve(ROOT, casosRel))) {
      behavior = deriveBehavior(readFileSync(resolve(ROOT, casosRel), 'utf8'));
      const stored = storedCobertura(text);
      // drift = o scorecard afirma um cobertura_uc que não bate com o vivo (YAML velho mentindo).
      if (stored && stored !== behavior.cobertura) drift = true;
    }
    rows.push({ file: f, screen, nota: nota ? Number(nota) : null, nivel, d1, behavior, drift });
  }
  return rows;
}

// ---------------------------------------------------------------------------
// CLI
// ---------------------------------------------------------------------------
function main() {
  const args = process.argv.slice(2);
  const asJson = args.includes('--json');
  const showAll = args.includes('--all');
  const screenIdx = args.indexOf('--screen');
  const onlyScreen = screenIdx >= 0 ? args[screenIdx + 1] : null;

  let rows = buildRows();
  if (onlyScreen) rows = rows.filter((r) => r.screen === onlyScreen || r.file === onlyScreen);
  if (!showAll && !onlyScreen) rows = rows.filter((r) => r.behavior); // só telas com casos.md

  rows.sort((a, b) => (a.screen || '').localeCompare(b.screen || ''));

  if (asJson) {
    console.log(JSON.stringify({ total: rows.length, rows }, null, 2));
    return;
  }

  const withCasos = rows.filter((r) => r.behavior).length;
  const drifted = rows.filter((r) => r.drift).length;
  console.log(`\n=== Foto lado a lado · UX × comportamento (Onda 0b / ADR 0320) · ${rows.length} telas ===\n`);
  console.log('  ' + 'Tela'.padEnd(34) + 'UX'.padStart(4) + '  ' + 'Nível'.padEnd(12) + 'Comportamento (casos)'.padEnd(26) + 'D1');
  console.log('  ' + '─'.repeat(80));
  for (const r of rows) {
    const ux = r.nota == null ? '—' : String(r.nota);
    let comp = '—';
    if (r.behavior) {
      const b = r.behavior;
      const glyphs = [b.green && `✅${b.green}`, b.testing && `🧪${b.testing}`, b.unverified && `⬜${b.unverified}`, b.broken && `❌${b.broken}`]
        .filter(Boolean).join(' ');
      comp = `${b.cobertura} · UC ${b.declared}${glyphs ? ` (${glyphs})` : ''}${b.backlog ? ` +${b.backlog}bl` : ''}`;
    }
    const d1 = r.d1 || '—';
    console.log('  ' + String(r.screen).padEnd(34) + ux.padStart(4) + '  ' + String(r.nivel).padEnd(12) + comp.padEnd(26) + d1 + (r.drift ? '  ⚠ drift' : ''));
  }
  console.log('\n  UX = scorecard (§7 ratchet) · Comportamento = derivado ao vivo do .casos.md (fonte da verdade)');
  console.log(`  ${withCasos}/${rows.length} com casos.md · ${drifted} com ⚠ drift (cobertura_uc gravado ≠ vivo)`);
  console.log('  Leia: UX alto NÃO compra comportamento — Leader 88 pode ter 0% + D1 🔴. Plugar, não fundir.\n');
}

// Só roda o CLI quando invocado direto (permite import no selftest sem efeito colateral).
if (fileURLToPath(import.meta.url) === resolve(process.argv[1] || '')) {
  main();
}
