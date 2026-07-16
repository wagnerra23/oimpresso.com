#!/usr/bin/env node
// scripts/layout-primitives-guard.mjs — enforcement da ADR 0253 (primitivos de layout)
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// A ADR 0253 criou a camada `resources/js/Components/layout/` (Box/Stack/Inline/Grid/
// Container/Text) e diz textual, nas Consequências:
//   "Enforcement por lint (proibir flex solto) = ADR/PR seguinte, depois da camada +
//    piloto."
// A camada existe (#2371) e a tela-piloto existe (#2372, Pages/Financeiro/ProvaViva.tsx).
// Logo, ESTE é o "PR seguinte": o gate que impede a doença voltar — `<div className=
// "flex …">`/`grid` solto nas TELAS em vez de compor `Stack/Inline/Grid`.
//
// Segue a lei do projeto (ADR 0240): "derivado + enforcado sobrevive / escrito +
// lembrado apodrece". Por isso NÃO há lista manual — tudo deriva do código real, com
// RATCHET (catraca) por arquivo. NÃO obriga limpar o legado; só impede PIORAR.
//
// =====================================================================================
// ALVO  (telas + componentes de feature — onde layout deve ser COMPOSIÇÃO de primitivos)
// =====================================================================================
//   resources/js/Pages/**/*.tsx
//   resources/js/Components/**/*.tsx
// EXCETO:
//   resources/js/Components/layout/**  → é a CASA do flex/grid (os próprios primitivos)
//   resources/js/Components/ui/**      → primitivos shadcn/Radix (leaf, flex legítimo)
//
// =====================================================================================
// O QUE CONTA COMO "FLEX/GRID SOLTO"
// =====================================================================================
// Numa linha que tem `className`, conta os tokens de DISPLAY de layout escritos crus:
//   - `flex`  (display:flex container) — exclui `inline-flex`, `flex-1`, `flex-col`,
//             `flex-row`, `flex-wrap`, `flex-shrink/-grow` (modificadores/leaf).
//   - `grid`  (display:grid container) — exclui `grid-cols-*` e o idioma de centragem
//             `grid place-items-*` (não há primitivo de "centralizar 1 ícone").
// Cada ocorrência = 1 achado no arquivo. (Inline JÁ é flex-row; Stack JÁ é flex-col;
// Grid JÁ é grid — por isso eles vivem em Components/layout e ficam fora do alvo.)
//
// =====================================================================================
// MODOS
// =====================================================================================
//   node scripts/layout-primitives-guard.mjs                  # validate vs baseline (default)
//   node scripts/layout-primitives-guard.mjs --write-baseline # (re)grava baseline (consciente)
//   node scripts/layout-primitives-guard.mjs --json           # saída JSON pra CI
//   node scripts/layout-primitives-guard.mjs --root <dir>     # raiz de scan (default: cwd) — pro selftest
//   node scripts/layout-primitives-guard.mjs --baseline <path># baseline alternativo (default: o hardcoded)
//
// FLAGS --root/--baseline: existem SÓ pra o gate-selftest (GT-G6) rodar o gate real
// contra fixtures isoladas. SEM elas, o comportamento é IDÊNTICO ao de produção
// (ROOT = cwd, baseline = scripts/layout-primitives-baseline.json) — backward-compat.
//
// RATCHET: baseline (scripts/layout-primitives-baseline.json) fotografa a CONTAGEM
// atual POR ARQUIVO. Gate falha se algum arquivo AUMENTAR vs baseline, ou se um
// arquivo NOVO nascer com flex/grid solto (base 0). Igual/menor → passa.
//
// Refs: ADR 0253 (primitivos-layout · "Enforcement por lint = PR seguinte"),
//       ADR 0209 (baseline ratchet), ADR 0240 (derivado+enforcado sobrevive),
//       tela-piloto resources/js/Pages/Financeiro/ProvaViva.tsx.

import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { resolve, join } from 'node:path';

// --root <dir> / --baseline <path>: valor no argv seguinte à flag. Sem a flag → default
// idêntico ao de hoje (cwd / baseline hardcoded relativo ao ROOT). Backward-compat.
function argValue(flag) {
  const i = process.argv.indexOf(flag);
  return i !== -1 && process.argv[i + 1] ? process.argv[i + 1] : null;
}

const ROOT_ARG = argValue('--root');
const ROOT = ROOT_ARG ? resolve(ROOT_ARG) : process.cwd();
const BASELINE_ARG = argValue('--baseline');
const BASELINE_PATH = BASELINE_ARG ? resolve(BASELINE_ARG) : resolve(ROOT, 'scripts/layout-primitives-baseline.json');
const MODE_WRITE = process.argv.includes('--write-baseline');
const MODE_JSON = process.argv.includes('--json');

// Diretórios-alvo (telas + componentes de feature).
const SCAN_ROOTS = [
  join('resources', 'js', 'Pages'),
  join('resources', 'js', 'Components'),
];

// Subárvores excluídas (a casa do flex/grid + primitivos leaf).
const EXCLUDE_PREFIXES = [
  'resources/js/Components/layout/',
  'resources/js/Components/ui/',
];

const IGNORE_DIR = new Set(['node_modules', '.git', 'vendor']);

// ---------------------------------------------------------------------------
// Coleta de arquivos-alvo (.tsx)
// ---------------------------------------------------------------------------
function walk(dir, acc) {
  let entries;
  try {
    entries = readdirSync(dir, { withFileTypes: true });
  } catch {
    return acc;
  }
  for (const e of entries) {
    if (e.isDirectory()) {
      if (IGNORE_DIR.has(e.name)) continue;
      walk(join(dir, e.name), acc);
    } else if (e.isFile() && e.name.endsWith('.tsx')) {
      acc.push(join(dir, e.name));
    }
  }
  return acc;
}

function relPosix(absPath) {
  return absPath.replace(/\\/g, '/').replace(`${ROOT.replace(/\\/g, '/')}/`, '');
}

function collectTargets() {
  const files = [];
  for (const root of SCAN_ROOTS) {
    const abs = resolve(ROOT, root);
    if (!existsSync(abs)) continue;
    for (const f of walk(abs, [])) {
      const rel = relPosix(f);
      if (EXCLUDE_PREFIXES.some((p) => rel.startsWith(p))) continue;
      files.push(f);
    }
  }
  return files;
}

// ---------------------------------------------------------------------------
// Heurística — conta tokens de display crus em linhas com className
// ---------------------------------------------------------------------------
// `flex` cru (não inline-flex, flex-1, flex-col, flex-row, flex-wrap, flex-shrink…)
const RE_FLEX = /(?<![-\w])flex(?![-\w])/g;
// `grid` cru (não grid-cols-*) e que NÃO seja o idioma de centragem `grid place-…`
const RE_GRID = /(?<![-\w])grid(?![-\w])(?!\s+place-)/g;

// Extrai SÓ o(s) valor(es) de className da linha — evita falso-positivo de
// `style={{ flex: x }}` (propriedade CSS) ou `flex` em prosa/comentário.
//   className="…"  ·  className='…'  ·  className={cn("…", cond && "…")}  (single-line)
function extractClassStrings(line) {
  const out = [];
  let m;
  const reStr = /className\s*=\s*("([^"]*)"|'([^']*)')/g;
  while ((m = reStr.exec(line)) !== null) out.push(m[2] ?? m[3] ?? '');
  const reExpr = /className\s*=\s*\{([^}]*)\}/g;
  while ((m = reExpr.exec(line)) !== null) {
    const lits = m[1].match(/"[^"]*"|'[^']*'|`[^`]*`/g) || [];
    for (const lit of lits) out.push(lit.slice(1, -1));
  }
  return out;
}

function isCommentLine(line) {
  const t = line.trim();
  return t.startsWith('//') || t.startsWith('*') || t.startsWith('/*');
}

function scanFile(absPath) {
  const rel = relPosix(absPath);
  let content;
  try {
    content = readFileSync(absPath, 'utf8');
  } catch {
    return { rel, count: 0, hits: [] };
  }
  const lines = content.split('\n');
  const hits = [];
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (isCommentLine(line) || !line.includes('className')) continue;
    let flex = 0;
    let grid = 0;
    for (const cls of extractClassStrings(line)) {
      flex += (cls.match(RE_FLEX) || []).length;
      grid += (cls.match(RE_GRID) || []).length;
    }
    const n = flex + grid;
    if (n > 0) hits.push({ linha: i + 1, n, trecho: line.trim().slice(0, 140) });
  }
  const count = hits.reduce((s, h) => s + h.n, 0);
  return { rel, count, hits };
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
function main() {
  const targets = collectTargets();
  const perFile = {};
  const allHits = {};
  let total = 0;
  for (const f of targets) {
    const { rel, count, hits } = scanFile(f);
    if (count > 0) {
      perFile[rel] = count;
      allHits[rel] = hits;
      total += count;
    }
  }

  if (MODE_WRITE) {
    const out = {
      _meta: {
        generated_at: new Date().toISOString(),
        total_findings: total,
        files_with_findings: Object.keys(perFile).length,
        files_scanned: targets.length,
        refs: ['ADR 0253', 'ADR 0209', 'ADR 0240'],
        nota: 'Contagem de flex/grid solto POR ARQUIVO. Gate falha se um arquivo AUMENTAR ou se arquivo novo nascer com flex/grid solto.',
      },
      files: Object.fromEntries(Object.entries(perFile).sort((a, b) => a[0].localeCompare(b[0]))),
    };
    writeFileSync(BASELINE_PATH, JSON.stringify(out, null, 2) + '\n');
    console.log(`layout-primitives-guard · WRITE-BASELINE`);
    console.log(`Arquivos escaneados: ${targets.length}`);
    console.log(`Arquivos com flex/grid solto: ${Object.keys(perFile).length} · total ${total}`);
    console.log(`\n✅ Baseline gravado em ${relPosix(BASELINE_PATH)}`);
    return;
  }

  if (!existsSync(BASELINE_PATH)) {
    console.error(`❌ Baseline não existe em ${relPosix(BASELINE_PATH)}`);
    console.error('   Rode: node scripts/layout-primitives-guard.mjs --write-baseline');
    process.exit(1);
  }

  const baseline = JSON.parse(readFileSync(BASELINE_PATH, 'utf8'));
  const baseFiles = baseline.files || {};

  const regressions = [];
  for (const [file, atual] of Object.entries(perFile)) {
    const base = baseFiles[file] || 0;
    if (atual > base) regressions.push({ file, base, atual, delta: atual - base });
  }

  if (MODE_JSON) {
    console.log(JSON.stringify({
      total,
      files_with_findings: Object.keys(perFile).length,
      regressions,
      ok: regressions.length === 0,
    }, null, 2));
    process.exit(regressions.length === 0 ? 0 : 1);
  }

  console.log(`layout-primitives-guard · VALIDATE (ADR 0253)`);
  console.log(`Arquivos escaneados: ${targets.length} · com flex/grid solto: ${Object.keys(perFile).length} · total ${total}`);

  if (regressions.length > 0) {
    console.error('');
    console.error(`❌ REGRESSÃO — ${regressions.length} arquivo(s) com MAIS flex/grid solto vs baseline:`);
    for (const r of regressions.sort((a, b) => b.delta - a.delta)) {
      console.error(`   ${r.file} · ${r.base} → ${r.atual} (Δ+${r.delta})`);
      for (const h of (allHits[r.file] || []).slice(0, 6)) {
        console.error(`        L${h.linha}: ${h.trecho}`);
      }
    }
    console.error('\n👉 Componha com primitivos de layout: <Stack>/<Inline>/<Grid>/<Box> (resources/js/Components/layout).');
    console.error('   Se a regressão for legítima (refator consciente): node scripts/layout-primitives-guard.mjs --write-baseline');
    process.exit(1);
  }

  console.log('\n✅ Sem regressões vs baseline (nenhuma tela ganhou flex/grid solto novo).');
}

main();
