#!/usr/bin/env node
// @ts-check
/**
 * doc-freshness-score.mjs — RADAR de frescor POR DOC (score 0-100 · régua Dosu).
 *
 * O QUE É: agregador determinístico (sem LLM, sem deps, sem DB) que dá um score de
 * frescor 0-100 pra CADA doc canônico e ranqueia os podres. Roubo #5 da pesquisa de
 * mercado 2026-07-09 (régua Dosu: score de frescor por doc em CI; lá o LLM só entra
 * na zona cinza — aqui NEM isso: 100% determinístico). Motivação-âncora: as 27/146
 * claims podres dos guias centrais só apareceram em auditoria MANUAL — as sentinelas
 * pontuais por classe não davam a visão de radar.
 *
 * AGREGADOR ≠ DENTE (declaração obrigatória — ressalva do adversário):
 * este script NÃO re-mede o que as sentinelas específicas já medem MELHOR — ele
 * COMPÕE os mesmos eixos num score por doc pra ranquear. Os dentes seguem sendo:
 *   · briefing-code-staleness.mjs      → porta×código por módulo (::warning por porta)
 *   · visual-comparison-staleness.mjs  → comparativo×tela
 *   · knowledge-drift.mjs              → ghost de módulo + path-fantasma + porta doc×doc
 *   · adr-proposto-parado.mjs          → ADR pendente de ratificação
 *   · memory-health.mjs (D/S/T/V)      → idade/fato-âncora/link da canon front-facing
 *   · sdd-scorecard distiller_freshness→ frescor do destilador
 * Se um dente morde um doc, é o dente que manda a ação; o radar só mostra ONDE olhar
 * primeiro (top-10 podres) e alimenta o Daily Brief (--json).
 *
 * EXTENSÃO, NÃO DUPLICAÇÃO: o ghost-check é do knowledge-drift.mjs — este script
 * IMPORTA de lá (classifyPathCitation/loadPathTombstones/WF_CITE_RE/MJS_CITE_RE/
 * MOD_REF_RE). knowledge-drift ganhou guard IS_MAIN pra ser import-safe.
 *
 * SCORE COMPOSTO (0-100 = 100 − penalidades; pesos ANTI-GAMING):
 * campo auto-declarado sozinho é gameável (presence-gate disfarçado — proibicoes §5 +
 * L-24 "presença ≠ correção"), então 80 dos 100 pontos vêm de sinais NÃO-declaráveis:
 *   · CHURN dos paths citados (max 40 · não-declarável) — commits que tocaram o
 *     código/mecanismo que o doc CITA depois da data-git do doc. Doc sobre código que
 *     anda envelhece mais rápido. Base = data-GIT do doc (bump vazio pra resetar exige
 *     um commit auditável no diff do PR — o custo de gaming é visível).
 *   · REFS QUEBRADAS (max 30 · não-declarável) — Modules/<X> inexistente + path de
 *     mecanismo phantom (classifyPathCitation, do knowledge-drift) + link .md relativo
 *     morto (espelha memory-health Check V, que segue sendo o dente da canon).
 *   · CLAIMS-FANTASMA CONHECIDAS (max 10 · não-declarável) — citação ATERRADA por
 *     tombstone (governance/ghost-rename-map.json): o doc fala de mecanismo que a
 *     curadoria já declarou morto = podridão conhecida, só falta o doc saber.
 *   · IDADE (max 20 · única parte declarável) — hoje − (data DECLARADA ?? data-git).
 *     Lição do briefing-code-staleness: git-date mente pra CIMA (commit mecânico
 *     rejuvenesce), então a declarada — quando existe — só pode PIORAR a idade, nunca
 *     melhorar abaixo da base git. Não dá pra ganhar pontos declarando.
 *
 * CORPUS: CLAUDE.md + @imports + guias raiz existentes + memory/requisitos/** /
 * {SPEC,BRIEFING,RUNBOOK*}.md + prototipo-ui/*.md.
 *
 * ADVISORY SEMPRE (ADR 0314 — required = só Tier 0; frescor de doc é higiene):
 * exit 0 no run normal; ::warning nos 5 piores quando em GitHub Actions (PR que toca
 * docs — step no workflow briefing-code-staleness.yml, o agregador de staleness).
 *
 * USO:
 *   node scripts/governance/doc-freshness-score.mjs             (tabela ranqueada + top-10)
 *   node scripts/governance/doc-freshness-score.mjs --json      (JSON pro Daily Brief)
 *   node scripts/governance/doc-freshness-score.mjs --selftest  (bite/release do núcleo puro)
 *   OIMPRESSO_DOC_FRESHNESS_CHURN_WINDOW=400 node …             (janela de churn tunável)
 *
 * Refs: ADR 0256 (Knowledge Survival) · ADR 0314 (advisory) · ADR 0270 (batimento) ·
 *       knowledge-drift.mjs (ghost-check dono) · briefing-code-staleness.mjs (idioma).
 */
import { readdirSync, readFileSync, existsSync, realpathSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  classifyPathCitation, loadPathTombstones,
  WF_CITE_RE, MJS_CITE_RE, MOD_REF_RE,
} from './knowledge-drift.mjs';

const ROOT = process.cwd();
const CHURN_WINDOW_DAYS = Number(process.env.OIMPRESSO_DOC_FRESHNESS_CHURN_WINDOW) || 400;

// Pesos do score (soma 100). 80/100 em sinais não-declaráveis — ver header.
export const PESOS = { churn: 40, refs: 30, tombstone: 10, idade: 20 };

// Superfícies de churn ALÉM das do knowledge-drift (concretas, token-boundary —
// mesma doutrina P16 "só path concreto, nunca nome fuzzy em prosa"). São superfície
// de CHURN (o que o doc cita e anda), não ghost-check (esse é importado).
const RES_CITE_RE = /(?<![\w./-])resources\/js\/[A-Za-z0-9._/-]+\.(?:tsx|ts|jsx|js|css)/g;
const APP_CITE_RE = /(?<![\w./-])app\/[A-Za-z0-9._/-]+\.php/g;

// ── núcleo PURO (o --selftest exercita ISTO — sem FS, sem git) ───────────────

/**
 * computeScore — compõe o score 0-100 a partir dos sinais já medidos.
 * @param {{ idadeDias:number, churnCommits:number, refsQuebradas:number, refsTombstoned:number }} s
 * @returns {{ score:number, penalidades:{ idade:number, churn:number, refs:number, tombstone:number } }}
 */
export function computeScore({ idadeDias, churnCommits, refsQuebradas, refsTombstoned }) {
  const penalidades = {
    // 2 pts por mês além dos 30 dias de graça, cap 20 (alinha STALE_DAYS=30 dos irmãos).
    idade: Math.min(PESOS.idade, Math.floor(Math.max(0, idadeDias - 30) / 30) * 2),
    // 2 pts por commit na superfície citada depois da data-git do doc, cap 40
    // (satura em 20 commits — calibrado no incidente #3714: 18 commits = porta podre).
    churn: Math.min(PESOS.churn, Math.max(0, churnCommits) * 2),
    // 5 pts por ref quebrada (ghost/phantom/link morto), cap 30.
    refs: Math.min(PESOS.refs, Math.max(0, refsQuebradas) * 5),
    // 3 pts por claim-fantasma conhecida (citação aterrada por tombstone), cap 10.
    tombstone: Math.min(PESOS.tombstone, Math.max(0, refsTombstoned) * 3),
  };
  const total = penalidades.idade + penalidades.churn + penalidades.refs + penalidades.tombstone;
  return { score: Math.max(0, 100 - total), penalidades };
}

/**
 * extractCitedRefs — extrai as referências CONCRETAS que o doc cita (puro).
 * @param {string} txt
 * @returns {{ modules:string[], mechPaths:string[], codePaths:string[], links:string[] }}
 *   modules   = nomes citados como Modules/<Nome> (ghost-check: existe no disco?)
 *   mechPaths = mecanismo concreto (workflow yml / scripts mjs) — ÚNICOS que passam
 *               pelo phantom-check (doutrina P16 do knowledge-drift: SPEC pode citar
 *               path PLANEJADO de app/resources — inexistência lá não é drift certo)
 *   codePaths = resources/js + app (superfície de CHURN apenas, nunca "quebrada")
 *   links     = alvos .md/arquivo relativos de links markdown (sufixo :linha strippado;
 *               fora: token sem cara de path, path absoluto/Windows-drive).
 */
export function extractCitedRefs(txt) {
  const modules = new Set(), mechPaths = new Set(), codePaths = new Set(), links = new Set();
  if (txt) {
    for (const m of txt.matchAll(MOD_REF_RE)) modules.add(m[1]);
    for (const re of [WF_CITE_RE, MJS_CITE_RE]) for (const m of txt.matchAll(re)) mechPaths.add(m[0]);
    for (const re of [RES_CITE_RE, APP_CITE_RE]) for (const m of txt.matchAll(re)) codePaths.add(m[0]);
    // links markdown relativos (não http/mailto/âncora/absoluto/drive-letter)
    for (const m of txt.matchAll(/\]\((?!https?:|mailto:|#)([^)\s]+)\)/g)) {
      const p = m[1].split('#')[0].trim().replace(/:\d+$/, ''); // [x](arq.php:185) → arq.php
      if (!p || p.startsWith('/') || p.includes(':')) continue; // absoluto/protocolo/C:/
      if (!p.includes('/') && !p.includes('.')) continue;       // "P1"/"Px" não é path
      links.add(p);
    }
  }
  return { modules: [...modules], mechPaths: [...mechPaths], codePaths: [...codePaths], links: [...links] };
}

/**
 * resolveRel — resolve link relativo a partir do doc (posix). Espelha o resolvedor do
 * memory-health Check V (lá é o dente da canon front-facing; aqui vira componente do score).
 * @param {string} fromRel @param {string} link @returns {string}
 */
export function resolveRel(fromRel, link) {
  const base = fromRel.includes('/') ? fromRel.slice(0, fromRel.lastIndexOf('/')) : '';
  const stack = base ? base.split('/') : [];
  for (const seg of link.split('/')) {
    if (seg === '..') stack.pop();
    else if (seg !== '.' && seg !== '') stack.push(seg);
  }
  return stack.join('/');
}

/**
 * dataDeclarada — maior carimbo declarado no doc (frontmatter/rodapé), ou null.
 * Mesmos carimbos do briefing-code-staleness + os PT-BR do memory-health Check D.
 * @param {string} txt @returns {string|null}
 */
export function dataDeclarada(txt) {
  if (!txt) return null;
  const dates = [];
  const push = (m) => { if (m && /^\d{4}-\d{2}-\d{2}$/.test(m[1])) dates.push(m[1]); };
  for (const key of ['updated_at', 'distilled_at', 'reviewed_at', 'last_updated']) {
    push(new RegExp(`^${key}:\\s*["']?(\\d{4}-\\d{2}-\\d{2})`, 'm').exec(txt));
  }
  push(/\*\*Atualizado:\*\*\s*(\d{4}-\d{2}-\d{2})/.exec(txt));
  push(/[uú]ltima atualiza[cç][aã]o[:*\s]*["']?(\d{4}-\d{2}-\d{2})/i.exec(txt));
  return dates.length ? dates.sort().at(-1) : null;
}

/** dias entre duas datas ISO (b − a). */
export const diasEntre = (a, b) =>
  Math.round((Date.parse(b + 'T00:00:00Z') - Date.parse(a + 'T00:00:00Z')) / 86400000);

// ── camada git/FS (impura — só no run real, nunca no selftest) ───────────────

function gitOut(cmd) {
  try {
    return execSync(cmd, { cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'], maxBuffer: 256 * 1024 * 1024 })
      .toString();
  } catch { return ''; }
}
const gitLastDate = (rel) => gitOut(`git log -1 --format=%cs -- "${rel}"`).trim() || null;

/** parseia `git log --format=@%cs --name-only` em [{date, files:Set, mods:Set}] */
function parseNameOnlyLog(raw) {
  const commits = [];
  let cur = null;
  for (const line of raw.split('\n')) {
    if (line.startsWith('@')) { cur = { date: line.slice(1).trim(), files: new Set(), mods: new Set() }; commits.push(cur); }
    else if (line.trim() && cur) {
      const f = line.trim();
      cur.files.add(f);
      const m = /^Modules\/([A-Z][A-Za-z0-9]+)\//.exec(f);
      if (m) cur.mods.add(m[1]);
    }
  }
  return commits;
}

/** CORPUS — docs canônicos avaliados (só o que EXISTE no disco; não inventa). */
export function corpusDocs({ exists = (p) => existsSync(join(ROOT, p)), listReq, listProto } = {}) {
  const docs = [];
  // guias raiz + CLAUDE.md e seus @imports
  const RAIZ = [
    'CLAUDE.md', 'README.md', 'DESIGN.md', 'TEAM.md', 'INFRA.md',
    'MANUAL_CLAUDE_CODE.md', 'HOW_TO_ASK_CLAUDE.md', 'MEMORY.md',
    'memory/why-oimpresso.md', 'memory/what-oimpresso.md', 'memory/how-trabalhar.md',
    'memory/proibicoes.md', 'memory/regras-time.md', 'memory/GUIA-DO-SISTEMA.md',
  ];
  for (const p of RAIZ) if (exists(p)) docs.push(p);
  docs.push(...(listReq ? listReq() : walkReq()));
  docs.push(...(listProto ? listProto() : protoMd()));
  return docs;
}
function walkReq() {
  const out = [];
  const walk = (rel) => {
    let entries; try { entries = readdirSync(join(ROOT, rel), { withFileTypes: true }); } catch { return; }
    for (const e of entries) {
      const p = `${rel}/${e.name}`;
      if (e.isDirectory()) walk(p);
      else if (/^(SPEC|BRIEFING|RUNBOOK[^/]*)\.md$/.test(e.name)) out.push(p);
    }
  };
  walk('memory/requisitos');
  return out;
}
function protoMd() {
  try {
    return readdirSync(join(ROOT, 'prototipo-ui'), { withFileTypes: true })
      .filter((e) => e.isFile() && e.name.endsWith('.md'))
      .map((e) => `prototipo-ui/${e.name}`);
  } catch { return []; }
}

/** scan — mede os 4 sinais por doc e compõe o score. */
function scan() {
  const docs = corpusDocs();
  const tombstones = loadPathTombstones();
  const hoje = gitLastDate('.') || new Date().toISOString().slice(0, 10);

  // 1 chamada git pras datas do corpus inteiro (primeira vez que um path aparece =
  // commit mais novo que o tocou) — evita 250 execSync de `git log -1`.
  const dateLog = parseNameOnlyLog(gitOut('git log --format=@%cs --name-only -- CLAUDE.md README.md DESIGN.md TEAM.md INFRA.md MANUAL_CLAUDE_CODE.md HOW_TO_ASK_CLAUDE.md MEMORY.md memory prototipo-ui'));
  const gitDateByDoc = new Map();
  for (const c of dateLog) for (const f of c.files) if (!gitDateByDoc.has(f)) gitDateByDoc.set(f, c.date);

  // 1 chamada git pro índice de churn (janela CHURN_WINDOW_DAYS até hoje).
  const since = new Date(Date.parse(hoje + 'T00:00:00Z') - CHURN_WINDOW_DAYS * 86400000)
    .toISOString().slice(0, 10);
  const churnIdx = parseNameOnlyLog(gitOut(`git log --since="${since} 00:00:00" --format=@%cs --name-only`));

  const rows = [];
  for (const doc of docs) {
    let txt = ''; try { txt = readFileSync(join(ROOT, doc), 'utf8'); } catch { continue; }
    const dataGit = gitDateByDoc.get(doc) || gitLastDate(doc);
    if (!dataGit) continue; // sem histórico git → não dá pra medir (não inventa)
    const declarada = dataDeclarada(txt);
    // idade: declarada só pode PIORAR (git mente pra cima — lição briefing-code-staleness).
    const baseIdade = declarada && declarada < dataGit ? declarada : dataGit;
    const idadeDias = Math.max(0, diasEntre(baseIdade, hoje));

    const cited = extractCitedRefs(txt);
    // ghost de módulo (mesma régua do knowledge-drift: Modules/<X> não existe no disco)
    const ghosts = cited.modules.filter((m) => !existsSync(join(ROOT, 'Modules', m)));
    // paths de mecanismo: live / tombstoned / phantom (ghost-check IMPORTADO — só wf/mjs)
    const phantoms = [], tombed = [];
    for (const p of cited.mechPaths) {
      const cls = classifyPathCitation(p, { resolveExists: (x) => existsSync(join(ROOT, x)), tombstones });
      if (cls.status === 'phantom') phantoms.push(p);
      else if (cls.status === 'tombstoned') tombed.push(p);
    }
    // links .md relativos mortos (espelha memory-health Check V, por doc)
    const brokenLinks = cited.links
      .map((l) => ({ l, alvo: resolveRel(doc, l) }))
      .filter(({ alvo }) => alvo && !existsSync(join(ROOT, alvo)))
      .map(({ l }) => l);

    // churn: commits (janela) DEPOIS da data-git do doc tocando a superfície citada
    const modSet = new Set(cited.modules.filter((m) => !ghosts.includes(m)));
    const pathSet = new Set([...cited.mechPaths, ...cited.codePaths]);
    let churnCommits = 0;
    for (const c of churnIdx) {
      if (c.date <= dataGit) continue;
      let hit = false;
      for (const m of c.mods) if (modSet.has(m)) { hit = true; break; }
      if (!hit) for (const p of pathSet) if (c.files.has(p)) { hit = true; break; }
      if (hit) churnCommits++;
    }

    const refsQuebradas = ghosts.length + phantoms.length + brokenLinks.length;
    const { score, penalidades } = computeScore({ idadeDias, churnCommits, refsQuebradas, refsTombstoned: tombed.length });
    rows.push({
      doc, score, penalidades, idadeDias, dataGit, dataDeclarada: declarada, churnCommits,
      refsQuebradas, quebradas: [...ghosts.map((g) => `Modules/${g}`), ...phantoms, ...brokenLinks].slice(0, 6),
      refsTombstoned: tombed.length, tombstoned: tombed.slice(0, 4),
    });
  }
  rows.sort((a, b) => a.score - b.score || b.churnCommits - a.churnCommits);
  return { hoje, since, rows };
}

// ── selftest (bite/release do NÚCLEO PURO — fixtures, sem FS/git) ─────────────
function selftest() {
  let fails = 0;
  const ok = (name, cond) => { console.log(`  ${cond ? '[OK]' : '[FAIL]'} ${name}`); if (!cond) fails++; };

  // BITE — doc velho-com-churn → score BAIXO (fixture pedida pelo roubo #5).
  const podre = computeScore({ idadeDias: 200, churnCommits: 25, refsQuebradas: 2, refsTombstoned: 0 });
  ok(`BITE: doc velho (200d) com churn (25 commits) + 2 refs quebradas → score baixo (${podre.score} < 50)`, podre.score < 50);

  // RELEASE — doc novo-sem-refs-quebradas → score ALTO.
  const fresco = computeScore({ idadeDias: 5, churnCommits: 0, refsQuebradas: 0, refsTombstoned: 0 });
  ok(`RELEASE: doc novo (5d) sem churn/quebradas → 100 (${fresco.score})`, fresco.score === 100);

  // ANTI-GAMING — data declarada "fresca" (idade 0) NÃO salva doc com sinais
  // não-declaráveis podres: 80/100 pontos são churn+refs+tombstone.
  const gamed = computeScore({ idadeDias: 0, churnCommits: 40, refsQuebradas: 6, refsTombstoned: 4 });
  ok(`ANTI-GAMING: idade 0 mas churn 40 + 6 quebradas + 4 aterradas → score ≤ 30 (${gamed.score})`, gamed.score <= 30);

  // MONOTONICIDADE — mais churn nunca melhora o score.
  const c1 = computeScore({ idadeDias: 60, churnCommits: 5, refsQuebradas: 0, refsTombstoned: 0 }).score;
  const c2 = computeScore({ idadeDias: 60, churnCommits: 20, refsQuebradas: 0, refsTombstoned: 0 }).score;
  ok(`MONOTONICIDADE: churn 20 ≤ churn 5 (${c2} ≤ ${c1})`, c2 <= c1);

  // EXTRAÇÃO — refs concretas saem, prosa fuzzy e lixo de link não.
  const refs = extractCitedRefs('Ver Modules/Jana e scripts/governance/foo.mjs, o [SPEC](../Jana/SPEC.md), .github/workflows/ci.yml, [ctl](../app/Http/X.php:185), app/Services/Y.php, [p](P1), [abs](C:/Users/w/x.md) e "o ragas-gate" solto em prosa.');
  ok('extractCitedRefs: pega Modules/Jana', refs.modules.includes('Jana'));
  ok('extractCitedRefs: mecanismo (mjs/yml) vai pro phantom-check', refs.mechPaths.includes('scripts/governance/foo.mjs') && refs.mechPaths.includes('.github/workflows/ci.yml'));
  ok('extractCitedRefs: app/resources é churn-only (nunca "quebrada" — path pode ser planejado)', refs.codePaths.includes('app/Services/Y.php') && !refs.mechPaths.includes('app/Services/Y.php'));
  ok('extractCitedRefs: pega link .md relativo e strippa sufixo :linha', refs.links.includes('../Jana/SPEC.md') && refs.links.includes('../app/Http/X.php'));
  ok('extractCitedRefs: descarta lixo de link (token "P1", path absoluto C:/)', !refs.links.some((l) => l === 'P1' || l.startsWith('C:')));
  ok('extractCitedRefs: NÃO inventa path de nome fuzzy em prosa', !refs.mechPaths.some((p) => p.includes('ragas')));

  // RESOLUÇÃO de link relativo (espelho Check V).
  ok('resolveRel: sobe ../ corretamente', resolveRel('memory/requisitos/Jana/SPEC.md', '../../decisions/0093-x.md') === 'memory/decisions/0093-x.md');

  // CONTRATO DE EXTENSÃO — o ghost-check vem do knowledge-drift (import vivo, não cópia).
  const phantom = classifyPathCitation('scripts/x/morto.mjs', { resolveExists: () => false, tombstones: new Map() });
  const tomb = classifyPathCitation('scripts/x/morto.mjs', { resolveExists: () => false, tombstones: new Map([['scripts/x/morto.mjs', { nome: 'scripts/x/morto.mjs', deletado_por_adr: '0271', substituto: '—' }]]) });
  ok('EXTENSÃO: classifyPathCitation importado do knowledge-drift morde (phantom) e solta (tombstoned)',
    phantom.status === 'phantom' && tomb.status === 'tombstoned');

  // IDADE anti-gaming — declarada mais velha que git PIORA; declarada "futura" não melhora.
  ok('dataDeclarada: extrai o maior carimbo', dataDeclarada('updated_at: 2026-05-01\n**Atualizado:** 2026-06-01') === '2026-06-01');

  console.log(fails
    ? `\n  ${fails} FALHA(S) — o radar de frescor não está honesto.\n`
    : `\n  SELFTEST OK — morde (podre) e solta (fresco); pesos anti-gaming valem.\n`);
  return fails ? 1 : 0;
}

// ── run (CLI) ────────────────────────────────────────────────────────────────
function run() {
  const JSON_OUT = process.argv.includes('--json');
  const { hoje, since, rows } = scan();
  const top10 = rows.slice(0, 10);

  if (JSON_OUT) {
    console.log(JSON.stringify({
      gate: 'doc-freshness-score',
      regua: 'Dosu — score 0-100 de frescor por doc, determinístico (sem LLM); agregador/radar, dentes = sentinelas específicas',
      pesos: PESOS, hoje, churn_window_since: since,
      avaliados: rows.length,
      top10_podres: top10,
      docs: rows,
    }, null, 2));
    return 0;
  }

  const banda = (s) => (s < 50 ? '🔴' : s < 80 ? '🟡' : '🟢');
  const nR = rows.filter((r) => r.score < 50).length;
  const nY = rows.filter((r) => r.score >= 50 && r.score < 80).length;

  console.log(`\n  RADAR DE FRESCOR POR DOC — score 0-100 (régua Dosu · determinístico · ${rows.length} docs · hoje=${hoje})`);
  console.log(`  pesos: churn ${PESOS.churn} + refs-quebradas ${PESOS.refs} + claims-aterradas ${PESOS.tombstone} (não-declaráveis) · idade ${PESOS.idade}`);
  console.log('  ' + '─'.repeat(96));
  console.log(`  ${'score'.padStart(5)}  ${'DOC'.padEnd(58)} ${'idade'.padStart(6)} ${'churn'.padStart(6)} ${'quebr'.padStart(6)}`);
  for (const r of rows.slice(0, 20)) {
    console.log(`  ${banda(r.score)} ${String(r.score).padStart(3)}  ${r.doc.padEnd(58)} ${String(r.idadeDias + 'd').padStart(6)} ${String(r.churnCommits).padStart(6)} ${String(r.refsQuebradas).padStart(6)}`);
    if (r.quebradas.length) console.log(`           └ quebradas: ${r.quebradas.join(' · ')}${r.tombstoned.length ? ` · aterradas: ${r.tombstoned.join(' · ')}` : ''}`);
  }
  if (rows.length > 20) console.log(`  … +${rows.length - 20} docs (use --json pra lista completa)`);
  console.log('  ' + '─'.repeat(96));
  console.log(`  🔴 ${nR} podres (<50) · 🟡 ${nY} envelhecendo (50-79) · 🟢 ${rows.length - nR - nY} frescos (80+)`);
  console.log('  RADAR advisory (ADR 0314) — a AÇÃO vem do dente específico (briefing-code-staleness /');
  console.log('  knowledge-drift / memory-health / visual-comparison-staleness); aqui só se vê ONDE olhar primeiro.\n');

  // Anotações GitHub — 5 piores, visíveis no PR (amarelo, non-blocking). Só em CI.
  if (process.env.GITHUB_ACTIONS === 'true') {
    for (const r of rows.slice(0, 5)) {
      if (r.score >= 80) continue; // top-5 frescos não viram warning
      console.log(`::warning title=doc podre (score ${r.score}/100)::${r.doc}: frescor ${r.score}/100 — idade ${r.idadeDias}d · ${r.churnCommits} commits na superfície citada desde a última edição · ${r.refsQuebradas} ref(s) quebrada(s)${r.refsTombstoned ? ` · ${r.refsTombstoned} claim(s) de mecanismo já aterrado` : ''}. Radar advisory: revisar/destilar o doc (o dente específico manda a ação).`);
    }
  }
  return 0; // advisory: NUNCA bloqueia
}

// ── main (só quando executado direto; importável sem rodar) ──────────────────
const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();

if (isMain) process.exit(process.argv.includes('--selftest') ? selftest() : run());
