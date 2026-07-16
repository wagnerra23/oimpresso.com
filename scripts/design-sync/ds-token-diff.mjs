#!/usr/bin/env node
// ds-token-diff.mjs — motor do protocolo de sync DESIGN → GIT (ADR design→git, emenda 0315).
//
// Compara os tokens CSS do Design System vivo (colors_and_type.css, puxado via
// `DesignSync get_file`) contra os tokens compilados do git (resources/css/tokens/
// _generated-*.css) e reporta, POR ESCOPO, cada divergência de valor + tokens que
// só existem de um lado. NÃO escreve nada — é read-only (relatório + JSON). O porte
// pro semantic.tokens.json é decisão humana (Fundações Tier 0, gate de screenshot).
//
// Uso:
//   node scripts/design-sync/ds-token-diff.mjs <design.css> [tokensDir] [--json] [--companion <f.css> ...]
//   tokensDir default = resources/css/tokens
//
// --companion (2026-07-10): o espelho pode CURAR — o `colors_and_type.css` traz fundações
//   legíveis e OMITE de propósito o set de domínio `.cockpit`-scoped (origin/stage/sla-dot-line/
//   canal-bg-fg-tint/kind-soft), que vive num arquivo COMPANION `cockpit_domains.css` (gerado do
//   SSOT, PR #4097, linkado ao lado). Sem `--companion`, o motor reportaria esses ~56 tokens como
//   "git-only" (falso drift — o espelho os tem, só noutro arquivo). `--companion` concatena esses
//   arquivos à SUPERFÍCIE do espelho antes do diff, fechando o falso git-only. Substitui o
//   `ds-project-diff.mjs` (que reinventava esta checagem só pro companion — 2026-07-10 [W]
//   "leia a documentação"). Repetível: `--companion a.css --companion b.css`.
//
// Escopos comparados (design selector ↔ git generated file):
//   light        : :root / @theme        ↔ _generated-inertia-theme.css + _generated-foundations-light.css
//   dark         : .dark/[data-theme]    ↔ _generated-inertia-dark.css + _generated-foundations-dark.css
//   cockpit-light: .cockpit              ↔ _generated-cockpit-light.css
//   cockpit-dark : .cockpit[data-theme]  ↔ _generated-cockpit-dark.css

import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const SCOPES = ['light', 'dark', 'cockpit-light', 'cockpit-dark'];

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
  return null; // element styles, :where(), html/body → ignora
}

// Design: um arquivo com vários blocos { }. Bodies não têm chaves aninhadas.
function parseDesign(css) {
  const maps = Object.fromEntries(SCOPES.map((s) => [s, new Map()]));
  const re = /([^{}]+)\{([^{}]*)\}/g;
  let m;
  while ((m = re.exec(css)) !== null) {
    const scope = scopeOf(m[1]);
    if (!scope) continue;
    for (const [k, v] of extractVars(m[2])) maps[scope].set(k, v);
  }
  return maps;
}

function parseGit(tokensDir) {
  const maps = Object.fromEntries(SCOPES.map((s) => [s, new Map()]));
  for (const scope of SCOPES) {
    for (const f of GIT_FILES[scope]) {
      let css;
      try { css = readFileSync(join(tokensDir, f), 'utf8'); } catch { continue; }
      for (const [k, v] of extractVars(css)) maps[scope].set(k, v);
    }
  }
  return maps;
}

const isAlias = (v) => /^var\(/.test(v);

function diffScope(design, git) {
  const diverge = [], designOnly = [], gitOnly = [], aliasSkipped = [];
  for (const [k, dv] of design) {
    if (!git.has(k)) { (isAlias(dv) ? aliasSkipped : designOnly).push({ k, dv }); continue; }
    const gv = git.get(k);
    if (dv !== gv) {
      if (isAlias(dv) || isAlias(gv)) aliasSkipped.push({ k, dv, gv });
      else diverge.push({ k, dv, gv });
    }
  }
  for (const [k, gv] of git) if (!design.has(k)) gitOnly.push({ k, gv });
  return { diverge, designOnly, gitOnly, aliasSkipped };
}

// ── main ──
const argvRaw = process.argv.slice(2);
// --companion <f> (repetível): arquivos que compõem a superfície do espelho junto do design.css.
const companions = [];
for (let i = 0; i < argvRaw.length; i++) if (argvRaw[i] === '--companion' && argvRaw[i + 1]) companions.push(argvRaw[++i]);
const posic = argvRaw.filter((a, i) => !a.startsWith('--') && argvRaw[i - 1] !== '--companion');
const [designPath, tokensDir = 'resources/css/tokens'] = posic;
const asJson = argvRaw.includes('--json');
if (!designPath) { console.error('uso: node ds-token-diff.mjs <design.css> [tokensDir] [--json] [--companion <f.css> ...]'); process.exit(1); }

// superfície do espelho = design.css + companions concatenados (o espelho cura em N arquivos)
const superficie = [designPath, ...companions].map((p) => readFileSync(p, 'utf8')).join('\n');
const design = parseDesign(superficie);
const git = parseGit(tokensDir);

const report = {};
let totalDiverge = 0;
for (const scope of SCOPES) {
  const r = diffScope(design[scope], git[scope]);
  report[scope] = r;
  totalDiverge += r.diverge.length;
}

if (asJson) { console.log(JSON.stringify({ totalDiverge, report }, null, 2)); process.exit(0); }

console.log(`\n═══ DS token diff (design → git) ═══  divergências de VALOR: ${totalDiverge}\n`);
for (const scope of SCOPES) {
  const r = report[scope];
  console.log(`── ${scope} ──  diverge:${r.diverge.length} · design-only:${r.designOnly.length} · git-only:${r.gitOnly.length} · alias:${r.aliasSkipped.length}`);
  for (const { k, dv, gv } of r.diverge) console.log(`   ✗ ${k.padEnd(26)} git:${gv.padEnd(24)} → design:${dv}`);
  if (r.designOnly.length) console.log(`   +design-only: ${r.designOnly.map((x) => x.k).join(', ')}`);
  if (r.gitOnly.length) console.log(`   +git-only:    ${r.gitOnly.map((x) => x.k).join(', ')}`);
  console.log('');
}
