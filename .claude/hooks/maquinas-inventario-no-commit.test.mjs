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
  ehDocDoCorpus, tokensDe, tokensDasMaquinas, diffCitaMaquina, DOCS_GERADOS,
  RX_ARQUIVO, RX_CRASE, RX_PASTA, pathsQueMudamOMapa,
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

// ---------------------------------------------------------------- (1b) commit so de documento
console.log('commit so de documento (nucleo):');
// Os regex e a lista de gerados sao COPIA do gerador. Se o gerador mudar e o hook nao, o hook
// passa a olhar tokens que o gerador ja nao conta (ou o contrario) — o assert abaixo acusa.
const GERADOR_SRC = readFileSync(join(dirname(fileURLToPath(import.meta.url)), '../../scripts/governance/maquinas-inventario.mjs'), 'utf8');
for (const [nome, rx] of [['RX_ARQUIVO', RX_ARQUIVO], ['RX_CRASE', RX_CRASE], ['RX_PASTA', RX_PASTA]]) {
  ok(GERADOR_SRC.includes('const ' + nome + ' = ' + String(rx) + ';'), 'SYNC: ' + nome + ' identico ao do gerador');
}
for (const g of DOCS_GERADOS) ok(GERADOR_SRC.includes("'" + g + "'"), 'SYNC: gerado ' + g + ' tambem e excluido no gerador');

ok(ehDocDoCorpus('memory/requisitos/X/SPEC.md'), 'corpus: memory/**.md');
ok(ehDocDoCorpus('docs/guia.md'), 'corpus: docs/**.md');
ok(!ehDocDoCorpus('memory/reference/MAQUINAS-INVENTARIO.md'), 'NEG corpus: o proprio indice nao conta');
ok(!ehDocDoCorpus('memory/x.json'), 'NEG corpus: nao-.md');
ok(!ehDocDoCorpus('README.md'), 'NEG corpus: README na raiz nao e corpus');

const IND = '| `deadlink-gate.yml` | pr |\n| `governance/foo-baseline.json` | x |\n| `brief-first` | A |\n| `ci` | x |\n';
const maq = tokensDasMaquinas(IND);
ok(maq.has('deadlink-gate.yml') && maq.has('deadlink-gate'), 'maquinas: nome e nome nu com hifen');
ok(maq.has('foo-baseline.json'), 'maquinas: basename do baseline');
ok(maq.has('ci') && !maq.has('c'), 'maquinas: nome sem hifen entra so inteiro');

ok(diffCitaMaquina('+usa o `deadlink-gate` aqui\n', maq), 'cita: crase com nome nu');
ok(diffCitaMaquina('-removi deadlink-gate.yml\n', maq), 'cita: linha REMOVIDA tambem conta');
ok(diffCitaMaquina('+ver .claude/skills/brief-first/SKILL.md\n', maq), 'cita: forma skills/<nome>');
ok(diffCitaMaquina('+lê `foo-baseline.json`\n', maq), 'cita: baseline em crase');
// ESPELHO do gerador: ate 2026-09-23 a alternativa `js` vencia `json` no RX_ARQUIVO e
// `governance/foo-baseline.json` virava o token `foo-baseline.js` (nao contava). Consertado no
// gerador (json antes de js + \b final); o hook reproduz, entao o path fora de crase CONTA.
ok(diffCitaMaquina('+lê governance/foo-baseline.json\n', maq), 'ESPELHO: path .json fora de crase conta (igual ao gerador)');
ok(!diffCitaMaquina('+lê governance/foo-baseline.jsonl\n', maq), 'NEG cita: .jsonl nao vira .json (\\b final)');
ok(!diffCitaMaquina('+texto sobre o deadlink-gate solto\n', maq), 'NEG cita: nome nu SOLTO nao conta (gerador tambem nao)');
ok(!diffCitaMaquina(' contexto com `deadlink-gate`\n', maq), 'NEG cita: linha de contexto nao conta');
ok(!diffCitaMaquina('+++ b/memory/x-deadlink-gate.yml.md\n', maq), 'NEG cita: cabecalho +++ nao conta');
ok(!diffCitaMaquina('+nada de maquina aqui\n', maq), 'NEG cita: doc sem maquina');
ok(!diffCitaMaquina('+`deadlink-gate`\n', new Set()), 'NEG cita: indice vazio nao dispara');
ok(tokensDe('`a-b` x.mjs agents/z').size === 3, 'tokensDe: as 3 formas');

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
    // gerador FALSO. Modo dry (sem arg) imprime o que o indice DEVERIA conter: 'indice NOVO'
    // se existir o marcador STALE, senao repete o conteudo atual (= fresco). O `--check` sai
    // SEMPRE 0 — cobertura completa — de proposito: o hook nao pode mais depender dele, e o
    // caso "nomes completos, conteudo divergente" tem que morder mesmo assim. CRASH faz o
    // dry sair 2 (fail-open); VAZIO faz ele imprimir nada (nao-medicao).
    writeFileSync(join(dir, 'scripts/governance/maquinas-inventario.mjs'), [
      "import { existsSync, readFileSync, writeFileSync } from 'node:fs';",
      "const modo = process.argv[2];",
      "if (modo === '--check') process.exit(0);",
      "if (modo === '--write') { writeFileSync('" + INDICE + "', 'indice via --write\\n'); process.exit(0); }",
      "if (existsSync('CRASH')) process.exit(2);",
      "if (existsSync('VAZIO')) process.exit(0);",
      "process.stdout.write(existsSync('STALE') ? 'indice NOVO\\n' : readFileSync('" + INDICE + "', 'utf8'));",
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
  ok(conteudo(dir) === 'indice NOVO\n', 'MORDE: gravou a saida do gerador (sem pagar um --write a mais)');
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
  ok(conteudo(dir) === 'indice velho\n', 'NEG: fresco -> nao regravou o indice');
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
  ok(!conteudo(dir).includes('NOVO'), 'NEG: nao coberto -> nem rodou o gerador');
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
  ok(conteudo(dir) === 'indice NOVO\n', 'pathspec: regenerou');
  ok(!stageado(dir).includes(INDICE), 'pathspec: NAO estagiou');
  ok(/NAO estagiei/.test(r.stderr), 'pathspec: explicou por que nao estagiou');
  rmSync(dir, { recursive: true, force: true });
}

// O CASO QUE MOTIVOU A MUDANCA: `--check` verde (cobertura completa) e conteudo divergente.
// O hook antigo chamava so o `--check` e ficava em silencio aqui — foi assim que o main juntou
// 2 linhas stale em 2026-09-22. O gerador falso deste sandbox tem `--check` sempre 0.
{
  const dir = sandbox({ staleInicial: true });
  const checkVerde = spawnSync('node', ['scripts/governance/maquinas-inventario.mjs', '--check'], { cwd: dir }).status === 0;
  mkdirSync(join(dir, '.github/workflows'), { recursive: true });
  writeFileSync(join(dir, '.github/workflows/w.yml'), 'on: push\n');
  execFileSync('git', ['add', '--', '.github/workflows/w.yml'], { cwd: dir, stdio: 'ignore' });
  const r = roda(dir, 'git commit -m "liga um step num workflow"');
  ok(checkVerde, 'pre-condicao: o --check deste sandbox esta VERDE (cobertura ok)');
  ok(conteudo(dir) === 'indice NOVO\n' && stageado(dir).includes(INDICE),
    'MORDE: --check verde + conteudo divergente -> regenera e estagia mesmo assim');
  ok(/REGENEREI e ESTAGIEI/.test(r.stderr), 'MORDE: avisou tambem neste caso');
  rmSync(dir, { recursive: true, force: true });
}

// FAIL-OPEN: gerador crasha -> nao toca o indice, nao afirma nada
{
  const dir = sandbox({ staleInicial: true });
  writeFileSync(join(dir, 'CRASH'), '');
  mkdirSync(join(dir, '.claude/hooks'), { recursive: true });
  writeFileSync(join(dir, '.claude/hooks/novo.mjs'), '// x\n');
  execFileSync('git', ['add', '--', '.claude/hooks/novo.mjs'], { cwd: dir, stdio: 'ignore' });
  const r = roda(dir, 'git commit -m x');
  ok(r.status === 0 && r.stderr === '' && conteudo(dir) === 'indice velho\n',
    'FAIL-OPEN: gerador crasha -> silencio e indice intocado');
  rmSync(dir, { recursive: true, force: true });
}

// NAO-MEDICAO: gerador sai 0 com saida VAZIA -> nao pode apagar o indice
{
  const dir = sandbox({ staleInicial: true });
  writeFileSync(join(dir, 'VAZIO'), '');
  mkdirSync(join(dir, '.claude/hooks'), { recursive: true });
  writeFileSync(join(dir, '.claude/hooks/novo.mjs'), '// x\n');
  execFileSync('git', ['add', '--', '.claude/hooks/novo.mjs'], { cwd: dir, stdio: 'ignore' });
  roda(dir, 'git commit -m x');
  ok(conteudo(dir) === 'indice velho\n' && !stageado(dir).includes(INDICE),
    'NAO-MEDICAO: saida vazia do gerador -> indice NAO e sobrescrito com nada');
  rmSync(dir, { recursive: true, force: true });
}

// COMMIT SO DE DOC: sandbox com indice que lista uma maquina real
function sandboxDoc() {
  const dir = sandbox({ staleInicial: true });
  writeFileSync(join(dir, INDICE), '| `deadlink-gate.yml` | pr |\n');
  mkdirSync(join(dir, 'memory/requisitos/X'), { recursive: true });
  return dir;
}

// MORDE: doc que passa a citar maquina -> regenera e estagia
{
  const dir = sandboxDoc();
  writeFileSync(join(dir, 'memory/requisitos/X/SPEC.md'), 'o gate `deadlink-gate` cobre isto\n');
  execFileSync('git', ['add', '--', 'memory/requisitos/X/SPEC.md'], { cwd: dir, stdio: 'ignore' });
  const r = roda(dir, 'git commit -m "so doc"');
  ok(conteudo(dir) === 'indice NOVO\n' && stageado(dir).includes(INDICE), 'DOC MORDE: doc cita maquina -> regenera e estagia');
  ok(/documento que cita maquina/.test(r.stderr), 'DOC MORDE: a mensagem nomeia o motivo documento');
  rmSync(dir, { recursive: true, force: true });
}

// MORDE: doc que DEIXA de citar (arquivo removido) -> linhas removidas contam
{
  const dir = sandboxDoc();
  writeFileSync(join(dir, 'memory/requisitos/X/SPEC.md'), 'ver deadlink-gate.yml\n');
  execFileSync('git', ['add', '-A'], { cwd: dir, stdio: 'ignore' });
  execFileSync('git', ['commit', '-qm', 'base'], { cwd: dir, stdio: 'ignore' });
  execFileSync('git', ['rm', '-q', '--', 'memory/requisitos/X/SPEC.md'], { cwd: dir, stdio: 'ignore' });
  roda(dir, 'git commit -m "remove doc"');
  ok(conteudo(dir) === 'indice NOVO\n', 'DOC MORDE: remover doc citador tambem regenera');
  rmSync(dir, { recursive: true, force: true });
}

// NEG: doc que nao cita maquina -> nem roda o gerador
{
  const dir = sandboxDoc();
  writeFileSync(join(dir, 'memory/requisitos/X/SPEC.md'), 'texto sem maquina nenhuma\n');
  execFileSync('git', ['add', '--', 'memory/requisitos/X/SPEC.md'], { cwd: dir, stdio: 'ignore' });
  const r = roda(dir, 'git commit -m x');
  ok(r.stderr === '' && !conteudo(dir).includes('NOVO'), 'DOC NEG: doc sem maquina -> silencio, gerador nao roda');
  rmSync(dir, { recursive: true, force: true });
}

// NEG: doc cita maquina mas indice ja FRESCO -> silencio
{
  const dir = sandboxDoc();
  rmSync(join(dir, 'STALE'));
  writeFileSync(join(dir, 'memory/requisitos/X/SPEC.md'), 'o gate `deadlink-gate`\n');
  execFileSync('git', ['add', '--', 'memory/requisitos/X/SPEC.md'], { cwd: dir, stdio: 'ignore' });
  const r = roda(dir, 'git commit -m x');
  ok(r.stderr === '' && !stageado(dir).includes(INDICE), 'DOC NEG: indice fresco -> silencio (nao e presence-gate)');
  rmSync(dir, { recursive: true, force: true });
}

// MORDE: CLAUDE.md sempre (os @imports decidem o rank da coluna)
{
  const dir = sandboxDoc();
  writeFileSync(join(dir, 'CLAUDE.md'), '@memory/x.md\n');
  execFileSync('git', ['add', '--', 'CLAUDE.md'], { cwd: dir, stdio: 'ignore' });
  roda(dir, 'git commit -m x');
  ok(conteudo(dir) === 'indice NOVO\n', 'DOC MORDE: CLAUDE.md dispara sempre');
  rmSync(dir, { recursive: true, force: true });
}

// MORDE: -a leva doc so do working tree
{
  const dir = sandboxDoc();
  writeFileSync(join(dir, 'memory/requisitos/X/SPEC.md'), 'base\n');
  execFileSync('git', ['add', '-A'], { cwd: dir, stdio: 'ignore' });
  execFileSync('git', ['commit', '-qm', 'base'], { cwd: dir, stdio: 'ignore' });
  writeFileSync(join(dir, 'memory/requisitos/X/SPEC.md'), 'base\nagora `deadlink-gate`\n');
  roda(dir, 'git commit -am x');
  ok(conteudo(dir) === 'indice NOVO\n', 'DOC MORDE: -a considera doc do working tree');
  rmSync(dir, { recursive: true, force: true });
}

// ---------------------------------------------------------------- (N) passo 2: SUPERFICIE.md
// Usa o GERADOR REAL (`module-surface.mjs` + `page-path.mjs`, copiados para o sandbox): o que
// esta sob teste e a decisao "este commit envelheceu o mapa de qual modulo", e so o gerador de
// verdade responde isso — um fake provaria o fake.
console.log('superficie (passo 2):');
const TAB = String.fromCharCode(9);
ok(JSON.stringify(pathsQueMudamOMapa('A' + TAB + 'Modules/Foo/x.php\nM' + TAB + 'Modules/Foo/y.php\nD' + TAB + 'Modules/Bar/z.php\n'))
  === JSON.stringify(['Modules/Foo/x.php', 'Modules/Bar/z.php']), 'so A e D mudam o mapa (M nao)');
ok(pathsQueMudamOMapa('').length === 0, 'diff vazio: nada');

const RAIZ_REPO = join(dirname(fileURLToPath(import.meta.url)), '../..');
const SUP = (m) => `memory/requisitos/${m}/SUPERFICIE.md`;

function sandboxSup() {
  const dir = mkdtempSync(join(tmpdir(), 'modsurf-'));
  const g = (args) => execFileSync('git', args, { cwd: dir, stdio: 'ignore' });
  g(['init', '-q']); g(['config', 'user.email', 't@t']); g(['config', 'user.name', 't']);
  for (const rel of ['scripts/governance/module-surface.mjs', 'scripts/qa/page-path.mjs']) {
    mkdirSync(join(dir, dirname(rel)), { recursive: true });
    writeFileSync(join(dir, rel), readFileSync(join(RAIZ_REPO, rel)));
  }
  for (const m of ['Foo', 'Bar']) {
    mkdirSync(join(dir, `Modules/${m}/Http/Controllers`), { recursive: true });
    mkdirSync(join(dir, `memory/requisitos/${m}`), { recursive: true });
    writeFileSync(join(dir, `Modules/${m}/module.json`), JSON.stringify({ name: m, providers: [`X${m}`] }));
    writeFileSync(join(dir, `Modules/${m}/Http/Controllers/A.php`), '<?php\n');
  }
  g(['add', '-A']);
  for (const m of ['Foo', 'Bar']) execFileSync('node', ['scripts/governance/module-surface.mjs', m, '--write'], { cwd: dir, stdio: 'ignore' });
  g(['add', '-A']); g(['commit', '-qm', 'base']);
  return dir;
}
const checkSup = (dir, m) => spawnSync('node', ['scripts/governance/module-surface.mjs', m, '--check'], { cwd: dir }).status;

{
  const dir = sandboxSup();
  ok(checkSup(dir, 'Foo') === 0 && checkSup(dir, 'Bar') === 0, 'controle: sandbox nasce sem drift');
  writeFileSync(join(dir, 'Modules/Foo/Http/Controllers/B.php'), '<?php\n');
  execFileSync('git', ['add', '--', 'Modules/Foo/Http/Controllers/B.php'], { cwd: dir, stdio: 'ignore' });
  ok(checkSup(dir, 'Foo') === 1, 'controle positivo: arquivo novo deixa Foo com drift');
  const r = roda(dir, 'git commit -m x');
  ok(stageado(dir).includes(SUP('Foo')), 'MORDE: arquivo novo regenera e estagia a SUPERFICIE do modulo');
  ok(checkSup(dir, 'Foo') === 0, 'MORDE: depois do hook o --check do modulo fica verde');
  ok(!stageado(dir).includes(SUP('Bar')), 'ESCOPO: modulo nao tocado nao entra');
  ok(/REGENEREI e ESTAGIEI/.test(r.stderr), 'avisa o que fez');
  rmSync(dir, { recursive: true, force: true });
}
{
  const dir = sandboxSup();
  writeFileSync(join(dir, 'Modules/Foo/Http/Controllers/A.php'), '<?php // editado\n');
  execFileSync('git', ['add', '--', 'Modules/Foo/Http/Controllers/A.php'], { cwd: dir, stdio: 'ignore' });
  const antes = readFileSync(join(dir, SUP('Foo')), 'utf8');
  const r = roda(dir, 'git commit -m x');
  ok(readFileSync(join(dir, SUP('Foo')), 'utf8') === antes && !r.stderr, 'SILENCIO: so edicao nao toca o mapa');
  rmSync(dir, { recursive: true, force: true });
}
{
  const dir = sandboxSup();
  // drift HERDADO em Bar: arquivo commitado sem regenerar
  writeFileSync(join(dir, 'Modules/Bar/Http/Controllers/Z.php'), '<?php\n');
  execFileSync('git', ['add', '-A'], { cwd: dir, stdio: 'ignore' });
  execFileSync('git', ['commit', '-qm', 'drift herdado'], { cwd: dir, stdio: 'ignore' });
  ok(checkSup(dir, 'Bar') === 1, 'controle: Bar ficou com drift herdado');
  writeFileSync(join(dir, 'Modules/Foo/Http/Controllers/B.php'), '<?php\n');
  execFileSync('git', ['add', '--', 'Modules/Foo/Http/Controllers/B.php'], { cwd: dir, stdio: 'ignore' });
  roda(dir, 'git commit -m x');
  ok(stageado(dir).includes(SUP('Foo')) && !stageado(dir).includes(SUP('Bar')), 'HERDADO: drift de outro modulo nao entra no commit alheio');
  rmSync(dir, { recursive: true, force: true });
}
{
  const dir = sandboxSup();
  writeFileSync(join(dir, 'Modules/Foo/Http/Controllers/B.php'), '<?php\n');
  writeFileSync(join(dir, 'Modules/Foo/Http/Controllers/SOLTO.php'), '<?php\n');   // nao rastreado
  execFileSync('git', ['add', '--', 'Modules/Foo/Http/Controllers/B.php'], { cwd: dir, stdio: 'ignore' });
  const antes = readFileSync(join(dir, SUP('Foo')), 'utf8');
  const r = roda(dir, 'git commit -m x');
  ok(readFileSync(join(dir, SUP('Foo')), 'utf8') === antes && !stageado(dir).includes(SUP('Foo')), 'LIMITE: arquivo nao rastreado no modulo => nao grava mapa que o CI nao ve');
  ok(/NAO regenerei/.test(r.stderr), 'LIMITE: e avisa por que pulou');
  rmSync(dir, { recursive: true, force: true });
}
{
  const dir = sandboxSup();
  writeFileSync(join(dir, 'Modules/Foo/Http/Controllers/B.php'), '<?php\n');
  execFileSync('git', ['add', '--', 'Modules/Foo/Http/Controllers/B.php'], { cwd: dir, stdio: 'ignore' });
  const r = roda(dir, 'git commit -m x -- Modules/Foo/Http/Controllers/B.php');
  ok(checkSup(dir, 'Foo') === 0 && !stageado(dir).includes(SUP('Foo')) && /NAO estagiei/.test(r.stderr), 'PATHSPEC: regenera mas nao estagia');
  rmSync(dir, { recursive: true, force: true });
}
{
  const dir = sandboxSup();
  execFileSync('git', ['rm', '-q', '--', 'Modules/Foo/Http/Controllers/A.php'], { cwd: dir, stdio: 'ignore' });
  roda(dir, 'git commit -m x');
  ok(stageado(dir).includes(SUP('Foo')) && checkSup(dir, 'Foo') === 0, 'MORDE: git rm tambem regenera');
  rmSync(dir, { recursive: true, force: true });
}
{
  const dir = sandboxSup();
  writeFileSync(join(dir, 'README.md'), 'x\n');
  execFileSync('git', ['add', '--', 'README.md'], { cwd: dir, stdio: 'ignore' });
  const r = roda(dir, 'git commit -m x');
  ok(!stageado(dir).includes(SUP('Foo')) && !stageado(dir).includes(SUP('Bar')) && !r.stderr, 'SILENCIO: arquivo fora de modulo nao faz nada');
  rmSync(dir, { recursive: true, force: true });
}

console.log('');
if (falhas) { console.error('FALHOU: ' + falhas + ' assert(s)'); process.exit(1); }
console.log('maquinas-inventario-no-commit: morde quando ha drift, silencia quando fresco, fail-open sempre.');
process.exit(0);
