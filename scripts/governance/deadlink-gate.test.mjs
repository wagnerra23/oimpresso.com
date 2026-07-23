#!/usr/bin/env node
// Teste de regressao do deadlink-gate (integridade referencial doc↔doc, ADR 0256).
// Prova que o gate MORDE (link morto novo reprova) e que os controles-negativos
// passam (link valido, historia append-only, divida grandfathered pelo baseline).
// O RED original foi a medicao 2026-07-23: ~7,5% de links internos mortos no corpus
// vivo de memory/ sem NENHUM checker no CI.
//
// Hermetico: monta um repo temporario (memory/ + CLAUDE.md) e roda o gate como
// subprocesso com --root. Nao toca o corpus real.
//
// Rodar: node scripts/governance/deadlink-gate.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, readFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'deadlink-gate.mjs');

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK] ' : '[FAIL] ') + name);
  if (!cond) fails++;
}

function run(root, ...args) {
  return spawnSync(process.execPath, [SCRIPT, '--root', root, ...args], { encoding: 'utf8' });
}

function makeRepo() {
  const root = mkdtempSync(join(tmpdir(), 'deadlink-'));
  mkdirSync(join(root, 'memory', 'decisions'), { recursive: true });
  mkdirSync(join(root, 'memory', 'handoffs'), { recursive: true });
  mkdirSync(join(root, 'governance'), { recursive: true });
  writeFileSync(join(root, 'CLAUDE.md'), '# primer\n');
  writeFileSync(join(root, 'memory', 'alvo-real.md'), '# alvo\n');
  return root;
}

// ── 1. MORDE: link morto em doc vivo, sem baseline → exit 1 ──────────────────
{
  const root = makeRepo();
  writeFileSync(join(root, 'memory', 'decisions', '0001-x.md'),
    'ver [morto](../nao-existe.md) e [vivo](../alvo-real.md)\n');
  const r = run(root, '--check');
  check('MORDE: link morto vivo sem baseline reprova (exit 1)', r.status === 1);
  check('MORDE: output nomeia o alvo morto', /nao-existe\.md/.test(r.stderr + r.stdout));
  rmSync(root, { recursive: true, force: true });
}

// ── 2. Controle-negativo: só links validos → exit 0 ──────────────────────────
{
  const root = makeRepo();
  writeFileSync(join(root, 'memory', 'decisions', '0001-x.md'),
    'ver [vivo](../alvo-real.md) e [externo](https://example.com) e [ancora](#secao) e [template](<Modulo>/SPEC.md)\n');
  const r = run(root, '--check');
  check('controle-negativo: corpus limpo passa (exit 0)', r.status === 0);
  rmSync(root, { recursive: true, force: true });
}

// ── 3. Historia append-only NUNCA enforça ────────────────────────────────────
{
  const root = makeRepo();
  writeFileSync(join(root, 'memory', 'handoffs', '2026-01-01-x.md'),
    'fossil aponta [morto](../purgado.md)\n');
  const r = run(root, '--check');
  check('historia: link morto em handoff NAO reprova (exit 0)', r.status === 0);
  const s = run(root, '--scan');
  check('historia: --scan ainda REPORTA o morto de historia', /HISTORIA: 1 /.test(s.stdout));
  rmSync(root, { recursive: true, force: true });
}

// ── 4. Ratchet: baseline grandfathers a divida; PIORAR reprova ───────────────
{
  const root = makeRepo();
  const adr = join(root, 'memory', 'decisions', '0002-y.md');
  writeFileSync(adr, 'divida antiga: [m1](../m1.md)\n');
  const w = run(root, '--write-baseline');
  check('baseline: --write-baseline grava (exit 0)', w.status === 0);
  const b = JSON.parse(readFileSync(join(root, 'governance', 'deadlink-baseline.json'), 'utf8'));
  check('baseline: registra 1 morto grandfathered', b.total_vivo === 1 && b.files['memory/decisions/0002-y.md'] === 1);

  const r1 = run(root, '--check');
  check('ratchet: divida grandfathered passa (exit 0)', r1.status === 0);

  writeFileSync(adr, 'divida antiga: [m1](../m1.md) e NOVA: [m2](../m2.md)\n');
  const r2 = run(root, '--check');
  check('ratchet MORDE: mesmo arquivo piorando (1→2) reprova', r2.status === 1);

  writeFileSync(join(root, 'memory', 'decisions', '0003-novo.md'), 'novo doc com [morto](../nada.md)\n');
  writeFileSync(adr, 'divida antiga: [m1](../m1.md)\n');
  const r3 = run(root, '--check');
  check('ratchet MORDE: arquivo NOVO com link morto reprova', r3.status === 1);
  rmSync(root, { recursive: true, force: true });
}

// ── 5. Case-sensitive (paridade CI Linux, mesmo no Windows) ──────────────────
{
  const root = makeRepo();
  // alvo real: memory/alvo-real.md — link com case errado deve ser MORTO
  writeFileSync(join(root, 'memory', 'decisions', '0004-case.md'),
    'case errado: [x](../Alvo-Real.md)\n');
  const r = run(root, '--check');
  check('case: link com case divergente do FS conta como morto (paridade Linux)', r.status === 1);
  rmSync(root, { recursive: true, force: true });
}

// ── 6. Raiz do repo entra no corpus vivo ─────────────────────────────────────
{
  const root = makeRepo();
  writeFileSync(join(root, 'README.md'), 'porta global aponta [morto](memory/sumiu.md)\n');
  const r = run(root, '--check');
  check('raiz: README.md com link morto reprova (entrypoints cobertos)', r.status === 1);
  rmSync(root, { recursive: true, force: true });
}

console.log('');
if (fails > 0) { console.error(`${fails} check(s) falharam`); process.exit(1); }
console.log('deadlink-gate.test: todos os checks passaram');
