#!/usr/bin/env node
// anchor-lint.mjs — parser da gramática anchor spec↔código (ADR 0273 · passo SA-A2
// do plano memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md).
//
// POR QUE EXISTE: "a spec mente" (auditoria SDD 2026-06-12). O campo
// `**Implementado em:**` não tinha formato máquina-parseável — sem lint, anchor
// falso/morto/placeholder era indistinguível de anchor verdadeiro. Este script
// implementa EXATAMENTE a gramática do ADR 0273 §1 (sentinelas `_pendente_` e
// `_parcial_` como estados de 1ª classe) e classifica cada US dos SPECs:
//
//   sem_campo      US sem linha `**Implementado em:**`
//   placeholder    legado: _[TODO…]_ · _[path]_ · (a criar…) · pseudo-path _xx_
//   pendente       `_pendente_` — tela não construída é estado LEGÍTIMO (coberta)
//   parcial        `_parcial_` + ≥1 path, todos existentes (coberta, pendência rastreável)
//   anchored_ok    preenchido com ≥1 segmento-path e TODOS os paths existem no disco
//   anchored_dead  preenchido mas path inexistente OU sem nenhum path verificável
//                  (anchor quebrado = mentira detectável — ADR 0273 §2)
//   anchored_zombie ⟵ SA-A2-bis (2026-06-22): path EXISTE no disco mas a Page está
//                  DESLIGADA — renderizada só por controller não-referenciado nas
//                  rotas (dormente / atrás de Route::redirect 301). Existir ≠ estar
//                  vivo. Fecha o ponto-cego que deixou US-FIN-013 (Dashboard/Index,
//                  deprecado 2026-06-06) passar como 🟢. Mentira mais sutil que dead.
//
// anchor_coverage = (anchored_ok + pendente + parcial) / US_total  — por módulo e global.
// zombie NÃO conta como coberta (é mentira, igual dead).
//
// TAMBÉM lint de `**Testado em:**` (SA-A2-bis): superfície antes 100% sem governança
// — os ~13 testes-fantasma do Financeiro (`AutoCriacaoTituloVendaTest` etc) passaram
// anos sem ninguém checar. dead_tests = ref de teste (path .php OU ClassName...Test)
// que não existe no repo.
//
// Uso (na raiz do repo):
//   node scripts/governance/anchor-lint.mjs                 # full-tree, tabela humana
//   node scripts/governance/anchor-lint.mjs --json          # JSON determinístico (sem timestamp/sha)
//   node scripts/governance/anchor-lint.mjs <SPEC.md ...>   # diff-aware: só os SPECs passados
//   node scripts/governance/anchor-lint.mjs --check         # exit 1 se dead>0, zombie>0,
//                                                           # dead_tests>0 ou violação v1 —
//                                                           # RESERVADO pra fase F2 (ADR 0273 §4);
//                                                           # F1 ADVISORY usa modos acima (exit 0 sempre)
//   node scripts/governance/anchor-lint.mjs --junit <summary.json> [--check-verde]
//                                                           # G1b-verde (Phase B): cruza o JUnit
//                                                           # (junit-summary/v1) e marca req_teste_vermelho
//                                                           # (US implementada+coberta cujo arquivo-de-teste
//                                                           # NÃO está verde POR ARQUIVO). --check-verde →
//                                                           # exit 1 se req_teste_vermelho>0; --check-entry
//                                                           # ganha essa 3ª exigência. Sem --junit =
//                                                           # behavior_unknown (advisory, nunca avermelha).
// Node puro (fs). Sem deps, sem DB, sem PHP — o JUnit entra como JSON via flag, NUNCA roda teste.
// Idioma: clone de knowledge-drift.mjs.

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join, dirname, resolve, relative } from 'node:path';

const ROOT = process.cwd();
const REQ = join(ROOT, 'memory', 'requisitos');
const JSON_OUT = process.argv.includes('--json');
const CHECK = process.argv.includes('--check');
// G1a (ADR 0303 emenda): --check-covers exit 1 se houver `testado_sem_covers` —
// teste que EXISTE mas não declara `// @covers-us <US-ID>` da US-pai (a brecha do
// `Testado em: \`SpatiePermissionsTest\``: teste genérico que não prova nada sobre a US).
// ADVISORY em produção (anchor-drift roda --check, NÃO --check-covers); flag opt-in
// pro gate-selftest provar que morde + arming futuro por calendário (ADR 0275).
const CHECK_COVERS = process.argv.includes('--check-covers');
// G1b-entry: --check-entry exit 1 se houver req_sem_aceite OU req_sem_covering_test —
// uma US que se diz IMPLEMENTADA (anchored_ok/parcial) SEM DoD/aceite definido OU SEM
// teste que declare @covers-us dela. É a "regra de entrada" (Wagner: "não pode ser feito
// e refeito por cada pessoa"): regra nova não nasce pronta sem aceite + teste. ADVISORY em
// produção (--check normal não inclui); opt-in pro fixture + arming (ADR 0275, com baseline
// grandfather do legado no flip).
const CHECK_ENTRY = process.argv.includes('--check-entry');
// G1b-verde (Phase B · "âncora improvada" design §1b · re-eval prioridade #1): --check-verde
// exit 1 se houver req_teste_vermelho — uma US que se diz IMPLEMENTADA (anchored_ok/parcial) E
// TEM teste-que-cobre declarado (@covers-us), mas o ARQUIVO desse teste NÃO está verde no JUnit.
// "verde POR ARQUIVO" = passed>0 E failed=0 E errors=0 (junit-summary/v1, agrega por arquivo).
// REGRA DURA: skipped != passed (defesa contra markTestSkipped — 34/45 testes fiscais pulam no
// lane sqlite; verde lá MENTE ×150 clientes). vermelho/skipped/ausente NÃO contam como verde.
// O JUnit entra via flag (lê JSON que o CI já produz via scripts/tests/junit-summary.mjs) — NUNCA
// roda PHP/DB/teste no lint (fs-puro, invariante ADR 0303). Sem --junit → behavior_unknown
// (advisory, nunca avermelha legado). exit 1 só com --check-verde OU --check-entry (que ganha a 3ª
// exigência). ADVISORY em produção (anchor-drift roda --check); arming por calendário (ADR 0275).
const CHECK_VERDE = process.argv.includes('--check-verde');
// G1c (item b · proposta 2026-06-24): --check-lane exit 1 se houver req_sem_lane — US com
// teste-que-cobre FORA das lanes de JUnit (de onde o gate verde lê) → o verde é estruturalmente
// IMPOSSÍVEL (cobertura de fachada). ADVISORY: anchor-drift roda --check normal; opt-in pro
// gate-selftest + arming futuro (ADR 0275). NÃO grandfatherado (gate à parte, igual o verde).
const CHECK_LANE = process.argv.includes('--check-lane');
const _rawArgv = process.argv.slice(2);
const _junitIdx = _rawArgv.indexOf('--junit');
const JUNIT_PATH = _junitIdx !== -1 ? _rawArgv[_junitIdx + 1] : null;

// loadJunit: lê o summary JSON (schema junit-summary/v1, scripts/tests/junit-summary.mjs) e
// indexa files[] por path-relativo (forward-slash). fs-puro: só JSON.parse, zero exec de teste.
//
// V6-A RESILIÊNCIA (avaliação SDD 2026-07-12 risco #2 · "a suite mente"): --junit
// ausente / 0-byte / não-JSON / schema errado / marcador de run inválido (invalid:true,
// ex. `fullsuite-summary-invalid/v1` que junit-summary.mjs grava pro run morto) /
// INCOERENTE (coherent:false, shard parcial) NÃO faz mais crash (exit 2) NEM avermelha —
// degrada a behavior_unknown (advisory). POR QUÊ: o gate verde (G1b) arma sobre um JUnit que
// a materialização sharded (chip harness) pode entregar 0-byte/parcial; um run morto/parcial
// NÃO pode avermelhar US cujos testes passaram noutro shard. run inválido = "não sei", nunca
// "vermelho". Retorna sentinela {unknown:true, reason} (→ JUNIT null → behavior_unknown) em vez
// de sair. Só junit-summary/* COERENTE vira mapa usável. fs-puro (só JSON.parse), invariante ADR 0303.
function loadJunit(p) {
  const abs = resolve(ROOT, p);
  const unknown = (reason) => {
    console.error(`anchor-lint: --junit ${reason} (${p}) → behavior_unknown (advisory · não avermelha, não crasha)`);
    return { unknown: true, reason };
  };
  if (!existsSync(abs)) return unknown('arquivo inexistente');
  let raw;
  try { raw = readFileSync(abs, 'utf8'); } catch (e) { return unknown(`ilegível: ${e.message}`); }
  if (!raw.trim()) return unknown('vazio/0-byte (run morto?)');
  let data;
  try { data = JSON.parse(raw); } catch (e) { return unknown(`não é JSON válido: ${e.message}`); }
  if (!data || typeof data !== 'object') return unknown('JSON não-objeto');
  if (data.invalid === true) return unknown(`marcador de run inválido (${data.schema || 'schema?'}${data.reason ? ` · ${data.reason}` : ''})`);
  if (typeof data.schema !== 'string' || !data.schema.startsWith('junit-summary/')) return unknown(`schema não junit-summary/* (${data.schema || 'ausente'}) — gere com scripts/tests/junit-summary.mjs`);
  if (data.coherent === false) return unknown('run INCOERENTE (coherent:false — shard parcial? verde POR ARQUIVO seria mentira)');
  const map = new Map();
  for (const f of (Array.isArray(data.files) ? data.files : [])) if (f && f.file) map.set(String(f.file).replace(/\\/g, '/'), f);
  return { map, schema: data.schema, source: data.source || p, coherent: data.coherent };
}
const JUNIT_RAW = JUNIT_PATH ? loadJunit(JUNIT_PATH) : null;
// sentinela unknown (--junit inválido/incoerente/ausente) NÃO vira JUnit usável → behavior_unknown.
const JUNIT = JUNIT_RAW && !JUNIT_RAW.unknown ? JUNIT_RAW : null;
const JUNIT_UNKNOWN_REASON = JUNIT_RAW && JUNIT_RAW.unknown ? JUNIT_RAW.reason : null;
const junitFiles = JUNIT ? JUNIT.map : null;
// status de um arquivo-de-teste no JUnit: verde só se passou de fato (≥1 passed, 0 fail/error).
// ausente = não rodou nesse lane · skipped = só pulou (markTestSkipped) · vazio = 0 testcases.
function junitStatus(rel) {
  if (!junitFiles) return 'unknown';
  const f = junitFiles.get(rel);
  if (!f) return 'ausente';
  if ((f.failed || 0) > 0 || (f.errors || 0) > 0) return 'vermelho';
  if ((f.passed || 0) > 0) return 'verde';
  if ((f.skipped || 0) > 0) return 'skipped';
  return 'vazio';
}

// SA-A2-ter ARMING (ADR 0275 calendário + ADR 0303): --baseline <path> grandfathera o
// DÉBITO LEGADO (US legadas anchored_ok/parcial sem aceite/teste-que-cobre + covers legados)
// pra o gate morder só mentira NOVA — no-new-lie, igual o anchored_dead já faz por diff-aware.
// Sem --baseline = comportamento IDÊNTICO ao anterior (advisory zero-regressão). fs-puro (1 JSON).
// --emit-baseline imprime o baseline da dívida ATUAL (determinístico, regenerável → ratchet só-desce).
// (Grandfathera entry-aceite/entry-teste/covers; req_teste_vermelho do --junit NÃO entra — verde é
// gate à parte, dormente sem --junit; grandfather de verde é trabalho futuro acoplado ao lane JUnit.)
const BASELINE_PATH = (() => { const i = process.argv.indexOf('--baseline'); return i >= 0 ? process.argv[i + 1] : null; })();
const EMIT_BASELINE = process.argv.includes('--emit-baseline');
// chaves canônicas de violação (US-ID é único global — memory-health Check N · ADR 0304):
const keyAceite = (us) => `entry-aceite:${us}`;
const keyTeste = (us) => `entry-teste:${us}`;
const keyCovers = (us, testSeg) => `covers:${us}:${String(testSeg).split('/').pop().replace(/\.php$/, '')}`;

// ── regexes canônicas (ADR 0273 §1 — referência única; NÃO afrouxar sem novo ADR) ──
const GRAMMAR_RE = /^\*\*Implementado em:\*\* (?:_pendente_(?: — .+)?|(?:_parcial_ · )?(?:`[^`]+`)(?: · `[^`]+`)* · verificado@[0-9a-f]{7} \(\d{4}-\d{2}-\d{2}\)(?: — .+)?)$/;
// detecção LENIENTE de campo (legados usam `> ` blockquote — Vestuario — e espaçamento vário)
const FIELD_RE = /^(?:>\s*)?\*\*Implementado em:\*\*\s*(.*)$/;
const TESTADO_RE = /^(?:>\s*)?\*\*Testado em:\*\*\s*(.*)$/;
// G1b-entry (gate de entrada · regra de aceite): marcadores de DoD/aceite reais nos SPECs
// (medido em main: **DoD:** 168× · **Definition of Done:** 63× · **Aceite:** 16×).
const DOD_RE = /^(?:>\s*)?\*\*(?:Definition of Done|DoD|Aceite|Crit[ée]rios? de [Aa]ceite|Acceptance [Cc]riteria(?: do epic)?)\s*:\*\*/;
const US_HEAD_RE = /^(#{2,4})\s+.*\bUS-[A-Z][A-Za-z0-9]*-\d/;
const US_ID_RE = /US-[A-Z][A-Za-z0-9]*-\d+(?:\.\.\d+)?/;
const HEAD_RE = /^(#{1,6})\s/;
// taxonomia de placeholder legado (ADR 0273 "Contexto") — pendente/parcial têm precedência
const PLACEHOLDER_RE = /TODO|_\[path\]_|\ba criar\b|_xx_/i;
const MDLINK_RE = /\[`([^`]+)`\]\(([^)]+)\)/g; // [`seg`](alvo) — alvo relativo ao SPEC
const ANCHOR_FORMAT_V1_RE = /^anchor_format:\s*["']?v1["']?\s*$/m;

// ── SA-A2-bis (2026-06-22): "wired ≠ só existe no disco" + lint de Testado em ──
// POR QUE: existsSync sozinho deixou passar âncora ZUMBI (US-FIN-013 apontava
// Dashboard/Index.tsx, dormente + 301→/unificado desde 2026-06-06; o lint dava 🟢).
// A verdade do "está vivo" é o ROTEADOR: uma Page só é VIVA se um controller
// REFERENCIADO nas rotas (use/::class — comentário não conta) a renderiza via
// Inertia::render. Determinístico, fs-puro, sem PHP/DB.

const _graphCache = new Map();
function listPhp(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) listPhp(p, acc);
    else if (e.name.endsWith('.php')) acc.push(p);
  }
  return acc;
}
// grafo de render por módulo: { allRendered:Set, liveRendered:Set } ou null (indeterminável)
function renderGraph(mod) {
  if (_graphCache.has(mod)) return _graphCache.get(mod);
  const modDir = join(ROOT, 'Modules', mod);
  const ctrlDir = join(modDir, 'Http', 'Controllers');
  if (!existsSync(ctrlDir)) { _graphCache.set(mod, null); return null; }
  // rotas podem viver em Routes/, routes/, Http/routes.php, Http/Routes/ — varre
  // qualquer .php cujo path tenha um segmento `route(s)` (exclui *RoutesTest.php,
  // pois "Routes" ali não vem após '/' nem início). Anti-falso-positivo: módulo
  // que registra rota fora desses lugares ficaria over-flagado.
  let routeTxt = '';
  for (const f of listPhp(modDir)) {
    const rel = f.slice(modDir.length).replace(/\\/g, '/');
    if (/(^|\/)routes?(\/|\.php$)/i.test(rel)) routeTxt += readFileSync(f, 'utf8') + '\n';
  }
  // controllers VIVOS = referenciados nas rotas por use/::class OU string-syntax
  // ('XController@metodo' — UltimatePOS/nWidart legado). Comentário (// DashboardController)
  // NÃO casa nenhum dos três → não vira vivo (mantém o zumbi real detectável).
  const live = new Set();
  for (const m of routeTxt.matchAll(/use\s+[\w\\]+\\([A-Za-z0-9_]+Controller)\s*;/g)) live.add(m[1]);
  for (const m of routeTxt.matchAll(/([A-Za-z0-9_]+Controller)::class/g)) live.add(m[1]);
  for (const m of routeTxt.matchAll(/['"][\w\\]*?([A-Za-z0-9_]+Controller)(?:@\w+)?['"]/g)) live.add(m[1]);
  const allRendered = new Set(), liveRendered = new Set();
  for (const f of listPhp(ctrlDir)) {
    const base = f.split(/[\\/]/).pop().replace(/\.php$/, '');
    const txt = readFileSync(f, 'utf8');
    for (const m of txt.matchAll(/Inertia::render\(\s*['"]([^'"]+)['"]/g)) {
      allRendered.add(m[1]);
      if (live.has(base)) liveRendered.add(m[1]);
    }
  }
  const g = { allRendered, liveRendered };
  _graphCache.set(mod, g);
  return g;
}
// uma Page-âncora é ZUMBI: existe no disco, é renderizada por ALGUM controller,
// mas por NENHUM controller vivo (rendered-but-only-via-dead/redirect path).
// Conservador: sub-componentes (_components/, components/) e renders por variável
// (não-literais → não estão em allRendered) NUNCA são marcados (evita falso-positivo).
function pageZombie(seg) {
  const m = seg.match(/^resources\/js\/Pages\/(.+)\.tsx$/);
  if (!m || /\/_?components\//.test(seg)) return false;
  const comp = m[1];
  const g = renderGraph(comp.split('/')[0]);
  if (!g) return false;
  return g.allRendered.has(comp) && !g.liveRendered.has(comp);
}
// ── 4º veredito ADVISORY `servido` (2026-07-09 · grade v3 "verificação runtime") ──
// POR QUE: dead/zombie/wired são ESTÁTICOS (disco + roteador). Faltava o eixo
// RUNTIME (régua Coverband/Wallarm): "existe + roteado mas 0 hits em Nd" é a
// mentira que o estático não vê. Fonte: governance/route-hits.json — ledger de
// execução real gerado por `php artisan route-hits:export --write` no host de
// prod (coleta: middleware ContadorHitsRota, flag ROUTE_HITS_ENABLED). fs-puro
// (1 JSON), zero PII (só componente Inertia + hits + data).
// ADVISORY DE NASCENÇA: NÃO entra em coverage, NÃO entra em --check/--check-*,
// NÃO muda flag 🟢/🟡/🔴. Sem ledger (ou ledger sem pages — coleta ainda OFF)
// → sem_ledger: NADA é marcado (zero regressão de output pra quem não tem o
// arquivo). hit ≠ funciona — é prova de USO, não de correção.
const HITS_PATH = join(ROOT, 'governance', 'route-hits.json');
const HITS = (() => {
  if (!existsSync(HITS_PATH)) return null;
  try {
    const d = JSON.parse(readFileSync(HITS_PATH, 'utf8'));
    const pages = d && d.pages && typeof d.pages === 'object' ? d.pages : {};
    return Object.keys(pages).length ? { pages, janela: d.janela_dias ?? null } : null;
  } catch { return null; } // ledger corrompido = sem sinal (advisory nunca derruba o lint)
})();
// mesmo recorte do pageZombie: só Pages de 1ª classe (sub-componentes nunca)
function pageHitKey(seg) {
  const s = String(seg).replace(/\\/g, '/');
  const m = s.match(/^resources\/js\/Pages\/(.+)\.tsx$/);
  return m && !/\/_?components\//.test(s) ? m[1] : null;
}

let _testBasenames = null;
// Map basename(sem .php) → 1º path absoluto. `.has()` segue valendo pro deadTestRefs;
// o path serve pro covers-check (G1a) ler o arquivo do teste e procurar @covers-us.
function testBasenames() {
  if (_testBasenames) return _testBasenames;
  _testBasenames = new Map();
  const modsDir = join(ROOT, 'Modules');
  if (existsSync(modsDir)) {
    for (const e of readdirSync(modsDir, { withFileTypes: true })) {
      if (!e.isDirectory()) continue;
      for (const f of listPhp(join(modsDir, e.name, 'Tests'))) {
        const base = f.split(/[\\/]/).pop().replace(/\.php$/, '');
        if (!_testBasenames.has(base)) _testBasenames.set(base, f);
      }
    }
  }
  return _testBasenames;
}
// G1c (item b · proposta 2026-06-24): "lane de JUnit" = de onde o gate verde (G1b) lê o status —
// os produtores de junit-summary: ci.yml (.github/ci-sqlite-pest.list) + financeiro/jana/nfebrasil-pest
// (Modules/<X>/Tests). Um teste-que-cobre FORA dessas lanes NUNCA pode ficar verde → req_sem_lane
// (o @covers-us existe mas o verde é estruturalmente impossível — fachada). fs-puro (1 lista + dirs).
let _laneEntries = null;
function laneEntries() {
  if (_laneEntries) return _laneEntries;
  _laneEntries = [];
  const list = join(ROOT, '.github', 'ci-sqlite-pest.list');
  if (existsSync(list)) for (const ln of readFileSync(list, 'utf8').split(/\r?\n/)) {
    const e = ln.trim().replace(/\/+$/, '');
    if (e && !e.startsWith('#')) _laneEntries.push(e);
  }
  return _laneEntries;
}
// lanes de módulo que PRODUZEM junit-summary (financeiro/jana/nfebrasil-pest.yml). Conservador: o
// resto (modules-pest etc.) NÃO alimenta o verde gate hoje; ci-sqlite-pest.list cobre o avulso.
const JUNIT_MODULE_LANES = ['Modules/Financeiro/Tests', 'Modules/Jana/Tests', 'Modules/NfeBrasil/Tests'];
function inLane(rel) {
  const r = String(rel).replace(/\\/g, '/');
  if (JUNIT_MODULE_LANES.some((d) => r === d || r.startsWith(`${d}/`))) return true;
  return laneEntries().some((e) => r === e || r.startsWith(`${e}/`));
}

// refs de teste mortas numa linha `**Testado em:**` (path .php inexistente OU
// ClassName…Test sem arquivo correspondente). _lacuna_ em itálico (sem backtick) = ignorado.
function deadTestRefs(rest, specDir) {
  const out = [];
  let remaining = rest;
  for (const m of rest.matchAll(MDLINK_RE)) {
    const t = m[2].split('#')[0];
    if (!/^https?:/.test(t) && (m[1].includes('/') || t.includes('/')) && !existsSync(resolve(specDir, t))) out.push(m[1]);
    remaining = remaining.replace(m[0], ' ');
  }
  for (const m of remaining.matchAll(/`([^`]+)`/g)) {
    const seg = m[1].replace(/[.,;:]+$/, '');
    if (seg.includes('/')) {
      // path-like: vale .php OU basename ...Test (sem sufixo) — antes só .php escapava
      // (falso-negativo: `Modules/.../AuditLogMutacoesTest` passava batido)
      const base = seg.split('/').pop();
      if ((seg.endsWith('.php') || /Test$/.test(base)) && !existsSync(resolve(ROOT, seg)) && !existsSync(resolve(ROOT, `${seg}.php`))) out.push(seg);
    } else if (/Test$/.test(seg) && !testBasenames().has(seg)) out.push(seg);
  }
  return out;
}

// G1a (ADR 0303 emenda): refs de teste numa linha `**Testado em:**` que EXISTEM mas
// NÃO declaram `// @covers-us <usId>` da US-pai. "covers" = marcador grep no .php
// (Pest covers()/comentário), NÃO atributo PHP — testes do repo são closures Pest
// (`uses(Tests\\TestCase::class)` + `it()`), e atributo PHP não anexa a closure. Só
// checa refs RESOLVÍVEIS a arquivo (markdown-link · backtick-path · basename…Test);
// ref inexistente já é dead_test (concern separado). usId nulo (linha fora de US) = skip.
function testadoCoversMissing(rest, specDir, usId) {
  if (!usId) return [];
  const refs = []; // {seg, abs} — só os que existem no disco
  let remaining = rest;
  for (const m of rest.matchAll(MDLINK_RE)) {
    const t = m[2].split('#')[0];
    if (!/^https?:/.test(t) && (m[1].includes('/') || t.includes('/'))) {
      const abs = resolve(specDir, t);
      if (existsSync(abs)) refs.push({ seg: m[1], abs });
    }
    remaining = remaining.replace(m[0], ' ');
  }
  for (const m of remaining.matchAll(/`([^`]+)`/g)) {
    const seg = m[1].replace(/[.,;:]+$/, '');
    if (seg.includes('/')) {
      const abs = [resolve(ROOT, seg), resolve(ROOT, `${seg}.php`)].find((p) => existsSync(p));
      if (abs) refs.push({ seg, abs });
    } else if (/Test$/.test(seg)) {
      const abs = testBasenames().get(seg);
      if (abs) refs.push({ seg, abs });
    }
  }
  const esc = usId.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const coversRe = new RegExp(`@covers-us\\s+${esc}(?:\\b|$)`);
  return refs.filter((r) => !coversRe.test(readFileSync(r.abs, 'utf8'))).map((r) => r.seg);
}

// G1b-entry/verde: índice de cobertura deste SPEC. coveredUs = todo `@covers-us US-X` achado
// nos arquivos de teste citados em `**Testado em:**` (resolvidos a path existente). usFiles =
// US-ID → Set<path-relativo-à-raiz> dos arquivos que a declaram cobrir (a chave usada pra cruzar
// com o JUnit em verde-por-arquivo). Independe de ONDE o `Testado em:` mora (bloco US ou regra
// R-NFE) — a verdade é o marcador no teste. Resolve a estrutura US↔regra do NfeBrasil sem FP.
function collectCoversIndex(testadoLines, specDir) {
  const coveredUs = new Set();
  const usFiles = new Map(); // US-ID → Set<rel>
  const seenFiles = new Set();
  const addFromFile = (abs) => {
    if (seenFiles.has(abs)) return;
    seenFiles.add(abs);
    const rel = relative(ROOT, abs).replace(/\\/g, '/');
    for (const m of readFileSync(abs, 'utf8').matchAll(/@covers-us\s+(US-[A-Z][A-Za-z0-9]*-\d+)/g)) {
      coveredUs.add(m[1]);
      if (!usFiles.has(m[1])) usFiles.set(m[1], new Set());
      usFiles.get(m[1]).add(rel);
    }
  };
  for (const t of testadoLines) {
    let remaining = t.rest;
    for (const m of t.rest.matchAll(MDLINK_RE)) {
      const target = m[2].split('#')[0];
      if (!/^https?:/.test(target) && (m[1].includes('/') || target.includes('/'))) {
        const abs = resolve(specDir, target);
        if (existsSync(abs)) addFromFile(abs);
      }
      remaining = remaining.replace(m[0], ' ');
    }
    for (const m of remaining.matchAll(/`([^`]+)`/g)) {
      const seg = m[1].replace(/[.,;:]+$/, '');
      if (seg.includes('/')) {
        const abs = [resolve(ROOT, seg), resolve(ROOT, `${seg}.php`)].find((p) => existsSync(p));
        if (abs) addFromFile(abs);
      } else if (/Test$/.test(seg)) {
        const abs = testBasenames().get(seg);
        if (abs) addFromFile(abs);
      }
    }
  }
  return { coveredUs, usFiles };
}

function frontmatter(txt) {
  if (!txt.startsWith('---')) return '';
  const end = txt.indexOf('\n---', 3);
  return end === -1 ? '' : txt.slice(0, end);
}

// extrai segmentos-path verificáveis do resto do campo; devolve {paths:[{seg,abs}],…}
function extractPaths(rest, specDir) {
  const paths = [];
  let remaining = rest;
  for (const m of rest.matchAll(MDLINK_RE)) {
    const target = m[2].split('#')[0];
    if (/^https?:/.test(target)) continue;
    if (m[1].includes('/') || target.includes('/')) {
      paths.push({ seg: m[1], abs: resolve(specDir, target) });
      remaining = remaining.replace(m[0], ' ');
    }
  }
  for (const m of remaining.matchAll(/`([^`]+)`/g)) {
    const seg = m[1].replace(/[.,;:]+$/, '');
    // segmento-path = contém '/' E é relativo à raiz do repo (ADR 0273 §1);
    // `/rota` (URL) e `~/...` (home) não são verificáveis → tratados como símbolo
    if (seg.includes('/') && !seg.startsWith('/') && !seg.startsWith('~')) paths.push({ seg, abs: resolve(ROOT, seg) });
  }
  return paths;
}

function classify(rest, specDir) {
  if (rest.startsWith('_pendente_')) return { state: 'pendente', dead: [], zombie: [], pages: [] };
  const parcial = rest.startsWith('_parcial_');
  if (!parcial && PLACEHOLDER_RE.test(rest)) return { state: 'placeholder', dead: [], zombie: [], pages: [] };
  const paths = extractPaths(rest, specDir);
  const dead = paths.filter((p) => !existsSync(p.abs)).map((p) => p.seg);
  if (!paths.length) return { state: 'anchored_dead', dead: ['(nenhum segmento-path — preenchido/parcial exige ≥1 path, ADR 0273 §1)'], zombie: [], pages: [] };
  if (dead.length) return { state: 'anchored_dead', dead, zombie: [], pages: [] };
  const zombie = paths.filter((p) => pageZombie(p.seg)).map((p) => p.seg);
  if (zombie.length) return { state: 'anchored_zombie', dead: [], zombie, pages: [] };
  // pages de 1ª classe da âncora — insumo do veredito advisory `servido`
  const pages = paths.map((p) => pageHitKey(p.seg)).filter(Boolean);
  return { state: parcial ? 'parcial' : 'anchored_ok', dead: [], zombie: [], pages };
}

function lintSpec(file) {
  const txt = readFileSync(file, 'utf8');
  const specDir = dirname(file);
  const isV1 = ANCHOR_FORMAT_V1_RE.test(frontmatter(txt));
  const lines = txt.split('\n');
  const usList = []; // {id, line, level, fields:[{line, raw, rest}]}
  const orphans = [];
  const testadoLines = []; // {line, rest}
  let cur = null;
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i].trimEnd();
    const head = line.match(HEAD_RE);
    if (head) {
      if (US_HEAD_RE.test(line)) {
        cur = { id: (line.match(US_ID_RE) || ['US-?'])[0], line: i + 1, level: head[1].length, fields: [] };
        usList.push(cur);
      } else if (cur && head[1].length <= cur.level) cur = null;
      continue;
    }
    const f = line.match(FIELD_RE);
    if (f) { (cur ? cur.fields : orphans).push({ line: i + 1, raw: line, rest: f[1] }); continue; }
    const t = line.match(TESTADO_RE);
    if (t) { testadoLines.push({ line: i + 1, rest: t[1], us: cur ? cur.id : null }); continue; }
    if (cur && DOD_RE.test(line)) cur.hasDod = true; // gate de entrada: aceite definido no bloco
  }
  const counts = { sem_campo: 0, placeholder: 0, pendente: 0, parcial: 0, anchored_ok: 0, anchored_dead: 0, anchored_zombie: 0 };
  const deadList = [], zombieList = [], v1Violations = [];
  let fieldsTotal = 0, fieldsPlaceholder = 0, grammarOk = 0;
  const everyField = [...usList.flatMap((u) => u.fields), ...orphans];
  for (const f of everyField) {
    fieldsTotal++;
    if (GRAMMAR_RE.test(f.raw)) grammarOk++;
    else if (isV1) v1Violations.push({ line: f.line, raw: f.raw.slice(0, 120) });
    const c = classify(f.rest, specDir);
    if (c.state === 'placeholder') fieldsPlaceholder++;
    f.state = c.state; f.dead = c.dead; f.zombie = c.zombie; f.pages = c.pages;
  }
  const naoServido = []; // advisory `servido` — só quando HITS carregado
  let servidoCount = 0;
  for (const u of usList) {
    if (!u.fields.length) { counts.sem_campo++; continue; }
    const c = u.fields[0]; // 1 linha por US (gramática); extras contam em fields_total
    counts[c.state]++;
    if (c.state === 'anchored_dead') deadList.push({ us: u.id, line: c.line, missing: c.dead });
    if (c.state === 'anchored_zombie') zombieList.push({ us: u.id, line: c.line, dead_screens: c.zombie });
    // 4º veredito ADVISORY `servido`: US wired (ok/parcial) ancorada em Page —
    // teve hit real na janela do ledger? 0 hits = wired-porém-não-servido
    // ("existe + roteado mas 0 hits em Nd" — o que zombie/dead não veem).
    if (HITS && (c.state === 'anchored_ok' || c.state === 'parcial') && c.pages.length) {
      const comHit = c.pages.filter((k) => HITS.pages[k] && HITS.pages[k].hits > 0);
      if (comHit.length) servidoCount++;
      else naoServido.push({ us: u.id, line: c.line, pages: c.pages });
    }
  }
  // lint de `Testado em:` — superfície antes sem governança (testes-fantasma)
  const deadTests = [];
  const testadoSemCovers = []; // G1a: teste existe mas não declara @covers-us da US-pai
  for (const t of testadoLines) {
    const refs = deadTestRefs(t.rest, specDir);
    if (refs.length) deadTests.push({ line: t.line, missing: refs });
    const noCovers = testadoCoversMissing(t.rest, specDir, t.us);
    if (noCovers.length) testadoSemCovers.push({ line: t.line, us: t.us, tests: noCovers });
  }
  // G1b-entry (gate de entrada): US que se diz IMPLEMENTADA precisa de aceite + teste-que-cobre
  // G1b-verde (Phase B): ...e esse teste-que-cobre tem que estar VERDE no JUnit (se --junit dado).
  const { coveredUs, usFiles } = collectCoversIndex(testadoLines, specDir);
  const reqSemAceite = [], reqSemTeste = [], reqTesteVermelho = [], reqSemLane = [];
  for (const u of usList) {
    const st = u.fields[0] && u.fields[0].state;
    if (st !== 'anchored_ok' && st !== 'parcial') continue; // _pendente_/dead/zombie/sem_campo não entram
    if (!u.hasDod) reqSemAceite.push({ us: u.id, line: u.line });
    if (!coveredUs.has(u.id)) { reqSemTeste.push({ us: u.id, line: u.line }); continue; } // sem teste = G1b-entry, não verde
    // G1c (item b): tem teste-que-cobre, mas NENHUM numa lane de JUnit → verde estruturalmente impossível
    const coverFiles = [...usFiles.get(u.id)];
    const inLaneCovers = coverFiles.filter((rel) => inLane(rel));
    if (!inLaneCovers.length) reqSemLane.push({ us: u.id, line: u.line, tests: coverFiles });
    // V6-C (avaliação SDD 2026-07-12 · risco #2): o verde-por-arquivo só JULGA covering tests DENTRO de uma
    // lane de JUnit. US inteiramente FORA de lane (nightly-only / shard não-materializado neste run) =
    // req_sem_lane → behavior_unknown, NUNCA req_teste_vermelho: o teste que estruturalmente não pode
    // aparecer no junit do PR ficaria `ausente` → false-red (as ~42 US req_sem_lane hoje). Só avalia vermelho
    // quando há ≥1 covering test in-lane (aí `ausente`/skipped in-lane É vermelho legítimo: devia ter rodado).
    if (junitFiles && inLaneCovers.length) {
      const tests = inLaneCovers.map((rel) => ({ file: rel, status: junitStatus(rel) }));
      if (!tests.some((t) => t.status === 'verde')) reqTesteVermelho.push({ us: u.id, line: u.line, tests });
    }
  }
  const usTotal = usList.length;
  const covered = counts.anchored_ok + counts.pendente + counts.parcial; // zombie/dead NÃO contam
  return {
    us_total: usTotal, counts, coverage_pct: usTotal ? Math.round((1000 * covered) / usTotal) / 10 : null,
    fields_total: fieldsTotal, fields_placeholder: fieldsPlaceholder, fields_grammar_ok: grammarOk,
    orphan_fields: orphans.length, anchor_format_v1: isV1, dead: deadList, zombie: zombieList,
    dead_tests: deadTests, testado_sem_covers: testadoSemCovers, testado_lines: testadoLines.length, v1_violations: v1Violations,
    req_sem_aceite: reqSemAceite, req_sem_covering_test: reqSemTeste, req_teste_vermelho: reqTesteVermelho,
    req_sem_lane: reqSemLane, servido: servidoCount, nao_servido: naoServido,
  };
}

// ── seleção de SPECs: full-tree ou diff-aware (args posicionais) ─────────────
// exclui o valor de `--baseline <path>` dos posicionais (é caminho de baseline, não SPEC).
// (`_rawArgv` já declarado na seção de flags — G1b-verde.)
const _baselineValIdx = _rawArgv.includes('--baseline') ? _rawArgv.indexOf('--baseline') + 1 : -1;
const args = _rawArgv.filter((a, i) => !a.startsWith('--') && i !== _baselineValIdx);
let specs;
if (args.length) {
  specs = args.map((a) => resolve(ROOT, a)).filter((p) => /memory[\\/]requisitos[\\/][^\\/]+[\\/]SPEC\.md$/.test(p) && existsSync(p)).sort();
} else {
  specs = readdirSync(REQ, { withFileTypes: true })
    .filter((e) => e.isDirectory() && existsSync(join(REQ, e.name, 'SPEC.md')))
    .map((e) => join(REQ, e.name, 'SPEC.md')).sort();
}

const modules = specs.map((f) => ({ module: dirname(f).split(/[\\/]/).pop(), ...lintSpec(f) }));

// flush-safe: `process.exit()` imediato após `stdout.write` descarta o buffer não-flushado quando
// stdout é PIPE (node >=22 truncava o JSON ~157KB consumido via execSync pelo sdd-scorecard.mjs —
// espinha do gate required GT-G3; reproduzido 2/6 runs, avaliação adversarial 2026-07-03). O callback
// do write só dispara após o flush real; o Promise nunca-resolvido segura o top-level até o exit.
const writeStdoutAndExit = (payload) => {
  process.stdout.write(payload, () => process.exit(0));
  return new Promise(() => {});
};

// ── baseline grandfather (ARMING SA-A2-ter · ADR 0275/0303) ──────────────────
// emit: imprime o baseline da dívida ATUAL (todos os entry/covers em chave canônica,
// sorted+unique) — fonte regenerável; arming consome o que ele emite. Mesma engine que
// CHECA é a que EMITE → as chaves nunca derivam (sem drift entre baseline e gate).
const _allAceiteKeys = modules.flatMap((m) => m.req_sem_aceite.map((r) => keyAceite(r.us)));
const _allTesteKeys = modules.flatMap((m) => m.req_sem_covering_test.map((r) => keyTeste(r.us)));
const _allCoversKeys = modules.flatMap((m) => m.testado_sem_covers.flatMap((e) => e.tests.map((t) => keyCovers(e.us, t))));
if (EMIT_BASELINE) {
  const grandfathered = [...new Set([..._allAceiteKeys, ..._allTesteKeys, ..._allCoversKeys])].sort();
  await writeStdoutAndExit(JSON.stringify({
    _meta: {
      baseline: 'anchor entry/covers GRANDFATHER — US legadas isentas (ratchet só-desce · ADR 0275 advisory→required por calendário)',
      regra: 'gate --check-entry/--check-covers com --baseline morde só chave AUSENTE daqui (no-new-lie). CRESCER esta lista = grandfatherar mentira nova → exige trailer `BASELINE-GROW` (baseline-tamper-guard). DIMINUIR (dívida paga) é livre.',
      gerado_por: 'node scripts/governance/anchor-lint.mjs --emit-baseline',
      consumido_por: ['scripts/governance/anchor-lint.mjs --baseline', '.github/workflows/anchor-drift.yml'],
      adr: ['0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes', '0303-anchor-lint-wired-testado-sa-a2-bis', '0273-anchor-spec-codigo-formato-canonico-fluxo-novo'],
      escopo: args.length ? 'diff-aware (args) — NÃO use pra gerar o baseline canônico; rode full-tree' : 'full-tree',
      nota_verde: 'req_teste_vermelho (--junit) NÃO entra neste baseline — verde é gate à parte (G1b Phase B), dormente sem --junit.',
      total: grandfathered.length,
    },
    grandfathered,
  }, null, 2) + '\n');
}
// load + filtro: violação grandfatherada NÃO conta no veredito de saída (report mantém visibilidade).
function loadBaseline(p) {
  if (!p) return null; // sem baseline = comportamento legado (raw)
  if (!existsSync(p)) return new Set(); // ausente = vazio (ordering-robusto entre PRs; advisory)
  try { return new Set((JSON.parse(readFileSync(p, 'utf8')).grandfathered) || []); }
  catch { console.error(`anchor-lint: --baseline ${p} não parseia como JSON.`); process.exit(2); }
}
const BASELINE = loadBaseline(BASELINE_PATH);
for (const m of modules) {
  m._aceite_active = BASELINE ? m.req_sem_aceite.filter((r) => !BASELINE.has(keyAceite(r.us))) : m.req_sem_aceite;
  m._teste_active = BASELINE ? m.req_sem_covering_test.filter((r) => !BASELINE.has(keyTeste(r.us))) : m.req_sem_covering_test;
  m._covers_active = BASELINE
    ? m.testado_sem_covers.map((e) => ({ ...e, tests: e.tests.filter((t) => !BASELINE.has(keyCovers(e.us, t))) })).filter((e) => e.tests.length)
    : m.testado_sem_covers;
}
const grandfatheredAceite = _allAceiteKeys.filter((k) => BASELINE && BASELINE.has(k)).length;
const grandfatheredTeste = _allTesteKeys.filter((k) => BASELINE && BASELINE.has(k)).length;
const grandfatheredCovers = _allCoversKeys.filter((k) => BASELINE && BASELINE.has(k)).length;

const sum = (k) => modules.reduce((a, m) => a + m[k], 0);
const states = ['sem_campo', 'placeholder', 'pendente', 'parcial', 'anchored_ok', 'anchored_dead', 'anchored_zombie'];
const byState = Object.fromEntries(states.map((s) => [s, modules.reduce((a, m) => a + m.counts[s], 0)]));
const usTotal = sum('us_total');
const covered = byState.anchored_ok + byState.pendente + byState.parcial;
const coverage = usTotal ? Math.round((1000 * covered) / usTotal) / 10 : null;
const deadTestsTotal = modules.reduce((a, m) => a + m.dead_tests.length, 0);
// ATIVOS (= raw quando sem --baseline; raw-menos-grandfathered quando com baseline) — é o que MORDE.
const testadoSemCoversTotal = modules.reduce((a, m) => a + m._covers_active.length, 0);
const reqSemAceiteTotal = modules.reduce((a, m) => a + m._aceite_active.length, 0);
const reqSemTesteTotal = modules.reduce((a, m) => a + m._teste_active.length, 0);
// verde (G1b Phase B) NÃO é grandfatherado pelo --baseline (gate à parte, dormente sem --junit).
const reqTesteVermelhoTotal = modules.reduce((a, m) => a + m.req_teste_vermelho.length, 0);
// req_sem_lane (G1c · item b): teste-que-cobre fora das lanes de JUnit → verde impossível. NÃO
// grandfatherado (gate à parte, advisory; só morde com --check-lane).
const reqSemLaneTotal = modules.reduce((a, m) => a + m.req_sem_lane.length, 0);
// servido (4º veredito · advisory runtime): só computado com ledger carregado.
const servidoTotal = modules.reduce((a, m) => a + m.servido, 0);
const naoServidoTotal = modules.reduce((a, m) => a + m.nao_servido.length, 0);

for (const m of modules) m.flag = m.us_total === 0 ? '🟡' : (m.counts.anchored_dead > 0 || m.counts.anchored_zombie > 0 || m.dead_tests.length || m.v1_violations.length || m.coverage_pct === 0) ? '🔴' : m.coverage_pct === 100 ? '🟢' : '🟡';

const report = {
  _meta: {
    lint: 'anchor spec↔código — gramática ADR 0273 §1 (sentinelas _pendente_/_parcial_ de 1ª classe) + wired-check + testado-check (SA-A2-bis)',
    generator: 'scripts/governance/anchor-lint.mjs',
    coverage_regra: 'anchor_coverage = (anchored_ok + pendente + parcial) / us_total — _pendente_ é coberto (tela não construída ≠ dívida de anchor); anchored_ok exige TODOS os paths existentes (§2) E vivos no roteador (zumbi não conta)',
    wired_regra: 'Page-âncora ZUMBI = existe no disco + renderizada por controller NÃO-referenciado nas rotas (dormente/atrás de 301). Existir ≠ estar vivo. Conservador: sub-componentes e renders por variável nunca marcados.',
    testado_regra: 'dead_tests = ref em `**Testado em:**` (path .php OU ClassName…Test) inexistente no repo.',
    covers_regra: 'testado_sem_covers (G1a · ADR 0303 emenda) = teste que EXISTE mas não declara `// @covers-us <US-ID>` da US-pai. ADVISORY: reportado sempre, exit 1 só com --check-covers (anchor-drift roda --check normal).',
    entrada_regra: 'GATE DE ENTRADA (G1b-entry): US que se diz IMPLEMENTADA (anchored_ok/parcial) precisa de DoD/aceite (req_sem_aceite) E de teste que declare @covers-us dela (req_sem_covering_test). _pendente_ é exceto. ADVISORY: exit 1 só com --check-entry (arming com baseline grandfather do legado, ADR 0275).',
    verde_regra: 'GATE VERDE (G1b-verde · Phase B): com --junit <summary.json> (junit-summary/v1), US implementada+coberta cujo arquivo-de-teste NÃO está verde no JUnit → req_teste_vermelho. verde POR ARQUIVO = passed>0 E failed=0 E errors=0; vermelho/skipped/ausente NÃO contam (skipped != passed, defesa markTestSkipped). V6-C: só julga covering tests DENTRO de uma lane de JUnit — US inteiramente fora de lane (nightly-only) = req_sem_lane → behavior_unknown, nunca req_teste_vermelho (senão o teste que não pode aparecer no junit do PR viraria false-red). fs-puro: lê o JSON que o CI já produz, NUNCA roda teste/PHP/DB. Sem --junit → behavior_unknown (nunca avermelha). exit 1 só com --check-verde OU --check-entry.',
    servido_regra: 'SERVIDO (4º veredito · ADVISORY runtime): US wired (anchored_ok/parcial) ancorada em Page com hits>0 na janela do ledger governance/route-hits.json (export do middleware ContadorHitsRota em prod). nao_servido = "existe + roteado mas 0 hits em Nd" — prova de USO, não de correção. NUNCA entra em coverage/--check/flag. Sem ledger (ou pages vazio) = sem_ledger, nada é marcado.',
    servido_ledger: HITS ? `${relative(ROOT, HITS_PATH).replace(/\\/g, '/')} (janela ${HITS.janela ?? '?'}d)` : 'sem_ledger',
    behavior: JUNIT ? `junit:${JUNIT.schema} · fonte ${JUNIT.source}` : `behavior_unknown (${JUNIT_UNKNOWN_REASON ? `--junit ${JUNIT_UNKNOWN_REASON}` : 'sem --junit'})`,
    determinismo: 'sem timestamps/sha no output — re-run sem mudança no repo = diff vazio',
    fase: 'F1 ADVISORY (ADR 0273 §4) — exit 0 sempre nos modos default/--json; --check (exit 1) reservado pra F2',
    baseline_regra: BASELINE
      ? `grandfather aplicado (${BASELINE_PATH}): entry/covers grandfatherados NÃO mordem (no-new-lie · ADR 0275). Totais entry/covers no summary = ATIVOS (mentira NOVA/tocada).`
      : 'sem --baseline: totais entry/covers = brutos (legado + novo). Arming passa --baseline pra grandfatherar o legado.',
    scope: args.length ? 'diff-aware (args)' : 'full-tree',
  },
  summary: {
    specs_total: modules.length, us_total: usTotal, anchor_coverage_pct: coverage, by_state: byState,
    fields_total: sum('fields_total'), fields_placeholder: sum('fields_placeholder'),
    fields_grammar_ok: sum('fields_grammar_ok'), orphan_fields: sum('orphan_fields'),
    dead_tests_total: deadTestsTotal, testado_sem_covers_total: testadoSemCoversTotal,
    req_sem_aceite_total: reqSemAceiteTotal, req_sem_covering_test_total: reqSemTesteTotal,
    req_teste_vermelho_total: reqTesteVermelhoTotal, req_sem_lane_total: reqSemLaneTotal, behavior_known: JUNIT ? true : false,
    servido_total: servidoTotal, nao_servido_total: naoServidoTotal, servido_ledger: HITS ? true : false,
    baseline_applied: BASELINE ? BASELINE_PATH : null,
    grandfathered: { aceite: grandfatheredAceite, covering_test: grandfatheredTeste, covers: grandfatheredCovers },
    v1_files: modules.filter((m) => m.anchor_format_v1).length, v1_violations: sum('v1_violations'),
  },
  modules,
};

if (JSON_OUT) { await writeStdoutAndExit(JSON.stringify(report, null, 2) + '\n'); }

console.log(`\n  ANCHOR LINT — spec↔código (ADR 0273 + wired/testado SA-A2-bis) · ${modules.length} SPECs · escopo: ${report._meta.scope}\n`);
console.log(`  ${'MÓDULO'.padEnd(20)} ${'US'.padStart(4)} ${'s/campo'.padStart(7)} ${'phold'.padStart(5)} ${'pend'.padStart(4)} ${'parc'.padStart(4)} ${'ok'.padStart(4)} ${'dead'.padStart(4)} ${'zomb'.padStart(4)} ${'dtst'.padStart(4)} ${'cov%'.padStart(6)}`);
console.log('  ' + '─'.repeat(82));
for (const m of modules) {
  const c = m.counts;
  console.log(`  ${m.flag} ${m.module.padEnd(18)} ${String(m.us_total).padStart(4)} ${String(c.sem_campo).padStart(7)} ${String(c.placeholder).padStart(5)} ${String(c.pendente).padStart(4)} ${String(c.parcial).padStart(4)} ${String(c.anchored_ok).padStart(4)} ${String(c.anchored_dead).padStart(4)} ${String(c.anchored_zombie).padStart(4)} ${String(m.dead_tests.length).padStart(4)} ${String(m.coverage_pct ?? '—').padStart(6)}`);
  for (const d of m.dead) console.log(`       💀 ${d.us} (L${d.line}): ${d.missing.join(' · ')}`);
  for (const z of m.zombie) console.log(`       🧟 ${z.us} (L${z.line}): tela DESLIGADA (renderizada só por controller fora das rotas) → ${z.dead_screens.join(' · ')}`);
  for (const t of m.dead_tests) console.log(`       🧪 Testado em (L${t.line}): teste inexistente → ${t.missing.join(' · ')}`);
  for (const tc of m._covers_active) console.log(`       🎯 Testado em (L${tc.line}): ${tc.us} — teste existe mas não declara @covers-us ${tc.us} → ${tc.tests.join(' · ')}`);
  for (const r of m._aceite_active) console.log(`       📋 ${r.us} (L${r.line}): diz IMPLEMENTADA mas SEM aceite/DoD definido (regra de entrada)`);
  for (const r of m._teste_active) console.log(`       🚪 ${r.us} (L${r.line}): diz IMPLEMENTADA mas NENHUM teste declara @covers-us dela (regra sem teste)`);
  for (const r of m.req_teste_vermelho) console.log(`       🟥 ${r.us} (L${r.line}): diz IMPLEMENTADA + tem teste-que-cobre, mas NENHUM arquivo-de-teste está verde no JUnit → ${r.tests.map((t) => `${t.file} [${t.status}]`).join(' · ')}`);
  for (const r of m.req_sem_lane) console.log(`       🚦 ${r.us} (L${r.line}): tem teste-que-cobre mas NENHUM numa lane de JUnit (verde impossível) → ${r.tests.join(' · ')}`);
  for (const r of m.nao_servido) console.log(`       🔕 ${r.us} (L${r.line}): wired porém NÃO-SERVIDO — 0 hits na janela do ledger (existe + roteado, ninguém usou) → ${r.pages.join(' · ')}`);
  for (const v of m.v1_violations) console.log(`       ✗ v1 L${v.line}: não casa gramática ADR 0273 §1 → ${v.raw}`);
}
console.log('  ' + '─'.repeat(82));
console.log(`\n  ANCHOR COVERAGE GLOBAL: ${coverage}%  (= (${byState.anchored_ok} ok + ${byState.pendente} pend + ${byState.parcial} parc) / ${usTotal} US)`);
console.log(`  Campos: ${report.summary.fields_total} total · ${report.summary.fields_placeholder} placeholder · ${report.summary.fields_grammar_ok} já na gramática v1 · ${report.summary.orphan_fields} órfãos (fora de bloco US)`);
console.log(`  Estados por US: sem_campo ${byState.sem_campo} · placeholder ${byState.placeholder} · pendente ${byState.pendente} · parcial ${byState.parcial} · anchored_ok ${byState.anchored_ok} · anchored_dead ${byState.anchored_dead} · anchored_zombie ${byState.anchored_zombie}`);
console.log(`  Testes-fantasma (dead_tests): ${deadTestsTotal}`);
console.log(`  Cobertura fora de lane (advisory · item b): ${reqSemLaneTotal} US com teste-que-cobre fora das lanes de JUnit (verde impossível até entrar numa lane)`);
console.log(`  Testado sem covers (teste existe mas não declara @covers-us · advisory): ${testadoSemCoversTotal}`);
console.log(`  Gate de entrada (advisory): ${reqSemAceiteTotal} US implementada SEM aceite/DoD · ${reqSemTesteTotal} US implementada SEM teste que a cobre${BASELINE ? ` (ATIVOS, pós-baseline)` : ''}`);
if (BASELINE) console.log(`  Grandfather (${BASELINE_PATH}): ${grandfatheredAceite} aceite + ${grandfatheredTeste} teste + ${grandfatheredCovers} covers isentos (no-new-lie · ratchet só-desce · ADR 0275)`);
console.log(`  Gate verde (advisory): ${JUNIT ? `${reqTesteVermelhoTotal} US implementada com teste-que-cobre NÃO-verde no JUnit (verde=passed>0 & fail=0; skipped/ausente não contam · skipped != passed)` : `behavior_unknown — ${JUNIT_UNKNOWN_REASON ? `--junit ${JUNIT_UNKNOWN_REASON}` : 'sem --junit'} (nunca avermelha)`}`);
console.log(`  Servido (advisory runtime): ${HITS ? `${servidoTotal} US com hit real na janela · ${naoServidoTotal} wired porém 0 hits (ledger ${report._meta.servido_ledger})` : 'sem_ledger — governance/route-hits.json ausente/vazio (coleta ROUTE_HITS_ENABLED ainda OFF?); nada marcado'}`);
console.log(`\n  💀 dead = path inexistente · 🧟 zombie = path existe mas tela desligada · 🧪 = teste citado inexistente. Corrigir via reconciliação — nunca inventar path.\n`);

if (CHECK && (byState.anchored_dead > 0 || byState.anchored_zombie > 0 || deadTestsTotal > 0 || report.summary.v1_violations > 0)) process.exit(1);
if (CHECK_COVERS && testadoSemCoversTotal > 0) process.exit(1);
// --check-entry ganha a 3ª exigência (verde) — só morde quando --junit dá o sinal (senão behavior_unknown=0).
if (CHECK_ENTRY && (reqSemAceiteTotal > 0 || reqSemTesteTotal > 0 || reqTesteVermelhoTotal > 0)) process.exit(1);
if (CHECK_VERDE && reqTesteVermelhoTotal > 0) process.exit(1);
if (CHECK_LANE && reqSemLaneTotal > 0) process.exit(1);
process.exit(0);
