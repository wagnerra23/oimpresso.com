#!/usr/bin/env node
// scripts/ds-ledger.mjs — Ledger de Conformidade DS (censo Onda 0, por tela).
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// O plano `DS Rollout - Ondas e Testes` (tradução em /governance/ds-rollout) promete um
// PLACAR ÚNICO que diz, a qualquer momento, quantas telas estão 100% no DS — e a regra
// de ouro é: **a tela só mostra número que veio de gate rodando** (não da palavra de
// ninguém). Este script É esse gate rodando: roda os checks DS por Page e popula a prop
// `census` da tela de verdade (vs o snapshot estático "≈6% · TODO ledger").
//
// É a Onda 0 do plano (o "censo de adoção" — a 1ª coluna do Ledger), aterrissada no repo.
//
// =====================================================================================
// O QUE CADA COLUNA MEDE (e o que HONESTAMENTE ainda não dá pra medir aqui)
// =====================================================================================
//   tokens 0 cru  →  cor crua de QUALQUER tipo == 0, somando 3 sinais reais:
//                      (a) eslint ds/no-arbitrary-color  (hex cru bg-[#..])
//                      (b) eslint ds/no-adhoc-status-text (text-rose/emerald-600 cru)
//                      (c) paleta Tailwind crua (bg-stone-50, text-rose-700, …) —
//                          contador de MEDIÇÃO (não é regra nova de lint; não falha CI,
//                          não muda Tier 0; só MEDE — as regras ds/* de hoje não pegam
//                          paleta crua, e foi JUSTO isso que a "Medição real" do plano
//                          achou no piloto Produto/Create).
//                      (d) conformance-gate.rawColorHits no CSS bespoke da tela (escopo
//                          atual = família Sells; outras telas → 0 até o gate generalizar).
//   primitivos    →  eslint ds/no-native-{radio,checkbox,select} + ds/no-rounded-xl == 0
//                      (usa <Checkbox>/<Select>/<RadioGroup>/radius canon, não na mão).
//   probe G1–G13  →  'na' — exige o probe de BROWSER (qa-conformance, Camada 2). Este
//                      censo é estático/determinístico; NÃO finge verde aqui.
//   dark          →  'na' — idem (precisa render). Não medido por censo estático.
//   [W] aprovou   →  charter da tela com `status: live` (aprovação registrada).
//
//   + components-tree-guard roda 1× (global): árvore de Components/ canônica? → banner.
//
// =====================================================================================
// Uso:
//   node scripts/ds-ledger.mjs            # tabela humana
//   node scripts/ds-ledger.mjs --json     # JSON (shape consumido pelo DsRolloutController)
//   node scripts/ds-ledger.mjs --write    # grava governance/ds-ledger.json (artefato carimbado)
//
// Refs: ADR 0209 (ratchet), 0239 (gov DS git=SSOT), 0240 (derivado+enforcado sobrevive),
//       conformance-gate.mjs · components-tree-guard.mjs · ds-report.mjs · PROTOCOL §10.

import { execSync, spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { resolve, join } from 'node:path';
import { rawColorHits } from './conformance-gate.mjs';

const AS_JSON = process.argv.includes('--json');
const DO_WRITE = process.argv.includes('--write');

const ROOT = process.cwd().replace(/\\/g, '/');
const PAGES_DIR = resolve(ROOT, 'resources/js/Pages');
const CSS_DIR = resolve(ROOT, 'resources/css');
const OUT_FILE = resolve(ROOT, 'governance/ds-ledger.json');

// Telas-referência (o "ouro" · molde) — marcadas ★ e FORA do % (o plano migra a Caixa
// pro DS só no Bloco C; até lá ela é a régua, não uma linha a "passar").
const REFERENCE = new Map([
  ['Atendimento', 'o ouro · referência'],
]);

// Módulos a ignorar na conta (não são telas-cliente reais).
const SKIP = new Set(['_Showcase', 'Modules', 'Home']);

// Famílias de regra ds/* → coluna do Ledger.
const TOKEN_RULES = new Set(['ds/no-arbitrary-color', 'ds/no-adhoc-status-text']);
const PRIM_RULES = new Set(['ds/no-native-radio', 'ds/no-native-checkbox', 'ds/no-native-select', 'ds/no-rounded-xl']);

// CSS bespoke por tela onde o conformance-gate (rawColorHits) tem escopo hoje.
const SCREEN_CSS = new Map([
  ['Sells', ['sells-cowork.css', 'sells-cowork-edit.css', 'sells-cowork-show.css']],
]);

// Paleta Tailwind crua (medição-only): bg-stone-50 / text-rose-700 / border-zinc-200 …
// NÃO casa token semântico (foreground/muted/primary/success/destructive/border/card/…).
const PALETTE = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
const PALETTE_RE = new RegExp(
  `\\b(?:bg|text|border|ring|from|to|via|outline|decoration|divide|accent|caret|fill|stroke|placeholder)-(?:${PALETTE})-(?:50|100|200|300|400|500|600|700|800|900|950)\\b`,
  'g',
);
const RULE_RE = /^(ds\/[a-z0-9-]+)/;

function shortSha() {
  try {
    return execSync('git rev-parse --short HEAD', { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  } catch { return null; }
}

function runEslintPages() {
  const cmd = `npx --no-install eslint "resources/js/Pages" --format=json --max-warnings=999999`;
  try {
    return JSON.parse(execSync(cmd, { encoding: 'utf8', maxBuffer: 200 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'], shell: true }));
  } catch (err) {
    if (err.stdout) { try { return JSON.parse(err.stdout); } catch { /* fallthrough */ } }
    console.error('[ds-ledger] eslint falhou — census parcial (ds/* = 0 assumido). Detalhe:', err.message);
    return [];
  }
}

function moduleOf(path) {
  const m = path.replace(/\\/g, '/').match(/\/Pages\/([^/]+)/);
  return m ? m[1] : null;
}

// Conta paleta crua num arquivo, ignorando exemplos em <code>/<pre> e blocos de comentário
// (a tela DsRollout, p.ex., cita "text-rose-700" como TEXTO de exemplo — não é uso real).
function paletteRawHits(file) {
  let src = readFileSync(file, 'utf8');
  src = src
    .replace(/<code[\s\S]*?<\/code>/gi, '')
    .replace(/<pre[\s\S]*?<\/pre>/gi, '')
    .replace(/\/\*[\s\S]*?\*\//g, '');
  return (src.match(PALETTE_RE) || []).length;
}

function listTsx(dir) {
  const out = [];
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, e.name);
    if (e.isDirectory()) out.push(...listTsx(full));
    else if (e.name.endsWith('.tsx') || e.name.endsWith('.ts')) out.push(full);
  }
  return out;
}

function moduleHasLiveCharter(dir) {
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, e.name);
    if (e.isDirectory()) { if (moduleHasLiveCharter(full)) return true; }
    else if (e.name.endsWith('.charter.md')) {
      try { if (/^status:\s*live\b/m.test(readFileSync(full, 'utf8'))) return true; } catch { /* skip */ }
    }
  }
  return false;
}

function cssRawForModule(mod) {
  const files = SCREEN_CSS.get(mod);
  if (!files) return 0;
  let n = 0;
  for (const f of files) {
    const p = join(CSS_DIR, f);
    if (existsSync(p)) { try { n += rawColorHits(readFileSync(p, 'utf8')).length; } catch { /* skip */ } }
  }
  return n;
}

function treeGuard() {
  const r = spawnSync('node', [resolve(ROOT, 'scripts/components-tree-guard.mjs')], { encoding: 'utf8' });
  const pass = r.status === 0;
  const m = (r.stderr || '').match(/(\d+)\s+viola/);
  return { pass, violations: pass ? 0 : (m ? Number(m[1]) : null) };
}

function collect() {
  // 1. eslint ds/* por módulo, separado por família.
  const lintTok = {}, lintPrim = {};
  for (const result of runEslintPages()) {
    const mod = moduleOf(result.filePath);
    if (!mod) continue;
    for (const msg of result.messages) {
      const mm = (msg.message || '').match(RULE_RE);
      if (!mm) continue;
      if (TOKEN_RULES.has(mm[1])) lintTok[mod] = (lintTok[mod] || 0) + 1;
      else if (PRIM_RULES.has(mm[1])) lintPrim[mod] = (lintPrim[mod] || 0) + 1;
    }
  }

  // 2. varre módulos no FS: paleta crua + charter live + contagem de arquivos.
  const rows = [];
  for (const e of readdirSync(PAGES_DIR, { withFileTypes: true })) {
    if (!e.isDirectory() || SKIP.has(e.name) || e.name.startsWith('_')) continue;
    const dir = join(PAGES_DIR, e.name);
    const files = listTsx(dir);
    if (!files.length) continue;

    const palette = files.reduce((s, f) => s + paletteRawHits(f), 0);
    const cssRaw = cssRawForModule(e.name);
    const tokensCru = (lintTok[e.name] || 0) + palette + cssRaw;
    const primCru = lintPrim[e.name] || 0;
    const approved = moduleHasLiveCharter(dir);
    const isRef = REFERENCE.has(e.name);

    rows.push({
      screen: e.name,
      note: isRef ? REFERENCE.get(e.name) : `${files.length} arquivo(s)`,
      ...(isRef ? { reference: true } : {}),
      cells: {
        tokens: isRef ? 'ref' : (tokensCru === 0 ? 'yes' : 'no'),
        primitivos: isRef ? 'ref' : (primCru === 0 ? 'yes' : 'no'),
        probe: 'na',
        dark: 'na',
        approved: approved ? 'yes' : 'no',
      },
      _m: { tokensCru, primCru, palette, cssRaw, approved, reference: isRef },
    });
  }

  // 3. ordena: referência primeiro, depois mais-verde primeiro, depois nome.
  const score = (r) => (r.cells.tokens === 'yes' ? 1 : 0) + (r.cells.primitivos === 'yes' ? 1 : 0) + (r.cells.approved === 'yes' ? 1 : 0);
  rows.sort((a, b) => {
    if (!!b.reference - !!a.reference) return (b.reference ? 1 : 0) - (a.reference ? 1 : 0);
    if (score(b) !== score(a)) return score(b) - score(a);
    return a.screen.localeCompare(b.screen);
  });

  // 4. % = telas com tokens E primitivos verdes / telas contadas (exclui referência).
  const counted = rows.filter((r) => !r.reference);
  const done = counted.filter((r) => r.cells.tokens === 'yes' && r.cells.primitivos === 'yes').length;
  const progressPct = counted.length ? Math.round((done / counted.length) * 100) : 0;

  const tree = treeGuard();

  return {
    ledger: rows.map(({ _m, ...r }) => r),
    progressPct,
    progressLabel: 'adoção tokens + primitivos (censo Onda 0)',
    measured: true,
    measuredAgainstSha: shortSha(),
    generatedAt: new Date().toISOString(),
    treeGuard: tree,
    counts: { screens: counted.length, done, references: rows.length - counted.length },
    _detail: rows.map((r) => ({ screen: r.screen, ...r._m })),
  };
}

function printTable(data) {
  const pad = (s, n) => String(s).padEnd(n);
  const lp = (s, n) => String(s).padStart(n);
  const g = (c) => ({ yes: '✓', no: '·', ref: '★', na: '–' }[c] || '?');
  console.log(`\n  Ledger de Conformidade DS — censo Onda 0 · @${data.measuredAgainstSha || '?'} · ${data.generatedAt.slice(0, 16).replace('T', ' ')}\n`);
  console.log(`  ${pad('Tela', 26)} ${'Tok'} ${'Prim'} ${'Prob'} ${'Dark'} ${'[W]'}`);
  console.log('  ' + '-'.repeat(54));
  for (const r of data.ledger) {
    const c = r.cells;
    console.log(`  ${pad(r.screen, 26)}  ${g(c.tokens)}    ${g(c.primitivos)}    ${g(c.probe)}    ${g(c.dark)}   ${g(c.approved)}`);
  }
  console.log('  ' + '-'.repeat(54));
  console.log(`\n  ${data.progressLabel}: ${lp(data.progressPct, 3)}%  (${data.counts.done}/${data.counts.screens} telas · ${data.counts.references} referência)`);
  console.log(`  árvore de Components/ (components-tree-guard): ${data.treeGuard.pass ? '✓ canônica' : `✗ ${data.treeGuard.violations} violação(ões)`}`);
  console.log(`  legenda: ✓ ok · · falta · ★ referência · – não medido (probe/dark = Camada 2 browser)\n`);
}

function main() {
  const data = collect();
  if (AS_JSON) { const { _detail, ...pub } = data; console.log(JSON.stringify(pub, null, 2)); return; }
  if (DO_WRITE) {
    const { _detail, ...pub } = data;
    writeFileSync(OUT_FILE, JSON.stringify(pub, null, 2) + '\n');
    console.log(`[ds-ledger] census gravado em governance/ds-ledger.json (${pub.counts.screens} telas · ${pub.progressPct}%).`);
    printTable(data);
    return;
  }
  printTable(data);
}

main();
