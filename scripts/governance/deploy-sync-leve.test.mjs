#!/usr/bin/env node
// Contrato de scripts/deploy/sync-leve-remoto.sh (o passo de git do job sync-light do deploy).
// Monta um "origin" e um "servidor" em git de verdade, num diretório temporário, e roda o
// script como o deploy roda: `bash -s -- <sha> < sync-leve-remoto.sh`, dentro do servidor.
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const SCRIPT = readFileSync(resolve('scripts/deploy/sync-leve-remoto.sh'), 'utf8');
const git = (cwd, ...a) => execFileSync('git', a, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();

function cenario() {
  const raiz = mkdtempSync(join(tmpdir(), 'sync-leve-'));
  const origem = join(raiz, 'origem.git');
  const dev = join(raiz, 'dev');
  execFileSync('git', ['init', '-q', '--bare', '-b', 'main', origem]);
  execFileSync('git', ['clone', '-q', origem, dev], { stdio: 'ignore' });
  for (const [k, v] of [['user.email', 't@t'], ['user.name', 't']]) git(dev, 'config', k, v);
  const commit = nome => { writeFileSync(join(dev, nome), nome); git(dev, 'add', nome); git(dev, 'commit', '-q', '-m', nome); return git(dev, 'rev-parse', 'HEAD'); };
  const A = commit('a.txt');
  git(dev, 'push', '-q', 'origin', 'HEAD:main');
  const servidor = join(raiz, 'servidor');
  execFileSync('git', ['clone', '-q', origem, servidor], { stdio: 'ignore' });
  return { dev, servidor, A, commit, publica: () => git(dev, 'push', '-q', 'origin', 'HEAD:main') };
}

function roda(servidor, sha, script = SCRIPT) {
  const out = execFileSync('bash', ['-s', '--', sha], { cwd: servidor, input: script, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'] });
  return { out, head: git(servidor, 'rev-parse', 'HEAD') };
}

let falhas = 0;
const check = (nome, ok) => { console.log(`${ok ? '✓' : '✗'} ${nome}`); if (!ok) falhas++; };

// 1. main no SHA do run: aplica.
{
  const c = cenario(); const B = c.commit('b.txt'); c.publica();
  check('RELEASE: main no SHA do run → o servidor vai a ele', roda(c.servidor, B).head === B);
}
// 2. O caso de 2026-10-29: main andou para um commit que não gera deploy; o servidor está atrás.
{
  const c = cenario(); const B = c.commit('b.casos.md'); c.commit('prototipo.jsx'); c.publica();
  const r = roda(c.servidor, B);
  check('RELEASE: main andou e o servidor está atrás → aplica o SHA do run, não o tip', r.head === B);
}
// 3. O servidor já contém o SHA (um run mais novo passou antes): nada muda.
{
  const c = cenario(); const B = c.commit('b.txt'); const C = c.commit('c.txt'); c.publica();
  git(c.servidor, 'fetch', '-q', 'origin'); git(c.servidor, 'reset', '-q', '--hard', C);
  check('RELEASE: o servidor já contém o SHA → não volta para trás', roda(c.servidor, B).head === C);
}
// 4. O servidor está num commit fora da linha da main: não mexe.
{
  const c = cenario(); const B = c.commit('b.txt'); c.commit('c.txt'); c.publica();
  writeFileSync(join(c.servidor, 'x.txt'), 'x');
  git(c.servidor, 'add', 'x.txt');
  execFileSync('git', ['-c', 'user.email=t@t', '-c', 'user.name=t', 'commit', '-q', '-m', 'x'], { cwd: c.servidor });
  const D = git(c.servidor, 'rev-parse', 'HEAD');
  check('RELEASE: servidor fora da linha da main → não mexe', roda(c.servidor, B).head === D);
}
// Mutações: o comportamento antigo (pular sempre que a main andou) e o tip no lugar do SHA.
{
  const pulaSempre = SCRIPT.replace(/  if git merge-base --is-ancestor "\$SHA"[\s\S]*?\nfi\n/, '  exit 0\nfi\n');
  const c = cenario(); const B = c.commit('b.casos.md'); c.commit('prototipo.jsx'); c.publica();
  check('BITE: pular sempre que a main andou deixa o servidor no commit antigo', pulaSempre !== SCRIPT && roda(c.servidor, B, pulaSempre).head === c.A);
}
{
  const vaiProTip = SCRIPT.replace('git reset --hard "$SHA"', 'git reset --hard origin/main');
  const c = cenario(); const B = c.commit('b.casos.md'); const C = c.commit('app.php'); c.publica();
  check('BITE: reset no tip publicaria o commit mais novo (runtime sem deploy completo)', vaiProTip !== SCRIPT && roda(c.servidor, B, vaiProTip).head === C);
}

console.log(falhas ? `\n${falhas} falha(s)` : '\nOK — sync leve vai ao SHA do run quando o servidor está atrás; 4 RELEASE + 2 mutações que mordem.');
process.exit(falhas ? 1 : 0);
