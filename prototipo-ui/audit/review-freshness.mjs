#!/usr/bin/env node
// review-freshness.mjs — gatilho de FRESCOR do review por tela (gap #3 da fila Cowork).
//
// Pergunta canônica (COWORK_NOTES → "Gerador design:review", resposta direta ao [W]
// "qual teste/automatização?"): toda `Pages/<Mod>/<Tela>.tsx` com charter `status: live`
// tem um `<Tela>.review.md` cujo `measured_against_sha` == sha do ÚLTIMO commit que tocou
// o `.tsx`? Senão = **missing** (sem review) ou **stale** (review velho vs a tela mudou).
//
// Espelha em NODE a lógica do Pest `DesignReviewFreshnessTest` (testes rodam no CT 100,
// não localmente — feedback #2076). Roda local: é a EVIDÊNCIA reproduzível por terceiro.
//
// Ratchet (mesmo padrão de `config/eslint-baseline.json`, ADR 0209): a dívida pré-existente
// vive em `review-freshness-baseline.json`; o gate só falha por uma tela NOVA fora do baseline
// (anti-regressão), nunca pela dívida herdada. `stale` é ADVISORY na v1 (reviews legados de
// 2026-05-17 não têm o campo sha) — vira HARD quando regenerados (proposta ADR).
//
// Uso:
//   node prototipo-ui/audit/review-freshness.mjs               # checa (exit 1 se regressão)
//   node prototipo-ui/audit/review-freshness.mjs --json        # saída JSON
//   node prototipo-ui/audit/review-freshness.mjs --write-baseline  # (re)grava o baseline

import { readFileSync, writeFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { join, dirname, basename, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execSync } from 'node:child_process';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = join(HERE, '..', '..');
const PAGES = join(REPO, 'resources/js/Pages');
const BASELINE = join(HERE, 'review-freshness-baseline.json');
const norm = (p) => p.replace(/\\/g, '/');
const argv = process.argv.slice(2);
const FLAG = (f) => argv.includes(f);

function gitSha(absFile) {
  try {
    const rel = norm(relative(REPO, absFile));
    return execSync(`git log -1 --format=%h -- "${rel}"`, { cwd: REPO }).toString().trim() || 'sem-commit';
  } catch { return 'sem-commit'; }
}

function fmField(src, key) {
  const m = src.match(/^---\s*\n([\s\S]*?)\n---/);
  if (!m) return null;
  const r = m[1].match(new RegExp(`^${key}:\\s*(.*)$`, 'm'));
  return r ? r[1].trim() : null;
}

function walkCharters(dir, acc = []) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) walkCharters(p, acc);
    else if (name.endsWith('.charter.md')) acc.push(p);
  }
  return acc;
}

// classifica cada tela live em fresh / stale / missing.
function audit() {
  const fresh = [], stale = [], missing = [];
  for (const ch of walkCharters(PAGES)) {
    const src = readFileSync(ch, 'utf8');
    if (fmField(src, 'status') !== 'live') continue; // só telas live
    const dir = dirname(ch);
    const base = basename(ch, '.charter.md');
    const tsx = join(dir, `${base}.tsx`);
    if (!existsSync(tsx)) continue; // charter órfã sem tela — fora de escopo deste gate
    const screen = norm(relative(PAGES, join(dir, base)));
    const review = join(dir, `${base}.review.md`);
    if (!existsSync(review)) { missing.push(screen); continue; }
    const recorded = fmField(readFileSync(review, 'utf8'), 'measured_against_sha');
    const last = gitSha(tsx);
    if (recorded && recorded === last) fresh.push(screen);
    else stale.push({ screen, recorded: recorded || '(sem campo)', last });
  }
  return { fresh: fresh.sort(), stale: stale.sort((a, b) => a.screen.localeCompare(b.screen)), missing: missing.sort() };
}

function loadBaseline() {
  if (!existsSync(BASELINE)) return { missing: [], stale_advisory_count: 0 };
  try { return JSON.parse(readFileSync(BASELINE, 'utf8')); } catch { return { missing: [], stale_advisory_count: 0 }; }
}

function main() {
  const res = audit();

  if (FLAG('--write-baseline')) {
    let sha = 'unknown';
    try { sha = execSync('git rev-parse --short HEAD', { cwd: REPO }).toString().trim(); } catch {}
    const baseline = {
      _doc: 'Ratchet de frescor de review por tela (espelha config/eslint-baseline.json · ADR 0209). '
        + 'Dívida HERDADA de reviews ausentes em telas `status: live`. O gate (review-freshness.mjs / '
        + 'DesignReviewFreshnessTest) só FALHA por uma tela live NOVA fora desta lista. A lista só ENCOLHE: '
        + 'ao gerar o review (review-gen.mjs), rode --write-baseline pra podar. NUNCA adicionar tela à mão.',
      generated_against_sha: sha,
      missing: res.missing,
      stale_advisory_count: res.stale.length,
    };
    writeFileSync(BASELINE, JSON.stringify(baseline, null, 2) + '\n', 'utf8');
    console.log(`[freshness] baseline gravado: ${res.missing.length} missing (herdado) · ${res.stale.length} stale (advisory) · sha ${sha}`);
    return;
  }

  const baseline = loadBaseline();
  const baselineMissing = new Set(baseline.missing || []);
  const newMissing = res.missing.filter((s) => !baselineMissing.has(s)); // regressão = missing fora do baseline

  if (FLAG('--json')) {
    console.log(JSON.stringify({ ...res, new_missing: newMissing, baseline_missing: baseline.missing || [] }, null, 2));
  } else {
    console.log(`[freshness] live: ${res.fresh.length} fresh · ${res.stale.length} stale (advisory) · ${res.missing.length} missing (${baselineMissing.size} no baseline)`);
    if (res.fresh.length) console.log(`  fresh: ${res.fresh.join(', ')}`);
    if (res.stale.length) console.log(`  stale (ADVISORY — regenerar via review-gen.mjs): ${res.stale.map((s) => `${s.screen}[${s.recorded}≠${s.last}]`).join(', ')}`);
    if (newMissing.length) {
      console.error(`\n[freshness] ✗ FALHA: ${newMissing.length} tela(s) live SEM review e FORA do baseline (regressão):`);
      for (const s of newMissing) console.error(`  - ${s} → rode: node prototipo-ui/audit/review-gen.mjs ${s}`);
    }
  }

  if (newMissing.length) process.exit(1);
  console.log('[freshness] OK — nenhuma regressão de frescor (missing ⊆ baseline).');
}

main();
