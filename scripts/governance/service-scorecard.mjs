#!/usr/bin/env node
// @ts-check
/**
 * service-scorecard.mjs — SCORECARD de SINAIS-VIVOS por serviço/módulo (estilo Cortex).
 *
 * A DOR (grade Catálogo/IDP 7,5 · memory/sessions/2026-07-21-grade-catalogo-aprendizado-vs-mercado.md,
 * linha "Catálogo/IDP · Backstage+Cortex"): o grafo tipado já saiu (catalog.json, PR #4629), mas
 * faltam DOIS itens nomeados como "atrás" — (a) um SCORECARD de sinais-vivos por serviço (Cortex
 * Service Scorecards) e (b) UI consultável. Este script entrega (a): um painel POR SERVIÇO que
 * AGREGA os sinais que JÁ existem, keyed pelo catálogo (o registro de serviços = catalog.json).
 *
 * ⚠️ AGREGADOR ADVISORY ≠ RÉGUA NOVA (declaração obrigatória — a mesma disciplina do
 * doc-freshness-score.mjs "AGREGADOR ≠ DENTE" e do catalog-graph.mjs "advisory de nascença"):
 *   - NÃO inventa uma nota de qualidade. O dono da nota é `module:grade` (rubrica ADR 0155,
 *     baseline governance/module-grades-baseline.json) — este script REUSA o número, nunca o recalcula.
 *   - NÃO é catraca. É sinal navegável (aponta pros donos). Required = só Tier-0 (ADR 0314/0275);
 *     este é conveniência de catálogo → `--check` pode ficar VERMELHO (drift) sem bloquear merge.
 *   - Os "checks" de maturidade medem PRESENÇA/CONEXÃO de artefato (estilo Backstage/Cortex
 *     maturity: tem SCOPE? tem charter? grafo conectado? tela sem drift?) — NÃO afirmam correção.
 *     Presença ≠ correção (L-24). A correção continua com os DONOS: module-grade (qualidade),
 *     casos-gate (contrato), briefing-code-staleness (frescor do BRIEFING), screen-coverage (tela).
 *
 * FONTES (todas DERIVADAS e commitadas — nada à mão, ADR 0256):
 *   1. memory/governance/catalog.json          → ESPINHA (36 serviços) + sinais de grafo
 *      (delegatesTo/providesApi/ownsTable/governedByAdr/hasComponent) — gerado dos SCOPE.md.
 *   2. governance/module-grades-baseline.json   → sinal QUALIDADE (nota 0-100, key = nome do módulo,
 *      match exato com o catálogo). Dono = module:grade / ADR 0155.
 *   3. memory/governance/vital-signs.json       → sinal TELA (nota_media/charter_pct/casos_pct/stale…),
 *      keyed pelo namespace Inertia (PAGES_NS ≠ nome do módulo em 5 casos) — reusa o mapa de
 *      module-surface.mjs. Dono = mv-metabolismo / screen-coverage.
 *   4. memory/requisitos/<Mod>/BRIEFING.md      → sinal FRESCOR do BRIEFING (git %cs, determinístico).
 *      Dono do VEREDITO de drift = briefing-code-staleness.mjs; aqui é só a data (ponteiro).
 *
 * SINAL AUSENTE por design (honesto, §5 lápide 2026-07-17 custo): CUSTO por-PR (agent-cost-per-pr.mjs)
 * NÃO é atribuível por módulo hoje (é por-PR; a atribuição é gap honesto, não inventar). Fica marcado
 * `cost.module_attributable=false` — surge se/quando existir a aresta PR→módulo.
 *
 * JOIN HONESTO (sem inventar mapeamento): a espinha são os 36 do catálogo. grade casa por nome exato;
 * tela casa por PAGES_NS e, só se falhar, por normalização EXATA (mesmo nome, casing/hífen diferente —
 * TeamMcp ↔ team-mcp; NÃO por similaridade — Crm↔Cliente NÃO casa, fica órfão honesto, §5 LC-08).
 * Módulo sem tela (backend-only, ex. Connector/Brief) → tela = n/a (NÃO falha). Módulo COM dir de Pages
 * mas SEM linha em vital-signs → `unmatched` (gap REAL, reportado). Namespaces de tela que nenhum
 * serviço consumiu (Sells/Produto/Cliente… = core-app) → `orphan_screen_ns`.
 *
 * SEM data volátil no corpo do JSON (igual catalog-graph/module-surface): guarda a DATA de commit
 * (%cs, estável até o arquivo mudar), não "age_days" (wall-clock apodrece). Assim `--json` é
 * byte-determinístico e `--check` só vermelha quando uma FONTE mudou de verdade.
 *
 * Uso (na raiz do repo):
 *   node scripts/governance/service-scorecard.mjs            (dry-run: tabela humana, exit 0)
 *   node scripts/governance/service-scorecard.mjs --json     (imprime o JSON determinístico, não grava)
 *   node scripts/governance/service-scorecard.mjs --write    (grava memory/governance/service-scorecard.json)
 *   node scripts/governance/service-scorecard.mjs --check    (CI advisory: exit 1 se committed ≠ regerado)
 *
 * Refs: ADR 0256 (survival, fonte única gerada) · ADR 0314/0275 (advisory-primeiro) · ADR 0155
 *       (module-grade, dono da qualidade) · grade 2026-07-21 (Catálogo/IDP: "scorecard de sinais-vivos").
 *       Irmãos: catalog-graph.mjs (grafo) · module-surface.mjs (superfície) · doc-freshness-score.mjs.
 */
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { execSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';
import { PAGES_NS } from './module-surface.mjs';

const ROOT = process.cwd();
const args = process.argv.slice(2);
const MODE = args.includes('--write') ? 'write' : args.includes('--check') ? 'check' : args.includes('--json') ? 'json' : 'dry';

const CATALOG_PATH = join(ROOT, 'memory', 'governance', 'catalog.json');
const GRADES_PATH = join(ROOT, 'governance', 'module-grades-baseline.json');
const VITAL_PATH = join(ROOT, 'memory', 'governance', 'vital-signs.json');
const OUT_PATH = join(ROOT, 'memory', 'governance', 'service-scorecard.json');

// ── helpers ──────────────────────────────────────────────────────────────────
/** @param {string} p */
function readJson(p) {
  try { return JSON.parse(readFileSync(p, 'utf8')); }
  catch (e) { console.error(`[service-scorecard] fonte não parseia: ${p} (${/** @type {Error} */ (e).message})`); process.exit(2); }
}
/** Índice normalizado (só a-z0-9) — casa mesmo-nome com casing/hífen diferente. */
const normKey = (s) => String(s).toLowerCase().replace(/[^a-z0-9]/g, '');

/** Data do último commit que tocou um path (%cs, ISO curta) — frescor REAL, não declarado. */
function gitLastDate(relPath) {
  try {
    const out = execSync(`git log -1 --format=%cs -- "${relPath}"`, {
      cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'],
    }).toString().trim();
    return out || null;
  } catch { return null; }
}

// ══ CORE PURO (injetável — testável com inputs sintéticos, sem fs/git) ══════════
/**
 * Agrega sinais de grafo de um serviço a partir das arestas do catálogo.
 * @param {string} moduleId  ex. "module:Admin"
 * @param {Array<{from:string,to:string,type:string}>} edges
 * @param {Set<string>} nodeIds
 */
export function graphSignals(moduleId, edges, nodeIds) {
  const out = { depends_on: 0, dependents: 0, provides_api: 0, owns_tables: 0, owns_legacy_views: 0, consumes_tables: 0, governed_by_adr: 0, components: 0, dangling_edges: 0, connected: false };
  for (const e of edges) {
    if (e.from === moduleId) {
      if (e.type === 'delegatesTo') out.depends_on++;
      else if (e.type === 'providesApi') out.provides_api++;
      else if (e.type === 'ownsTable') out.owns_tables++;
      else if (e.type === 'ownsLegacyView') out.owns_legacy_views++;
      else if (e.type === 'consumesTable') out.consumes_tables++;
      else if (e.type === 'governedByAdr' || e.type === 'charteredByAdr') out.governed_by_adr++;
      else if (e.type === 'hasComponent') out.components++;
      if (!nodeIds.has(e.to)) out.dangling_edges++; // aresta pendurada (alvo inexistente)
    }
    if (e.to === moduleId && e.type === 'delegatesTo') out.dependents++;
  }
  out.connected = out.depends_on + out.dependents + out.provides_api + out.owns_tables + out.governed_by_adr + out.components > 0;
  return out;
}

/**
 * Constrói o documento inteiro do scorecard a partir de dados JÁ carregados + fontes injetadas.
 * PURA: não lê fs nem git; recebe tudo. `deps.hasPagesDir(ns)` e `deps.briefingInfo(mod)` são
 * injetados (o main passa as versões reais; o teste passa stubs).
 * @param {{catalog:any, gradesDoc:any, vitalDoc:any}} src
 * @param {{pagesNs:Record<string,string>, hasPagesDir:(ns:string)=>boolean, scopeExists:(rel:string)=>boolean, briefingInfo:(mod:string)=>{present:boolean,last_commit:string|null}}} deps
 */
export function buildDoc(src, deps) {
  const { catalog, gradesDoc, vitalDoc } = src;
  const { pagesNs, hasPagesDir, scopeExists, briefingInfo } = deps;

  const grades = gradesDoc.modules || {};
  const vitalByNs = new Map();
  for (const m of vitalDoc.modulos || []) vitalByNs.set(m.mod, m);
  const vitalByNorm = new Map();
  for (const k of vitalByNs.keys()) vitalByNorm.set(normKey(k), k);

  const moduleNodes = catalog.nodes.filter((/** @type {any} */ n) => n.type === 'module');
  const nodeIds = new Set(catalog.nodes.map((/** @type {any} */ n) => n.id));
  const consumedNs = new Set();

  function buildService(node) {
    const mod = node.module;
    const ns = pagesNs[mod] || mod;

    // ── qualidade (module-grade — REUSA, não recalcula) ──
    const gradeVal = typeof grades[mod] === 'number' ? grades[mod] : null;

    // ── tela (vital-signs: PAGES_NS direto → normalização EXATA de fallback) ──
    let v = vitalByNs.get(ns);
    let vNs = ns;
    let via = 'direto';
    if (!v) {
      const cand = vitalByNorm.get(normKey(mod));
      if (cand && cand !== ns) { v = vitalByNs.get(cand); vNs = cand; via = 'normalizado'; }
    }
    const hasDir = hasPagesDir(ns);
    let screens;
    let unmatchedScreenDir = false;
    if (v) {
      consumedNs.add(vNs);
      screens = {
        matched: true, ns: vNs, via, telas: v.telas, com_scorecard: v.com_scorecard,
        nota_media: v.nota_media, nota_min: v.nota_min, pior_tela: v.pior_tela,
        charter_pct: v.charter_pct, casos_pct: v.casos_pct, stale: v.stale, idade_max_dias: v.idade_max_dias,
      };
    } else if (hasDir) {
      screens = { matched: false, ns, note: `dir resources/js/Pages/${ns} existe mas sem linha em vital-signs — gap` };
      unmatchedScreenDir = true;
    } else {
      screens = { matched: false, ns, backend_only: true, note: 'sem superfície de tela (backend-only) — n/a, não é falha' };
    }

    const graph = graphSignals(node.id, catalog.edges, nodeIds);
    const briefing = { ...briefingInfo(mod), verdict_owner: 'briefing-code-staleness.mjs' };
    const cost = { module_attributable: false, owner: 'agent-cost-per-pr.mjs', note: 'custo é por-PR; atribuição por módulo é gap honesto (§5 2026-07-17)' };

    // ── checks de MATURIDADE de catálogo (presença/conexão · advisory · não-qualidade) ──
    const checks = [
      { key: 'has_scope', applicable: true, ok: !!node.path && scopeExists(node.path), rot: 'SCOPE.md presente' },
      { key: 'has_charter_adr', applicable: true, ok: !!node.charter_adr, rot: 'charter/ADR declarado' },
      { key: 'graded', applicable: true, ok: gradeVal !== null, rot: 'nota module-grade presente' },
      { key: 'briefing_present', applicable: true, ok: briefing.present, rot: 'BRIEFING.md presente' },
      { key: 'graph_connected', applicable: true, ok: graph.connected, rot: 'conectado no grafo (≥1 aresta)' },
      { key: 'no_dangling_edges', applicable: true, ok: graph.dangling_edges === 0, rot: 'sem aresta pendurada' },
      { key: 'screens_matched', applicable: hasDir || !!v, ok: !!v, rot: 'telas rastreadas em vital-signs' },
      { key: 'charter_full', applicable: !!v, ok: !!v && v.charter_pct === 100, rot: 'charter 100% nas telas' },
      { key: 'no_stale_screens', applicable: !!v, ok: !!v && v.stale === false, rot: 'nenhuma tela stale' },
    ];
    const applic = checks.filter((c) => c.applicable);
    const passed = applic.filter((c) => c.ok).length;
    const ratio = applic.length ? passed / applic.length : 0;
    const level = ratio >= 0.9 ? 'ouro' : ratio >= 0.7 ? 'prata' : 'bronze';

    return {
      service: {
        id: mod,
        trust: node.trust ?? null,
        owner: node.owner ?? null,
        permission_prefix: node.permission_prefix ?? null,
        charter_adr: node.charter_adr ?? null,
        scope: node.path ?? null,
        purpose: node.purpose ?? null,
        signals: {
          grade: gradeVal === null ? null : { value: gradeVal, ruler: gradesDoc.rubric_adr || 'ADR 0155', baseline: gradesDoc.baseline_version || null },
          screens, graph, briefing, cost,
        },
        checks: checks.map(({ applicable, ...c }) => (applicable ? c : { ...c, na: true })),
        maturity: { passed, applicable: applic.length, ratio: Math.round(ratio * 100) / 100, level },
      },
      unmatchedScreenDir,
    };
  }

  const built = moduleNodes.slice().sort((a, b) => a.module.localeCompare(b.module)).map(buildService);
  const services = built.map((b) => b.service);
  const orphanScreenNs = [...vitalByNs.keys()].filter((ns) => !consumedNs.has(ns)).sort();
  const withGrade = services.filter((s) => s.signals.grade !== null).length;
  const unmatchedScreens = built.filter((b) => b.unmatchedScreenDir).map((b) => b.service.id);
  const levels = { ouro: 0, prata: 0, bronze: 0 };
  for (const s of services) levels[s.maturity.level]++;

  return {
    $generator: 'scripts/governance/service-scorecard.mjs',
    $doc: 'Scorecard de sinais-vivos por serviço, AGREGADO das fontes derivadas (catalog.json + module-grades-baseline.json + vital-signs.json + BRIEFING git-mtime). NÃO editar à mão — a próxima geração sobrescreve. Regenerar: node scripts/governance/service-scorecard.mjs --write',
    $advisory: 'Advisory de nascença (ADR 0314/0275) — NÃO é gate required. AGREGADOR, não régua: a nota é do module-grade (ADR 0155); os checks de maturidade medem presença/conexão de catálogo (Backstage/Cortex), não correção.',
    generated_from: {
      catalog: { path: 'memory/governance/catalog.json', stats: catalog.stats?.modules ?? null },
      grades: { path: 'governance/module-grades-baseline.json', baseline: gradesDoc.baseline_version || null, rubric: gradesDoc.rubric_adr || null },
      vital_signs: { path: 'memory/governance/vital-signs.json', generated_at: vitalDoc.generated_at || null },
    },
    stats: {
      services: services.length,
      with_grade: withGrade,
      unmatched_screen_dirs: unmatchedScreens,
      orphan_screen_ns: orphanScreenNs,
      maturity_levels: levels,
    },
    services,
  };
}

// ── wiring real (fs + git) ─────────────────────────────────────────────────────
function buildFromDisk() {
  const catalog = readJson(CATALOG_PATH);
  const gradesDoc = readJson(GRADES_PATH);
  const vitalDoc = readJson(VITAL_PATH);
  const hasPagesDir = (ns) => existsSync(join(ROOT, 'resources', 'js', 'Pages', ns));
  const scopeExists = (rel) => existsSync(join(ROOT, rel));
  const briefingInfo = (mod) => {
    const rel = `memory/requisitos/${mod}/BRIEFING.md`;
    const present = existsSync(join(ROOT, rel));
    return { present, last_commit: present ? gitLastDate(rel) : null };
  };
  return buildDoc({ catalog, gradesDoc, vitalDoc }, { pagesNs: PAGES_NS, hasPagesDir, scopeExists, briefingInfo });
}

// ── saída / modos ──────────────────────────────────────────────────────────────
function printHuman(doc) {
  console.log(`\n🗂️  Service Scorecard — sinais-vivos por serviço (advisory · AGREGADOR)`);
  console.log(`    ${doc.stats.services} serviços · ${doc.stats.with_grade} com nota · maturidade: ` +
    `🥇${doc.stats.maturity_levels.ouro} 🥈${doc.stats.maturity_levels.prata} 🥉${doc.stats.maturity_levels.bronze}\n`);
  const pad = (s, n) => String(s ?? '').padEnd(n).slice(0, n);
  console.log(`    ${pad('SERVIÇO', 20)} ${pad('NOTA', 5)} ${pad('TELAS', 6)} ${pad('CHRT%', 6)} ${pad('STALE', 6)} ${pad('DEP→', 5)} ${pad('API', 4)} ${pad('MATUR', 12)}`);
  console.log(`    ${'-'.repeat(72)}`);
  for (const s of doc.services) {
    const g = s.signals.grade ? s.signals.grade.value : '—';
    const sc = s.signals.screens;
    const telas = sc.matched ? sc.telas : (sc.backend_only ? 'bkd' : '?');
    const chrt = sc.matched ? sc.charter_pct + '' : '—';
    const stale = sc.matched ? (sc.stale ? '⚠️sim' : 'não') : '—';
    const emoji = s.maturity.level === 'ouro' ? '🥇' : s.maturity.level === 'prata' ? '🥈' : '🥉';
    console.log(`    ${pad(s.id, 20)} ${pad(g, 5)} ${pad(telas, 6)} ${pad(chrt, 6)} ${pad(stale, 6)} ${pad(s.signals.graph.depends_on, 5)} ${pad(s.signals.graph.provides_api, 4)} ${pad(emoji + ' ' + s.maturity.passed + '/' + s.maturity.applicable, 12)}`);
  }
  if (doc.stats.unmatched_screen_dirs.length) console.log(`\n    ⚠️  telas sem linha em vital-signs (gap): ${doc.stats.unmatched_screen_dirs.join(', ')}`);
  if (doc.stats.orphan_screen_ns.length) console.log(`    ℹ️  namespaces de tela órfãos (core-app, sem serviço no catálogo): ${doc.stats.orphan_screen_ns.join(', ')}`);
  console.log('');
}

function main() {
  const doc = buildFromDisk();
  const json = JSON.stringify(doc, null, 2) + '\n';
  if (MODE === 'json') { process.stdout.write(json); return; }
  if (MODE === 'write') {
    writeFileSync(OUT_PATH, json, 'utf8');
    console.log(`[service-scorecard] ${doc.stats.services} serviços → memory/governance/service-scorecard.json (gravado)`);
    return;
  }
  if (MODE === 'check') {
    const atual = existsSync(OUT_PATH) ? readFileSync(OUT_PATH, 'utf8') : null;
    if (atual !== json) {
      console.error(`[service-scorecard] DRIFT: memory/governance/service-scorecard.json desatualizado vs as fontes. Rode: node scripts/governance/service-scorecard.mjs --write`);
      process.exit(1);
    }
    console.log(`[service-scorecard] OK (${doc.stats.services} serviços, sem drift)`);
    return;
  }
  printHuman(doc);
}

if (import.meta.url === pathToFileURL(process.argv[1] || '').href) main();
