#!/usr/bin/env node
// placar-recibos.test.mjs — bite-test do `placar.mjs --recibos-perdidos` (incidente #7842 · #7844, 2026-09-23).
// Exercita o CLI DE FORA, num repo git descartável, porque o chokepoint é o git diff real e não
// a função pura (§5 2026-07-30: assert em helper exportado não prova o pipeline).
//   BITE      apagar um `_saida` sem recolocar o conteúdo → rc 1, nomeando o path.
//   RELEASE   `git mv` pra outra pasta · apagar e recriar idêntico noutro path · cópia que já
//             existia noutro lugar · apagar arquivo que não é recibo → rc 0.
//   NÃO MEDI  base inexistente · sem --base → rc 2 (nunca "0 apagados").

import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, rmSync, renameSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const SCRIPT = join(dirname(fileURLToPath(import.meta.url)), 'placar.mjs');
const TMP = join(tmpdir(), `placar-recibos-test-${process.pid}`);
let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };
const git = (cwd, ...a) => execFileSync('git', a, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
const cli = (cwd, args) => {
  try { return { rc: 0, out: execFileSync('node', [SCRIPT, '--root', cwd, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }), err: '' }; }
  catch (e) { return { rc: e.status ?? 1, out: e.stdout || '', err: e.stderr || '' }; }
};

let n = 0;
/** Repo com 1 commit base contendo `arquivos`; `mudar(dir)` produz o 2º commit. */
function cenario(arquivos, mudar) {
  const dir = join(TMP, `r${n++}`);
  mkdirSync(dir, { recursive: true });
  git(dir, 'init', '-q');
  git(dir, 'config', 'user.email', 't@t'); git(dir, 'config', 'user.name', 't');
  git(dir, 'config', 'core.autocrlf', 'false');
  for (const [p, c] of Object.entries(arquivos)) { mkdirSync(dirname(join(dir, p)), { recursive: true }); writeFileSync(join(dir, p), c); }
  git(dir, 'add', '-A'); git(dir, 'commit', '-q', '-m', 'base');
  mudar(dir);
  git(dir, 'add', '-A'); git(dir, 'commit', '-q', '--allow-empty', '-m', 'mudanca');
  return dir;
}
const REC = 'inbox/mod/playbook/_saida-01.md';
const BASE = { [REC]: 'recibo de quem executou\n', 'outro.txt': 'x\n' };
const args = ['--recibos-perdidos', '--base', 'HEAD~1', '--head', 'HEAD'];

try {
  let d = cenario(BASE, (dir) => rmSync(join(dir, REC)));
  let r = cli(d, args);
  ok(r.rc === 1 && r.err.includes(REC), 'BITE: recibo apagado sem recolocar → rc 1, nomeando o path');

  d = cenario({ ...BASE, 'inbox/mod/playbook/_saida-06-bens.md': 'fora do padrao NN\n' },
    (dir) => rmSync(join(dir, 'inbox/mod/playbook/_saida-06-bens.md')));
  ok(cli(d, args).rc === 1, 'BITE: `_saida` fora do formato NN também conta (é recibo do mesmo jeito)');

  d = cenario(BASE, (dir) => { mkdirSync(join(dir, 'novo/playbook'), { recursive: true }); renameSync(join(dir, REC), join(dir, 'novo/playbook/_saida-01.md')); });
  r = cli(d, args);
  ok(r.rc === 0, 'RELEASE: mudança de pasta (mesmo conteúdo) passa');

  d = cenario(BASE, (dir) => { rmSync(join(dir, REC)); mkdirSync(join(dir, 'b'), { recursive: true }); writeFileSync(join(dir, 'b/_saida-01.md'), 'recibo de quem executou\n'); writeFileSync(join(dir, 'b/pad.txt'), 'y'.repeat(4000)); });
  ok(cli(d, args).rc === 0, 'RELEASE: apagado e recriado IDÊNTICO noutro path passa');

  d = cenario({ ...BASE, 'copia/_saida-01.md': 'recibo de quem executou\n' }, (dir) => rmSync(join(dir, REC)));
  ok(cli(d, args).rc === 0, 'RELEASE: cópia idêntica que já existia noutro path (blob na árvore final) passa');

  d = cenario(BASE, (dir) => rmSync(join(dir, 'outro.txt')));
  ok(cli(d, args).rc === 0, 'CONTROLE: apagar arquivo que não é recibo não acusa');

  d = cenario(BASE, (dir) => writeFileSync(join(dir, REC), 'recibo editado\n'));
  ok(cli(d, args).rc === 0, 'CONTROLE: editar o recibo (M) não é apagar');

  d = cenario(BASE, () => {});
  r = cli(d, ['--recibos-perdidos', '--base', 'naoexiste']);
  ok(r.rc === 2 && /NÃO MEDI/.test(r.err), 'NÃO MEDI: base inexistente → rc 2, nunca "0 apagados"');
  r = cli(d, ['--recibos-perdidos']);
  ok(r.rc === 2 && /NÃO MEDI/.test(r.err), 'NÃO MEDI: sem --base → rc 2');
} finally {
  rmSync(TMP, { recursive: true, force: true });
}
console.log(`\n${fails ? `${fails} FALHA(S)` : 'todos os casos passaram'}`);
process.exit(fails ? 1 : 0);
