#!/usr/bin/env node
// @ts-check
/**
 * charter-refs.mjs — catraca de integridade de refs dos Page Charters (ADR 0256).
 *
 * Espelha o check `charter_refs_broken` do `jana:health-check`
 * (Modules/Jana/Services/CharterHealthChecker.php) no lado node/CI, e adiciona:
 *   - `--check`  conta refs quebradas na árvore atual; FALHA (exit 1) se > teto do
 *                baseline (governance/charter-refs-baseline.json). Se < teto, passa
 *                mas avisa que o baseline pode DESCER (ratchet-down).
 *   - `--fix`    aplica SÓ correção segura de profundidade off-by-one: reescreve link
 *                cujo alvo atual NÃO existe E cuja versão com +N "../" resolve pra
 *                arquivo existente. Nunca toca link bom nem ref genuinamente morta.
 *   - `--list`   dump completo das refs quebradas (triagem manual).
 *
 * Determinístico, sem LLM, sem deps. Preserva bytes/newlines não-tocados (no --fix).
 * O que conta como "quebrada" tem que bater 1:1 com o checker PHP (senão advisory e
 * gate divergem): refs estruturadas do frontmatter (component/runbook/parent_capterra
 * repo-relative) + links markdown relativos (`](../x)` / `](./x)`) no corpo.
 *
 * Uso: node scripts/governance/charter-refs.mjs [--check|--fix|--list]
 */
import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { join, dirname } from 'node:path';

const ROOT = process.cwd();
const PAGES = join(ROOT, 'resources/js/Pages');
const BASELINE_PATH = join(ROOT, 'governance/charter-refs-baseline.json');

// ── scan ──────────────────────────────────────────────────────────────────────
const hasUnderscoreSeg = (rel) => rel.split('/').some((s) => s && s[0] === '_');

function charterFiles() {
  const out = [];
  (function walk(dir) {
    for (const e of readdirSync(dir, { withFileTypes: true })) {
      const p = join(dir, e.name);
      if (e.isDirectory()) walk(p);
      else if (e.isFile()) {
        const rel = p.slice(PAGES.length + 1).replaceAll('\\', '/');
        if (p.endsWith('.charter.md') && !hasUnderscoreSeg(rel)) out.push(rel);
      }
    }
  })(PAGES);
  return out.sort();
}

function splitFrontmatter(content) {
  const m = content.match(/^---\r?\n(.*?)\r?\n---\r?\n?(.*)$/s);
  return m ? [m[1], m[2]] : ['', content];
}
const isRepoRelative = (p) => !/^https?:\/\//.test(p) && /^[A-Za-z0-9._-]+\//.test(p);

/** Resolve `../` manualmente (realpath falha quando o alvo não existe — é o que detectamos). */
function resolveRelative(dir, rel) {
  if (!rel || /^https?:\/\//.test(rel)) return null;
  const path = (dir + '/' + rel).replaceAll('\\', '/');
  const drive = path.match(/^[A-Za-z]:/) ? path.slice(0, 2) : '';
  const isAbsRoot = path.startsWith('/');
  const parts = [];
  for (const seg of (drive ? path.slice(2) : path).split('/')) {
    if (seg === '' || seg === '.') continue;
    if (seg === '..') { parts.pop(); continue; }
    parts.push(seg);
  }
  return drive + (isAbsRoot && !drive ? '/' : drive ? '/' : '') + parts.join('/');
}

/** @returns {Array<{charter:string, kind:string, target:string, link?:string}>} */
function collectBroken() {
  const broken = [];
  for (const rel of charterFiles()) {
    const abs = join(PAGES, rel);
    const [fm, body] = splitFrontmatter(readFileSync(abs, 'utf8'));

    for (const key of ['component', 'runbook', 'parent_capterra']) {
      const m = fm.match(new RegExp('^' + key + ':\\s*["\\\']?(\\S+?)["\\\']?\\s*$', 'm'));
      if (m) {
        const p = m[1];
        if (isRepoRelative(p) && !existsSync(join(ROOT, p.replace(/^\//, '')))) {
          broken.push({ charter: rel, kind: 'fm:' + key, target: p });
        }
      }
    }

    const seen = new Set();
    for (const mm of body.matchAll(/\]\((\.\.?\/[^)\s]+)\)/g)) {
      const link = mm[1];
      if (seen.has(link)) continue;
      seen.add(link);
      const clean = link.replace(/[#:].*$/, '');
      if (!clean) continue;
      const target = resolveRelative(dirname(abs).replaceAll('\\', '/'), clean);
      if (target && !existsSync(target)) broken.push({ charter: rel, kind: 'link', target: clean, link });
    }
  }
  return broken;
}

// ── fix (off-by-one seguro) ─────────────────────────────────────────────────────
function applySafeFix() {
  let fixed = 0, files = 0, unfixable = 0;
  const byCharter = new Map();
  for (const b of collectBroken()) {
    if (b.kind !== 'link') continue; // frontmatter (repo-relative) não é off-by-one
    if (!byCharter.has(b.charter)) byCharter.set(b.charter, []);
    byCharter.get(b.charter).push(b);
  }
  for (const [rel, items] of byCharter) {
    const abs = join(PAGES, rel);
    const dir = dirname(abs).replaceAll('\\', '/');
    let content = readFileSync(abs, 'utf8');
    let touched = false;
    for (const b of items) {
      const clean = (b.link || b.target).replace(/[#:].*$/, '');
      let corrected = null;
      for (const extra of ['../', '../../', '../../../']) {
        if (existsSync(resolveRelative(dir, extra + clean))) { corrected = extra + b.link; break; }
      }
      if (!corrected) { unfixable++; continue; }
      const needle = '](' + b.link + ')';
      if (content.includes(needle)) { content = content.split(needle).join('](' + corrected + ')'); touched = true; fixed++; }
    }
    if (touched) { writeFileSync(abs, content); files++; }
  }
  return { fixed, files, unfixable };
}

// ── baseline ────────────────────────────────────────────────────────────────────
function readCeiling() {
  if (!existsSync(BASELINE_PATH)) return null;
  try { return JSON.parse(readFileSync(BASELINE_PATH, 'utf8')).ceiling ?? null; }
  catch { return null; }
}

// ── CLI ──────────────────────────────────────────────────────────────────────────
const mode = process.argv.find((a) => ['--check', '--fix', '--list'].includes(a)) || '--check';

if (mode === '--list') {
  const broken = collectBroken();
  console.log(`charter_refs_broken: ${broken.length}`);
  for (const b of broken) console.log(`  ${b.charter} → ${b.kind === 'link' ? b.target : b.kind + ': ' + b.target}`);
  process.exit(0);
}

if (mode === '--fix') {
  const r = applySafeFix();
  console.log(`charter-refs --fix: ${r.fixed} link(s) corrigido(s) em ${r.files} charter(s); ${r.unfixable} não-fixável(is) (alvo morto — triagem manual).`);
  console.log(`Refs quebradas restantes: ${collectBroken().length}`);
  process.exit(0);
}

// --check (default)
const broken = collectBroken();
const ceiling = readCeiling();
const n = broken.length;

if (ceiling === null) {
  console.error('charter-refs --check: governance/charter-refs-baseline.json ausente ou sem `ceiling`. Crie o baseline primeiro.');
  process.exit(2);
}

console.log(`charter_refs_broken: ${n} (teto/baseline: ${ceiling})`);

if (n > ceiling) {
  console.error(`\n✗ catraca: ${n} refs quebradas > teto ${ceiling}. ${n - ceiling} nova(s) regressão(ões).`);
  console.error('  Refs introduzidas (amostra):');
  for (const b of broken.slice(0, 12)) console.error(`     - ${b.charter} → ${b.kind === 'link' ? b.target : b.kind + ': ' + b.target}`);
  console.error('\n  Conserte com: node scripts/governance/charter-refs.mjs --fix  (off-by-one seguro)');
  console.error('  Ou, se a ref morreu de vez, remova-a do charter. NÃO suba o teto sem BASELINE-ABSORB.');
  process.exit(1);
}

if (n < ceiling) {
  console.log(`\n✓ abaixo do teto. Catraca pode DESCER: ajuste governance/charter-refs-baseline.json ceiling ${ceiling}→${n} (commit só-baseline ou BASELINE-ABSORB).`);
} else {
  console.log('\n✓ no teto exato — sem regressão.');
}
process.exit(0);
