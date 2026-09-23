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
 * O QUE O PR MUDOU se mede contra o MERGE-BASE, não contra a ponta de `origin/main`
 * (§5 2026-09-15, eixo BASE-DE-PR). Comparar a árvore do PR com a ponta fazia todo
 * scorecard nascido em `main` DEPOIS do fork aparecer como "deletado" — e toda nota
 * que `main` subiu depois aparecer como "regressão". Medido em 2026-09-23 no PR #7766:
 * o merge ref foi calculado às 12:03:58, o job esperou 17 min na fila, buscou
 * `origin/main` às 12:21 e acusou 18 scorecards que o PR nunca tocou. Nem o merge ref
 * do `pull_request` protege — a fila basta. Por isso:
 *   - deleção  = `git diff --diff-filter=D <merge-base>` (o que ESTE PR apagou);
 *   - regressão = nota(PR) < nota(origin/main), SÓ em scorecard presente nos dois lados
 *     E alterado pelo PR vs o merge-base (o que o PR não tocou é herdado de `main`).
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

// A regra de "deleção legítima × fuga" mora em scripts/lib/ desde 2026-09-09: este arquivo
// executa a catraca no top-level, então quem importasse a função daqui rodaria este gate junto.
// Re-exportada para não quebrar consumidor que já a importava deste módulo.
import { classificarDelecoes } from '../lib/delecao-legitima.mjs';
export { classificarDelecoes };

/** Merge-base entre BASE_REF e HEAD, ou null se não deu pra calcular (→ cego, exit 1). */
function mergeBase() {
  try {
    const mb = execSync(`git merge-base ${BASE_REF} HEAD`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
    return mb || null;
  } catch {
    return null;
  }
}

/**
 * Scorecards que o PR mudou vs o merge-base, filtrados por status git (`D` = apagados,
 * `d` minúsculo = tudo MENOS apagado). Compara o merge-base com o WORKING TREE (sem
 * `HEAD`), pra valer também localmente com mudança não-commitada (§5 2026-08-20).
 * `--no-renames`: rename vira D+A e o D segue acusável — renomear continua fora da
 * cobertura, como o cabeçalho declara. null = o git falhou (não-medi ≠ nada mudou).
 */
function mudadosVsMergeBase(mb, filtro) {
  try {
    const out = execSync(`git diff --no-renames --diff-filter=${filtro} --name-only ${mb} -- ${REL}/`, {
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
    });
    return out.split('\n').map((l) => l.trim()).filter((l) => l.endsWith('.yaml')).map((l) => l.split('/').pop());
  } catch {
    return null; // não consegui perguntar — ver guard de cegueira abaixo
  }
}

/** Conteúdo do scorecard numa ref (o merge-base) — pra ler o `path:` do que foi deletado. */
function textoNaRef(ref, f) {
  try {
    return execSync(`git show ${ref}:${REL}/${f}`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
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

  // Eixo BASE-DE-PR (PR #7766, 2026-09-23): branch ATRASADA — `main` ganhou scorecard e
  // subiu nota DEPOIS do fork. Comparar com a ponta acusava as duas coisas; o PR não fez nenhuma.
  console.log('\n[E2E] branch atrasada vs main que andou (merge-base, não a ponta)');
  const tmp2 = mkdtempSync(join(tmpdir(), 'ratchet-fork-'));
  const sh2 = (c) => execSync(c, { cwd: tmp2, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
  try {
    mkdirSync(join(tmp2, REL), { recursive: true });
    mkdirSync(join(tmp2, 'p'), { recursive: true });
    writeFileSync(join(tmp2, REL, 'a.yaml'), 'screen: A\npath: p/A.tsx\nnota: 80\n');
    writeFileSync(join(tmp2, REL, 'b.yaml'), 'screen: B\npath: p/B.tsx\nnota: 74\n');
    for (const t of ['A', 'B', 'C']) writeFileSync(join(tmp2, 'p', `${t}.tsx`), 'x'); // C.tsx vivo, SEM scorecard ainda
    sh2('git init -q . && git config user.email t@t && git config user.name t');
    sh2('git add -A && git commit -qm fork && git branch -f pr');
    // `main` anda: nasce c.yaml (tela C já viva) e a nota de A sobe 80→90.
    writeFileSync(join(tmp2, REL, 'c.yaml'), 'screen: C\npath: p/C.tsx\nnota: 70\n');
    writeFileSync(join(tmp2, REL, 'a.yaml'), 'screen: A\npath: p/A.tsx\nnota: 90\n');
    sh2('git add -A && git commit -qm main-andou && git branch -f base');
    sh2('git checkout -q pr'); // PR parado no fork: sem c.yaml, A ainda com 80
    const rodar2 = () => {
      try {
        execSync(`node "${join(ROOT, 'scripts', 'qa', 'screen-grades-ratchet.mjs')}"`, {
          cwd: tmp2, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'],
          env: { ...process.env, SCREEN_RATCHET_BASE_REF: 'base', SCREEN_RATCHET_ALLOW_REGRESSION: '' },
        });
        return 0;
      } catch (e) { return e.status ?? 1; }
    };
    ok(rodar2() === 0, 'E2E CN: branch atrasada que NÃO apaga nada → exit 0 (c.yaml nasceu em main, A subiu em main)');
    rmSync(join(tmp2, REL, 'b.yaml'));
    sh2('git add -A && git commit -qm apaga-b');
    ok(rodar2() === 1, 'E2E BITE: mesma branch atrasada APAGA b.yaml com B.tsx vivo → exit 1');
    sh2('git revert --no-edit HEAD');
    writeFileSync(join(tmp2, REL, 'a.yaml'), 'screen: A\npath: p/A.tsx\nnota: 60\n');
    sh2('git add -A && git commit -qm baixa-a');
    ok(rodar2() === 1, 'E2E BITE: branch atrasada que ALTERA a.yaml e fica abaixo de main → exit 1');
  } finally {
    try { rmSync(tmp2, { recursive: true, force: true }); } catch { /* tmp */ }
  }

  console.log(falhas ? `\n✗ selftest: ${falhas} falha(s)` : '\n✓ selftest: tudo verde');
  process.exit(falhas ? 1 : 0);
}

if (!existsSync(DIR)) {
  console.error(`✗ ${REL} não existe — rode screen-grade-seed.mjs primeiro.`);
  process.exit(2);
}

const files = readdirSync(DIR).filter((f) => f.endsWith('.yaml'));

// O que ESTE PR mudou, medido contra o merge-base (ver cabeçalho — a ponta mente).
const MB = mergeBase();
const deletados = MB ? mudadosVsMergeBase(MB, 'D') : null;
const alteradosLista = MB ? mudadosVsMergeBase(MB, 'd') : null;
const cego = deletados === null || alteradosLista === null;
const alterados = new Set(alteradosLista ?? []);

const regress = [];
let novas = 0,
  ok = 0,
  herdadas = 0;

for (const f of files) {
  const cur = parseNota(readFileSync(join(DIR, f), 'utf8'));
  if (cur === null) continue;
  const base = notaInMain(`${REL}/${f}`);
  if (base === null) {
    novas++;
    continue;
  }
  if (cur >= base) ok++;
  // O PR não tocou este scorecard: a nota "menor" é a de antes do fork, quem subiu foi `main`.
  else if (!cego && !alterados.has(f)) herdadas++;
  else regress.push({ file: f, base, cur, delta: cur - base });
}

// ── Vetor 2: deleção de scorecard com a tela ainda VIVA ──────────────────────
// Universo = o que ESTE PR apagou vs o merge-base, não a diferença de árvore contra a ponta.
let del = { fuga: [], legitimas: 0, semPath: 0 };
if (!cego) {
  del = classificarDelecoes({
    naBase: deletados,
    noPr: files,
    pathDe: (f) => parsePath(textoNaRef(MB, f)),
    tsxVivo: (p) => existsSync(join(ROOT, p)),
  });
}

const sufixoDel = cego
  ? ' · ⛔ deleções NÃO medidas'
  : ` · 🗑 ${del.legitimas} deleção(ões) legítima(s)${del.fuga.length ? ` · 🚨 ${del.fuga.length} suspeita(s)` : ''}`;
const sufixoHerd = herdadas ? ` · ↪ ${herdadas} subiu/subiram em main depois do fork (herdado)` : '';

console.log(`\nCatraca screen-grade · ${files.length} telas · ✅ ${ok} ok/subiu · ✨ ${novas} novas · 🔻 ${regress.length} regrediram${sufixoDel}${sufixoHerd}`);

if (cego) {
  // Não consegui calcular o que o PR mudou: não posso afirmar que nada foi deletado.
  console.error(`\n⛔ CEGO: não consegui calcular o merge-base com ${BASE_REF} ou o diff de ${REL}/ (falta fetch? shallow?).`);
  console.error('   Deleção não medida, e regressão de nota não pôde ser separada de herança — "não medi" ≠ "nada sumiu".');
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
