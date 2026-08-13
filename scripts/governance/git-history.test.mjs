// @ts-check
/**
 * git-history.test.mjs — bite-test do guard anti-fabricação de medida por clone raso.
 * Roda: node --test scripts/governance/git-history.test.mjs
 *
 * As DUAS pernas, sempre: MORDE no ruim (history truncada → "não medido") e
 * SOLTA no bom (history completa → mede de verdade). Guard que só tem a perna
 * do bom é carimbo; guard que só tem a do ruim pode estar grudado em `null` e
 * ninguém percebe (LC-15).
 *
 * Os casos de FINURA usam repositórios de verdade em tmp, não flags injetadas —
 * porque a finura É o comportamento do detector contra o `.git/shallow`, e
 * injetar `raso` justamente pularia o que se quer provar.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { isShallowHistory, gitLastDate, gitLogRaw, _resetCacheParaTeste } from './lib/git-history.mjs';

// ── helpers de fixture ────────────────────────────────────────────────────────
const sh = (cmd, cwd) => execSync(cmd, { cwd, stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();

/** Repo real com N commits — o único jeito honesto de exercitar o detector. */
function repoComHistoria(nCommits = 3) {
  const dir = mkdtempSync(join(tmpdir(), 'githist-'));
  sh('git init -q -b main', dir);
  sh('git config user.email "teste@oimpresso.local"', dir);
  sh('git config user.name "teste"', dir);
  sh('git config commit.gpgsign false', dir);
  for (let i = 1; i <= nCommits; i++) {
    writeFileSync(join(dir, 'arquivo.md'), `versao ${i}\n`);
    sh('git add -A', dir);
    sh(`git commit -q -m "commit ${i}"`, dir);
  }
  return dir;
}

// ══ 1. CONTRATO com `raso` injetado (barato, determinístico) ═══════════════════

test('BITE raso: history truncada NÃO inventa data — devolve o "não medido"', () => {
  assert.equal(gitLastDate('arquivo.md', { raso: true }), null);
});

test('CONTROLE completo: com history NÃO truncada mede de verdade (ISO curta)', () => {
  const dir = repoComHistoria();
  const d = gitLastDate('arquivo.md', { raso: false, cwd: dir });
  assert.match(String(d), /^\d{4}-\d{2}-\d{2}$/);
});

test('CONTROLE: path inexistente devolve "não medido" nos DOIS modos (vazio ≠ fabricação)', () => {
  const dir = repoComHistoria();
  assert.equal(gitLastDate('__nao_existe__.md', { raso: false, cwd: dir }), null);
  assert.equal(gitLastDate('__nao_existe__.md', { raso: true, cwd: dir }), null);
});

test('`ausente` custom preserva o contrato do consumidor (memory-health espera "")', () => {
  // trocar o tipo de retorno por baixo do consumidor seria trocar um bug por outro
  assert.equal(gitLastDate('arquivo.md', { raso: true, ausente: '' }), '');
});

test('BITE raso em gitLogRaw: passada --name-only/--since também não fabrica', () => {
  assert.equal(gitLogRaw('git log --format=@%cs --name-only', { raso: true }), null);
});

test('CONTROLE completo em gitLogRaw: devolve stdout de verdade', () => {
  const dir = repoComHistoria();
  const out = gitLogRaw('git log --oneline', { raso: false, cwd: dir });
  assert.equal(String(out).trim().split('\n').length, 3);
});

// ══ 2. FINURA do detector — o que separa este módulo do `--is-shallow-repository` ══

test('detector: repo COMPLETO → false (não cega sozinho)', () => {
  _resetCacheParaTeste();
  assert.equal(isShallowHistory({ cwd: repoComHistoria() }), false);
});

test('BITE detector: clone --depth 1 → true (history REALMENTE truncada)', () => {
  const origem = repoComHistoria(3);
  const destino = mkdtempSync(join(tmpdir(), 'githist-raso-'));
  const alvo = join(destino, 'clone');
  sh(`git clone -q --depth 1 "${pathToFileURL(origem).href}" "${alvo}"`, destino);
  _resetCacheParaTeste();
  assert.equal(isShallowHistory({ cwd: alvo }), true);
  // e o efeito de ponta: sem injetar nada, a data não é fabricada
  assert.equal(gitLastDate('arquivo.md', { cwd: alvo }), null);
});

test('FINURA: órfã buscada com --depth 1 marca o repo shallow mas NÃO trunca o HEAD → false', () => {
  // Este é o caso que o `--is-shallow-repository` cru erra: o `git fetch <órfã>
  // --depth 1` (que o próprio ratchet manda rodar) põe um boundary no
  // .git/shallow que NÃO é ancestral do HEAD. Detector grosso cegaria um repo
  // perfeitamente medível; o fino tem que soltar.
  const origem = repoComHistoria(3);
  // órfã sem ancestral comum com main, com 2 commits (pra o --depth 1 truncar ALGO)
  sh('git checkout -q --orphan nightly-floor', origem);
  sh('git rm -rq --cached . || true', origem);
  writeFileSync(join(origem, 'floor.json'), '{"a":1}\n');
  sh('git add -A', origem);
  sh('git commit -q -m "floor 1"', origem);
  writeFileSync(join(origem, 'floor.json'), '{"a":2}\n');
  sh('git add -A', origem);
  sh('git commit -q -m "floor 2"', origem);
  sh('git checkout -q main', origem);

  const consumidor = repoComHistoria(3);
  sh(`git fetch -q --depth 1 "${pathToFileURL(origem).href}" nightly-floor`, consumidor);

  // pré-condição: o git REALMENTE marcou o repo como shallow (senão o teste não prova nada)
  assert.equal(sh('git rev-parse --is-shallow-repository', consumidor), 'true',
    'pré-condição falhou: o fetch da órfã não marcou shallow — o caso não está sendo exercitado');

  _resetCacheParaTeste();
  assert.equal(isShallowHistory({ cwd: consumidor }), false,
    'detector GROSSO: cegou um repo cuja history do HEAD está intacta');
  assert.match(String(gitLastDate('arquivo.md', { cwd: consumidor })), /^\d{4}-\d{2}-\d{2}$/);
});

test('memo: mesmo cwd não re-mede (o detector custa execSync)', () => {
  const dir = repoComHistoria();
  _resetCacheParaTeste();
  assert.equal(isShallowHistory({ cwd: dir }), isShallowHistory({ cwd: dir }));
});
