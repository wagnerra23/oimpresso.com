#!/usr/bin/env node
// knowledge-drift.mjs — primeira batida do "batimento" (ADR 0270 / sessão 2026-06-11).
//
// POR QUE EXISTE: o sistema de conhecimento é otimizado pra ESCREVER, não pra LER.
// Nenhum mecanismo media a DERIVADA (está ficando melhor ou pior de usar com o tempo?).
// Este script torna o drift VISÍVEL e MEDIDO — o "loop fechado por métrica" (Constituição
// v2, Princípio 4) aplicado a conhecimento. É a coisa que faz o apodrecimento gritar
// em vez de mentir 73/100 (caso SRS/MemCofre, sessão 2026-06-11).
//
// MEDE, por módulo em memory/requisitos/<Mod>/:
//   - read_path_hops : quantos docs se abre pra saber "a verdade atual" (meta: 1)
//   - porta          : tem BRIEFING.md? é auto-contida ou um índice de links?
//   - identity_drift : os docs VIVOS citam Modules/<X>/ que NÃO existe no disco? (pegou o MemCofre)
//                      RECONCILIAÇÃO P11 (KL-E2): a contagem de ghost PULA adr/ (append-only,
//                      ADR 0094 Art.3), espelhando o escopo do corretor ghost-fix.mjs:60-61.
//                      Antes o detector contava tombstones de ADR que o corretor NUNCA poderia
//                      zerar → métrica de FORMA, não de correção. Agora mede só drift corrigível.
//   - staleness      : a porta é mais velha (git) que o doc mais novo do módulo?
//                      (EIXO DOC-vs-DOC.) O eixo IRMÃO porta-vs-CÓDIGO (a porta ficou
//                      atrás de Modules/<X> ∪ Pages/<X>?) vive em
//                      scripts/governance/briefing-code-staleness.mjs — pega o que este
//                      NÃO pega: código andando com porta E docs irmãos congelados
//                      (incidente #3714 Compras). Não duplicar aqui.
//   - path_fantasma  : docs/ADRs citam .github/workflows/<X>.yml ou scripts/**/*.mjs que
//                      NÃO existe no disco E não tem tombstone curado? (P16 — "mecanismo-
//                      fantasma"). SÓ paths CONCRETOS — nunca "nome-de-gate" fuzzy em prosa
//                      (falso-positivo: ragas VIVE como jana-ragas-gate.yml, nome≠path).
//                      Advisory (ADR 0314); tombstones em governance/ghost-rename-map.json.
//
// NÃO recomenda ADICIONAR — toda nota ruim aponta pra DESTILAR/FUNDIR/APAGAR.
// Uso:  node scripts/governance/knowledge-drift.mjs [--json]
//       node scripts/governance/knowledge-drift.mjs --check [--baseline <dir>]
//       node scripts/governance/knowledge-drift.mjs --write-baseline [--baseline <dir>]
//       node scripts/governance/knowledge-drift.mjs --check-paths   (advisory: cita path morto sem tombstone?)
//       node scripts/governance/knowledge-drift.mjs --selftest      (prova que a catraca de path morde/solta)
// Node puro (fs + git via execSync). Sem deps, sem DB, sem PHP.

import { readdirSync, statSync, readFileSync, existsSync, mkdirSync, writeFileSync, realpathSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = process.cwd();
const REQ = join(ROOT, 'memory', 'requisitos');
const JSON_OUT = process.argv.includes('--json');

// Import-safe (Roubo #5 · doc-freshness-score.mjs): o agregador de frescor por doc
// IMPORTA os helpers puros do ghost-check daqui (classifyPathCitation/loadPathTombstones/
// regexes) em vez de duplicar — o ghost-check é DESTE script. IS_MAIN garante que o CLI
// (flags + relatório + process.exit) só roda quando executado direto, nunca no import.
// Mesmo idioma de briefing-code-staleness.mjs (núcleo exportado + isMain guard).
const IS_MAIN = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();

// thresholds (calibrados na sessão 2026-06-11 — Jana porta=27 links era "índice")
const LINKS_INDICE = 15;   // > isso, a "porta" é índice, não verdade auto-contida
const STALE_DAYS = 30;     // porta mais velha que o doc novo por > isso = stale

function allMd(dir) {
  const out = [];
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) {
      // US-GOV-035: planos do roadmap citam módulos legados/renomeados em
      // contexto de planejamento (não são ghost vivo) — isenta _Governanca/roadmap/.
      if (e.name === "roadmap" && p.includes("_Governanca")) continue;
      out.push(...allMd(p));
    }
    else if (e.name.endsWith('.md')) out.push(p);
  }
  return out;
}

// allMdLive — mesma varredura, mas PULA a subárvore adr/ (append-only, ADR 0094 Art.3).
// RECONCILIAÇÃO detector×corretor (P11 KL-E2): o corretor ghost-fix.mjs:60-61 pula adr/
// por design (um ADR de rename CITA o nome antigo como FATO histórico — não é drift vivo,
// e não pode ser reescrito sem violar o Tier 0 que reprovou o commit d415b4a55e). O detector
// CONTAVA esses tombstones como ghost, então a métrica ghost_count media nomes que o corretor
// NUNCA poderia zerar — DoD "→0" estruturalmente impossível. Alinhando o ESCOPO DA CONTAGEM
// DE GHOST ao do corretor, ghost_count passa a medir só drift VIVO (corrigível). Door/hops/
// staleness seguem na varredura FULL (allMd) — semântica deles não depende deste argumento.
function allMdLive(dir) {
  const out = [];
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) {
      if (e.name === 'adr') continue; // hard-skip append-only — espelha ghost-fix.mjs:60-61
      if (e.name === 'roadmap' && p.includes('_Governanca')) continue; // US-GOV-035: planos do roadmap citam módulos legados/renomeados em contexto de planejamento (não ghost vivo)
      out.push(...allMdLive(p));
    } else if (e.name.endsWith('.md')) out.push(p);
  }
  return out;
}

function gitDate(file) {
  try {
    return execSync(`git log -1 --format=%cs -- "${file}"`, { cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'] })
      .toString().trim() || null;
  } catch { return null; }
}

const TRUTH_RE = /^(SPEC|README|ARCHITECTURE|BRIEFING|CAPTERRA.*|CAPTERRA-INVENTARIO|AUDIT.*|AUDITORIA.*)\.md$/i;
// MOD_REF_RE — referência a um app-module Modules/<X>. Compartilhada (export): usada aqui
// (scanGhostsByModule + reportDrift) e no doc-freshness-score.mjs (extractCitedRefs).
// Um path de TESTE — tests/Feature/Modules/<X>/… ou tests/Unit/Modules/<X>/… — contém o
// literal "Modules/<X>" mas NÃO é referência a módulo: os testes de um sub-namespace vivem
// sob tests/{Feature,Unit}/Modules/<X>/ mesmo sem Modules/<X>/ no disco (ex: 21 testes em
// tests/Feature/Modules/Copiloto/ sem Modules/Copiloto/; idem tests/Feature/Modules/Sells/).
// Contar isso inventava um módulo-fantasma (ghost falso no --check daqui + ref-quebrada de
// 5pts no doc-freshness). Lookbehind negativo exclui o prefixo de suíte Pest/PHPUnit — uma
// referência REAL a Modules/<X> em prosa nunca vem precedida de "Feature/" nem "Unit/".
export const MOD_REF_RE = /(?<!Feature\/)(?<!Unit\/)Modules\/([A-Z][A-Za-z0-9]+)/g;

// ---------------------------------------------------------------------------
// DETECTOR DE PATH FANTASMA (P16 — "docs/ADRs apontam pra mecanismo-fantasma").
// Irmão do detector de módulo-ghost acima: aquele pega Modules/<X> inexistente;
// este pega CAMINHO CONCRETO de mecanismo (workflow .yml / script .mjs) que o doc
// CITA mas que não existe no disco E não tem tombstone curado. Caso-âncora: o canon
// citava .github/workflows/mwart-gate.yml — DELETADO (commit 7be91b3347, PR #2531,
// "onda 2 dos gates ADR 0271"; cobertura migrou pro casos-gate required ADR 0264).
//
// CONTRATO: um path citado é GROUNDED sse (a) resolve a arquivo real OU (b) tem
// tombstone {nome, deletado_por_adr, substituto} em governance/ghost-rename-map.json.
// Fora disso = phantom = drift a corrigir.
//
// SÓ PATHS CONCRETOS (ressalva adversária P16): a regex casa .github/workflows/<X>.yml
// e scripts/**/*.mjs LITERAIS. NUNCA "nome-de-gate" solto em prosa (ex: "o ragas-gate")
// — fuzzy gera falso-positivo, e ragas VIVE como jana-ragas-gate.yml (nome ≠ path).
//
// ADVISORY (ADR 0314): a superfície é SURGIR/MEDIR, não bloquear merge. O conserto de
// citação-fantasma DENTRO de ADR aceita (append-only) NÃO é editar a ADR — é o tombstone
// (aterra o detector aqui) + injeção no decisions-fetch (PR SEPARADO, follow-up).
const RENAME_MAP_FILE = join(ROOT, 'governance', 'ghost-rename-map.json');
const MEM = join(ROOT, 'memory');
// Contextos de PLANEJAMENTO citam mecanismo ainda-não-criado DE PROPÓSITO (não é rot) —
// mesma isenção que o detector de módulo-ghost dá a _Governanca/roadmap/. Proposals idem.
const PLAN_CTX = ['memory/decisions/proposals/', 'memory/requisitos/_Governanca/roadmap/'];
const relPosix = (abs) => abs.slice(ROOT.length + 1).split(sep).join('/');
const isPlanningDoc = (abs) => { const r = relPosix(abs); return PLAN_CTX.some(p => r.startsWith(p)); };

// Formas CONCRETAS apenas (token-boundary). Glob (`.github/workflows/*.yml`,
// `scripts/**/*.mjs`) não casa: `*` não está na classe → o `+…\.ext` não fecha.
export const WF_CITE_RE = /\.github\/workflows\/[A-Za-z0-9._-]+\.ya?ml/g;
export const MJS_CITE_RE = /(?<![\w./-])scripts\/[A-Za-z0-9._/-]+\.mjs/g;

export function loadPathTombstones() {
  const m = new Map(); // nome(=path) -> { nome, deletado_por_adr, substituto, ... }
  try {
    const raw = JSON.parse(readFileSync(RENAME_MAP_FILE, 'utf8'));
    for (const t of raw.path_tombstones ?? []) if (t && t.nome) m.set(t.nome, t);
  } catch { /* sem map = sem groundings; o detector ainda funciona (tudo vira phantom) */ }
  return m;
}

// PURO + injetável — o --selftest exercita ISTO (o contrato), nunca o console.
//   'live'       existe no disco
//   'tombstoned' não existe, mas há tombstone curado
//   'phantom'    não existe e não há tombstone → citação-fantasma
export function classifyPathCitation(citedPath, { resolveExists, tombstones }) {
  if (resolveExists(citedPath)) return { status: 'live' };
  const tomb = tombstones.get(citedPath);
  if (tomb) return { status: 'tombstoned', tombstone: tomb };
  return { status: 'phantom' };
}

function allMemoryMd(dir = MEM) {
  const out = [];
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) out.push(...allMemoryMd(p));
    else if (e.name.endsWith('.md')) out.push(p);
  }
  return out;
}

// Varre memory/**/*.md (menos contextos de planejamento) e classifica cada path
// concreto citado. Retorna só os MORTOS: path -> { status:'phantom'|'tombstoned', docs:Set }.
export function scanPhantomPaths() {
  const tombstones = loadPathTombstones();
  const resolveExists = (p) => existsSync(join(ROOT, p));
  const byPath = new Map();
  if (!existsSync(MEM)) return byPath;
  for (const f of allMemoryMd()) {
    if (isPlanningDoc(f)) continue;
    const txt = readFileSync(f, 'utf8');
    const cited = new Set();
    for (const mm of txt.matchAll(WF_CITE_RE)) cited.add(mm[0]);
    for (const mm of txt.matchAll(MJS_CITE_RE)) cited.add(mm[0]);
    if (!cited.size) continue;
    const rel = relPosix(f);
    for (const c of cited) {
      const cls = classifyPathCitation(c, { resolveExists, tombstones });
      if (cls.status === 'live') continue; // vivo: nada a reportar
      if (!byPath.has(c)) byPath.set(c, { status: cls.status, docs: new Set() });
      byPath.get(c).docs.add(rel);
    }
  }
  return byPath;
}

// ---------------------------------------------------------------------------
// CATRACA ANTI-GHOST (KL-A2 — plano SDD 2026-06-12, Semana 0).
// Baseline POR MÓDULO em governance/knowledge-ghosts-baseline/<Mod>.json
// (1 arquivo por módulo citante — anti conflito entre streams paralelos).
//   --write-baseline : congela os ghosts ATUAIS. Se já existe baseline do
//                      módulo, o rewrite é a INTERSEÇÃO (só diminui) — ghost
//                      novo NUNCA é absorvido: o write recusa e manda corrigir.
//   --check          : exit 1 SÓ se algum doc cita ghost NOVO fora do baseline.
//                      Ghost legado (no baseline) passa; entrada que deixou de
//                      ser ghost vira aviso de limpeza (rodar --write-baseline).
// Scan leve: só ghosts, sem git log (rápido o bastante pra rodar em todo PR).
// ---------------------------------------------------------------------------
const CHECK = process.argv.includes('--check');
const WRITE_BASELINE = process.argv.includes('--write-baseline');
const SELFTEST = process.argv.includes('--selftest');
const CHECK_PATHS = process.argv.includes('--check-paths');
const bIdx = process.argv.indexOf('--baseline');
const BASELINE_DIR = bIdx > -1 && process.argv[bIdx + 1]
  ? join(ROOT, process.argv[bIdx + 1])
  : join(ROOT, 'governance', 'knowledge-ghosts-baseline');

if (IS_MAIN && SELFTEST) {
  // Prova bite/release da catraca de path-fantasma. Asserções ancoradas em CONTRATO
  // citado (existência REAL no repo + forma do tombstone curado) e no retorno da
  // função pura classifyPathCitation — NUNCA no texto do console (que pode mudar).
  let fails = 0;
  const ok = (name, cond) => { console.log(`  ${cond ? '[OK]' : '[FAIL]'} ${name}`); if (!cond) fails++; };
  const MWART = '.github/workflows/mwart-gate.yml';        // deletado (commit 7be91b3347 / ADR 0271 onda 2)
  const RAGAS = '.github/workflows/jana-ragas-gate.yml';   // VIVO — o "não é fantasma" que o P16 alerta
  const tomb = loadPathTombstones();

  // Âncoras de contrato (repo real) — as PREMISSAS do P16 têm que ser verdade agora.
  ok('contrato: mwart-gate.yml deletado (não existe no repo)', !existsSync(join(ROOT, MWART)));
  ok('contrato: jana-ragas-gate.yml vivo (existe — nome≠path, não é fantasma)', existsSync(join(ROOT, RAGAS)));
  const mt = tomb.get(MWART);
  ok('contrato: tombstone de mwart-gate tem {nome,deletado_por_adr,substituto}',
     !!mt && !!mt.nome && !!mt.deletado_por_adr && !!mt.substituto);

  // BITE — path morto SEM tombstone → acusa (phantom).
  ok('BITE: path morto sem tombstone → phantom',
     classifyPathCitation(MWART, { resolveExists: () => false, tombstones: new Map() }).status === 'phantom');
  // RELEASE — path morto COM tombstone → solta (tombstoned).
  ok('RELEASE: path morto com tombstone → tombstoned',
     classifyPathCitation(MWART, { resolveExists: () => false, tombstones: tomb }).status === 'tombstoned');
  // RELEASE — path VIVO (resolver = existsSync real) → solta (live).
  ok('RELEASE: path vivo → live',
     classifyPathCitation(RAGAS, { resolveExists: p => existsSync(join(ROOT, p)), tombstones: tomb }).status === 'live');

  // MOD_REF_RE — referência real casa; path de suíte de teste (tests/{Feature,Unit}/Modules/X) NÃO.
  const modsIn = (s) => [...s.matchAll(MOD_REF_RE)].map(m => m[1]);
  ok('MOD_REF_RE: referência real Modules/Repair casa', modsIn('imite Modules/Repair/ como base').includes('Repair'));
  ok('MOD_REF_RE: path de teste tests/Feature/Modules/Copiloto NÃO vira módulo (ghost falso)',
     !modsIn('cobre tests/Feature/Modules/Copiloto/AdapterResolverTest.php').includes('Copiloto'));
  ok('MOD_REF_RE: path de teste tests/Unit/Modules/Foo NÃO vira módulo (ghost falso)',
     !modsIn('roda tests/Unit/Modules/Foo/BarTest.php').includes('Foo'));

  console.log(fails
    ? `\n  ${fails} FALHA(S) — a catraca de path-fantasma não está honesta.\n`
    : `\n  SELFTEST OK — morde (phantom) e solta (tombstoned/live).\n`);
  process.exit(fails ? 1 : 0);
}

function scanGhostsByModule() {
  const map = new Map(); // mod -> [ghosts sorted]
  for (const mod of readdirSync(REQ, { withFileTypes: true })) {
    if (!mod.isDirectory()) continue;
    const ghosts = new Set();
    // RECONCILIAÇÃO P11: allMdLive pula adr/ — só conta ghost VIVO (corrigível pelo codemod).
    for (const d of allMdLive(join(REQ, mod.name))) {
      const txt = readFileSync(d, 'utf8');
      for (const m of txt.matchAll(MOD_REF_RE)) {
        if (!existsSync(join(ROOT, 'Modules', m[1]))) ghosts.add(m[1]);
      }
    }
    if (ghosts.size) map.set(mod.name, [...ghosts].sort());
  }
  return map;
}

function readBaseline(mod) {
  const f = join(BASELINE_DIR, `${mod}.json`);
  if (!existsSync(f)) return null;
  try { return JSON.parse(readFileSync(f, 'utf8')).ghosts ?? []; } catch { return null; }
}

if (IS_MAIN && WRITE_BASELINE) {
  mkdirSync(BASELINE_DIR, { recursive: true });
  const current = scanGhostsByModule();
  let refused = 0;
  for (const [mod, ghosts] of current) {
    const old = readBaseline(mod);
    let frozen = ghosts;
    if (old !== null) {
      frozen = old.filter(g => ghosts.includes(g)); // catraca: só interseção (diminui)
      const novos = ghosts.filter(g => !old.includes(g));
      if (novos.length) {
        refused++;
        console.error(`  RECUSADO ${mod}: ghost NOVO [${novos.join(', ')}] não entra no baseline — corrija o doc (ou crie Modules/${novos[0]}).`);
      }
    }
    writeFileSync(join(BASELINE_DIR, `${mod}.json`), JSON.stringify({ module: mod, ghosts: frozen }) + '\n');
  }
  // Catraca também ENCOLHE baselines de módulos que ficaram 100% limpos (saíram de `current`).
  // Sem isso, um módulo que zerou os ghosts deixava o baseline ANTIGO listando nomes-fantasma
  // que não existem mais nos docs vivos (stale) — o --check só avisava, nunca limpava. A
  // interseção old∩current=∅ é o encolhimento máximo (catraca só diminui, nunca cresce).
  let pruned = 0;
  if (existsSync(BASELINE_DIR)) {
    for (const f of readdirSync(BASELINE_DIR)) {
      if (!f.endsWith('.json')) continue;
      const mod = f.slice(0, -5);
      if (current.has(mod)) continue; // já reescrito acima
      const old = readBaseline(mod);
      if (old && old.length) {
        writeFileSync(join(BASELINE_DIR, `${mod}.json`), JSON.stringify({ module: mod, ghosts: [] }) + '\n');
        pruned++;
      }
    }
  }
  console.log(`  Baseline anti-ghost escrito em ${BASELINE_DIR} — ${current.size} módulos citantes${pruned ? `, ${pruned} módulos limpos zerados` : ''}.`);
  process.exit(refused ? 1 : 0);
}

if (IS_MAIN && CHECK) {
  const current = scanGhostsByModule();
  const news = [];   // ghost fora do baseline => FAIL
  let legacy = 0;    // ghost congelado no baseline => passa
  for (const [mod, ghosts] of current) {
    const base = readBaseline(mod) ?? [];
    for (const g of ghosts) (base.includes(g) ? legacy++ : news.push({ mod, g }));
  }
  const cleanups = []; // entrada de baseline que não é mais ghost => aviso
  if (existsSync(BASELINE_DIR)) {
    for (const f of readdirSync(BASELINE_DIR)) {
      if (!f.endsWith('.json')) continue;
      const mod = f.slice(0, -5);
      const gone = (readBaseline(mod) ?? []).filter(g => !(current.get(mod) ?? []).includes(g));
      if (gone.length) cleanups.push(`${mod}: ${gone.join(', ')}`);
    }
  }
  console.log(`\n  CATRACA ANTI-GHOST — ${current.size} módulos citantes · ${legacy} ghosts legados (baseline) · ${news.length} NOVOS\n`);
  for (const n of news) console.log(`  FAIL ${n.mod}: cita Modules/${n.g} que NÃO existe e NÃO está no baseline.`);
  for (const c of cleanups) console.log(`  aviso ${c} — não é mais ghost; rode --write-baseline pra encolher a catraca.`);
  if (news.length) {
    console.log('\n  Corrija o doc (nome real do módulo) ou marque "(planejado — não existe)" — NUNCA adicione ao baseline.\n');
    process.exit(1);
  }
  console.log('  OK — nenhum ghost novo fora do baseline.\n');
  process.exit(0);
}

if (IS_MAIN && CHECK_PATHS) {
  const dead = [...scanPhantomPaths()];
  const phantom = dead.filter(([, v]) => v.status === 'phantom').sort((a, b) => b[1].docs.size - a[1].docs.size);
  const grounded = dead.filter(([, v]) => v.status === 'tombstoned');
  console.log(`\n  CITAÇÕES DE PATH FANTASMA (workflow/script) — advisory (ADR 0314)\n`);
  console.log(`  ${grounded.length} path(s) morto(s) ATERRADO(s) por tombstone · ${phantom.length} FANTASMA (sem tombstone)\n`);
  for (const [p, v] of phantom) console.log(`  👻 ${p}  — ${v.docs.size} doc(s), sem tombstone`);
  if (phantom.length) {
    console.log(`\n  Conserte o doc (path real) OU cure um tombstone {nome,deletado_por_adr,substituto} em governance/ghost-rename-map.json.`);
    console.log(`  ADR aceita (append-only) NÃO se edita — aterre com tombstone + injete no decisions-fetch (PR separado).\n`);
    process.exit(1);
  }
  console.log(`  OK — nenhuma citação de path fantasma sem tombstone.\n`);
  process.exit(0);
}

// Relatório default (tabela/--json) — só quando executado direto. Guard IS_MAIN em vez
// de wrap+reindent do bloco inteiro: diff mínimo, revisável (o corpo abaixo é intocado).
if (IS_MAIN) reportDrift();

function reportDrift() {
const rows = [];
for (const mod of readdirSync(REQ, { withFileTypes: true })) {
  if (!mod.isDirectory()) continue;
  const dir = join(REQ, mod.name);
  const docs = allMd(dir);
  if (docs.length < 2) continue;

  const briefing = join(dir, 'BRIEFING.md');
  const hasDoor = existsSync(briefing);

  // porta: auto-contida ou índice?
  let lines = 0, links = 0, indice = false;
  if (hasDoor) {
    const txt = readFileSync(briefing, 'utf8');
    lines = txt.split('\n').length;
    links = (txt.match(/\]\(/g) || []).length;
    indice = links > LINKS_INDICE;
  }

  // docs concorrendo pela "verdade"
  const competing = docs.filter(d => TRUTH_RE.test(d.split('/').pop())).length;

  // read_path_hops
  const hops = !hasDoor ? docs.length : (indice ? 1 + competing : 1);

  // identity drift: docs VIVOS citam Modules/<X> inexistente?
  // RECONCILIAÇÃO P11: usa allMdLive (pula adr/) — espelha o escopo do corretor ghost-fix.mjs.
  // Citação de nome morto DENTRO de adr/ é tombstone append-only (FATO histórico), não drift.
  const ghosts = new Set();
  for (const d of allMdLive(dir)) {
    const txt = readFileSync(d, 'utf8');
    for (const m of txt.matchAll(MOD_REF_RE)) {
      if (!existsSync(join(ROOT, 'Modules', m[1]))) ghosts.add(m[1]);
    }
  }

  // staleness (git): porta mais velha que o doc mais novo?
  let stale = false, doorDate = null, newestDate = null;
  if (hasDoor) {
    doorDate = gitDate(briefing);
    for (const d of docs) {
      if (d === briefing) continue;
      const dt = gitDate(d);
      if (dt && (!newestDate || dt > newestDate)) newestDate = dt;
    }
    if (doorDate && newestDate) {
      const gap = (new Date(newestDate) - new Date(doorDate)) / 86400000;
      stale = gap > STALE_DAYS;
    }
  }

  // classificação (🔴 pior)
  let flag = '🟢';
  if (!hasDoor && docs.length >= 8) flag = '🔴';
  else if (ghosts.size > 0) flag = '🔴';
  else if (indice || stale) flag = '🟡';
  else if (!hasDoor) flag = '🟡';

  rows.push({ mod: mod.name, docs: docs.length, hops, door: hasDoor ? (indice ? 'índice' : 'ok') : 'NÃO',
    links, competing, ghosts: [...ghosts], stale, flag });
}

rows.sort((a, b) => b.hops - a.hops);

if (JSON_OUT) { console.log(JSON.stringify(rows, null, 2)); process.exit(0); }

const withDoor = rows.filter(r => r.door !== 'NÃO').length;
const selfContained = rows.filter(r => r.door === 'ok' && !r.stale).length;
const drift = rows.filter(r => r.ghosts.length).length;
const hopsArr = rows.map(r => r.hops).sort((a, b) => a - b);
const median = hopsArr[Math.floor(hopsArr.length / 2)];

console.log(`\n  BATIMENTO DO CONHECIMENTO — drift por módulo (${rows.length} módulos)\n`);
console.log(`  ${'MÓDULO'.padEnd(20)} ${'docs'.padStart(4)} ${'hops'.padStart(4)}  ${'porta'.padEnd(7)} drift/stale`);
console.log('  ' + '─'.repeat(64));
for (const r of rows) {
  const d = [r.ghosts.length ? `👻 cita Modules/${r.ghosts.join(',')} inexistente` : '', r.stale ? 'stale' : ''].filter(Boolean).join(' · ');
  console.log(`  ${r.flag} ${r.mod.padEnd(18)} ${String(r.docs).padStart(4)} ${String(r.hops).padStart(4)}  ${r.door.padEnd(7)} ${d}`);
}
console.log('  ' + '─'.repeat(64));
console.log(`\n  Cobertura de porta:        ${withDoor}/${rows.length} (${Math.round(100*withDoor/rows.length)}%)`);
console.log(`  Portas auto-contidas:      ${selfContained}/${rows.length} (${Math.round(100*selfContained/rows.length)}%)`);
console.log(`  read_path_hops mediano:    ${median}  (meta: 1)`);
console.log(`  Módulos com identity-drift:${String(drift).padStart(3)}  (docs citam Modules/X inexistente)`);

// Advisory (ADR 0314) — citações de PATH fantasma (workflow/script). Informativo: não altera exit.
const deadPaths = [...scanPhantomPaths()];
const phantomPaths = deadPaths.filter(([, v]) => v.status === 'phantom').sort((a, b) => b[1].docs.size - a[1].docs.size);
const groundedPaths = deadPaths.filter(([, v]) => v.status === 'tombstoned').length;
console.log(`  Citações de path fantasma: ${String(phantomPaths.length).padStart(3)}  (workflow/script citado sem existir nem tombstone · ${groundedPaths} aterrado; \`--check-paths\`)`);
for (const [p, v] of phantomPaths.slice(0, 5)) console.log(`      👻 ${p} (${v.docs.size} doc)`);
if (phantomPaths.length > 5) console.log(`      … +${phantomPaths.length - 5}`);

console.log(`\n  Toda linha 🔴/🟡 = recomendação SUBTRATIVA: destilar/fundir/apagar — nunca adicionar.\n`);
} // fim reportDrift (guard IS_MAIN)
