#!/usr/bin/env node
// smoke-bundle.mjs — o prod serve os assets que ESTE build gerou?
//
// Uso:
//   node scripts/deploy/smoke-bundle.mjs --manifest artifact/build-inertia/manifest.json --html /tmp/smoke.html
//   node scripts/deploy/smoke-bundle.mjs --selftest
//
// Exit: 0 = o /login serve exatamente os assets de entrada do manifest deste build ·
//       1 = serve outros (a publicação não chegou) · 2 = NÃO MEDI (manifest ou HTML ilegível,
//       ou nenhum asset de entrada achado) — nunca "passou".
//
// POR QUE EXISTE (2026-09-30). O smoke do deploy.yml reprovava quando `resources/js|css` tinha
// mudado desde o último deploy bem-sucedido E o hash servido era o mesmo de antes. O predicado
// era um substituto: "arquivo de front mudou" não implica "bundle muda". O #8297 mudou só
// COMENTÁRIO num .tsx; o build minificado saiu byte-idêntico, o hash ficou igual e o deploy
// reprovou com o bundle certo no ar. Pior: a base do `frontend_changed` é o último deploy COM
// SUCESSO, então o commit ficava no intervalo de TODO deploy seguinte e a esteira travava em
// vermelho até alguém mudar o front de verdade (medido: runs 36759140875 e o do 4fa39eb8f,
// que não toca `resources/js` e reprovou igual).
//
// O predicado certo é o que o gate queria provar desde o começo: os assets de entrada que o
// /login serve são os que o manifest DESTE build declara. Pega a publicação que não chegou
// (o caso dos hashes stale de 20/05) e não reprova build idêntico.

import { readFileSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

// Mesmo recorte do smoke do deploy.yml: os assets de entrada `app-*` e `inertia-*`.
const ASSET = /\/assets\/(?:inertia|app)-[a-zA-Z0-9_-]+\.(?:js|css)/g;

/** Assets de entrada que o manifest declara (file + css das entradas), no formato `/assets/x`. */
export function esperados(manifest) {
  const out = new Set();
  for (const v of Object.values(manifest || {})) {
    if (!v || !v.isEntry) continue;
    for (const f of [v.file, ...(v.css || [])]) {
      if (typeof f !== 'string') continue;
      const m = ('/' + f).match(ASSET);
      if (m) out.add(m[0]);
    }
  }
  return [...out].sort();
}

/** Assets de entrada que o HTML servido referencia. */
export function servidos(html) {
  return [...new Set(String(html).match(ASSET) || [])].sort();
}

/** 0 = igual · 1 = diferente · 2 = não medi. */
export function veredito(serv, esp) {
  if (!esp.length || !serv.length) return 2;
  return serv.length === esp.length && serv.every((x, i) => x === esp[i]) ? 0 : 1;
}

function main(argv) {
  if (argv.includes('--selftest')) return selftest();
  const arg = (n) => { const i = argv.indexOf(n); return i >= 0 ? argv[i + 1] : undefined; };
  const mPath = arg('--manifest');
  const hPath = arg('--html');
  if (!mPath || !hPath) { console.error('uso: --manifest <path> --html <path>'); return 2; }
  let manifest, html;
  try { manifest = JSON.parse(readFileSync(mPath, 'utf8')); } catch (e) { console.error(`NÃO MEDI: manifest ilegível (${mPath}): ${e.message}`); return 2; }
  try { html = readFileSync(hPath, 'utf8'); } catch (e) { console.error(`NÃO MEDI: HTML ilegível (${hPath}): ${e.message}`); return 2; }
  const esp = esperados(manifest);
  const serv = servidos(html);
  console.log(`ESPERADO (manifest deste build): ${esp.join(' ') || '(nenhum)'}`);
  console.log(`SERVIDO  (/login):               ${serv.join(' ') || '(nenhum)'}`);
  const v = veredito(serv, esp);
  if (v === 0) console.log('OK: o prod serve os assets deste build');
  else if (v === 1) console.log('DIFERENTE: o prod não serve os assets deste build (publicação não chegou)');
  else console.log('NÃO MEDI: nenhum asset de entrada no manifest ou no HTML');
  return v;
}

function selftest() {
  let falhas = 0;
  const ok = (cond, msg) => { console.log(`${cond ? '✓' : '✗'} ${msg}`); if (!cond) falhas++; };
  const man = {
    'resources/css/inertia.css': { file: 'assets/inertia-H1.css', isEntry: true },
    'resources/js/app.tsx': { file: 'assets/app-J1.js', css: ['assets/app-C1.css', 'assets/inertia-H1.css'], isEntry: true },
    'resources/js/Pages/X.tsx': { file: 'assets/X-Z9.js' },
  };
  const htmlCerto = '<link href="https://oimpresso.com/build-inertia/assets/app-C1.css"><link href="/build-inertia/assets/inertia-H1.css"><script src="/build-inertia/assets/app-J1.js">';
  const htmlVelho = htmlCerto.replace('app-J1.js', 'app-J0.js');

  ok(esperados(man).join(' ') === '/assets/app-C1.css /assets/app-J1.js /assets/inertia-H1.css', 'esperados: file + css das entradas, sem chunk de página');
  ok(veredito(servidos(htmlCerto), esperados(man)) === 0, 'build idêntico ao servido (o caso do #8297: só comentário) → 0, não reprova');
  ok(veredito(servidos(htmlVelho), esperados(man)) === 1, 'MORDE: prod serve hash velho → 1 (publicação não chegou)');
  ok(veredito([], esperados(man)) === 2, 'HTML sem asset → 2 (não medi), nunca 0');
  ok(veredito(servidos(htmlCerto), []) === 2, 'manifest sem entrada → 2 (não medi), nunca 0');

  // Pelo CLI, de fora: o contrato é o exit code que o deploy.yml lê.
  const dir = mkdtempSync(join(tmpdir(), 'smoke-bundle-'));
  try {
    const m = join(dir, 'manifest.json');
    const h = join(dir, 'login.html');
    writeFileSync(m, JSON.stringify(man));
    const cli = (html) => {
      writeFileSync(h, html);
      try { execFileSync(process.execPath, [fileURLToPath(import.meta.url), '--manifest', m, '--html', h], { stdio: 'pipe' }); return 0; }
      catch (e) { return e.status; }
    };
    ok(cli(htmlCerto) === 0, 'CLI: igual → exit 0');
    ok(cli(htmlVelho) === 1, 'CLI: hash velho → exit 1');
    writeFileSync(m, '{quebrado');
    ok(cli(htmlCerto) === 2, 'CLI: manifest ilegível → exit 2');
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
  console.log(falhas ? `\n${falhas} falha(s)` : '\ntodos os casos passaram');
  return falhas ? 1 : 0;
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  process.exit(main(process.argv.slice(2)));
}
