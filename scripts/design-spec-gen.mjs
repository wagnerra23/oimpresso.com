#!/usr/bin/env node
// scripts/design-spec-gen.mjs — deriva + gateia o CONTRATO ESTRUTURAL por-tela de uma .tsx.
//
// POR QUE (ADR 0255 · dossiê 2026-06-06-arte-view-contract-deterministico): a estrutura de UI
// por-tela (componentes/tokens/layout) é PURA e DERIVÁVEL, mas era julgada por LLM
// (review/visual-comparison/screen-grade). Este projeta, por-tela, o que reuse-index +
// foundation-guard fazem global → `<Tela>.design-spec.json` MACHINE-CHECKABLE ao lado do
// `<Tela>.charter.md`. Charter = intenção (LLM-judge ok). design-spec = estrutura (teste determinístico).
//
// DERIVADO da .tsx a cada run → não apodrece (ADR 0239, git=SSOT). O gate (--check) garante que o
// spec commitado SEMPRE reflete a .tsx: mudou a tela → regenera → o diff do spec mostra a mudança
// estrutural (componente novo, oklch cru, px hardcoded…) → revisável no PR. Drift = spec stale = falha.
//
// USO:
//   node scripts/design-spec-gen.mjs <Tela>.tsx            # imprime o spec (stdout)
//   node scripts/design-spec-gen.mjs <Tela>.tsx --write    # grava <Tela>.design-spec.json
//   node scripts/design-spec-gen.mjs --check               # GATE: todo .design-spec.json commitado bate a .tsx? (CI)
//   node scripts/design-spec-gen.mjs --write-all           # regenera todos os specs commitados

import { readFileSync, writeFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { execSync } from 'node:child_process';

const ROOT = process.cwd();
const PAGES = 'resources/js/Pages';

function sha() { try { return execSync('git rev-parse --short HEAD', { cwd: ROOT }).toString().trim(); } catch { return 'unknown'; } }

// --- gera o spec derivado de uma .tsx -------------------------------------------------------
function genSpec(file) {
  const txt = readFileSync(join(ROOT, file), 'utf8');
  const comp = { shell: [], ui: [], shared: [], layout: [], local: [], hooks: [] };
  const named = (line) => {
    const m = line.match(/import\s+(?:type\s+)?(?:(\w+)\s*,?\s*)?(?:\{([^}]*)\})?\s+from/);
    const out = [];
    if (m) {
      if (m[1]) out.push(m[1]);
      if (m[2]) out.push(...m[2].split(',').map((s) => s.replace(/\s+as\s+\w+/, '').trim()).filter(Boolean));
    }
    return out;
  };
  for (const line of txt.split('\n')) {
    const m = line.match(/from\s+['"]([^'"]+)['"]/);
    if (!m || !/^\s*import/.test(line)) continue;
    const src = m[1];
    const names = named(line).filter((n) => !/^type$/.test(n));
    if (/@\/Layouts\//.test(src)) comp.shell.push(...names);
    else if (/@\/Components\/ui\b/.test(src)) comp.ui.push(...names);
    else if (/@\/Components\/layout\b/.test(src)) comp.layout.push(...names);
    else if (/@\/Components\//.test(src)) comp.shared.push(...names);
    else if (/@\/Hooks\//.test(src)) comp.hooks.push(...names);
    else if (/^\.\.?\//.test(src)) comp.local.push(...names.map((n) => `${n} (${src})`));
  }
  for (const k of Object.keys(comp)) comp[k] = [...new Set(comp[k])].sort();

  const count = (re) => (txt.match(re) || []).length;
  const violations = {
    raw_oklch: count(/oklch\(/g),
    raw_hex: count(/#[0-9a-fA-F]{3,8}\b/g),
    hardcoded_px: count(/\[[0-9]+px\]/g),
    inline_style: count(/style=\{\{/g),
    native_select: count(/<select\b/g),
    native_input: count(/<input\b/g),
  };
  return {
    screen: file.replace(/^.*resources\/js\/Pages\//, '').replace(/\.tsx$/, ''),
    path: file.replace(/\\/g, '/'),
    measured_against_sha: sha(),
    generated_by: 'scripts/design-spec-gen.mjs (DERIVADO — não editar à mão)',
    shell: comp.shell,
    components: { ui: comp.ui, shared: comp.shared, layout: comp.layout, local: comp.local },
    hooks: comp.hooks,
    violations,
    totals: {
      canon_components: comp.ui.length + comp.shared.length + comp.layout.length,
      local_components: comp.local.length,
      uses_layout_primitives: comp.layout.length > 0,
      structural_violations: Object.values(violations).reduce((a, b) => a + b, 0),
    },
  };
}

// campos voláteis ignorados na comparação de freshness (mudam a cada commit, não são estrutura)
const VOLATILE = new Set(['measured_against_sha', 'generated_by']);
const stable = (spec) => JSON.stringify(spec, (k, v) => (VOLATILE.has(k) ? undefined : v), 2);

// acha todos os .design-spec.json commitados sob Pages/
function findSpecs(dir = PAGES, out = []) {
  let entries;
  try { entries = readdirSync(join(ROOT, dir), { withFileTypes: true }); } catch { return out; }
  for (const e of entries) {
    const rel = join(dir, e.name);
    if (e.isDirectory()) findSpecs(rel, out);
    else if (e.name.endsWith('.design-spec.json')) out.push(rel.replace(/\\/g, '/'));
  }
  return out;
}

const write = (file, spec) => writeFileSync(join(ROOT, file.replace(/\.tsx$/, '.design-spec.json')), JSON.stringify(spec, null, 2) + '\n');

// --- dispatcher -----------------------------------------------------------------------------
const args = process.argv.slice(2);

if (args.includes('--check')) {
  const specs = findSpecs();
  const stale = [];
  for (const specPath of specs) {
    const tsx = specPath.replace(/\.design-spec\.json$/, '.tsx');
    let committed;
    try { committed = JSON.parse(readFileSync(join(ROOT, specPath), 'utf8')); } catch { stale.push([specPath, 'spec ilegível']); continue; }
    const fresh = genSpec(tsx);
    if (stable(committed) !== stable(fresh)) stale.push([specPath, 'estrutura mudou — regenere']);
  }
  if (stale.length === 0) {
    console.log(`✓ design-spec:check OK — ${specs.length} spec(s) por-tela em sincronia com a .tsx.`);
    process.exit(0);
  }
  console.error(`✗ design-spec:check FALHOU — ${stale.length} spec(s) STALE (não batem a .tsx):\n`);
  for (const [p, why] of stale) console.error(`  ${p} — ${why}`);
  console.error(`\nA estrutura da tela mudou sem atualizar o contrato. Rode: npm run design-spec:write-all`);
  console.error(`(o diff do .design-spec.json mostra o que mudou — componente novo / oklch cru / px hardcoded — revisável no PR).`);
  process.exit(1);
}

if (args.includes('--write-all')) {
  const specs = findSpecs();
  for (const specPath of specs) {
    const tsx = specPath.replace(/\.design-spec\.json$/, '.tsx');
    write(tsx, genSpec(tsx));
  }
  console.log(`✓ ${specs.length} design-spec(s) regenerado(s).`);
  process.exit(0);
}

const file = args.find((a) => a.endsWith('.tsx'));
if (!file) { console.error('uso: design-spec-gen.mjs <Tela>.tsx [--write] | --check | --write-all'); process.exit(2); }
const spec = genSpec(file);
if (args.includes('--write')) { write(file, spec); console.log(`✓ design-spec gravado: ${file.replace(/\.tsx$/, '.design-spec.json')}`); }
else console.log(JSON.stringify(spec, null, 2));
