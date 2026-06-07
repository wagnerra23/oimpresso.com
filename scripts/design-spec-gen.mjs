#!/usr/bin/env node
// scripts/design-spec-gen.mjs — PoC: deriva o CONTRATO ESTRUTURAL por-tela de uma .tsx.
//
// POR QUE (dossiê 2026-06-06-arte-view-contract-deterministico): hoje a estrutura de UI
// por-tela (componentes/tokens/layout) é julgada por LLM (review/visual-comparison/screen-grade)
// quando é PURA e DERIVÁVEL. Este gerador projeta, por-tela, o que reuse-index + foundation-guard
// já fazem global → um `<Tela>.design-spec.json` MACHINE-CHECKABLE ao lado do `<Tela>.charter.md`.
// Charter = intenção (LLM-judge ok). design-spec = estrutura (teste determinístico).
//
// DERIVADO da .tsx a cada run (measured_against_sha) → não apodrece (princípio ADR 0239, git=SSOT).
// NÃO substitui charter nem os gates globais — é a projeção por-tela que faltava.
//
// USO:  node scripts/design-spec-gen.mjs resources/js/Pages/Sells/Create.tsx [--write]
//       --write grava <Tela>.design-spec.json ao lado da .tsx; sem flag = stdout (PoC).

import { readFileSync, writeFileSync } from 'node:fs';
import { execSync } from 'node:child_process';

const file = process.argv.find((a) => a.endsWith('.tsx'));
const DO_WRITE = process.argv.includes('--write');
if (!file) { console.error('uso: node scripts/design-spec-gen.mjs <Tela>.tsx [--write]'); process.exit(2); }

const txt = readFileSync(file, 'utf8');
const lines = txt.split('\n');

function sha() { try { return execSync('git rev-parse --short HEAD').toString().trim(); } catch { return 'unknown'; } }

// --- 1. Composição: imports categorizados por fonte (o contrato de COMPOSIÇÃO) ------------
const comp = { shell: [], ui: [], shared: [], layout: [], local: [], hooks: [] };
const importRe = /import\s+(?:[\w*{}\s,]+?)\s+from\s+['"]([^'"]+)['"]/g;
// captura nomes importados pra cada source
const named = (line) => {
  const m = line.match(/import\s+(?:type\s+)?(?:(\w+)\s*,?\s*)?(?:\{([^}]*)\})?\s+from/);
  const out = [];
  if (m) {
    if (m[1]) out.push(m[1]);
    if (m[2]) out.push(...m[2].split(',').map((s) => s.replace(/\s+as\s+\w+/, '').trim()).filter(Boolean));
  }
  return out;
};
lines.forEach((line) => {
  const m = line.match(/from\s+['"]([^'"]+)['"]/);
  if (!m || !/^\s*import/.test(line)) return;
  const src = m[1];
  const names = named(line).filter((n) => !/^type$/.test(n));
  if (/@\/Layouts\//.test(src)) comp.shell.push(...names);
  else if (/@\/Components\/ui\b/.test(src)) comp.ui.push(...names);
  else if (/@\/Components\/layout\b/.test(src)) comp.layout.push(...names);
  else if (/@\/Components\/shared\b/.test(src)) comp.shared.push(...names);
  else if (/@\/Components\//.test(src)) comp.shared.push(...names);
  else if (/@\/Hooks\//.test(src)) comp.hooks.push(...names);
  else if (/^\.\//.test(src) || /^\.\.\//.test(src)) comp.local.push(...names.map((n) => `${n} (${src})`));
});
for (const k of Object.keys(comp)) comp[k] = [...new Set(comp[k])].sort();

// --- 2. Violações estruturais (determinísticas — o que um teste por-tela cobraria) --------
const count = (re) => (txt.match(re) || []).length;
const violations = {
  raw_oklch: count(/oklch\(/g),                                  // cor crua (foundation-guard global → aqui por-tela)
  raw_hex: count(/#[0-9a-fA-F]{3,8}\b/g),
  hardcoded_px: count(/\[[0-9]+px\]/g),                          // text-[22px] etc — tipografia/espaço fora do token
  inline_style: count(/style=\{\{/g),
  native_select: count(/<select\b/g),                           // ds/no-native-select (anti-pattern REGISTRY)
  native_input: count(/<input\b/g),
};

const spec = {
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

const json = JSON.stringify(spec, null, 2);
if (DO_WRITE) {
  const out = file.replace(/\.tsx$/, '.design-spec.json');
  writeFileSync(out, json + '\n');
  console.log(`✓ design-spec gravado: ${out}`);
} else {
  console.log(json);
}
