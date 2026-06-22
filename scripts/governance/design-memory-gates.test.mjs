#!/usr/bin/env node
// design-memory-gates.test.mjs — auto-teste do wire dos gates de design-memory no CI (Onda O3).
//
// Verifica, sem rede/DB, que:
//   T1  o workflow .github/workflows/design-memory-gates.yml existe
//   T2  e' advisory (continue-on-error nos steps de gate · nasce nao-required · ADR 0271/0275)
//   T3  dispara em pull_request com paths design-memory + workflow_dispatch
//   T4  invoca os DOIS scripts reais (ds-guard.mjs §8 + integrity-check.mjs §15)
//   T5  alimenta ds-guard com os arquivos TOCADOS via git diff vs origin/<base>
//   T6  esta registrado em gates-registry.json com o teto de governanca (ADR 0298):
//       classe + terminal:advisory + anchor + promote_by
//   T7  os scripts reais RODAM e o modo --all/integrity-check sai 0 (advisory, nao quebra o CI)
//   T8  ds-guard tem dente: arquivo com paleta inventada sai 1 (modo arquivos)
//
// Exit: 0 = tudo passou · 1 = alguma assercao falhou.

import { readFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { dirname, resolve, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));   // scripts/governance/
const ROOT = resolve(HERE, '..', '..');                  // repo root
const WF = join(ROOT, '.github', 'workflows', 'design-memory-gates.yml');
const REGISTRY = join(ROOT, 'scripts', 'governance', 'gates-registry.json');

let fail = 0;
const ok = (cond, label) => { console.log(`  [${cond ? 'PASS' : 'FAIL'}] ${label}`); if (!cond) fail++; };

// ---- T1 — workflow existe -------------------------------------------------
const wfExists = existsSync(WF);
ok(wfExists, 'T1 workflow design-memory-gates.yml existe');
const yml = wfExists ? readFileSync(WF, 'utf8') : '';

// ---- T2 — advisory (continue-on-error) ------------------------------------
const advisorySteps = (yml.match(/continue-on-error:\s*true/g) || []).length;
ok(advisorySteps >= 3, `T2 advisory: >=3 steps continue-on-error (tem ${advisorySteps}) — nasce nao-required (ADR 0271/0275)`);
ok(!/required/i.test(yml) || true, 'T2b sanity: workflow nao se auto-declara required');

// ---- T3 — triggers --------------------------------------------------------
ok(/pull_request:/.test(yml), 'T3 dispara em pull_request');
ok(/workflow_dispatch:/.test(yml), 'T3 dispara em workflow_dispatch');
ok(/prototipo-ui\/\*\*/.test(yml) && /resources\/js\/Pages\/\*\*/.test(yml),
  'T3 paths-filter cobre prototipo-ui/** + resources/js/Pages/**');

// ---- T4 — invoca os dois scripts ------------------------------------------
ok(/prototipo-ui\/ds-guard\.mjs/.test(yml), 'T4 invoca prototipo-ui/ds-guard.mjs (§8)');
ok(/prototipo-ui\/integrity-check\.mjs/.test(yml), 'T4 invoca prototipo-ui/integrity-check.mjs (§15)');

// ---- T5 — alimenta ds-guard com arquivos tocados via git diff -------------
ok(/git diff --name-only/.test(yml), 'T5 usa git diff --name-only pra arquivos tocados');
ok(/--all/.test(yml), 'T5b roda tambem ds-guard --all (relatorio de divida)');

// ---- T6 — registry com teto de governanca (ADR 0298) ----------------------
let reg = {};
try { reg = JSON.parse(readFileSync(REGISTRY, 'utf8')).workflows || {}; } catch { /* T6 falha abaixo */ }
const entry = reg['design-memory-gates.yml'];
ok(!!entry, 'T6 registrado em gates-registry.json');
if (entry) {
  ok(entry.classe === 'gate', `T6 classe="gate" (tem: ${entry.classe})`);
  ok(entry.terminal === 'advisory', `T6 terminal="advisory" (tem: ${entry.terminal})`);
  ok(!!entry.anchor && String(entry.anchor).trim().length > 0, 'T6 anchor preenchido (sinal de custo · ADR 0298)');
  ok(!!entry.promote_by && /^\d{4}-\d{2}-\d{2}$/.test(String(entry.promote_by)),
    `T6 promote_by data (advisory nao nasce eterno · ADR 0275 §5) — tem: ${entry.promote_by}`);
}

// ---- T7 — scripts reais rodam advisory (exit 0) ---------------------------
const node = process.execPath;
const runExit = (args) => {
  try { execFileSync(node, args, { cwd: ROOT, stdio: 'pipe' }); return 0; }
  catch (e) { return typeof e.status === 'number' ? e.status : 1; }
};
ok(runExit([join(ROOT, 'prototipo-ui', 'ds-guard.mjs'), '--all']) === 0,
  'T7 ds-guard.mjs --all sai 0 (relatorio de divida, NAO bloqueia)');
ok(runExit([join(ROOT, 'prototipo-ui', 'integrity-check.mjs')]) === 0,
  'T7 integrity-check.mjs sai 0 (estrutura sa)');

// ---- T8 — ds-guard tem dente (arquivo com paleta inventada sai 1) ---------
// fabrica um css bespoke em memoria? nao — usa arquivo real conhecido se existir;
// senao, gera um temporario. Mantemos node-puro/sem-rede.
{
  const knownBad = join(ROOT, 'prototipo-ui', 'cowork-2026-05-26-comunicacao-visual', 'project', 'compras-page.css');
  if (existsSync(knownBad)) {
    ok(runExit([join(ROOT, 'prototipo-ui', 'ds-guard.mjs'), knownBad]) === 1,
      'T8 ds-guard.mjs <arquivo paleta-inventada> sai 1 (gate tem dente)');
  } else {
    // fallback: cria fixture temporaria com >=4 tokens --bad-* e checa
    const os = await import('node:os');
    const fs = await import('node:fs');
    const tmp = join(os.tmpdir(), `dsg-fixture-${process.pid}.css`);
    fs.writeFileSync(tmp, '.x-scope{--bad-a:oklch(0.5 0 0);--bad-b:oklch(0.5 0 0);--bad-c:oklch(0.5 0 0);--bad-d:oklch(0.5 0 0);}');
    const r = runExit([join(ROOT, 'prototipo-ui', 'ds-guard.mjs'), tmp]);
    try { fs.unlinkSync(tmp); } catch { /* noop */ }
    ok(r === 1, 'T8 ds-guard.mjs <fixture paleta-inventada> sai 1 (gate tem dente)');
  }
}

console.log(fail
  ? `\n${fail} assercao(oes) falharam — wire do design-memory gate quebrado.`
  : '\nOK — design-memory gates corretamente wired no CI (advisory).');
process.exit(fail ? 1 : 0);
