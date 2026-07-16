#!/usr/bin/env node
// Catraca do contrato de fluxos visuais de Sells/Create: cenário sem viewport, ação ou
// evidência não entra no CI. Irmão do scripts/visreg-flows-lint.mjs (Financeiro) — manifesto
// e ações PRÓPRIOS (opção (b) da decisão de design) pra não acoplar ao gate enforcing do
// Financeiro. Fonte única: tests/Browser/visreg-flows-sells.json.
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(fileURLToPath(new URL('..', import.meta.url)));
const FILE = 'tests/Browser/visreg-flows-sells.json';
const ACTIONS = new Set(['add_product', 'open_product_search', 'apply_discount', 'add_payment']);

export function validate(manifest, root = ROOT) {
  const errors = [];
  const viewports = manifest?.viewports;
  if (!viewports || typeof viewports !== 'object') return ['viewports ausente'];
  for (const [id, viewport] of Object.entries(viewports)) {
    if (!Number.isInteger(viewport?.width) || !Number.isInteger(viewport?.height) || viewport.width < 320 || viewport.height < 400) {
      errors.push(`viewport inválido: ${id}`);
    }
  }
  for (const [screen, spec] of Object.entries(manifest?.screens ?? {})) {
    if (!spec.route?.startsWith('/') || !spec.anchor || !spec.charter) errors.push(`${screen}: route/anchor/charter obrigatório`);
    if (!existsSync(resolve(root, spec.charter ?? ''))) errors.push(`${screen}: charter ausente`);
    if (!Array.isArray(spec.viewports) || !spec.viewports.length) errors.push(`${screen}: sem viewports`);
    for (const id of spec.viewports ?? []) if (!viewports[id]) errors.push(`${screen}: viewport inexistente ${id}`);
    if (!Array.isArray(spec.flows) || !spec.flows.length) errors.push(`${screen}: sem fluxos`);
    for (const flow of spec.flows ?? []) {
      if (!flow.id || !ACTIONS.has(flow.action) || !flow.evidence) errors.push(`${screen}: fluxo inválido ${flow.id ?? '(sem id)'}`);
    }
  }
  return errors;
}

function load(file = FILE) {
  try { return JSON.parse(readFileSync(resolve(ROOT, file), 'utf8')); }
  catch (error) { console.error(`X manifesto ilegível: ${error.message}`); process.exit(2); }
}

if (process.argv.includes('--selftest')) {
  const good = { viewports: { desktop: { width: 1280, height: 800 } }, screens: { s: { route: '/s', anchor: 'S', charter: 'package.json', viewports: ['desktop'], flows: [{ id: 'a', action: 'add_product', evidence: 'x' }] } } };
  const cases = [
    ['contrato íntegro passa', validate(good).length === 0],
    ['viewport inválido morde', validate({ ...good, viewports: { x: { width: 1, height: 2 } } }).length > 0],
    ['ação desconhecida morde', validate({ ...good, screens: { s: { ...good.screens.s, flows: [{ id: 'a', action: 'inventada', evidence: 'x' }] } } }).length > 0],
  ];
  const fails = cases.filter(([, ok]) => !ok);
  for (const [label, ok] of cases) console.log(`${ok ? 'OK' : 'X'} ${label}`);
  process.exit(fails.length ? 1 : 0);
}

const errors = validate(load());
for (const error of errors) console.error(`X ${error}`);
console.log(`visreg-sells-lint · ${errors.length} problema(s)`);
process.exit(errors.length ? 1 : 0);
