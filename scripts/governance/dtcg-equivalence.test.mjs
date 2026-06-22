#!/usr/bin/env node
// ─────────────────────────────────────────────────────────────────────────────
// dtcg-equivalence.test.mjs — self-test do check de equivalência DTCG.
//
// "Quem vigia os vigias" (SDD GT-G6): um gate que não morde é pior que nenhum.
// Este teste prova DUAS coisas, sem tocar os arquivos versionados:
//   (1) FIXTURE BOA  → o check passa (rc0) sobre os tokens reais do repo.
//   (2) FIXTURE RUIM → o check FALHA (rc1) quando um token DTCG diverge do CSS.
//
// Roda o check como subprocesso (node), inspeciona rc + saída JSON. A fixture
// ruim é montada num diretório temporário (cópia dos tokens + CSS reais com UM
// token adulterado), NUNCA mutando resources/css/tokens/*.json do repo.
//
// Node puro, sem deps. rc0 = self-test passou. Uso: node scripts/governance/dtcg-equivalence.test.mjs
// ─────────────────────────────────────────────────────────────────────────────
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdtempSync, mkdirSync, cpSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { tmpdir } from 'node:os';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = join(HERE, '..', '..');
const CHECK = join(HERE, 'dtcg-equivalence.mjs');

let failures = 0;
function assert(cond, msg) {
  if (cond) { console.log(`  ✓ ${msg}`); }
  else { console.log(`  ✗ ${msg}`); failures++; }
}

function runCheck(checkPath) {
  // Retorna { rc, json }. IMPORTANTE: o check resolve ROOT via import.meta.url
  // (dirname(check)/../..), NÃO via cwd. Então pra apontar pro sandbox, rodamos
  // a CÓPIA do check que vive dentro do sandbox (checkPath), não o do repo.
  // O check faz process.exit(1) em divergência → execFileSync lança; capturamos status.
  try {
    const out = execFileSync('node', [checkPath, '--json'], { encoding: 'utf8' });
    return { rc: 0, json: JSON.parse(out) };
  } catch (e) {
    const out = e.stdout ? String(e.stdout) : '';
    let json = null;
    try { json = JSON.parse(out); } catch { /* saída não-json */ }
    return { rc: e.status ?? 1, json };
  }
}

console.log('dtcg-equivalence.test.mjs');

// ── (1) FIXTURE BOA — repo real ──────────────────────────────────────────────
console.log('\n[1] fixture boa (tokens reais do repo) → deve PASSAR (rc0)');
{
  const { rc, json } = runCheck(CHECK);
  assert(rc === 0, `rc0 sobre o repo real (rc=${rc})`);
  assert(json && json.summary && json.summary.ok === true, 'summary.ok === true');
  assert(json && json.summary.proven > 0, `provou >0 tokens (proven=${json?.summary?.proven})`);
  assert(json && json.summary.divergences === 0, 'divergences === 0');
}

// ── (2) FIXTURE RUIM — token adulterado num sandbox temporário ────────────────
console.log('\n[2] fixture ruim (1 token DTCG divergente) → deve FALHAR (rc1)');
{
  const sandbox = mkdtempSync(join(tmpdir(), 'dtcg-selftest-'));
  try {
    // Espelha a árvore mínima que o check lê: scripts/governance/ + resources/css/{tokens,*.css}
    mkdirSync(join(sandbox, 'scripts', 'governance'), { recursive: true });
    mkdirSync(join(sandbox, 'resources', 'css', 'tokens'), { recursive: true });
    const sandboxCheck = join(sandbox, 'scripts', 'governance', 'dtcg-equivalence.mjs');
    cpSync(CHECK, sandboxCheck);
    // Pós-ativação o check lê os _generated-*.css (SAÍDA do Style Dictionary, o
    // CSS que o build consome), não mais os blocos inline dos .css canônicos.
    // Copiamos os gerados reais pro sandbox; adulteramos só o $value no JSON →
    // JSON diverge do gerado (não-regenerado) → o check deve pegar.
    for (const gen of [
      '_generated-inertia-theme.css', '_generated-inertia-dark.css',
      '_generated-foundations-light.css', '_generated-foundations-dark.css',
      '_generated-cockpit-light.css', '_generated-cockpit-dark.css',
    ]) {
      cpSync(join(ROOT, 'resources', 'css', 'tokens', gen), join(sandbox, 'resources', 'css', 'tokens', gen));
    }
    for (const tj of ['base.tokens.json', 'semantic.tokens.json']) {
      cpSync(join(ROOT, 'resources', 'css', 'tokens', tj), join(sandbox, 'resources', 'css', 'tokens', tj));
    }
    // Adultera UM $value conhecido (color.primary roxo) pra um valor impossível,
    // SEM regenerar o gerado → o JSON deixa de bater com o _generated-inertia-theme.css.
    const semPath = join(sandbox, 'resources', 'css', 'tokens', 'semantic.tokens.json');
    const sem = JSON.parse(readFileSync(semPath, 'utf8'));
    sem.color.primary.$value = 'oklch(0.01 0.99 7)';
    writeFileSync(semPath, JSON.stringify(sem, null, 2));

    const { rc, json } = runCheck(sandboxCheck);
    assert(rc === 1, `rc1 com token adulterado (rc=${rc})`);
    assert(json && json.summary && json.summary.ok === false, 'summary.ok === false');
    assert(json && json.summary.divergences >= 1, `detectou >=1 divergência (divergences=${json?.summary?.divergences})`);
    const hit = json?.errors?.some((e) => e.var === '--color-primary' && e.kind === 'valor-divergente');
    assert(!!hit, 'apontou --color-primary como valor-divergente');
  } finally {
    rmSync(sandbox, { recursive: true, force: true });
  }
}

console.log(`\n${failures === 0 ? '✓ self-test PASSOU' : `✗ self-test FALHOU (${failures} asserts)`}`);
process.exit(failures ? 1 : 0);
