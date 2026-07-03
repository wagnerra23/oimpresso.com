#!/usr/bin/env node
// @ts-check
/**
 * exposicao-tier0.mjs — Sentinela de cadência (Onda 0c · pilar CADÊNCIA da ADR 0256).
 *
 * =====================================================================================
 * POR QUE EXISTE
 * =====================================================================================
 * A catraca (0b · casos-coverage-guard) IMPEDE regredir; esta sentinela AVISA quando o
 * débito cresce apesar da catraca (ex: telas Tier-0 novas nascendo sem teste de
 * comportamento). É a diferença entre controle manual (a REGRA MESTRE do `num_uf`,
 * R$ inflado ×100k) e controle que sobrevive ao tempo (ADR 0256). Responde a pergunta
 * do Wagner: "como isso sobrevive ao tempo?".
 *
 * Cruza as 3 CAMADAS por tela:
 *   1. EXPOSIÇÃO Tier-0 — dinheiro / estoque / PII / fiscal, por CONTEÚDO (.tsx) + MÓDULO.
 *   2. COBERTURA de comportamento — E2E (path exato em tests/Browser) OU casos_coverage
 *      (UC de <Tela>.casos.md citado por >=1 teste — G-2 rastreabilidade, ADR 0264).
 *      NÃO conta presença nua de charter/casos (proibicoes §gate-de-presença rejeitado).
 *   3. DÉBITO ranqueado — tela QUENTE (exposta) SEM cobertura, peso por categoria
 *      (dinheiro/estoque = 4 · fiscal = 3 · PII = 2 — REGRA MESTRE: valor/estoque no topo).
 *
 * UNIVERSO alinhado ao screen-coverage-map.mjs, porém ESTRITO: exclui _components/,
 * Partials/ E qualquer pasta _* (o protótipo inflava 279 vs 242 telas honestas do
 * inventário — subcomponentes _drawer/*, _Showcase contados como tela). Baseline
 * congelado com ruído = catraca protegendo número errado (0c §1b).
 *
 * REGEXES revisados vs protótipo: PII é ESTRITO (cpf/cnpj/rg/inscrição estadual),
 * NÃO credita "email"/"phone" genéricos (que inflavam PII no protótipo).
 *
 * MODOS (idioma dos guards existentes — casos-coverage-guard / screen-coverage-map):
 *   node scripts/qa/exposicao-tier0.mjs            # relatório read-only (sem efeito)
 *   node scripts/qa/exposicao-tier0.mjs --json     # + grava baseline (uso consciente)
 *   node scripts/qa/exposicao-tier0.mjs --trend    # tendência vs baseline (cron semanal)
 *   node scripts/qa/exposicao-tier0.mjs --check     # exit 1 se o PISO quente regredir
 *
 * BASELINE: memory/governance/exposicao-tier0-baseline.json (o que a catraca lê).
 *
 * Refs: ADR 0256 (catraca/sentinela/gate/cadência) · ADR 0264 (trio + G-2) ·
 *       ADR 0271 (required = só Tier-0; advisory até promoção) · REGRA MESTRE valor/estoque.
 */

import { readFileSync, readdirSync, writeFileSync, existsSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { ucScanRe, ucHeadRe } from '../lib/uc-regex.mjs';

const ROOT = process.cwd();
const PAGES_DIR = join(ROOT, 'resources', 'js', 'Pages');
const BROWSER_DIR = join(ROOT, 'tests', 'Browser');
const BASELINE = join(ROOT, 'memory', 'governance', 'exposicao-tier0-baseline.json');
// Onde um UC pode ser citado por um teste que o defende (mesma lista do casos-guard, ADR 0264).
const TEST_DIRS = ['Modules', 'tests', 'app', 'e2e'].map((d) => join(ROOT, d));
const TEST_EXT = new Set(['.php', '.ts', '.tsx', '.js', '.mjs']);

const flags = new Set(process.argv.slice(2));
const norm = (p) => relative(ROOT, p).split(sep).join('/');

// =====================================================================================
// 1. UNIVERSO DE TELAS (estrito — 242 honestas)
// =====================================================================================
/** Lista recursiva de arquivos sob `dir`. */
function walk(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === 'node_modules' || entry.name === 'vendor' || entry.name === '.git') continue;
      walk(full, acc);
    } else if (entry.isFile()) {
      acc.push(full);
    }
  }
  return acc;
}

/**
 * Uma TELA é `.tsx` roteável sob Pages/**, excluindo (a) `_components/` e `Partials/`
 * (como o screen-coverage-map) e (b) QUALQUER segmento de pasta começando com `_`
 * (`_Showcase`, `_drawer`, …) — o passo que falta no screen-coverage-map e que separa
 * 279 (com ruído) de 242 (honesto). Também exclui `*.charter.tsx` e `*.test.*`.
 */
function isScreen(relTsx) {
  if (!relTsx.endsWith('.tsx')) return false;
  if (relTsx.endsWith('.charter.tsx') || relTsx.includes('.test.')) return false;
  const dirs = relTsx.split('/').slice(0, -1);
  if (dirs.some((d) => d === '_components' || d === 'Partials')) return false;
  if (dirs.some((d) => d.startsWith('_'))) return false;
  return true;
}

const screenFiles = walk(PAGES_DIR)
  .map((abs) => ({ abs, rel: relative(PAGES_DIR, abs).split(sep).join('/') }))
  .filter((s) => isScreen(s.rel));

// =====================================================================================
// 2. EXPOSIÇÃO Tier-0 — categorias (conteúdo + módulo)
// =====================================================================================
// Peso por categoria (REGRA MESTRE: valor/estoque são o topo do risco Tier-0).
const CATEGORY_WEIGHT = { dinheiro: 4, estoque: 4, fiscal: 3, pii: 2 };

// Sinais de CONTEÚDO — casam no corpo do .tsx (regex case-insensitive). Conservador de
// propósito: tokens de DOMÍNIO (não palavras inglesas genéricas como "total"/"value"/"email").
const CATEGORY_CONTENT = {
  dinheiro:
    /\b(final_total|total_before_tax|subtotal|desconto|acr[eé]scimo|comiss[aã]o|num_uf|num_f|parsedecimalptbr|parsecurrency|numberptbr|valor_pago|valor_total|preco_|montante|cobran[cç]a|fatura|\bboleto\b|\bpix\b|forma.{0,3}pagamento|meio.{0,3}pagamento|estorno|reembolso|parcela|r\$)/i,
  estoque:
    /\b(estoque|quantidade|\bqty\b|movimenta[cç][aã]o|reserva.{0,6}estoque|baixa.{0,6}estoque|invent[aá]rio|saldo.{0,6}estoque|opening_stock|current_stock|allow_overselling|almoxarif|romaneio)/i,
  // PII ESTRITO: documentos de pessoa. NÃO credita "email"/"phone"/"nome" (ruído do protótipo).
  pii: /\b(cpf|cnpj|cpf_cnpj|inscricao_estadual|inscri[cç][aã]o.{0,3}estadual|\brg\b|passaporte|\bpis\b|\bnis\b)\b/i,
  fiscal:
    /\b(nfe|nfc-?e|nfce|nfs-?e|nfse|sefaz|\bcfop\b|\bncm\b|\bcst\b|\bicms\b|\bipi\b|\biss\b|danfe|nota.{0,3}fiscal|inutiliz|natureza.{0,3}opera|regime.{0,3}tribut|\bcsosn\b)/i,
};

// Sinais de MÓDULO — a tela HERDA exposição do domínio do módulo mesmo que este .tsx
// específico leia fino (ex: uma subpágina de Sells é dinheiro-Tier-0 por domínio). É a
// direção segura (flag a mais > perder uma quente). O ranking separa confirmado-por-
// -conteúdo de herdado-por-módulo (peso cheio vs meio) pra não afogar o sinal.
const MODULE_TIER0 = {
  Sells: ['dinheiro', 'estoque'],
  Compras: ['dinheiro', 'estoque'],
  Purchase: ['dinheiro', 'estoque'],
  Financeiro: ['dinheiro'],
  RecurringBilling: ['dinheiro'],
  TransactionPayment: ['dinheiro'],
  PaymentGateway: ['dinheiro'],
  NfeBrasil: ['fiscal'],
  Nfse: ['fiscal'],
  Fiscal: ['fiscal'],
  StockAdjustment: ['estoque'],
  StockTransfer: ['estoque'],
  Estoque: ['estoque'],
  Manufacturing: ['estoque'],
  Produto: ['estoque'],
  Cliente: ['pii'],
};

// =====================================================================================
// 3. COBERTURA de comportamento — E2E (path exato) + casos_coverage (UC citado por teste)
// =====================================================================================
// E2E: corpus de tests/Browser (o path Mod/Tela aparece LITERALMENTE — heurística do
// screen-coverage-map, ADR 0249: nada de basename solto que credita 90 telas homônimas).
const browserCorpus = walk(BROWSER_DIR)
  .filter((f) => f.endsWith('.php'))
  .map((f) => readFileSync(f, 'utf8'))
  .join('\n');

function hasE2E(relTsx) {
  const key = relTsx.replace(/\.tsx$/, '');
  return browserCorpus.includes(key) || browserCorpus.includes(key.replace(/\//g, '\\'));
}

// casos_coverage: UCs que uma <Tela>.casos.md declara E que são citados por >=1 teste
// (G-2 rastreabilidade). Construímos UMA VEZ o conjunto de UC-ids citados no corpus de
// teste; um caso "conta" só se o UC dele aparece lá (não presença nua do .casos.md).
function citedUcSet() {
  const cited = new Set();
  for (const dir of TEST_DIRS) {
    for (const f of walk(dir)) {
      const ext = f.slice(f.lastIndexOf('.'));
      if (!TEST_EXT.has(ext)) continue;
      const body = readFileSync(f, 'utf8');
      const m = body.match(ucScanRe());
      if (m) for (const id of m) cited.add(id.toUpperCase());
    }
  }
  return cited;
}
const CITED_UCS = citedUcSet();

/** UCs declarados num .casos.md (heading `UC-XX ...`). */
function ucsDeclaredIn(casosAbs) {
  const out = [];
  for (const line of readFileSync(casosAbs, 'utf8').split(/\r?\n/)) {
    const m = line.match(ucHeadRe());
    if (m) out.push(m[1].toUpperCase());
  }
  return out;
}

/** Uma tela tem cobertura por casos se seu <Tela>.casos.md declara >=1 UC citado por teste. */
function hasCasosCoverage(abs) {
  const casos = abs.replace(/\.tsx$/, '.casos.md');
  if (!existsSync(casos)) return false;
  return ucsDeclaredIn(casos).some((uc) => CITED_UCS.has(uc));
}

// =====================================================================================
// CRUZAMENTO — 1 linha por tela
// =====================================================================================
const rows = screenFiles.map(({ abs, rel }) => {
  const mod = rel.split('/')[0];
  const body = readFileSync(abs, 'utf8');

  const contentCats = Object.keys(CATEGORY_CONTENT).filter((c) => CATEGORY_CONTENT[c].test(body));
  const moduleCats = MODULE_TIER0[mod] || [];
  const cats = [...new Set([...contentCats, ...moduleCats])];

  // Score: categoria confirmada por conteúdo pesa cheio; herdada só por módulo pesa meio.
  let score = 0;
  for (const c of cats) {
    const w = CATEGORY_WEIGHT[c];
    score += contentCats.includes(c) ? w : w / 2;
  }

  const e2e = hasE2E(rel);
  const casos = hasCasosCoverage(abs);
  const covered = e2e || casos;

  return {
    screen: rel,
    module: mod,
    categories: cats,
    content_cats: contentCats,
    hot: cats.length > 0,
    exposure_score: Math.round(score * 10) / 10,
    e2e,
    casos,
    covered,
    debt: cats.length > 0 && !covered ? Math.round(score * 10) / 10 : 0,
  };
});

// =====================================================================================
// AGREGADOS + RANKING
// =====================================================================================
const hot = rows.filter((r) => r.hot);
const hotCovered = hot.filter((r) => r.covered);
const hotDebt = hot.filter((r) => !r.covered);

const byCategory = {};
for (const c of Object.keys(CATEGORY_WEIGHT)) {
  const inCat = hot.filter((r) => r.categories.includes(c));
  byCategory[c] = {
    exposed: inCat.length,
    covered: inCat.filter((r) => r.covered).length,
    debt: inCat.filter((r) => !r.covered).length,
  };
}

const byModule = {};
for (const r of hot) {
  const m = (byModule[r.module] ??= { hot: 0, covered: 0, debt: 0 });
  m.hot++;
  if (r.covered) m.covered++;
  else m.debt++;
}

// Ranking do débito: quente + descoberta, por exposure_score desc, depois nº de categorias.
const debtRanked = hotDebt
  .slice()
  .sort(
    (a, b) =>
      b.exposure_score - a.exposure_score ||
      b.categories.length - a.categories.length ||
      a.screen.localeCompare(b.screen),
  )
  .map((r) => ({ screen: r.screen, module: r.module, categories: r.categories, exposure_score: r.exposure_score }));

const aggregates = {
  universe: rows.length,
  tier0_hot: hot.length,
  hot_covered: hotCovered.length, // ← PISO da catraca Tier-0 (só sobe)
  hot_debt: hotDebt.length,
  by_category: byCategory,
};

// Lista de telas quentes COBERTAS (paths) — pra --check pegar regressão por-tela, não só
// contagem (uma coberta que perde teste + uma nova coberta manteria a contagem e esconderia).
const coveredHotScreens = hotCovered.map((r) => r.screen).sort();

const snapshot = {
  _meta: {
    gate: 'exposicao-tier0 (Onda 0c · sentinela de cadência · ADR 0256/0264/0271)',
    nota: 'baseline da catraca de exposição Tier-0 — NÃO editar à mão. Regravar consciente: node scripts/qa/exposicao-tier0.mjs --json',
    universo: '242 telas honestas (exclui _components/, Partials/, pastas _*)',
    piso: 'hot_covered + covered_hot_screens = piso Tier-0 (só sobe); sentinela reporta hot_debt (tendência)',
    refs: ['ADR 0256', 'ADR 0264', 'ADR 0271'],
  },
  aggregates,
  covered_hot_screens: coveredHotScreens,
  debt_ranked: debtRanked,
  by_module: byModule,
};

// =====================================================================================
// RELATÓRIO (stdout)
// =====================================================================================
const pct = (n, d) => (d ? Math.round((n / d) * 1000) / 10 : 0);

function report() {
  console.log(`\n=== Exposição Tier-0 × cobertura de comportamento · ${aggregates.universe} telas ===\n`);
  console.log(
    `  QUENTES (Tier-0)      : ${aggregates.tier0_hot}/${aggregates.universe}  (${pct(aggregates.tier0_hot, aggregates.universe)}%)`,
  );
  console.log(
    `  ✓ cobertas (piso)     : ${aggregates.hot_covered}/${aggregates.tier0_hot}  (${pct(aggregates.hot_covered, aggregates.tier0_hot)}%)`,
  );
  console.log(
    `  ✗ débito (descoberta) : ${aggregates.hot_debt}/${aggregates.tier0_hot}  (${pct(aggregates.hot_debt, aggregates.tier0_hot)}%)\n`,
  );
  console.log('  Categoria'.padEnd(14) + 'Exposta  Coberta  Débito');
  for (const [c, s] of Object.entries(byCategory)) {
    console.log('  ' + c.padEnd(12) + String(s.exposed).padStart(7) + String(s.covered).padStart(9) + String(s.debt).padStart(8));
  }
  console.log('\n  Top 15 débito (tela quente sem teste de comportamento):');
  for (const r of debtRanked.slice(0, 15)) {
    console.log('   ' + String(r.exposure_score).padStart(4) + '  ' + r.screen + '  [' + r.categories.join(',') + ']');
  }
  console.log('');
}

// =====================================================================================
// MODOS
// =====================================================================================
report();

if (flags.has('--json')) {
  writeFileSync(BASELINE, JSON.stringify(snapshot, null, 2) + '\n');
  console.log(`✓ baseline gravado em ${norm(BASELINE)}\n`);
}

if (flags.has('--trend')) {
  if (!existsSync(BASELINE)) {
    console.error('✗ baseline ausente — rode com --json primeiro.');
    process.exit(2);
  }
  const prev = JSON.parse(readFileSync(BASELINE, 'utf8')).aggregates;
  const dDebt = aggregates.hot_debt - prev.hot_debt;
  const dCovered = aggregates.hot_covered - prev.hot_covered;
  const dHot = aggregates.tier0_hot - prev.tier0_hot;
  const arrow = (n) => (n === 0 ? '→ estável' : n > 0 ? `↑ +${n}` : `↓ ${n}`);
  console.log('=== TENDÊNCIA (vs baseline) ===');
  console.log(`  telas quentes : ${prev.tier0_hot} → ${aggregates.tier0_hot}  (${arrow(dHot)})`);
  console.log(`  piso coberto  : ${prev.hot_covered} → ${aggregates.hot_covered}  (${arrow(dCovered)})`);
  console.log(`  DÉBITO quente : ${prev.hot_debt} → ${aggregates.hot_debt}  (${arrow(dDebt)})`);
  if (dDebt > 0)
    console.log(`\n  ⚠️  o débito Tier-0 CRESCEU (+${dDebt}) — telas quentes novas nascendo sem teste de comportamento.`);
  else if (dDebt < 0) console.log(`\n  ✓ o débito Tier-0 encolheu (${dDebt}). O conjunto quente está sendo coberto.`);
  else console.log(`\n  → débito estável. Sem novas quentes descobertas nem novas coberturas.`);
  // Marcadores estáveis pra o workflow CI parsear a tendência (aviso de débito crescendo).
  console.log(`\nDEBT_DELTA=${dDebt}`);
  console.log(`FLOOR_DELTA=${dCovered}`);
  console.log('');
}

if (flags.has('--check')) {
  if (!existsSync(BASELINE)) {
    console.error('✗ baseline ausente — rode com --json primeiro.');
    process.exit(2);
  }
  const base = JSON.parse(readFileSync(BASELINE, 'utf8'));
  const prevCovered = base.aggregates.hot_covered;
  const prevSet = new Set(base.covered_hot_screens || []);

  // Regressão por-tela: uma tela ANTES coberta que hoje é quente E descoberta.
  const nowUncoveredHot = new Set(hotDebt.map((r) => r.screen));
  const regressedScreens = [...prevSet].filter((s) => nowUncoveredHot.has(s));

  const floorDropped = aggregates.hot_covered < prevCovered;

  if (floorDropped || regressedScreens.length) {
    console.error('✗ CATRACA Tier-0: o conjunto quente REGREDIU. Bloqueado.');
    if (floorDropped) console.error(`   piso coberto: ${prevCovered} → ${aggregates.hot_covered}`);
    for (const s of regressedScreens) console.error(`   perdeu cobertura: ${s}`);
    process.exit(1);
  }
  console.log('✓ CATRACA Tier-0: nenhuma regressão do conjunto quente.\n');
}
