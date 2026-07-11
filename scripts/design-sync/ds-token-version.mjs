#!/usr/bin/env node
// ds-token-version.mjs — semver + changelog do PACOTE DE TOKENS do Design System.
//
// POR QUE EXISTE: os tokens do DS (semantic.tokens.json → _generated-*.css) são um
//   contrato consumido pelo app, pelo espelho e pelos bundles. Hoje a "versão" do contrato
//   é implícita (o commit sha no README do espelho). Sem semver + changelog, um consumidor
//   não sabe se um token sumiu (quebra) ou só mudou de valor (re-render). Este script versiona
//   a SUPERFÍCIE de tokens e emite um changelog de delta — derivado do próprio surface, não
//   da palavra de ninguém (mesma disciplina do ds-token-diff: fonte = os _generated-*.css).
//
// NÃO duplica ds-ledger (censo de adoção por tela) nem ds-report (placar eslint) — versiona
//   o CONTRATO de tokens, coisa que nenhum dos dois faz.
//
// SUPERFÍCIE = todos os --token declarados nos 6 _generated-*.css, por escopo
//   (light/dark/cockpit-light/cockpit-dark). fingerprint = sha256 do surface ordenado.
//
// SEMVER (convenção design-token · DTCG/Style Dictionary community):
//   MAJOR = token REMOVIDO/renomeado         (consumidor quebra)
//   MINOR = token ADICIONADO ou VALOR mudado (mudança visível, nome compatível)
//   (sem bump = surface idêntica; mudança só de comentário/formato não altera surface)
//
// Uso:
//   node scripts/design-sync/ds-token-version.mjs            # relatório (versão + drift + bump sugerido)
//   node scripts/design-sync/ds-token-version.mjs --check    # exit 1 se tokens mudaram sem bump (gate)
//   node scripts/design-sync/ds-token-version.mjs --write [--date YYYY-MM-DD] [--prev <dir>]
//                                                            # bumpa version.json + prepend CHANGELOG.md
//   flags de teste: --tokens <dir> --version-file <f> --changelog <f>
//
//   default tokens       = resources/css/tokens
//   default version-file = resources/css/tokens/version.json
//   default changelog    = resources/css/tokens/CHANGELOG.md
//   --prev <dir>         = surface anterior p/ o delta (default: git HEAD dos _generated-*.css)

import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { dirname, join, resolve } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '..', '..');

const GIT_FILES = {
  light: ['_generated-inertia-theme.css', '_generated-foundations-light.css'],
  dark: ['_generated-inertia-dark.css', '_generated-foundations-dark.css'],
  'cockpit-light': ['_generated-cockpit-light.css'],
  'cockpit-dark': ['_generated-cockpit-dark.css'],
};
const SCOPES = Object.keys(GIT_FILES);
const norm = (v) => v.trim().replace(/\s+/g, ' ').replace(/;+$/, '').trim();

function extractVars(css) {
  const out = new Map();
  const re = /(--[a-z0-9-]+)\s*:\s*([^;]+);/gi;
  let m;
  while ((m = re.exec(css)) !== null) if (!out.has(m[1])) out.set(m[1], norm(m[2]));
  return out;
}

// surface = { scope: Map<token,value> } lido dos _generated-*.css de um diretório.
function surfaceFromDir(dir, readFile) {
  const s = {};
  for (const scope of SCOPES) {
    const map = new Map();
    for (const f of GIT_FILES[scope]) {
      let css;
      try { css = readFile(join(dir, f)); } catch { continue; }
      if (css == null) continue;
      for (const [k, v] of extractVars(css)) map.set(k, v);
    }
    s[scope] = map;
  }
  return s;
}

// serialização determinística: "scope|token=value" ordenado.
function serialize(surface) {
  const lines = [];
  for (const scope of SCOPES) {
    for (const [k, v] of [...surface[scope].entries()].sort((a, b) => a[0].localeCompare(b[0]))) {
      lines.push(`${scope}|${k}=${v}`);
    }
  }
  return lines.join('\n');
}
const fingerprint = (surface) => createHash('sha256').update(serialize(surface)).digest('hex');
const tokenCount = (surface) => SCOPES.reduce((n, s) => n + surface[s].size, 0);

// delta prev→cur por escopo.
function delta(prev, cur) {
  const added = [], removed = [], changed = [];
  for (const scope of SCOPES) {
    const p = prev[scope] || new Map(), c = cur[scope] || new Map();
    for (const [k, v] of c) {
      if (!p.has(k)) added.push({ scope, k, v });
      else if (p.get(k) !== v) changed.push({ scope, k, from: p.get(k), to: v });
    }
    for (const [k, v] of p) if (!c.has(k)) removed.push({ scope, k, v });
  }
  return { added, removed, changed };
}
const bumpClass = (d) => d.removed.length ? 'major' : (d.added.length || d.changed.length) ? 'minor' : 'none';
function bump(version, cls) {
  const [maj, min, pat] = String(version || '0.0.0').split('.').map((n) => parseInt(n, 10) || 0);
  if (cls === 'major') return `${maj + 1}.0.0`;
  if (cls === 'minor') return `${maj}.${min + 1}.0`;
  return `${maj}.${min}.${pat}`;
}

// surface anterior via git HEAD dos _generated-*.css (default do --write/report).
function surfaceFromGit(tokensDir) {
  const rel = (f) => `resources/css/tokens/${f}`;
  return surfaceFromDir(tokensDir, (abs) => {
    const f = abs.split(/[\\/]/).pop();
    try {
      return execFileSync('git', ['show', `HEAD:${rel(f)}`], { cwd: REPO, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
    } catch { return null; }
  });
}

// ── args ──
const args = process.argv.slice(2);
const flag = (name, def) => { const i = args.indexOf(name); return i >= 0 && args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : def; };
const has = (name) => args.includes(name);

const tokensDir = resolve(flag('--tokens', join(REPO, 'resources', 'css', 'tokens')));
const versionFile = resolve(flag('--version-file', join(tokensDir, 'version.json')));
const changelogFile = resolve(flag('--changelog', join(tokensDir, 'CHANGELOG.md')));

const cur = surfaceFromDir(tokensDir, (abs) => readFileSync(abs, 'utf8'));
const fp = fingerprint(cur);
const recorded = existsSync(versionFile) ? JSON.parse(readFileSync(versionFile, 'utf8')) : { version: '0.0.0', fingerprint: '', tokenCount: 0 };
const drifted = fp !== recorded.fingerprint;

// ── --check (gate) ──
if (has('--check')) {
  if (!existsSync(versionFile)) {
    console.error('ds-token-version: version.json ausente — rode  npm run tokens:version:write  pra semear.');
    process.exit(1);
  }
  if (drifted) {
    console.error(`ds-token-version: superfície de tokens MUDOU sem bump de versão.\n  gravado : ${recorded.version}  fp:${recorded.fingerprint.slice(0, 12)}\n  atual   : fp:${fp.slice(0, 12)}  (${tokenCount(cur)} tokens)\n  → rode  npm run tokens:version:write  e commite version.json + CHANGELOG.md.`);
    process.exit(1);
  }
  console.log(`ds-token-version: OK — v${recorded.version} em dia (${tokenCount(cur)} tokens, fp:${fp.slice(0, 12)}).`);
  process.exit(0);
}

// ── delta contra prev (git HEAD, ou --prev dir) ──
const prevDir = flag('--prev', null);
const prev = prevDir ? surfaceFromDir(resolve(prevDir), (abs) => { try { return readFileSync(abs, 'utf8'); } catch { return null; } }) : surfaceFromGit(tokensDir);
const d = delta(prev, cur);
const cls = bumpClass(d);

// ── --write ──
if (has('--write')) {
  const seed = !existsSync(versionFile);
  if (!seed && cls === 'none' && !drifted) { console.log('ds-token-version: sem delta na superfície — nada a versionar.'); process.exit(0); }
  const date = flag('--date', new Date().toISOString().slice(0, 10));
  const newVersion = seed ? '1.0.0' : bump(recorded.version, cls === 'none' ? 'minor' : cls);
  writeFileSync(versionFile, JSON.stringify({ version: newVersion, fingerprint: fp, tokenCount: tokenCount(cur), updated: date }, null, 2) + '\n');

  const sec = (title, arr, fmt) => arr.length ? `\n**${title}**\n${arr.map(fmt).join('\n')}\n` : '';
  const entry = seed
    ? `## v1.0.0 — ${date}  (seed)\n\nSuperfície inicial: ${tokenCount(cur)} tokens (${SCOPES.map((s) => `${s} ${cur[s].size}`).join(' · ')}).\n`
    : `## v${newVersion} — ${date}  (${cls === 'none' ? 'MINOR' : cls.toUpperCase()})\n` +
      sec('Removidos (BREAKING)', d.removed, (x) => `- \`${x.k}\` [${x.scope}] (era \`${x.v}\`)`) +
      sec('Adicionados', d.added, (x) => `- \`${x.k}\` [${x.scope}] = \`${x.v}\``) +
      sec('Valor alterado', d.changed, (x) => `- \`${x.k}\` [${x.scope}]: \`${x.from}\` → \`${x.to}\``);
  const prevLog = existsSync(changelogFile) ? readFileSync(changelogFile, 'utf8') : `# Changelog — pacote de tokens do Design System\n\n> Gerado por \`ds-token-version.mjs\` a partir da superfície dos \`_generated-*.css\`. Semver: MAJOR=remoção · MINOR=adição/valor.\n`;
  const head = prevLog.split('\n').slice(0, prevLog.startsWith('#') ? 4 : 0).join('\n');
  const body = prevLog.slice(head.length);
  writeFileSync(changelogFile, `${head}\n${entry}${body}`);
  console.log(`ds-token-version: v${recorded.version} → v${newVersion} (${cls === 'none' ? 'seed' : cls}) · +${d.added.length} ~${d.changed.length} -${d.removed.length} · CHANGELOG atualizado.`);
  process.exit(0);
}

// ── relatório (default) ──
console.log(`═══ ds-token-version ═══`);
console.log(`versão gravada : v${recorded.version}  (${recorded.tokenCount} tokens)`);
console.log(`superfície atual: ${tokenCount(cur)} tokens · fp:${fp.slice(0, 12)}${drifted ? '  ⚠️ DRIFT vs gravado' : '  ✓ em dia'}`);
if (drifted) {
  console.log(`\ndelta vs ${prevDir ? prevDir : 'git HEAD'}: +${d.added.length} adicionados · ~${d.changed.length} valor · -${d.removed.length} removidos`);
  console.log(`bump sugerido  : ${cls.toUpperCase()} → v${bump(recorded.version, cls === 'none' ? 'minor' : cls)}`);
  for (const x of d.removed.slice(0, 8)) console.log(`   - ${x.k} [${x.scope}]`);
  for (const x of d.changed.slice(0, 8)) console.log(`   ~ ${x.k} [${x.scope}] ${x.from} → ${x.to}`);
  for (const x of d.added.slice(0, 8)) console.log(`   + ${x.k} [${x.scope}]`);
  console.log(`\n→ rode  npm run tokens:version:write  pra gravar version.json + CHANGELOG.`);
}
