#!/usr/bin/env node
// ds-guard.mjs — DS-GUARD (defesa tipo-forte) · PROCESSO_MEMORIA_CC.md §8
//
// Check mecânico que DISPARA SOZINHO e pega os anti-padrões L-02 / L-21 / L-23
// sem depender de eu lembrar de ler (TESTE-04/TESTE-06). Rodar no fim de toda
// build visual, ANTES do `done`, sobre os ARQUIVOS TOCADOS.
//
// Regras do gate (verbatim §8):
//   - paleta inventada = >=4 tokens de cor (oklch/hex) com o mesmo prefixo
//     bespoke (cor so por `.<tela>-scope{--accent}`, nunca `--<prefixo>-*`).
//   - tela na raiz      = `.html` com React (text/babel + createRoot/ReactDOM)
//     fora do `oimpresso.com.html`.
//   - ilegivel (char especial no nome) = FALHA alta ("checar manual"), nunca
//     skip silencioso.
//   - arvore-inteira (--all) = RELATORIO de divida (migracao DS), NAO bloqueio.
//
// A heuristica (regex de familia de tokens, limiar >=4) e' transcrita do §8 de
// proposito — o processo proibe "melhorar de passagem" (regra de corte §10).
//
// Uso:
//   node prototipo-ui/ds-guard.mjs <arquivo.css|arquivo.html> [...]   # arquivos tocados (BLOQUEIA)
//   node prototipo-ui/ds-guard.mjs --changed [base]                   # git diff --name-only vs base (default: HEAD)
//   node prototipo-ui/ds-guard.mjs --all                              # relatorio de divida (NAO bloqueia)
//
// Exit code: 0 = limpo / relatorio · 1 = BLOQUEIA (>=1 falha em modo arquivos)

import { readFile } from 'node:fs/promises';
import { readdirSync, statSync } from 'node:fs';
import { basename, join, resolve, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execSync } from 'node:child_process';

const HERE = dirname(fileURLToPath(import.meta.url)); // prototipo-ui/
const ROOT = resolve(HERE, '..');                     // repo root
const HOST_HTML = 'oimpresso.com.html';               // o unico .html com React permitido (§8)

const log = (...a) => console.log(...a);

const safeRead = async (f) => {
  try { return { ok: 1, t: await readFile(f, 'utf8') }; }
  catch { return { ok: 0 }; }
};

// familia de tokens de cor por prefixo bespoke (§8 fams) — >=4 = paleta inventada
const fams = (css) => {
  const d = css.match(/--[\w-]+\s*:\s*(?:oklch\(|#[0-9a-fA-F]{3,8})/g) || [];
  const b = {};
  for (const x of d) { const m = x.match(/--([a-z]+)-/i); b[m ? m[1] : '_'] = (b[m ? m[1] : '_'] || 0) + 1; }
  return Object.entries(b).filter(([, n]) => n >= 4);
};

async function gate(cssFiles, htmlFiles) {
  let fail = 0;
  for (const f of cssFiles) {
    const r = await safeRead(f);
    if (!r.ok) { log('! ' + f + ' ILEGIVEL=FALHA'); fail++; continue; }
    const F = fams(r.t);
    if (F.length) { log('X ' + f + ' paleta ' + F.map(([p, n]) => '--' + p + '-*(' + n + ')').join(' ')); fail++; }
    else log('OK ' + f);
  }
  for (const f of htmlFiles) {
    const r = await safeRead(f);
    if (!r.ok) { log('! ' + f + ' ILEGIVEL=FALHA'); fail++; continue; }
    if (basename(f) !== HOST_HTML && /text\/babel/.test(r.t) && /(createRoot|ReactDOM)/.test(r.t)) {
      log('X ' + f + ' tela na raiz (L-21)'); fail++;
    } else log('OK ' + f);
  }
  return fail;
}

// varre prototipo-ui/ (sem _arquivo / _BACKUP) por *-page.css e *.html (relatorio --all)
function walkProto() {
  const css = [], html = [];
  const skip = new Set(['_arquivo', '_BACKUP-NAO-USAR', 'node_modules', '.git']);
  (function walk(dir) {
    for (const name of readdirSync(dir)) {
      if (skip.has(name)) continue;
      const full = join(dir, name);
      let st; try { st = statSync(full); } catch { continue; }
      if (st.isDirectory()) walk(full);
      else if (/-page\.css$/.test(name)) css.push(full);
      else if (/\.html$/.test(name)) html.push(full);
    }
  })(HERE);
  return { css, html };
}

const argv = process.argv.slice(2);

if (argv[0] === '--all') {
  // RELATORIO de divida (nao bloqueia) — §8: arvore-inteira = relatorio, nao gate
  const { css, html } = walkProto();
  log('# DS-GUARD --all (relatorio de divida · NAO bloqueia) · ' + relative(ROOT, HERE) + '/');
  const fail = await gate(css, html);
  log(fail ? ('-- divida: ' + fail + ' arquivo(s) com anti-padrao (migracao DS pendente)') : '-- limpo');
  process.exit(0);
}

let files = argv;
if (argv[0] === '--changed') {
  const base = argv[1] || 'HEAD';
  try {
    const out = execSync(`git -C "${ROOT}" diff --name-only ${base}`, { encoding: 'utf8' });
    files = out.split('\n').map((s) => s.trim()).filter(Boolean).map((p) => join(ROOT, p));
  } catch (e) { log('! git diff falhou: ' + e.message); process.exit(1); }
}

if (!files.length) {
  log('uso: node prototipo-ui/ds-guard.mjs <arquivos tocados> | --changed [base] | --all');
  process.exit(0);
}

const cssFiles = files.filter((f) => /\.css$/.test(f));
const htmlFiles = files.filter((f) => /\.html$/.test(f));
const ignored = files.filter((f) => !/\.(css|html)$/.test(f));
for (const f of ignored) log('. ' + f + ' (ignorado: nao e css/html)');

const fail = await gate(cssFiles, htmlFiles);
log(fail ? ('BLOQUEIA: ' + fail) : 'limpo');
process.exit(fail ? 1 : 0);
