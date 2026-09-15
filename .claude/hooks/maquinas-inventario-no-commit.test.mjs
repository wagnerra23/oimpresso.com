#!/usr/bin/env node
/**
 * Bite-test do `maquinas-inventario-no-commit`.
 *
 * DUAS CAMADAS, de proposito (§5 2026-07-30: assert em helper exportado prova a FUNCAO, nunca o
 * pipeline):
 *   (1) nucleo puro — os 4 predicados, com controle negativo em cada;
 *   (2) o CLI DE FORA, por subprocesso, em SANDBOX de git com gerador FALSO (padrao do
 *       `gate-selftest`: sandbox por cwd). O gerador falso deixa o teste controlar stale/fresco
 *       e custar milissegundos, em vez dos ~6,5s do real.
 *
 * O caso que mais importa e o NEGATIVO: indice FRESCO tem que dar silencio absoluto. Se ele
 * falasse ali, o hook seria o presence-gate que a §5 2026-07-01 baniu.
 */

import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, existsSync, rmSync, readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

import {
  ehGitCommit, temPathspecExplicito, levaWorkingTree, tocaCoberto, COBERTOS,
} from './maquinas-inventario-no-commit.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'maquinas-inventario-no-commit.mjs');
const INDICE = 'memory/reference/MAQUINAS-INVENTARIO.md';
let falhas = 0;

function ok(cond, nome) {
  if (cond) console.log('  ok  ' + nome);
  else { console.error('  XX  ' + nome); falhas++; }
}

// ---------------------------------------------------------------- (1) nucleo puro
console.log('nucleo puro:');
ok(ehGitCommit('git commit -m "x"'), 'reconhece git commit');
ok(ehGitCommit('git add -- a && git commit -m x'), 'reconhece em comando composto');
ok(ehGitCommit('git -C /repo commit -m x'), 'reconhece com flag global antes');
ok(!ehGitCommit('git status'), 'NEG: git status nao e commit');
ok(!ehGitCommit('echo "git commit" > nota.txt'), 'NEG: mencao em string nao dispara');
ok(!ehGitCommit(''), 'NEG: comando vazio');
ok(!ehGitCommit(undefined), 'NEG: undefined nao explode');

ok(temPathspecExplicito('git commit -m x -- a/b.md'), 'detecta pathspec explicito');
ok(!temPathspecExplicito('git commit -m x'), 'NEG: sem pathspec');
ok(!temPathspecExplicito('git commit -am x'), 'NEG: -am nao e pathspec');

ok(levaWorkingTree('git commit -am x'), 'detecta -a agrupado');
ok(levaWorkingTree('git commit --all -m x'), 'detecta --all');
ok(!levaWorkingTree('git commit -m x'), 'NEG: commit so do stage');
ok(!levaWorkingTree('git commit -m "tudo agora"'), 'NEG: palavra na mensagem nao conta');

ok(tocaCoberto(['.claude/hooks/x.mjs']), 'cobre .claude/');
ok(tocaCoberto(['scripts/governance/y.mjs']), 'cobre scripts/governance/');
ok(tocaCoberto(['.github/workflows/z.yml']), 'cobre .github/workflows/');
ok(tocaCoberto(['README.md', '.claude/settings.json']), 'cobre se QUALQUER path casa');
ok(!tocaCoberto(['README.md', 'app/Models/User.php']), 'NEG: path nao coberto');
ok(!tocaCoberto([]), 'NEG: lista vazia');
ok(!tocaCoberto(null), 'NEG: null nao explode');
ok(COBERTOS.length === 3, 'COBERTOS tem os 3 prefixos derivados do gerador');

// ---------------------------------------------------------------- (2) CLI de fora, em sandbox
function sandbox({ staleInicial, comGerador = true }) {
  const dir = mkdtempSync(join(tmpdir(), 'maqinv-'));
  const g = (args) => execFileSync('git', args, { cwd: dir, stdio: 'ignore' });
  g(['init', '-q']);
  g(['config', 'user.email', 't@t']);
  g(['config', 'user.name', 't']);

  mkdirSync(join(dir, 'memory/reference'), { recursive: true });
  writeFileSync(join(dir, INDICE), 'indice velho\n');

  if (comGerador) {
    mkdirSync(join(dir, 'scripts/governance'), { recursive: true });
    // gerador FALSO: --check sai 1 se existir o marcador STALE; --write regrava o indice
    writeFileSync(join(dir, 'scripts/governance/maquinas-inventario.mjs'), [
      "import { existsSync, writeFileSync } from 'node:fs';",
      "const modo = process.argv[2];",
      "if (modo === '--check') process.exit(existsSync('STALE') ? 1 : 0);",
      "if (modo === '--write') { writeFileSync('" + INDICE + "', 'indice NOVO\\n'); process.exit(0); }",
      "process.exit(0);",
    ].join('\n'));
  }
  if (staleInicial) writeFileSync(join(dir, 'STALE'), '');
  return dir;
}

function roda(dir, cmd) {
  const input = JSON.stringify({ tool_name: 'Bash', tool_input: { command: cmd } });
  // spawnSync porque o hook sai 0 SEMPRE: com execFileSync o stderr do caso de sucesso
  // nunca chegaria ao assert, e os 2 asserts de mensagem passariam por nao-medicao.
  const r = spawnSync('node', [HOOK], { cwd: dir, input, encoding: 'utf8' });
  return { status: r.status, stderr: String(r.stderr || '') };
}

function stageado(dir) {
  const out = execFileSync('git', ['diff', '--cached', '--name-only'], { cwd: dir, encoding: 'utf8' });
  return out.split('\n').filter(Boolean);
}
const conteudo = (dir) => readFileSync(join(dir, INDICE), 'utf8');

console.log('CLI de fora (sandbox):');

// MORDE: toca maquina + indice stale -> regenera E estagia
{
  const dir = sandbox({ staleInicial: true });
  mkdirSync(join(dir, '.claude/hooks'), { recursive: true });
  writeFileSync(join(dir, '.claude/hooks/novo.mjs'), '// x\n');
  execFileSync('git', ['add', '--', '.claude/hooks/novo.mjs'], { cwd: dir, stdio: 'ignore' });
  const r = roda(dir, 'git commit -m "toca maquina"');
  ok(r.status === 0, 'MORDE: exit 0 (nunca bloqueia)');
  ok(conteudo(dir).includes('NOVO'), 'MORDE: rodou o --write');
  ok(stageado(dir).includes(INDICE), 'MORDE: ESTAGIOU o indice');
  ok(/REGENEREI e ESTAGIEI/.test(r.stderr), 'MORDE: disse o que fez');
  ok(/DRIFT HERDADO/.test(r.stderr), 'MORDE: avisa do churn de terceiros');
  rmSync(dir, { recursive: true, force: true });
}

// NEG decisivo: indice FRESCO -> silencio absoluto (aqui morre o presence-gate)
{
  const dir = sandbox({ staleInicial: false });
  mkdirSync(join(dir, '.claude/hooks'), { recursive: true });
  writeFileSync(join(dir, '.claude/hooks/novo.mjs'), '// x\n');
  execFileSync('git', ['add', '--', '.claude/hooks/novo.mjs'], { cwd: dir, stdio: 'ignore' });
  const r = roda(dir, 'git commit -m x');
  ok(r.status === 0 && r.stderr === '', 'NEG: indice fresco -> silencio (nao e presence-gate)');
  ok(!conteudo(dir).includes('NOVO'), 'NEG: fresco -> nao chamou o --write');
  ok(!stageado(dir).includes(INDICE), 'NEG: fresco -> nao estagiou');
  rmSync(dir, { recursive: true, force: true });
}

// NEG: commit que nao toca maquina -> nao paga a medicao
{
  const dir = sandbox({ staleInicial: true });
  writeFileSync(join(dir, 'README.md'), 'oi\n');
  execFileSync('git', ['add', '--', 'README.md'], { cwd: dir, stdio: 'ignore' });
  const r = roda(dir, 'git commit -m x');
  ok(r.status === 0 && r.stderr === '', 'NEG: path nao coberto -> silencio');
  ok(!conteudo(dir).includes('NOVO'), 'NEG: nao coberto -> nem rodou o --check');
  rmSync(dir, { recursive: true, force: true });
}

// NEG: nao e commit
{
  const dir = sandbox({ staleInicial: true });
  const r = roda(dir, 'git status --short');
  ok(r.status === 0 && r.stderr === '', 'NEG: git status -> silencio');
  ok(!conteudo(dir).includes('NOVO'), 'NEG: git status -> nao rodou gerador');
  rmSync(dir, { recursive: true, force: true });
}

// FAIL-OPEN: sem o gerador no checkout
{
  const dir = sandbox({ staleInicial: true, comGerador: false });
  mkdirSync(join(dir, '.claude/hooks'), { recursive: true });
  writeFileSync(join(dir, '.claude/hooks/novo.mjs'), '// x\n');
  execFileSync('git', ['add', '--', '.claude/hooks/novo.mjs'], { cwd: dir, stdio: 'ignore' });
  const r = roda(dir, 'git commit -m x');
  ok(r.status === 0, 'FAIL-OPEN: sem gerador -> exit 0');
  rmSync(dir, { recursive: true, force: true });
}

// FAIL-OPEN: stdin ilegivel
{
  const dir = sandbox({ staleInicial: true });
  let st = 0;
  try {
    execFileSync('node', [HOOK], { cwd: dir, input: 'isto nao e json', encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
  } catch (e) { st = typeof e.status === 'number' ? e.status : -1; }
  ok(st === 0, 'FAIL-OPEN: stdin ilegivel -> exit 0');
  rmSync(dir, { recursive: true, force: true });
}

// pathspec explicito: regenera mas NAO estagia (estagiar nao entraria no commit)
{
  const dir = sandbox({ staleInicial: true });
  mkdirSync(join(dir, '.claude/hooks'), { recursive: true });
  writeFileSync(join(dir, '.claude/hooks/novo.mjs'), '// x\n');
  execFileSync('git', ['add', '--', '.claude/hooks/novo.mjs'], { cwd: dir, stdio: 'ignore' });
  const r = roda(dir, 'git commit -m x -- .claude/hooks/novo.mjs');
  ok(conteudo(dir).includes('NOVO'), 'pathspec: regenerou');
  ok(!stageado(dir).includes(INDICE), 'pathspec: NAO estagiou');
  ok(/NAO estagiei/.test(r.stderr), 'pathspec: explicou por que nao estagiou');
  rmSync(dir, { recursive: true, force: true });
}

console.log('');
if (falhas) { console.error('FALHOU: ' + falhas + ' assert(s)'); process.exit(1); }
console.log('maquinas-inventario-no-commit: morde quando ha drift, silencia quando fresco, fail-open sempre.');
process.exit(0);
