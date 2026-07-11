#!/usr/bin/env node
// pt-conformance.mjs — VERIFICA que uma tela que DECLARA "herda PT-0X" tem de fato a
//   assinatura estrutural desse Padrão de Tela. Sem isto, declarar `related_prototype: "herda
//   PT-05 Kanban"` num charter é COUNT-PUMP: o design-coverage conta a string, ninguém checa se
//   a tela é mesmo um kanban (revisão adversarial 2026-07-11). Este gate torna a declaração
//   FALSIFICÁVEL — uma tela que jura PT-02 mas não tem <form> é MISMATCH.
//
// ⚠️ v1 HEURÍSTICO — detecção por ASSINATURA (regex de componente), não parsing semântico.
//   Mesma honestidade do reconcile-triplet.mjs (que faz slot-parity fino do PT-01). Aqui o
//   escopo é mais raso e mais largo: pega MISMATCH GROSSO do PT declarado nos 5 arquétipos.
//   PT-03 (Detalhe) vs PT-04 (Dashboard) compartilham KPIs → distinção fuzzy (ver classify).
//
// Contrato: memory/requisitos/_DesignSystem/padroes-tela/PT-0{1..5}-*.md (assinaturas dos golden).
//
// Uso:
//   node scripts/governance/pt-conformance.mjs            # relatório de todas as telas que declaram PT
//   node scripts/governance/pt-conformance.mjs --json      # JSON determinístico
//   node scripts/governance/pt-conformance.mjs --check     # exit 1 se houver MISMATCH (declara PT que não é)
//   node scripts/governance/pt-conformance.mjs --selftest  # fixtures herméticas

import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..', '..');
const PAGES = join(ROOT, 'resources', 'js', 'Pages');

// ── assinaturas estruturais por arquétipo (do golden de cada PT-0X) ──
const has = (code, ...res) => res.some((r) => r.test(code));
function detectSignals(code) {
  return {
    form: has(code, /\buseForm\b/, /<form[\s>]/, /FormSection/, /FormGrid/),
    list: has(code, /<table[\s>]/, /<thead/, /<tbody/, /DataTable/i, /Pagination/) ||
          has(code, /grid-cols/, /ProdutoCard/i, /<article[\s>]/),
    kanban: has(code, /dnd-kit/, /KanbanDndProvider/, /BoardColumn/, /onDragStart/, /draggable/, /\bKanban\b/),
    kpi: has(code, /KpiCard/, /KpiGrid/, /KpiFilterCard/),
    detail: has(code, /FsmActionPanel/, /NextActionPanel/, /VdNextActionPanel/, /Timeline/, /Histórico/i),
  };
}

// PT declarado → sinal MÍNIMO que a tela precisa ter pra a declaração não ser mentira.
// (Requisito grosso; não exige AUSÊNCIA de outros sinais — telas são híbridas.)
const REQUIRED = {
  'PT-01': (s) => s.list,
  'PT-02': (s) => s.form,
  'PT-03': (s) => s.detail || s.kpi,   // detalhe: painel de ação FSM/histórico ou KPIs de 1 registro
  'PT-04': (s) => s.kpi,               // dashboard: KPIs agregados
  'PT-05': (s) => s.kanban,
};

const fmGet = (fm, key) => {
  const m = fm.match(new RegExp('^' + key + ':\\s*(.+)$', 'im'));
  return m ? m[1].trim() : null;
};
const claimedPT = (relProto) => {
  const m = (relProto || '').match(/PT-0[1-5]/i);
  return m ? m[0].toUpperCase() : null;
};

function walk(dir) {
  const out = [];
  for (const e of readdirSync(dir)) {
    const p = join(dir, e); const st = statSync(p);
    if (st.isDirectory()) out.push(...walk(p));
    else if (e.endsWith('.charter.md')) out.push(p);
  }
  return out;
}

// classifica uma tela: { page, pt, signals, verdict }
function classifyCharter(charterPath) {
  const fm = readFileSync(charterPath, 'utf8').split(/^---$/m)[1] || '';
  const pt = claimedPT(fmGet(fm, 'related_prototype'));
  if (!pt) return null; // não declara PT → fora do escopo deste gate (é o design-coverage que cobra declaração)
  const comp = fmGet(fm, 'component');
  const tsx = comp ? resolve(ROOT, comp) : charterPath.replace(/\.charter\.md$/, '.tsx');
  if (!existsSync(tsx)) return { page: fmGet(fm, 'page') || charterPath, pt, verdict: 'SEM_TSX' };
  const signals = detectSignals(readFileSync(tsx, 'utf8'));
  const ok = REQUIRED[pt] ? REQUIRED[pt](signals) : true;
  return { page: fmGet(fm, 'page') || charterPath, pt, signals, verdict: ok ? 'CONFORME' : 'MISMATCH' };
}

function runAll() {
  return walk(PAGES).map(classifyCharter).filter(Boolean);
}

// ── selftest ──
if (process.argv.includes('--selftest')) {
  let fails = 0;
  const t = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };
  const s = detectSignals;
  t(REQUIRED['PT-02'](s('const x = useForm({}); <form>')), 'PT-02 ok com useForm/<form>');
  t(!REQUIRED['PT-02'](s('<div><table><tbody/></table></div>')), 'PT-02 MISMATCH quando é table (sem form)');
  t(REQUIRED['PT-05'](s('import {KanbanDndProvider} from "x"; <BoardColumn/>')), 'PT-05 ok com kanban');
  t(!REQUIRED['PT-05'](s('<form><Input/></form>')), 'PT-05 MISMATCH quando é form');
  t(REQUIRED['PT-01'](s('<DataTable/><Pagination/>')), 'PT-01 ok com DataTable');
  t(!REQUIRED['PT-01'](s('const x=useForm()')), 'PT-01 MISMATCH quando é só form');
  t(REQUIRED['PT-04'](s('<KpiGrid><KpiCard/></KpiGrid>')), 'PT-04 ok com KPIs');
  t(claimedPT('n/a (herda PT-05 Kanban; segue o DS)') === 'PT-05', 'extrai PT-05 do related_prototype');
  t(claimedPT('prototipo-ui/cowork/vendas-page.jsx') === null, 'protótipo bespoke → sem PT declarado (fora do escopo)');
  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — declaração de PT é falsificável.');
  process.exit(fails ? 1 : 0);
}

const rows = runAll();
const mismatch = rows.filter((r) => r.verdict === 'MISMATCH');
const conforme = rows.filter((r) => r.verdict === 'CONFORME');

if (process.argv.includes('--json')) {
  console.log(JSON.stringify({ total: rows.length, conforme: conforme.length, mismatch: mismatch.length, rows }, null, 2));
  process.exit(0);
}
if (process.argv.includes('--check')) {
  if (mismatch.length) {
    console.error(`pt-conformance: ${mismatch.length} tela(s) declaram um PT que NÃO têm a assinatura:`);
    for (const r of mismatch) console.error(`  ✗ ${r.page} declara ${r.pt} — sinais: ${JSON.stringify(r.signals)}`);
    console.error(`  → corrija o related_prototype pro PT correto, OU a tela não segue o padrão que declara.`);
    process.exit(1);
  }
  console.log(`pt-conformance: OK — ${conforme.length} declarações de PT conferem com a assinatura (0 mismatch).`);
  process.exit(0);
}

console.log('═══ PT-conformance (declaração de Padrão de Tela × assinatura real) ═══');
console.log(`telas que declaram PT : ${rows.length}  ·  ✅ conforme: ${conforme.length}  ·  ⚠️ mismatch: ${mismatch.length}`);
for (const r of mismatch) console.log(`  ⚠️ ${r.page} declara ${r.pt} mas não tem a assinatura`);
console.log(`\n(v1 heurístico — pega mismatch grosso. Complementa reconcile-triplet.mjs, que faz slot-parity fino do PT-01.)`);
