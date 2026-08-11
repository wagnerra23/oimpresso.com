#!/usr/bin/env node
// @ts-check
/**
 * screen-grades-ratchet.mjs — catraca anti-regressão da nota por tela.
 *
 * Espelha o `module-grades-gate` (ADR 0155): compara a nota de cada scorecard de
 * tela no PR contra a nota em `origin/main` e BLOQUEIA (exit 1) se alguma cair.
 * VETORES DE BURLA COBERTOS (enumerar é o ponto — dizer "robusto contra burla"
 * sem dizer contra o quê foi o defeito medido em 2026-08-10, §5 "Catraca que
 * itera o LADO DO PR"):
 *   1. BAIXAR o valor  — compara sempre vs `origin/main`, nunca vs o
 *      `baseline_anterior` do próprio arquivo, que o PR poderia baixar junto.
 *   2. APAGAR o item   — o laço principal itera `readdirSync` do PR, então o
 *      arquivo deletado nunca entrava nele. Coberto por `classificarDelecoes`,
 *      que decide por FATO (o `path:` do scorecard) e não por heurística.
 * NÃO cobertos: renomear o scorecard, e isentar via `SCREEN_RATCHET_ALLOW_REGRESSION`.
 *
 * Regra (catraca = nota só sobe):
 *   - nota(PR) <  nota(main)   → REGRESSÃO → bloqueia
 *   - nota(PR) >= nota(main)   → ok (subiu ou estável)
 *   - tela nova (ausente em main) → ok, vira o novo baseline
 *
 * Override (Wagner aprova regressão consciente): variável de ambiente
 *   SCREEN_RATCHET_ALLOW_REGRESSION=1  (espelha o label do module-grades-gate).
 *
 * Pré-req no CI: actions/checkout fetch-depth: 0 (precisa de origin/main).
 *
 * Uso:
 *   node scripts/qa/screen-grades-ratchet.mjs
 */

import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';

const ROOT = process.cwd();
const DIR = join(ROOT, 'memory', 'governance', 'scorecards', 'screens');
const REL = 'memory/governance/scorecards/screens';
const ALLOW = process.env.SCREEN_RATCHET_ALLOW_REGRESSION === '1';
// Ref de baseline (default origin/main). Configurável pra teste local.
const BASE_REF = process.env.SCREEN_RATCHET_BASE_REF || 'origin/main';

/** Lê `nota:` de um YAML de scorecard (formato controlado pelo seed). */
const parseNota = (text) => {
  const m = text.match(/^nota:\s*(\d+)/m);
  return m ? parseInt(m[1], 10) : null;
};

/** Nota da versão em origin/main, ou null se a tela é nova. */
function notaInMain(relPath) {
  try {
    const out = execSync(`git show ${BASE_REF}:${relPath}`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
    return parseNota(out);
  } catch {
    return null; // ausente em main → tela nova
  }
}

/**
 * Vetor 2 — DELEÇÃO (o buraco medido em 2026-08-10, §5 "Catraca que itera o LADO DO PR").
 *
 * O laço principal itera `readdirSync` do PR: arquivo deletado NUNCA entra nele,
 * então apagar um scorecard passava por baixo da catraca. A promessa "robusto
 * contra burla" do cabeçalho cobria só o vetor de BAIXAR `baseline_anterior`.
 *
 * A distinção NÃO é heurística — o scorecard declara `path:`:
 *   - sumiu o YAML **e** o `.tsx` daquele path também  → tela removida, LEGÍTIMO (cala)
 *   - sumiu o YAML **e** o `.tsx` continua vivo         → vetor de fuga (acusa)
 *
 * FP medido no histórico completo antes de armar (regra "LIGUE A MÁQUINA" item 4):
 * 258 deleções de scorecard, 258 com o `.tsx` morto junto ⇒ **0 falso-positivo**.
 * (E 0 verdadeiro-positivo: o vetor nunca foi usado — isto é defesa preventiva.)
 *
 * NÚCLEO PURO + injeção, pra o selftest poder exercitar sem git. Mas assert sobre
 * helper puro NÃO prova o pipeline (§5 2026-07-30) — por isso o `--selftest`
 * também roda um bite-test E2E contra um repo git de verdade.
 */
export function classificarDelecoes({ naBase, noPr, pathDe, tsxVivo }) {
  const noPrSet = new Set(noPr);
  const fuga = [];
  let legitimas = 0;
  let semPath = 0;
  for (const f of naBase) {
    if (noPrSet.has(f)) continue; // não foi deletado
    const p = pathDe(f);
    if (!p) { semPath++; continue; } // sem `path:` declarado → não dá pra decidir; não acusa
    if (tsxVivo(p)) fuga.push({ file: f, path: p });
    else legitimas++;
  }
  return { fuga, legitimas, semPath };
}

/** Lista os scorecards que existem na ref de base. */
function scorecardsNaBase() {
  try {
    const out = execSync(`git ls-tree -r --name-only ${BASE_REF} -- ${REL}/`, {
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
    });
    return out.split('\n').map((l) => l.trim()).filter((l) => l.endsWith('.yaml')).map((l) => l.split('/').pop());
  } catch {
    return null; // não consegui perguntar — ver guard de cegueira abaixo
  }
}

/** Conteúdo do scorecard na ref de base (pra ler o `path:` do que foi deletado). */
function textoNaBase(f) {
  try {
    return execSync(`git show ${BASE_REF}:${REL}/${f}`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
  } catch {
    return '';
  }
}

const parsePath = (text) => (text.match(/^path:\s*(.+)$/m) || [])[1]?.trim() || null;

// ── --selftest ────────────────────────────────────────────────────────────────
if (process.argv.includes('--selftest')) {
  const { mkdtempSync, writeFileSync, mkdirSync, rmSync } = await import('node:fs');
  const { tmpdir } = await import('node:os');
  let falhas = 0;
  const ok = (cond, nome) => { console.log(`${cond ? '  ✓' : '  ✗'} ${nome}`); if (!cond) falhas++; };

  console.log('\n[núcleo puro] classificarDelecoes');
  // BITE: yaml sumiu, .tsx vivo → acusa
  let r = classificarDelecoes({ naBase: ['a.yaml'], noPr: [], pathDe: () => 'p/A.tsx', tsxVivo: () => true });
  ok(r.fuga.length === 1 && r.legitimas === 0, 'BITE: yaml deletado + .tsx VIVO → acusa fuga');
  // CN 1: yaml sumiu, .tsx morto → cala (é a remoção legítima de tela)
  r = classificarDelecoes({ naBase: ['a.yaml'], noPr: [], pathDe: () => 'p/A.tsx', tsxVivo: () => false });
  ok(r.fuga.length === 0 && r.legitimas === 1, 'CN: yaml deletado + .tsx morto → LEGÍTIMO, não acusa');
  // CN 2: nada deletado → cala mesmo com .tsx vivo
  r = classificarDelecoes({ naBase: ['a.yaml'], noPr: ['a.yaml'], pathDe: () => 'p/A.tsx', tsxVivo: () => true });
  ok(r.fuga.length === 0, 'CN: nada deletado → não acusa');
  // CN 3: sem `path:` → não decide, não acusa
  r = classificarDelecoes({ naBase: ['a.yaml'], noPr: [], pathDe: () => null, tsxVivo: () => true });
  ok(r.fuga.length === 0 && r.semPath === 1, 'CN: scorecard sem `path:` → não acusa (não dá pra decidir)');

  console.log('\n[E2E] bite-test com git de verdade (helper puro não prova pipeline)');
  const tmp = mkdtempSync(join(tmpdir(), 'ratchet-'));
  const sh = (c) => execSync(c, { cwd: tmp, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
  try {
    mkdirSync(join(tmp, REL), { recursive: true });
    mkdirSync(join(tmp, 'p'), { recursive: true });
    writeFileSync(join(tmp, REL, 'a.yaml'), 'screen: A\npath: p/A.tsx\nnota: 80\n');
    writeFileSync(join(tmp, REL, 'b.yaml'), 'screen: B\npath: p/B.tsx\nnota: 74\n');
    writeFileSync(join(tmp, 'p', 'A.tsx'), 'x');
    writeFileSync(join(tmp, 'p', 'B.tsx'), 'x');
    sh('git init -q . && git config user.email t@t && git config user.name t');
    sh('git add -A && git commit -qm base && git branch -f base');
    const rodar = () => {
      try {
        execSync(`node "${join(ROOT, 'scripts', 'qa', 'screen-grades-ratchet.mjs')}"`, {
          cwd: tmp, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'],
          env: { ...process.env, SCREEN_RATCHET_BASE_REF: 'base', SCREEN_RATCHET_ALLOW_REGRESSION: '' },
        });
        return 0;
      } catch (e) { return e.status ?? 1; }
    };
    ok(rodar() === 0, 'E2E CN: árvore intacta → exit 0');
    rmSync(join(tmp, REL, 'b.yaml')); // deleta o scorecard, DEIXA o .tsx vivo
    ok(rodar() === 1, 'E2E BITE: scorecard deletado com .tsx VIVO → exit 1');
    rmSync(join(tmp, 'p', 'B.tsx')); // agora a tela morreu junto → legítimo
    ok(rodar() === 0, 'E2E CN: scorecard + .tsx deletados juntos → exit 0 (remoção legítima)');
  } finally {
    try { rmSync(tmp, { recursive: true, force: true }); } catch { /* tmp */ }
  }

  console.log(falhas ? `\n✗ selftest: ${falhas} falha(s)` : '\n✓ selftest: tudo verde');
  process.exit(falhas ? 1 : 0);
}

if (!existsSync(DIR)) {
  console.error(`✗ ${REL} não existe — rode screen-grade-seed.mjs primeiro.`);
  process.exit(2);
}

const files = readdirSync(DIR).filter((f) => f.endsWith('.yaml'));
const regress = [];
let novas = 0,
  ok = 0;

for (const f of files) {
  const cur = parseNota(readFileSync(join(DIR, f), 'utf8'));
  if (cur === null) continue;
  const base = notaInMain(`${REL}/${f}`);
  if (base === null) {
    novas++;
    continue;
  }
  if (cur < base) regress.push({ file: f, base, cur, delta: cur - base });
  else ok++;
}

// ── Vetor 2: deleção de scorecard com a tela ainda VIVA ──────────────────────
const naBase = scorecardsNaBase();
let del = { fuga: [], legitimas: 0, semPath: 0 };
if (naBase !== null) {
  del = classificarDelecoes({
    naBase,
    noPr: files,
    pathDe: (f) => parsePath(textoNaBase(f)),
    tsxVivo: (p) => existsSync(join(ROOT, p)),
  });
}

const sufixoDel = naBase === null
  ? ' · ⛔ deleções NÃO medidas'
  : ` · 🗑 ${del.legitimas} deleção(ões) legítima(s)${del.fuga.length ? ` · 🚨 ${del.fuga.length} suspeita(s)` : ''}`;

console.log(`\nCatraca screen-grade · ${files.length} telas · ✅ ${ok} ok/subiu · ✨ ${novas} novas · 🔻 ${regress.length} regrediram${sufixoDel}`);

if (naBase === null) {
  // Não consegui listar a base: não posso afirmar que nada foi deletado.
  console.error(`\n⛔ CEGO no vetor de deleção: não consegui listar ${REL}/ em ${BASE_REF} (falta fetch? shallow?).`);
  console.error('   O eixo de NOTA acima foi medido; o de DELEÇÃO não — e "não medi" ≠ "nada sumiu".');
  process.exit(1);
}

if (del.fuga.length) {
  console.log('\nScorecard deletado com a TELA AINDA VIVA (fuga da catraca):');
  for (const d of del.fuga) console.log(`  🚨 ${d.file} sumiu, mas ${d.path} continua no repo`);
  console.log('  → Se a tela morreu, apague o .tsx no mesmo PR. Se a tela vive, o scorecard tem que ficar.');
  if (!ALLOW) {
    console.error('\n✗ CATRACA: deleção de scorecard sem a tela morrer junto. (override: SCREEN_RATCHET_ALLOW_REGRESSION=1)');
    process.exit(1);
  }
  console.log('\n⚠️  SCREEN_RATCHET_ALLOW_REGRESSION=1 — deleção consciente autorizada.');
}

if (regress.length) {
  console.log('\nRegressões (nota caiu vs origin/main):');
  for (const r of regress.sort((a, b) => a.delta - b.delta)) {
    console.log(`  🔻 ${r.file}: ${r.base} → ${r.cur} (${r.delta})`);
  }
  if (ALLOW) {
    console.log('\n⚠️  SCREEN_RATCHET_ALLOW_REGRESSION=1 — regressão consciente autorizada. PASS.');
    process.exit(0);
  }
  console.error('\n✗ CATRACA: nota de tela caiu. PR bloqueado. (override: SCREEN_RATCHET_ALLOW_REGRESSION=1)');
  process.exit(1);
}

console.log('\n✓ CATRACA: nenhuma tela regrediu.');
