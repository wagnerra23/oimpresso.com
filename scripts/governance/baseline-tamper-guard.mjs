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
import { readFileSync, existsSync } from 'node:fs';
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
  return out;
}

// charter-refs-baseline.json: { ceiling: N }. Afrouxar = subir o teto.
function detectCharterRefs(base, head) {
  const prev = (base || {}).ceiling ?? 0;
  const now = (head || {}).ceiling ?? 0;
  return now > prev ? [`charter_refs_broken teto subiu: ${prev}→${now}`] : [];
}

// Mapa path→detector. Estender = adicionar o baseline + seu detector aqui
// (e o path no trigger do workflow). Só guarda o que tem detector de schema:
// afrouxamento genérico em formato heterogêneo daria falso-positivo.
const GUARDED = {
  'scripts/governance/.memory-health-baseline.json': detectMemoryHealth,
  'governance/charter-refs-baseline.json': detectCharterRefs,
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

const loosenings = [];
for (const [path, detector] of Object.entries(GUARDED)) {
  if (!existsSync(join(ROOT, path))) continue;
  const baseTxt = git(`show ${BASE}:${path}`);
  const base = baseTxt ? parseJson(baseTxt) : {}; // ausente na base = {} (tudo conta como novo)
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
const guardedSet = new Set(Object.keys(GUARDED));
const codeTouched = changed.filter((f) => !guardedSet.has(f));
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
