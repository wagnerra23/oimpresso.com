#!/usr/bin/env node
// ds-token-diff.test.mjs — selftest do MOTOR CENTRAL do loop diff-first.
//
// POR QUE EXISTE: ds-token-diff.mjs é o motor que TODO o loop reusa (ds-push valida com ele,
//   ds-mirror-drift roda ele, o sentinela vivo é ele). Até aqui não tinha selftest nenhum —
//   o gap de maturidade mais afiado (revisão adversarial 2026-07-11): "o motor central não tem
//   teste, e o outro script já vazou um blocker". Esta catraca fecha isso: prova, com fixtures,
//   os 5 comportamentos que a régua depende — e trava o alias-skip (comportamento sutil que,
//   se mudar calado, quebra a semântica de VALOR:0).
//
// Usa fixtures em dir temporário (não toca resources/css/tokens). Roda o script real via --json.

import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(HERE, 'ds-token-diff.mjs');
const ROOT = join(tmpdir(), `ds-token-diff-test-${process.pid}`);
const TOK = join(ROOT, 'tokens');

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

// git surface: light = _generated-inertia-theme.css (:root) ; cockpit-light = _generated-cockpit-light.css
function writeGit(lightBody, cockpitBody) {
  mkdirSync(TOK, { recursive: true });
  writeFileSync(join(TOK, '_generated-inertia-theme.css'), `:root{\n${lightBody}\n}`);
  writeFileSync(join(TOK, '_generated-cockpit-light.css'), `.cockpit{\n${cockpitBody}\n}`);
}
function writeFile(name, body) { const p = join(ROOT, name); writeFileSync(p, body); return p; }
function run(designPath, companions = []) {
  const args = [SCRIPT, designPath, TOK, '--json'];
  for (const c of companions) { args.push('--companion', c); }
  return JSON.parse(execFileSync('node', args, { encoding: 'utf8' }));
}

console.log('ds-token-diff.test — motor central (5 comportamentos)');
if (ROOT) rmSync(ROOT, { recursive: true, force: true });
writeGit('  --a: oklch(0.5 0 0);\n  --b: oklch(0.6 0 0);', '  --origin-x: red;\n  --d: blue;');

// A — MATCH → VALOR:0
let r = run(writeFile('a.css', ':root{ --a: oklch(0.5 0 0); --b: oklch(0.6 0 0); }'));
ok(r.totalDiverge === 0, 'match exato → divergências de VALOR: 0');

// B — DIVERGE de valor → conta
r = run(writeFile('b.css', ':root{ --a: oklch(0.5 0 0); --b: oklch(0.99 0 0); }'));
ok(r.totalDiverge === 1 && r.report.light.diverge.some((x) => x.k === '--b'), 'valor diferente → 1 divergência (--b)');

// C — designOnly / gitOnly
r = run(writeFile('c.css', ':root{ --a: oklch(0.5 0 0); --b: oklch(0.6 0 0); --novo: green; }'));
ok(r.report.light.designOnly.some((x) => x.k === '--novo'), 'token só no design → designOnly');
ok(r.report.light.gitOnly.length === 0, 'sem gitOnly quando design cobre o git (light)');

// D — ALIAS pulado: valor difere mas é var(...) → NÃO conta como diverge (comportamento sutil travado)
writeGit('  --a: var(--color-primary);', '');
r = run(writeFile('d.css', ':root{ --a: oklch(0.5 0 0); }'));  // git=var(...), design=literal → difere, mas alias
ok(r.totalDiverge === 0 && r.report.light.aliasSkipped.some((x) => x.k === '--a'), 'diff envolvendo var(...) → aliasSkipped, NÃO diverge');

// E — COMPANION fecha falso gitOnly do domínio cockpit
writeGit('  --a: red;', '  --origin-x: blue;\n  --origin-y: green;');
const designNoDomain = writeFile('e.css', ':root{ --a: red; }\n.cockpit{ }');  // .cockpit sem domínio
r = run(designNoDomain);
ok(r.report['cockpit-light'].gitOnly.length === 2, 'sem --companion → 2 gitOnly de domínio (falso drift)');
const companion = writeFile('e-comp.css', '.cockpit{ --origin-x: blue; --origin-y: green; }');
r = run(designNoDomain, [companion]);
ok(r.report['cockpit-light'].gitOnly.length === 0, 'com --companion → 0 gitOnly (falso drift fechado)');

rmSync(ROOT, { recursive: true, force: true });
if (fails) { console.error(`\nds-token-diff.test: ${fails} FALHA(S)`); process.exit(1); }
console.log('\nds-token-diff.test: OK');
