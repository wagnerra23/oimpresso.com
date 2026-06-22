#!/usr/bin/env node
// @ts-check
// SELF-TEST — prova que reconcile-triplet.mjs:
//   (a) charter=grid, prod=grid, proto=AUSENTE → slot 5 CONFORME (não-muda) MAS reporta proto ausente
//   (b) charter=grid, prod=tabela (sem declaração) → DIVERGENCIA_MUDA, exit≠0 em --strict
//   (c) charter=grid, prod=tabela + divergence_from_blueprint declarado → DIVERGENCIA_DECLARADA, NÃO falha
//   (d) todos conformes → verde (exit 0, sem MUDA)
//   (e) charter-blueprint-pointers detecta ponteiro órfão e libera quando todos existem
//
// Monta repo-fixtures temporários (mesmo code path cwd-based) e roda o script via spawn.
// Rodar: node scripts/governance/reconcile-triplet.test.mjs — exit 0 = passa.

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const RECONCILE = join(__dirname, 'reconcile-triplet.mjs');
const POINTERS = join(__dirname, 'charter-blueprint-pointers.mjs');

let fails = 0;
const check = (name, cond, extra = '') => {
  console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}${cond ? '' : '  ← ' + extra}`);
  if (!cond) fails++;
};

/** cria um repo-fixture com 1 charter + 1 tsx numa pasta Pages/<Mod>/<Tela>. */
function makeRepo({ charter, tsx, prototypeFiles = {}, coworkMap = null }) {
  const root = mkdtempSync(join(tmpdir(), 'reconcile-'));
  const pagesDir = join(root, 'resources', 'js', 'Pages', 'Demo');
  mkdirSync(pagesDir, { recursive: true });
  writeFileSync(join(pagesDir, 'Index.charter.md'), charter);
  writeFileSync(join(pagesDir, 'Index.tsx'), tsx);
  mkdirSync(join(root, 'prototipo-ui'), { recursive: true });
  if (coworkMap) writeFileSync(join(root, 'prototipo-ui', 'cowork-map.json'), JSON.stringify(coworkMap));
  for (const [rel, content] of Object.entries(prototypeFiles)) {
    const abs = join(root, rel);
    mkdirSync(dirname(abs), { recursive: true });
    writeFileSync(abs, content);
  }
  return root;
}

const run = (root, extra = []) =>
  spawnSync('node', [RECONCILE, '--module=Demo', '--tela=Index', ...extra], { cwd: root, encoding: 'utf8' });

// ── charters de fixture ───────────────────────────────────────────────────────
const charterGrid = [
  '---',
  'page: /demo',
  'tier: A',
  'mwart_pattern_reuse:',
  '  blueprint_cowork: "prototipo-ui/prototipos/demo-cockpit/"',
  '  divergence_from_blueprint: "none"',
  '---',
  '',
  '# Charter Demo',
  '',
  '## Goals — Features (faz)',
  '',
  '- `<PageHeader>` shared com título',
  '- Grid view de cards (NÃO tabela)',
  '- Search bar (busca em nome + SKU)',
  '',
  '## UX Anti-patterns',
  '',
  '- ❌ Tabela ao invés de cards',
  '',
].join('\n');

const charterGridDeclarado = charterGrid.replace(
  '  divergence_from_blueprint: "none"',
  '  divergence_from_blueprint: "produção migrou pra tabela densa por pedido do cliente (consciente)"',
);

// produção GRID (bate o charter)
const tsxGrid = [
  'import { PageHeader } from "@/Components/shared/PageHeader";',
  'export default function Index() {',
  '  return (<div>',
  '    <PageHeader title="Demo" />',
  '    <input type="search" placeholder="Buscar por nome" />',
  '    <div className="grid grid-cols-1 md:grid-cols-3 gap-3">',
  '      <article className="card">card</article>',
  '    </div>',
  '  </div>);',
  '}',
].join('\n');

// produção TABELA (contradiz o charter)
const tsxTabela = [
  'import { PageHeader } from "@/Components/shared/PageHeader";',
  'export default function Index() {',
  '  return (<div>',
  '    <PageHeader title="Demo" />',
  '    <input type="search" placeholder="Buscar por nome" />',
  '    <table><thead><tr><th>Nome</th></tr></thead><tbody></tbody></table>',
  '  </div>);',
  '}',
].join('\n');

// ── (a) charter=grid, prod=grid, proto AUSENTE → slot 5 não-muda, proto ausente reportado ──
{
  const root = makeRepo({ charter: charterGrid, tsx: tsxGrid });
  const r = run(root, ['--json']);
  let parsed = null;
  try { parsed = JSON.parse(r.stdout); } catch { /* */ }
  const res = parsed && parsed.results && parsed.results[0];
  const slot5 = res && res.cells.find((c) => c.slot === 5);
  check('(a) exit 0 (advisory)', r.status === 0, `status=${r.status}`);
  check('(a) slot 5 CONFORME (grid≡grid)', slot5 && slot5.estado === 'CONFORME', slot5 && slot5.estado);
  check('(a) protótipo AUSENTE reportado', res && res.prototipo.presente === false, JSON.stringify(res && res.prototipo));
  check('(a) ponteiro órfão listado', res && res.prototipo.orfaos.length >= 1, JSON.stringify(res && res.prototipo.orfaos));
  check('(a) veredito tela = CONFORME', res && res.veredito.estado === 'CONFORME', res && res.veredito.estado);
}

// ── (b) charter=grid, prod=tabela, sem declaração → MUDA, --strict exit≠0 ──
{
  const root = makeRepo({ charter: charterGrid, tsx: tsxTabela });
  const adv = run(root, ['--json']);
  const strict = run(root, ['--strict', '--json']);
  let parsed = null;
  try { parsed = JSON.parse(adv.stdout); } catch { /* */ }
  const res = parsed && parsed.results && parsed.results[0];
  const slot5 = res && res.cells.find((c) => c.slot === 5);
  check('(b) advisory exit 0', adv.status === 0, `status=${adv.status}`);
  check('(b) slot 5 DIVERGENCIA_MUDA', slot5 && slot5.estado === 'DIVERGENCIA_MUDA', slot5 && slot5.estado);
  check('(b) veredito tela = MUDA', res && res.veredito.estado === 'DIVERGENCIA_MUDA', res && res.veredito.estado);
  check('(b) --strict exit 1', strict.status === 1, `status=${strict.status}`);
}

// ── (c) charter=grid, prod=tabela, COM divergence_from_blueprint → DECLARADA, NÃO falha ──
{
  const root = makeRepo({ charter: charterGridDeclarado, tsx: tsxTabela });
  const strict = run(root, ['--strict', '--json']);
  let parsed = null;
  try { parsed = JSON.parse(strict.stdout); } catch { /* */ }
  const res = parsed && parsed.results && parsed.results[0];
  const slot5 = res && res.cells.find((c) => c.slot === 5);
  check('(c) slot 5 DIVERGENCIA_DECLARADA', slot5 && slot5.estado === 'DIVERGENCIA_DECLARADA', slot5 && slot5.estado);
  check('(c) --strict NÃO falha (exit 0)', strict.status === 0, `status=${strict.status}`);
  check('(c) divergence_declared capturada', res && !!res.divergence_declared, JSON.stringify(res && res.divergence_declared));
}

// ── (d) todos conformes, proto presente → verde ──
{
  const charterMinimo = [
    '---', 'page: /demo', 'tier: A',
    'mwart_pattern_reuse:',
    '  blueprint_cowork: "prototipo-ui/prototipos/demo/"',
    '  divergence_from_blueprint: "none"',
    '---', '',
    '# Charter', '', '## Goals', '', '- Grid view de cards', '',
  ].join('\n');
  const protoGrid = '<div class="grid grid-cols-3"><article class="card">x</article></div>';
  const root = makeRepo({
    charter: charterMinimo,
    tsx: tsxGrid,
    prototypeFiles: { 'prototipo-ui/prototipos/demo/visual-source.html': protoGrid },
  });
  const strict = run(root, ['--strict', '--json']);
  let parsed = null;
  try { parsed = JSON.parse(strict.stdout); } catch { /* */ }
  const res = parsed && parsed.results && parsed.results[0];
  const slot5 = res && res.cells.find((c) => c.slot === 5);
  check('(d) --strict verde (exit 0)', strict.status === 0, `status=${strict.status}`);
  check('(d) protótipo presente', res && res.prototipo.presente === true, JSON.stringify(res && res.prototipo));
  check('(d) protótipo slot 5 = grid', slot5 && slot5.prototipo_mostra === 'grid', slot5 && slot5.prototipo_mostra);
  check('(d) sem ponteiro órfão', res && res.prototipo.orfaos.length === 0, JSON.stringify(res && res.prototipo.orfaos));
}

// ── (e) pointer-audit: detecta órfão e libera quando existe ──
{
  // charter com 1 ponteiro órfão + 1 vivo
  const charterPtrs = [
    '---', 'tier: A',
    'mwart_pattern_reuse:',
    '  blueprint_cowork: "prototipo-ui/prototipos/fantasma/"',
    '---', '',
    '# Charter', '',
    'Ref viva: `prototipo-ui/prototipos/real/visual-source.html`', '',
  ].join('\n');
  const root = makeRepo({
    charter: charterPtrs,
    tsx: tsxGrid,
    prototypeFiles: { 'prototipo-ui/prototipos/real/visual-source.html': '<div/>' },
  });
  const txt = spawnSync('node', [POINTERS, '--json'], { cwd: root, encoding: 'utf8' });
  let parsed = null;
  try { parsed = JSON.parse(txt.stdout); } catch { /* */ }
  check('(e) pointer-audit detecta 1 órfão', parsed && parsed.total_orfaos === 1, JSON.stringify(parsed));
  check('(e) charter com órfão listado', parsed && parsed.charters_com_orfao === 1, JSON.stringify(parsed && parsed.charters_com_orfao));
  const strictP = spawnSync('node', [POINTERS, '--strict'], { cwd: root, encoding: 'utf8' });
  check('(e) --strict exit 1 com órfão', strictP.status === 1, `status=${strictP.status}`);

  // agora todos vivos
  const root2 = makeRepo({
    charter: [
      '---', 'tier: A',
      'mwart_pattern_reuse:',
      '  blueprint_cowork: "prototipo-ui/prototipos/real/"',
      '---', '', '# Charter', '',
    ].join('\n'),
    tsx: tsxGrid,
    prototypeFiles: { 'prototipo-ui/prototipos/real/x.jsx': '<div/>' },
  });
  const ok = spawnSync('node', [POINTERS, '--strict', '--json'], { cwd: root2, encoding: 'utf8' });
  let parsed2 = null;
  try { parsed2 = JSON.parse(ok.stdout); } catch { /* */ }
  check('(e) sem órfão → exit 0', ok.status === 0, `status=${ok.status}`);
  check('(e) total_orfaos = 0', parsed2 && parsed2.total_orfaos === 0, JSON.stringify(parsed2 && parsed2.total_orfaos));
}

console.log('');
if (fails) { console.error(`✗ ${fails} asserção(ões) falharam.`); process.exit(1); }
console.log('✓ reconcile-triplet.test.mjs: todas as asserções passaram.');
process.exit(0);
