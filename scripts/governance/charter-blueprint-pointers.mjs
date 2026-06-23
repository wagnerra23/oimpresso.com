#!/usr/bin/env node
// @ts-check
/**
 * charter-blueprint-pointers.mjs — auditoria de PONTEIROS DE PROTÓTIPO dos Page Charters.
 *
 * Complementa charter-refs.mjs (que cobre refs markdown + frontmatter component/runbook/
 * parent_capterra). ESTE foca no elo que o loop design→code quebrava silenciosamente: o
 * ponteiro de PROTÓTIPO/BLUEPRINT do charter (`mwart_pattern_reuse.blueprint_cowork`,
 * `blueprint_cowork`, e Refs apontando pra `prototipo-ui/**` ou `ui_kits/**`). Quando esse
 * ponteiro aponta pro VÁCUO, o gate 3-way (reconcile-triplet) não tem com o que comparar a
 * coluna do meio → o protótipo some e o conflito charter×produção fica invisível.
 *
 * Caso real provado: Produto tem 3 ponteiros órfãos —
 *   prototipo-ui/prototipos/produto-cockpit/  (frontmatter blueprint_cowork)
 *   prototipo-ui/prototipos/produto/          (cowork-map / charter)
 *   ui_kits/cowork-2026-05-09/prod-page.jsx   (Refs)
 *
 * Determinístico, sem deps, sem LLM. case-SENSITIVE (espelha CI Linux/Hostinger).
 *
 * Uso:
 *   node scripts/governance/charter-blueprint-pointers.mjs            (texto)
 *   node scripts/governance/charter-blueprint-pointers.mjs --json
 *   node scripts/governance/charter-blueprint-pointers.mjs --strict   (exit 1 se houver órfão)
 */
import { readFileSync, readdirSync, realpathSync, statSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const PAGES = join(ROOT, 'resources/js/Pages');

function existsExact(p) {
  if (!p) return false;
  try { return realpathSync.native(p).replaceAll('\\', '/') === p.replaceAll('\\', '/'); }
  catch { return false; }
}
function dirExists(p) {
  try { return statSync(p).isDirectory(); } catch { return false; }
}

const hasUnderscoreSeg = (rel) => rel.split('/').some((s) => s && s[0] === '_');

function charterFiles() {
  const out = [];
  if (!dirExists(PAGES)) return out;
  (function walk(dir) {
    for (const e of readdirSync(dir, { withFileTypes: true })) {
      const p = join(dir, e.name);
      if (e.isDirectory()) walk(p);
      else if (e.isFile() && p.endsWith('.charter.md')) {
        const rel = p.slice(PAGES.length + 1).replaceAll('\\', '/');
        if (!hasUnderscoreSeg(rel)) out.push(rel);
      }
    }
  })(PAGES);
  return out.sort();
}

function splitFrontmatter(content) {
  const m = content.match(/^---\r?\n(.*?)\r?\n---\r?\n?(.*)$/s);
  return m ? [m[1], m[2]] : ['', content];
}

/** Coleta todos os ponteiros de protótipo declarados num charter (com a fonte). */
function pointersOf(charterRel) {
  const abs = join(PAGES, charterRel);
  const [fm, body] = splitFrontmatter(readFileSync(abs, 'utf8'));
  const ptrs = [];

  // 1. frontmatter blueprint_cowork (sob mwart_pattern_reuse ou raiz)
  for (const mm of fm.matchAll(/^\s*blueprint_cowork:\s*["']?(\S+?)["']?\s*$/gm)) {
    ptrs.push({ path: mm[1], src: 'frontmatter:blueprint_cowork' });
  }
  // blueprint_screenshot_approval às vezes referencia path — ignoramos (não é dir/arquivo).

  // 2. Refs no corpo: backtick-paths pra prototipo-ui/** ou ui_kits/**
  for (const mm of body.matchAll(/`(prototipo-ui\/[^`]+|ui_kits\/[^`]+)`/g)) {
    const raw = mm[1].trim();
    if (/\.(jsx|tsx|html|css|md|json)$/.test(raw) || raw.endsWith('/')) {
      ptrs.push({ path: raw, src: 'corpo:Refs' });
    }
  }
  // 3. Refs no corpo via link markdown ](prototipo-ui/...) ou ](../...prototipo-ui...)
  for (const mm of body.matchAll(/\]\(([^)\s]*(?:prototipo-ui|ui_kits)\/[^)\s]+)\)/g)) {
    const raw = mm[1].trim();
    // só os que são caminho de repo (não http)
    if (!/^https?:\/\//.test(raw)) ptrs.push({ path: raw, src: 'corpo:link-md' });
  }

  // dedup por path
  const seen = new Set();
  return ptrs.filter((p) => { const k = p.path; if (seen.has(k)) return false; seen.add(k); return true; });
}

/** Resolve um ponteiro (pode ter ../ relativo ao charter) a um caminho repo-absoluto. */
function resolvePtr(charterRel, ptrPath) {
  if (/^https?:\/\//.test(ptrPath)) return null;
  // limpa âncoras/markdown
  let clean = ptrPath.replace(/[#].*$/, '');
  if (clean.startsWith('../') || clean.startsWith('./')) {
    // relativo ao dir do charter dentro de Pages/
    const charterDir = join(PAGES, charterRel, '..');
    const joined = join(charterDir, clean).replaceAll('\\', '/');
    return joined;
  }
  // repo-relative (prototipo-ui/... ou ui_kits/...)
  return join(ROOT, clean).replaceAll('\\', '/');
}

function isOrphan(absPath, rawPtr) {
  if (!absPath) return false;
  const isFile = /\.(jsx|tsx|html|css|md|json)$/.test(rawPtr);
  return isFile ? !existsExact(absPath) : !dirExists(absPath);
}

function audit() {
  const perCharter = [];
  for (const rel of charterFiles()) {
    const ptrs = pointersOf(rel);
    const orphans = [];
    for (const p of ptrs) {
      const abs = resolvePtr(rel, p.path);
      if (isOrphan(abs, p.path)) orphans.push({ path: p.path, src: p.src });
    }
    if (ptrs.length) perCharter.push({ charter: 'resources/js/Pages/' + rel, total: ptrs.length, orphans });
  }
  const withOrphans = perCharter.filter((c) => c.orphans.length > 0);
  const totalOrphans = withOrphans.reduce((a, c) => a + c.orphans.length, 0);
  return { perCharter, withOrphans, totalOrphans };
}

/** Advisory B1 (ponte design↔código): charters com `visual_source:` SEM `visual_source_sha:`.
 *  Sem o sha do export, não dá pra detectar DRIFT quando `cowork/` é sobrescrito no próximo
 *  handoff. Report-only — NÃO afeta --strict (é base do `<tela>.map.json` por-região, B2). */
function shaAdvisory() {
  const missing = [];
  for (const rel of charterFiles()) {
    const [fm] = splitFrontmatter(readFileSync(join(PAGES, rel), 'utf8'));
    if (!/^visual_source:\s*\S/m.test(fm)) continue;
    if (!/^visual_source_sha:\s*\S/m.test(fm)) missing.push('resources/js/Pages/' + rel);
  }
  return missing;
}

// ── CLI ──────────────────────────────────────────────────────────────────────
const json = process.argv.includes('--json');
const strict = process.argv.includes('--strict');
const r = audit();
const shaMiss = shaAdvisory();

if (json) {
  console.log(JSON.stringify({
    tool: 'charter-blueprint-pointers',
    charters_com_ponteiro: r.perCharter.length,
    charters_com_orfao: r.withOrphans.length,
    total_orfaos: r.totalOrphans,
    detalhe: r.withOrphans,
    visual_source_sha_faltando: shaMiss,
  }, null, 2));
} else {
  console.log('charter-blueprint-pointers — auditoria de ponteiros de protótipo/blueprint dos charters\n');
  console.log(`Charters com ≥1 ponteiro de protótipo: ${r.perCharter.length}`);
  console.log(`Charters com ponteiro ÓRFÃO (aponta pro vácuo): ${r.withOrphans.length}`);
  console.log(`Total de ponteiros órfãos: ${r.totalOrphans}\n`);
  for (const c of r.withOrphans) {
    console.log(`  ${c.charter}  (${c.orphans.length}/${c.total} órfão)`);
    for (const o of c.orphans) console.log(`     ✗ ${o.path}   [${o.src}]`);
  }
  if (!r.withOrphans.length) console.log('  ✓ nenhum ponteiro órfão.');
  console.log(`\n— advisory B1 (ponte design↔código): charters com visual_source: SEM visual_source_sha: = ${shaMiss.length}`);
  console.log('  (sem sha do export, drift não rastreável quando cowork/ é sobrescrito — base do mapa por-região)');
  for (const m of shaMiss) console.log(`     ⚠ ${m}`);
}

if (strict && r.totalOrphans > 0) process.exit(1);
process.exit(0);
