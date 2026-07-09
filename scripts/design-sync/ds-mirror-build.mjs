#!/usr/bin/env node
// ds-mirror-build.mjs — montador determinístico do colors_and_type.css do espelho a partir do git.
//
// Inverso do ds-token-diff.mjs: pega o colors_and_type.css ATUAL do espelho (o scaffold — header,
// @font-face, aliases, estilos de elemento) e, POR ESCOPO, troca o VALOR de cada token que o git
// também define pelo valor do git (resources/css/tokens/_generated-*.css). Preserva tudo o mais
// (comentários, tokens só-do-espelho/aliases, estrutura). É a perna 2 do runbook design-sync-push.md.
//
// NÃO acrescenta tokens git-only (mudança estrutural = decisão humana) — só reconcilia valores.
// Uso:
//   node scripts/design-sync/ds-mirror-build.mjs <mirror-atual.css> [tokensDir] > <reconciliado.css>
//   tokensDir default = resources/css/tokens

import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const GIT_FILES = {
  light: ['_generated-inertia-theme.css', '_generated-foundations-light.css'],
  dark: ['_generated-inertia-dark.css', '_generated-foundations-dark.css'],
  'cockpit-light': ['_generated-cockpit-light.css'],
  'cockpit-dark': ['_generated-cockpit-dark.css'],
};
const norm = (v) => v.trim().replace(/\s+/g, ' ').replace(/;+$/, '').trim();

function extractVars(body) {
  const out = new Map();
  const re = /(--[a-z0-9-]+)\s*:\s*([^;]+);/gi;
  let m;
  while ((m = re.exec(body)) !== null) out.set(m[1], norm(m[2]));
  return out;
}
function scopeOf(selector) {
  const s = selector.toLowerCase();
  const dark = s.includes('[data-theme="dark"]') || /\.dark\b/.test(s);
  const cockpit = s.includes('.cockpit');
  if (cockpit && dark) return 'cockpit-dark';
  if (cockpit) return 'cockpit-light';
  if (dark) return 'dark';
  if (s.includes(':root') || s.includes('@theme')) return 'light';
  return null;
}
function gitScope(tokensDir, scope) {
  const map = new Map();
  for (const f of GIT_FILES[scope]) {
    let css; try { css = readFileSync(join(tokensDir, f), 'utf8'); } catch { continue; }
    for (const [k, v] of extractVars(css)) map.set(k, v);
  }
  return map;
}

const [mirrorPath, tokensDir = 'resources/css/tokens'] = process.argv.slice(2);
if (!mirrorPath) { console.error('uso: node ds-mirror-build.mjs <mirror-atual.css> [tokensDir]'); process.exit(1); }
const css = readFileSync(mirrorPath, 'utf8');

let changed = 0, blocks = 0;
// Reescreve bloco a bloco: dentro de cada { }, troca o valor dos tokens que o git define nesse escopo.
const out = css.replace(/([^{}]+)\{([^{}]*)\}/g, (full, sel, body) => {
  const scope = scopeOf(sel);
  if (!scope) return full;
  blocks++;
  const git = gitScope(tokensDir, scope);
  const newBody = body.replace(/(--[a-z0-9-]+)(\s*:\s*)([^;]+)(;)/gi, (line, name, sep, val, semi) => {
    if (!git.has(name)) return line;                 // token só-do-espelho/alias → intacto
    const gv = git.get(name);
    if (norm(val) === gv) return line;               // já bate → intacto
    changed++;
    return `${name}${sep}${gv}${semi}`;              // troca só o valor; comentário pós-';' preservado
  });
  return `${sel}{${newBody}}`;
});

process.stderr.write(`ds-mirror-build: ${blocks} blocos de escopo · ${changed} valores reconciliados a partir do git\n`);
process.stdout.write(out);
