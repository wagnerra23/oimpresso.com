#!/usr/bin/env node
// @ts-check
/**
 * baseline-tamper-guard.mjs — anti-grandfather (Gap 2 do blueprint SDD · ADR 0256/0258).
 *
 * Fecha o buraco que TODO ratchet-com-baseline compartilha: dá pra AFROUXAR o
 * baseline (subir teto / grandfatherar item novo) NO MESMO PR que mete o código
 * que o ratchet deveria pegar — a regressão entra verde. (Casos reais catalogados:
 * MemCofre #2848, ghost 14→16, ambos mergearam verdes mascarados pelo baseline.)
 *
 * Regra: se um baseline guardado foi AFROUXADO em relação à base do PR E o PR
 * também toca código (qualquer arquivo que NÃO seja um baseline guardado), FALHA
 * 🔴 — a não ser que (a) algum commit do range carregue o marcador `BASELINE-ABSORB`
 * (override consciente e auditável) OU (b) a mudança de baseline esteja ISOLADA
 * (PR só toca baselines guardados = curadoria deliberada, nada de código pra mascarar).
 *
 * Base do diff: --base <ref> · env BASE_SHA · env GITHUB_BASE_REF (origin/<ref>) ·
 *   fallback `git merge-base HEAD origin/main` · senão SKIP (sem base = nunca falha).
 *
 * Determinístico, sem LLM, sem deps. Roda em CI (PR toca baseline) e local.
 * Uso: node scripts/governance/baseline-tamper-guard.mjs [--base <ref>]
 */
import { readFileSync, existsSync, readdirSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';

const ROOT = process.cwd();
const argBase = (() => { const i = process.argv.indexOf('--base'); return i >= 0 ? process.argv[i + 1] : null; })();

function git(cmd) {
  try { return execSync(`git ${cmd}`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim(); }
  catch { return ''; }
}
const parseJson = (txt) => { try { return JSON.parse(txt); } catch { return null; } };

// ── detectores de afrouxamento por schema de baseline ────────────────────────
// Cada um recebe (base, head) já parseados e devolve lista de descrições do que
// foi afrouxado (subiu teto / grandfatherou item novo). Vazio = nada afrouxou.
function detectMemoryHealth(base, head) {
  const out = [];
  const b = base || {}, h = head || {};
  const bC = b.checkC || {}, hC = h.checkC || {};
  for (const [f, n] of Object.entries(hC)) {
    const prev = bC[f] || 0;
    if (n > prev) out.push(`checkC teto subiu: ${f} (${prev}→${n})`);
  }
  const bL = new Set(b.checkL || []);
  for (const s of (h.checkL || [])) if (!bL.has(s)) out.push(`checkL grandfatherado novo: ${s}`);
  const bN = new Set(b.checkN || []);
  for (const s of (h.checkN || [])) if (!bN.has(s)) out.push(`checkN grandfatherado novo: ${s}`); // ADR 0304
  return out;
}

// governance/sdd-scorecard-baseline.json — schema { metrics: { <name>: { value,
// direction:'up'|'down', armed } } }. Afrouxamento = (a) métrica ARMADA na base
// teve value regredido na direção contrária a `direction` (espelha a lógica `worse`
// de sdd-scorecard.mjs:300), OU (b) métrica que era armed:true virou armed:false
// (desarmar = afrouxar o teto). Métrica desarmada na base só conta se foi armada→não:
// regressão de value de desarmada é warn no ratchet próprio, não afrouxamento aqui.
function detectSddScorecard(base, head) {
  const out = [];
  const bM = (base && base.metrics) || {}, hM = (head && head.metrics) || {};
  for (const [name, b] of Object.entries(bM)) {
    if (b.armed !== true) continue; // só métricas armadas na base têm teto a afrouxar
    const h = hM[name];
    if (!h) { out.push(`métrica armada REMOVIDA: ${name}`); continue; }
    if (h.armed === false) out.push(`métrica DESARMADA (armed:true→false): ${name}`);
    if (typeof b.value === 'number' && typeof h.value === 'number') {
      const worse = b.direction === 'down' ? h.value > b.value : h.value < b.value;
      if (worse) out.push(`value regrediu em métrica armada: ${name} (${b.value}→${h.value}, só pode ${b.direction === 'down' ? 'DESCER' : 'SUBIR'})`);
    }
  }
  return out;
}

// Ratchet homogêneo { "path": <nº de violações toleradas> }: afrouxar = teto
// subiu numa key existente OU key nova grandfatherada com tolerância > 0.
// Compartilhado pelos baselines de mesmo schema (conformance cor-crua, fontramp,
// foundation-guard, dsih, scheme). Key removida ou teto que baixou = melhora (ok).
function detectCountRatchet(base, head) {
  const out = [];
  const b = base || {}, h = head || {};
  for (const [f, n] of Object.entries(h)) {
    if (typeof n !== 'number') continue;
    const prev = typeof b[f] === 'number' ? b[f] : 0;
    if (n > prev) out.push(`teto subiu: ${f} (${prev}→${n})`);
  }
  return out;
}

// governance/knowledge-ghosts-baseline/<Mod>.json — schema { module, ghosts:[...] }.
// Recebe (base, head) de UM módulo já parseados (a engine expande o diretório em
// arquivos — ver loop). Afrouxamento = ghost presente em head e ausente em base =
// grandfatherou ghost novo (exatamente o vetor #2848: ghost 14→16).
function detectKnowledgeGhosts(base, head) {
  const out = [];
  const mod = (head && head.module) || (base && base.module) || '?';
  const bG = new Set((base && base.ghosts) || []);
  for (const g of ((head && head.ghosts) || [])) if (!bG.has(g)) out.push(`grandfatherou ghost novo: ${mod}/${g}`);
  return out;
}

// governance/required-checks-baseline.json — schema { classic_protection.contexts:[],
// rulesets.contexts:[] }. Afrouxamento = contexto required presente na base e ausente
// no head = required REMOVIDO (demoção). SÓ compara contexts[] entrar/sair (nunca
// infere flags ausentes — o _meta.limitacao avisa que `strict` não é exposto no resumo
// público; inferir geraria falso-positivo — kill-criteria do P05).
function detectRequiredChecks(base, head) {
  const out = [];
  const ctxs = (o) => new Set([
    ...((o && o.classic_protection && o.classic_protection.contexts) || []),
    ...((o && o.rulesets && o.rulesets.contexts) || []),
  ]);
  const h = ctxs(head);
  for (const c of ctxs(base)) if (!h.has(c)) out.push(`required REMOVIDO (demoção): ${c}`);
  return out;
}

// scripts/casos-coverage-baseline.json — schema { _meta, violations:[string] } (ADR 0264
// G-1/G-2). Afrouxamento = violação presente no head e ausente na base = grandfatherou
// débito novo (tela sem trio / UC órfão absorvido). Mesma forma do detectKnowledgeGhosts:
// só ENTRAR violação conta (encolher = melhora). Fecha o furo do audit 2026-06-22 — o
// casos-baseline era um dos únicos ratchets sem anti-grandfather (a allowlist segue aberta).
function detectViolationList(base, head) {
  const out = [];
  const b = new Set((base && base.violations) || []);
  for (const v of ((head && head.violations) || [])) {
    if (!b.has(v)) out.push(`grandfatherou violação nova: ${v}`);
  }
  return out;
}

// Mapa path→detector. Estender = adicionar o baseline + seu detector aqui
// (e o path no trigger do workflow). Só guarda o que tem detector de schema:
// afrouxamento genérico em formato heterogêneo daria falso-positivo.
// Diretório de baselines (1 arquivo por item, ex.: knowledge-ghosts) entra com
// sufixo '/' — a engine expande em arquivos via git ls-tree + readdir (união dos
// dois lados: arquivo NOVO no head conta como base vazia).
const GUARDED = {
  'scripts/governance/.memory-health-baseline.json': detectMemoryHealth,
  // baselines SDD (P05 · fecha o vetor #2848 grandfather)
  'governance/sdd-scorecard-baseline.json': detectSddScorecard,
  'governance/required-checks-baseline.json': detectRequiredChecks,
  'governance/knowledge-ghosts-baseline/': detectKnowledgeGhosts,
  // baselines-ratchet de UI/cor-crua (main · ratchet homogêneo { path: nº })
  '.conformance-baseline.json': detectCountRatchet,
  '.fontramp-baseline.json': detectCountRatchet,
  '.foundation-guard-baseline.json': detectCountRatchet,
  '.dsih-baseline.json': detectCountRatchet,
  '.scheme-baseline.json': detectCountRatchet,
  // catraca de refs de Page Charter (ceiling único → ratchet homogêneo)
  'governance/charter-refs-baseline.json': detectCountRatchet,
  // catraca de cobertura de casos (trio-de-tela + rastreabilidade UC↔teste · ADR 0264)
  'scripts/casos-coverage-baseline.json': detectViolationList,
};

// ── resolver base do diff ─────────────────────────────────────────────────────
function resolveBase() {
  if (argBase) return argBase;
  if (process.env.BASE_SHA) return process.env.BASE_SHA;
  if (process.env.GITHUB_BASE_REF) {
    git(`fetch origin ${process.env.GITHUB_BASE_REF} --depth=100`);
    return `origin/${process.env.GITHUB_BASE_REF}`;
  }
  return git('merge-base HEAD origin/main') || null;
}

// ── run ────────────────────────────────────────────────────────────────────────
const BASE = resolveBase();
if (!BASE) {
  console.log('baseline-tamper-guard: sem base determinável (não é PR / sem origin/main) — SKIP.');
  process.exit(0);
}

// Expande as entradas de GUARDED em arquivos concretos: path simples = ele mesmo;
// path-diretório (termina em '/') = união dos *.json do head (readdir) com os do
// base (git ls-tree) — arquivo NOVO em qualquer lado entra (base ausente = {}).
function expandGuarded() {
  const files = []; // { file, detector }
  for (const [key, detector] of Object.entries(GUARDED)) {
    if (!key.endsWith('/')) { files.push({ file: key, detector }); continue; }
    const set = new Set();
    if (existsSync(join(ROOT, key))) {
      for (const f of readdirSync(join(ROOT, key))) if (f.endsWith('.json')) set.add(key + f);
    }
    for (const line of git(`ls-tree --name-only ${BASE} ${key}`).split('\n').map((s) => s.trim()).filter(Boolean)) {
      if (line.endsWith('.json')) set.add(line);
    }
    for (const f of set) files.push({ file: f, detector });
  }
  return files;
}

const loosenings = [];
for (const { file: path, detector } of expandGuarded()) {
  const baseTxt = git(`show ${BASE}:${path}`);
  const base = baseTxt ? parseJson(baseTxt) : {}; // ausente na base = {} (tudo conta como novo)
  if (!existsSync(join(ROOT, path))) continue; // baseline removido do head: não é afrouxamento desta engine
  const head = parseJson(readFileSync(join(ROOT, path), 'utf8'));
  if (head === null) { console.error(`baseline-tamper-guard: ${path} não parseia como JSON.`); process.exit(1); }
  for (const desc of detector(base, head)) loosenings.push(`${path} — ${desc}`);
}

if (!loosenings.length) {
  console.log(`baseline-tamper-guard: nenhum baseline guardado afrouxado vs ${BASE}. ✓`);
  process.exit(0);
}

// Houve afrouxamento — está pareado com código (suspeito) ou isolado/justificado?
const changed = git(`diff --name-only ${BASE}..HEAD`).split('\n').map((s) => s.trim()).filter(Boolean);
// "guardado" = arquivo-baseline exato OU arquivo sob um diretório-baseline (sufixo '/').
const guardedFiles = new Set(Object.keys(GUARDED).filter((k) => !k.endsWith('/')));
const guardedDirs = Object.keys(GUARDED).filter((k) => k.endsWith('/'));
const isGuarded = (f) => guardedFiles.has(f) || guardedDirs.some((d) => f.startsWith(d));
const codeTouched = changed.filter((f) => !isGuarded(f));
// Marcador só vale no commit que DE FATO toca um baseline guardado — não em
// qualquer commit do range que mencione o token (senão o próprio commit que
// implementa/documenta o guard auto-justificaria: falso-negativo "o gate se
// auto-aceita"). E exige o token ancorado em início de linha (trailer), não em prosa.
const guardedPaths = Object.keys(GUARDED).join(' ');
const baselineCommits = git(`log ${BASE}..HEAD --format=%H -- ${guardedPaths}`).split('\n').filter(Boolean);
let hasMarker = false;
for (const sha of baselineCommits) {
  if (/^[ \t]*BASELINE-ABSORB\b/m.test(git(`log -1 --format=%B ${sha}`))) { hasMarker = true; break; }
}

console.log(`baseline-tamper-guard: ${loosenings.length} afrouxamento(s) vs ${BASE}:`);
loosenings.forEach((l) => console.log(`  - ${l}`));

if (codeTouched.length && !hasMarker) {
  console.error('\n✗ baseline-tamper-guard: baseline AFROUXADO no mesmo PR que toca código,');
  console.error(`  sem o marcador \`BASELINE-ABSORB\`. ${codeTouched.length} arquivo(s) de código no diff:`);
  codeTouched.slice(0, 10).forEach((f) => console.error(`     - ${f}`));
  console.error('\n  Isso é o vetor que deixa regressão entrar verde (ex: #2848, ghost 14→16). Opções:');
  console.error('   1. Isole a mudança de baseline num PR/commit SÓ de baseline (sem código), OU');
  console.error('   2. Justifique conscientemente: inclua `BASELINE-ABSORB: <motivo>` na mensagem de commit.');
  process.exit(1);
}

console.log(hasMarker
  ? '\n✓ afrouxamento justificado por `BASELINE-ABSORB` (override consciente e auditável).'
  : '\n✓ afrouxamento isolado (PR só toca baseline guardado — curadoria deliberada, sem código pra mascarar).');
process.exit(0);
