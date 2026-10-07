// @ts-check
/**
 * module-surface-escopo.test.mjs — BITE-TEST do `--all --check --escopo-ref=<ref>` exercitando
 * o CLI DE FORA, num repo git hermético (o selftest de helper puro não prova o pipeline —
 * §5 2026-07-30). Roda: node --test scripts/governance/module-surface-escopo.test.mjs
 *
 * Cenário: dois módulos (Foo, Bar). A base já carrega drift em Bar (o estado do main em
 * 2026-10-06, quando um merge deixou NfeBrasil atrás da árvore e 6 PRs que nem tocavam o
 * módulo ficaram vermelhos). Cada caso cria um "PR" por cima da base e roda o CLI real.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, copyFileSync, writeFileSync, appendFileSync, rmSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { tmpdir } from 'node:os';
import { spawnSync, execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const REPO = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const SCRIPT = 'scripts/governance/module-surface.mjs';

function git(cwd, ...a) {
  return execFileSync('git', ['-c', 'user.email=t@t', '-c', 'user.name=t', '-c', 'commit.gpgsign=false', ...a], {
    cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'],
  });
}

function cli(cwd, ...a) {
  const r = spawnSync(process.execPath, [SCRIPT, ...a], { cwd, encoding: 'utf8', env: { ...process.env, GITHUB_ACTIONS: '' } });
  return { rc: r.status, out: `${r.stdout}\n${r.stderr}` };
}

function put(root, rel, txt) {
  mkdirSync(dirname(join(root, rel)), { recursive: true });
  writeFileSync(join(root, rel), txt);
}

/** Base: Foo e Bar regenerados, depois um arquivo novo em Bar SEM regenerar (drift herdado). */
function sandbox() {
  const root = mkdtempSync(join(tmpdir(), 'msurf-escopo-'));
  for (const f of [SCRIPT, 'scripts/qa/page-path.mjs']) {
    mkdirSync(dirname(join(root, f)), { recursive: true });
    copyFileSync(join(REPO, f), join(root, f));
  }
  for (const m of ['Foo', 'Bar']) {
    put(root, `Modules/${m}/module.json`, JSON.stringify({ name: m, active: 1, providers: [`Modules\\${m}\\P`] }));
    put(root, `Modules/${m}/Http/Controllers/${m}Controller.php`, '<?php\n');
    mkdirSync(join(root, `memory/requisitos/${m}`), { recursive: true });
  }
  git(root, 'init', '-q', '-b', 'main');
  git(root, 'add', '-A');
  for (const m of ['Foo', 'Bar']) assert.equal(cli(root, m, '--write').rc, 0);
  git(root, 'add', '-A');
  git(root, 'commit', '-q', '-m', 'base limpa');
  put(root, 'Modules/Bar/Http/Controllers/NovoController.php', '<?php\n');
  git(root, 'add', '-A');
  git(root, 'commit', '-q', '-m', 'merge que deixou Bar em drift');
  git(root, 'tag', 'base');
  git(root, 'checkout', '-q', '-b', 'pr');
  return root;
}

test('CONTROLE: sem --escopo-ref, o drift herdado de Bar reprova (prova que o drift existe)', () => {
  const root = sandbox();
  try {
    put(root, 'docs/x.md', 'nada de módulo\n');
    git(root, 'add', '-A'); git(root, 'commit', '-q', '-m', 'pr fora de módulo');
    const r = cli(root, '--all', '--check');
    assert.equal(r.rc, 1, r.out);
    assert.match(r.out, /DRIFT em Bar/);
  } finally { rmSync(root, { recursive: true, force: true }); }
});

test('BOM: PR que não toca Bar passa, e o drift herdado sai como AVISO nomeando Bar', () => {
  const root = sandbox();
  try {
    put(root, 'Modules/Foo/Http/Controllers/OutroController.php', '<?php\n');
    assert.equal(cli(root, 'Foo', '--write').rc, 0);
    git(root, 'add', '-A'); git(root, 'commit', '-q', '-m', 'pr toca Foo e regenera');
    const r = cli(root, '--all', '--check', '--escopo-ref=base');
    assert.equal(r.rc, 0, r.out);
    assert.match(r.out, /módulos tocados = Foo/);
    assert.match(r.out, /AVISO: drift HERDADO .*Bar/);
  } finally { rmSync(root, { recursive: true, force: true }); }
});

test('RUIM: PR que muda Foo sem regenerar reprova mesmo com escopo (fail-closed no tocado)', () => {
  const root = sandbox();
  try {
    put(root, 'Modules/Foo/Http/Controllers/OutroController.php', '<?php\n');
    git(root, 'add', '-A'); git(root, 'commit', '-q', '-m', 'pr toca Foo sem regenerar');
    const r = cli(root, '--all', '--check', '--escopo-ref=base');
    assert.equal(r.rc, 1, r.out);
    assert.match(r.out, /DRIFT em Foo/);
  } finally { rmSync(root, { recursive: true, force: true }); }
});

test('RUIM: PR que edita à mão o SUPERFICIE.md de Bar responde por Bar', () => {
  const root = sandbox();
  try {
    appendFileSync(join(root, 'memory/requisitos/Bar/SUPERFICIE.md'), '\nedição à mão\n');
    git(root, 'add', '-A'); git(root, 'commit', '-q', '-m', 'pr mexe na projeção de Bar');
    const r = cli(root, '--all', '--check', '--escopo-ref=base');
    assert.equal(r.rc, 1, r.out);
    assert.match(r.out, /módulos tocados = Bar/);
  } finally { rmSync(root, { recursive: true, force: true }); }
});

test('RUIM: PR que toca o gerador responde por TODOS — o drift de Bar volta a reprovar', () => {
  const root = sandbox();
  try {
    appendFileSync(join(root, SCRIPT), '\n// comentário\n');
    git(root, 'add', '-A'); git(root, 'commit', '-q', '-m', 'pr toca o gerador');
    const r = cli(root, '--all', '--check', '--escopo-ref=base');
    assert.equal(r.rc, 1, r.out);
    assert.match(r.out, /TODOS \(o PR toca o gerador\)/);
  } finally { rmSync(root, { recursive: true, force: true }); }
});

test('NÃO-MEDI: ref inexistente sai 2 (nunca 0 com escopo vazio)', () => {
  const root = sandbox();
  try {
    const r = cli(root, '--all', '--check', '--escopo-ref=nao-existe');
    assert.equal(r.rc, 2, r.out);
    assert.match(r.out, /não foi possível calcular o diff/);
  } finally { rmSync(root, { recursive: true, force: true }); }
});

test('USO: --escopo-ref sem --check, ou vazio, sai 2', () => {
  const root = sandbox();
  try {
    assert.equal(cli(root, '--all', '--escopo-ref=base').rc, 2);
    assert.equal(cli(root, '--all', '--check', '--escopo-ref=').rc, 2);
    assert.equal(cli(root, '--all', '--check', '--escopo-ref').rc, 2);
  } finally { rmSync(root, { recursive: true, force: true }); }
});
