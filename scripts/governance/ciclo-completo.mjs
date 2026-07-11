#!/usr/bin/env node
// ciclo-completo.mjs — GATE "a tela nasceu (e segue) COMPLETA?" (Constituição UI v2 · UI-0013).
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// Wagner 2026-07-11: "fazer na mão é sorteio e não garante funcionamento". O gerador
// criar-tela.mjs faz a tela NASCER completa (carimbada do Padrão de Tela); este gate é o
// outro lado — GARANTE que toda tela viva TEM o conjunto obrigatório do ciclo, senão é
// "incompleta". É a catraca que impede a tela de degradar depois de nascer.
//
// Conjunto obrigatório por tela (resources/js/Pages/**/*.tsx roteada):
//   1. charter   — <Tela>.charter.md existe (o contrato de design/produto)
//   2. PT declarado — o charter declara qual Padrão de Tela herda (related_prototype: PT-0X)
//   3. pt-conforme — a tela TEM a assinatura do PT que declara (consome pt-conformance --json)
//   4. casos     — <Tela>.casos.md existe (o contrato de teste · ADR 0264 G-1)
//   5. teste     — o casos.md referencia ≥1 teste/E2E (a rastreabilidade caso↔teste)
//   6. golden-live — o golden do PT herdado está `live` (GOLDEN-LIVE: tela não fecha o ciclo
//                    se o golden do padrão dela ainda é draft → força o Design a terminar)
// Faltou qualquer um → tela INCOMPLETA.
//
// NÃO DUPLICA: consome pt-conformance --json (#3) e ecoa o trio do casos-guard (#1/#4/#5) numa
// visão POR-TELA unificada + a dimensão nova que ninguém cobria (#2 PT declarado + #6 golden-live).
//
// ADVISORY de nascença (ADR 0314/0271 — required = só Tier-0; cobertura de ciclo é quality) +
// CATRACA: o nº de telas COMPLETAS só sobe (regressão bloqueia; débito legado é absorvido).
//
// Uso:
//   node scripts/governance/ciclo-completo.mjs            # relatório (read-only) + lado Design
//   node scripts/governance/ciclo-completo.mjs --json      # grava baseline (catraca)
//   node scripts/governance/ciclo-completo.mjs --check     # exit 1 se `completo` regrediu vs baseline
//   node scripts/governance/ciclo-completo.mjs --selftest  # fixtures herméticas (bite/release)

import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, readdirSync, statSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..', '..');
const PAGES = join(ROOT, 'resources', 'js', 'Pages');
const GOLDEN_DIR = join(ROOT, 'memory', 'requisitos', '_DesignSystem', 'padroes-tela');
const BASELINE = join(ROOT, 'memory', 'governance', 'ciclo-completo-baseline.json');
const PT_FILE = {
  'PT-01': 'PT-01-Lista.md', 'PT-02': 'PT-02-Form-Drawer.md', 'PT-03': 'PT-03-Detalhe.md',
  'PT-04': 'PT-04-Dashboard.md', 'PT-05': 'PT-05-Kanban.md',
};

// ─────────────────────────────────────────────────────────────────────────────
// coleta
// ─────────────────────────────────────────────────────────────────────────────
const relRoot = (p, root) => resolve(p).replace(resolve(root), '').replace(/^[\\/]/, '').replace(/\\/g, '/');

// "Página roteada" = .tsx em Pages/** fora de _components/_partials, sem .test/.charter.tsx
// (mesma heurística do casos-coverage-guard.mjs — G-1).
function walkPages(pagesDir) {
  const out = [];
  if (!existsSync(pagesDir)) return out;
  for (const e of readdirSync(pagesDir)) {
    const p = join(pagesDir, e);
    const st = statSync(p);
    if (st.isDirectory()) { if (e !== '_components' && e !== '_partials') out.push(...walkPages(p)); }
    else if (e.endsWith('.tsx') && !e.endsWith('.charter.tsx') && !e.includes('.test.')) out.push(p);
  }
  return out;
}

const fmField = (content, key) => {
  const m = content.match(/^---\s*\n([\s\S]*?)\n---/);
  const fm = m ? m[1] : '';
  const f = fm.match(new RegExp(`^${key}\\s*:\\s*(.+)$`, 'm'));
  return f ? f[1].trim() : null;
};
const declaredPT = (relProto) => {
  const m = (relProto || '').match(/PT-0[1-5]/i);
  return m ? m[0].toUpperCase() : null;
};
// #5 teste: o casos.md aponta pra um teste/E2E real (path de teste em qualquer linha).
const TESTE_RE = /(tests?\/|Modules\/[^\s]+\/Tests?\/|e2e\/|\.spec\.[tj]s|Test\.php)/i;

// pt-conforme (#3): mapa tsxRel → verdict, do pt-conformance --json (join estável via `tsx`).
// Tolerante ao formato antigo (sem `tsx`): cai pra `page`. Injetável no selftest (não shella).
function loadPtMap() {
  try {
    const raw = execFileSync('node', [join(HERE, 'pt-conformance.mjs'), '--json'], { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
    const j = JSON.parse(raw);
    const map = new Map();
    // Indexa por TODA chave disponível: `tsx` (caminho, join estável) E `page` (rota do
    // charter). Tolera o pt-conformance ANTIGO (só `page`) e o NOVO (com `tsx`) — o gate
    // funciona antes e depois do enhance do pt-conformance chegar no main.
    for (const r of j.rows || []) {
      if (r.tsx) map.set(r.tsx, r.verdict);
      if (r.page) map.set(r.page, r.verdict);
    }
    return map;
  } catch (e) {
    console.error(`ciclo-completo: falha ao consumir pt-conformance --json: ${e.message}`);
    process.exit(2);
  }
}

// golden status por PT (#6): lê o frontmatter `status:` de cada golden. Injetável no selftest.
function loadGoldenStatus(goldenDir) {
  const out = {};
  for (const [pt, file] of Object.entries(PT_FILE)) {
    try {
      const s = fmField(readFileSync(join(goldenDir, file), 'utf8'), 'status');
      out[pt] = (s || 'desconhecido').toLowerCase();
    } catch { out[pt] = 'ausente'; }
  }
  return out;
}

// ─────────────────────────────────────────────────────────────────────────────
// núcleo — classifica cada página (puro, testável)
// ─────────────────────────────────────────────────────────────────────────────
function classifyPage(tsxAbs, { root, ptMap, goldenStatus }) {
  const dir = dirname(tsxAbs);
  const base = tsxAbs.replace(/\.tsx$/, '').split(/[\\/]/).pop();
  const charterPath = join(dir, `${base}.charter.md`);
  const casosPath = join(dir, `${base}.casos.md`);
  const tsxRel = relRoot(tsxAbs, root);

  const hasCharter = existsSync(charterPath);
  const charterTxt = hasCharter ? readFileSync(charterPath, 'utf8') : '';
  const pt = hasCharter ? declaredPT(fmField(charterTxt, 'related_prototype')) : null;
  const route = hasCharter ? fmField(charterTxt, 'page') : null; // chave de join alternativa (pt-conformance antigo)
  const hasCasos = existsSync(casosPath);
  const casosTxt = hasCasos ? readFileSync(casosPath, 'utf8') : '';

  // pt-conforme: junta por caminho do tsx (pt-conformance novo) OU pela rota `page:` (antigo).
  const conforme = ptMap.get(tsxRel) === 'CONFORME' || (route && ptMap.get(route) === 'CONFORME');
  const checks = {
    charter: hasCharter,
    pt_declarado: !!pt,
    pt_conforme: pt ? !!conforme : false,
    casos: hasCasos,
    teste: hasCasos && TESTE_RE.test(casosTxt),
    golden_live: pt ? goldenStatus[pt] === 'live' : false,
  };
  const faltando = Object.entries(checks).filter(([, v]) => !v).map(([k]) => k);
  return { page: tsxRel, pt, checks, faltando, completo: faltando.length === 0 };
}

function computeRows(root, pagesDir, deps) {
  return walkPages(pagesDir).map((p) => classifyPage(p, { root, ...deps })).sort((a, b) => a.page.localeCompare(b.page));
}

// ─────────────────────────────────────────────────────────────────────────────
// SELFTEST — fixtures herméticas: prova que o gate MORDE (incompleta) e SOLTA (completa),
// e que golden-draft SOZINHO reprova (GOLDEN-LIVE). Anti-fantasma (ADR 0256).
// ─────────────────────────────────────────────────────────────────────────────
if (process.argv.includes('--selftest')) {
  let fails = 0;
  const t = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };
  const tmp = join(HERE, `.ciclo-selftest-${process.pid}`);
  const pagesDir = join(tmp, 'resources', 'js', 'Pages');
  const wr = (rel, content) => { const p = join(tmp, rel); mkdirSync(dirname(p), { recursive: true }); writeFileSync(p, content); };
  const charter = (pt) => `---\ncomponent: x\nrelated_prototype: n/a (herda ${pt} X; segue o Padrão de Tela)\n---\n`;
  const casosCom = `---\nowner: w\n---\n## UC-X-01\n- **Teste:** e2e/x.spec.ts\n`;
  const casosSem = `---\nowner: w\n---\n## UC-X-01\n- sem teste\n`;

  try {
    // Mod/Completa — tudo presente, PT-01 conforme, golden live → COMPLETO
    wr('resources/js/Pages/Mod/Completa.tsx', '<DataTable/>');
    wr('resources/js/Pages/Mod/Completa.charter.md', charter('PT-01'));
    wr('resources/js/Pages/Mod/Completa.casos.md', casosCom);
    // Mod/SemCasos — falta casos.md
    wr('resources/js/Pages/Mod/SemCasos.tsx', '<DataTable/>');
    wr('resources/js/Pages/Mod/SemCasos.charter.md', charter('PT-01'));
    // Mod/SemTeste — casos.md sem ref de teste
    wr('resources/js/Pages/Mod/SemTeste.tsx', '<DataTable/>');
    wr('resources/js/Pages/Mod/SemTeste.charter.md', charter('PT-01'));
    wr('resources/js/Pages/Mod/SemTeste.casos.md', casosSem);
    // Mod/GoldenDraft — TUDO ok mas o golden do PT-02 é draft → só isso reprova (GOLDEN-LIVE)
    wr('resources/js/Pages/Mod/GoldenDraft.tsx', 'useForm');
    wr('resources/js/Pages/Mod/GoldenDraft.charter.md', charter('PT-02'));
    wr('resources/js/Pages/Mod/GoldenDraft.casos.md', casosCom);
    // Mod/SemPT — charter não declara PT
    wr('resources/js/Pages/Mod/SemPT.tsx', '<DataTable/>');
    wr('resources/js/Pages/Mod/SemPT.charter.md', `---\ncomponent: x\n---\n`);

    const ptMap = new Map([
      ['resources/js/Pages/Mod/Completa.tsx', 'CONFORME'],
      ['resources/js/Pages/Mod/SemCasos.tsx', 'CONFORME'],
      ['resources/js/Pages/Mod/SemTeste.tsx', 'CONFORME'],
      ['resources/js/Pages/Mod/GoldenDraft.tsx', 'CONFORME'],
    ]);
    const goldenStatus = { 'PT-01': 'live', 'PT-02': 'draft', 'PT-03': 'draft', 'PT-04': 'draft', 'PT-05': 'draft' };
    const rows = computeRows(tmp, pagesDir, { ptMap, goldenStatus });
    const by = (n) => rows.find((r) => r.page.endsWith(`/${n}.tsx`));

    t(by('Completa').completo, 'tela com tudo (charter+PT+conforme+casos+teste+golden-live) = COMPLETA');
    t(!by('SemCasos').completo && by('SemCasos').faltando.includes('casos'), 'sem casos.md = INCOMPLETA (falta casos)');
    t(!by('SemTeste').completo && by('SemTeste').faltando.includes('teste'), 'casos sem ref de teste = INCOMPLETA (falta teste)');
    t(!by('GoldenDraft').completo && by('GoldenDraft').faltando.join() === 'golden_live', 'golden DRAFT sozinho reprova (GOLDEN-LIVE)');
    t(!by('SemPT').completo && by('SemPT').faltando.includes('pt_declarado'), 'charter sem PT declarado = INCOMPLETA');
    const completoAtual = rows.filter((r) => r.completo).length;
    t(completoAtual === 1, 'catraca conta 1 completa nas fixtures');
    // catraca (--check): baseline > atual ⇒ regressão bloqueia. Prova a comparação pura.
    t(completoAtual < 2, 'baseline=2 > atual=1 ⇒ regressão detectável (catraca morde)');
  } finally {
    try { rmSync(tmp, { recursive: true, force: true }); } catch { /* ignore */ }
  }
  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — gate morde (incompleta) e solta (completa); golden-draft reprova.');
  process.exit(fails ? 1 : 0);
}

// ─────────────────────────────────────────────────────────────────────────────
// modos de produção
// ─────────────────────────────────────────────────────────────────────────────
const rows = computeRows(ROOT, PAGES, { ptMap: loadPtMap(), goldenStatus: loadGoldenStatus(GOLDEN_DIR) });
const completo = rows.filter((r) => r.completo).length;
const total = rows.length;
const pct = total ? Math.round((completo / total) * 100) : 0;

if (process.argv.includes('--json')) {
  writeFileSync(BASELINE, JSON.stringify({
    completo, total,
    note: 'Catraca do ciclo-de-tela (UI-0013): `completo` (telas com charter+PT+conforme+casos+teste+golden-live) só sobe. Baixar exige decisão consciente. ADVISORY (ADR 0314).',
  }, null, 2) + '\n');
  console.log(`baseline gravado: completo=${completo}/${total}`);
  process.exit(0);
}

if (process.argv.includes('--check')) {
  if (!existsSync(BASELINE)) { console.error('ciclo-completo: baseline ausente — rode --json pra semear.'); process.exit(1); }
  const base = JSON.parse(readFileSync(BASELINE, 'utf8'));
  if (completo < base.completo) {
    console.error(`ciclo-completo: REGREDIU — completo ${completo} < baseline ${base.completo}. Uma tela perdeu peça do ciclo (charter/PT/conforme/casos/teste/golden-live).`);
    process.exit(1);
  }
  console.log(`ciclo-completo: OK — completo ${completo} ≥ baseline ${base.completo} (catraca).`);
  process.exit(0);
}

// ── relatório (read-only) ──
console.log('═══ CICLO-COMPLETO por tela (nasceu/segue completa? · UI-0013) ═══');
console.log(`telas roteadas : ${total}  ·  ✅ completas: ${completo} (${pct}%)  ·  ⚠️ incompletas: ${total - completo}`);

// contagem por peça faltante (onde o ciclo mais fura)
const contPeca = {};
for (const r of rows) for (const f of r.faltando) contPeca[f] = (contPeca[f] || 0) + 1;
console.log('\nonde o ciclo fura (telas faltando cada peça):');
for (const [peca, n] of Object.entries(contPeca).sort((a, b) => b[1] - a[1])) console.log(`  • ${peca.padEnd(13)}: ${n}`);

// ── LADO DESIGN (GOLDEN-LIVE) — quantas telas estão travadas SÓ pelo golden draft ──
const golden = loadGoldenStatus(GOLDEN_DIR);
console.log('\nlado Design — golden de cada Padrão de Tela (draft trava o fechamento do ciclo):');
for (const [pt, st] of Object.entries(golden)) {
  const soGolden = rows.filter((r) => r.pt === pt && r.faltando.join() === 'golden_live').length;
  const flag = st === 'live' ? '✅' : '⚠️ ';
  const extra = st !== 'live' && soGolden ? `  ← ${soGolden} tela(s) fechariam o ciclo se este golden virasse live` : '';
  console.log(`  ${flag} ${pt}: ${st}${extra}`);
}
console.log('\ncatraca: `completo` só sobe. Nasça a tela com `criar-tela.mjs` (conjunto completo por construção).');
console.log('GOLDEN-LIVE: pra fechar telas de um PT, o Design precisa levar o golden do PT de draft → live.');
