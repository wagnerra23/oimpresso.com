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
  '  blueprint_cowork: "prototipo-ui/cowork/Wagner/legado/demo-cockpit/"',
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
    '  blueprint_cowork: "prototipo-ui/cowork/Wagner/legado/demo/"',
    '  divergence_from_blueprint: "none"',
    '---', '',
    '# Charter', '', '## Goals', '', '- Grid view de cards', '',
  ].join('\n');
  const protoGrid = '<div class="grid grid-cols-3"><article class="card">x</article></div>';
  const root = makeRepo({
    charter: charterMinimo,
    tsx: tsxGrid,
    prototypeFiles: { 'prototipo-ui/cowork/Wagner/legado/demo/visual-source.html': protoGrid },
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
    '  blueprint_cowork: "prototipo-ui/cowork/Wagner/legado/fantasma/"',
    '---', '',
    '# Charter', '',
    'Ref viva: `prototipo-ui/cowork/Wagner/legado/real/visual-source.html`', '',
  ].join('\n');
  const root = makeRepo({
    charter: charterPtrs,
    tsx: tsxGrid,
    prototypeFiles: { 'prototipo-ui/cowork/Wagner/legado/real/visual-source.html': '<div/>' },
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
      '  blueprint_cowork: "prototipo-ui/cowork/Wagner/legado/real/"',
      '---', '', '# Charter', '',
    ].join('\n'),
    tsx: tsxGrid,
    prototypeFiles: { 'prototipo-ui/cowork/Wagner/legado/real/x.jsx': '<div/>' },
  });
  const ok = spawnSync('node', [POINTERS, '--strict', '--json'], { cwd: root2, encoding: 'utf8' });
  let parsed2 = null;
  try { parsed2 = JSON.parse(ok.stdout); } catch { /* */ }
  check('(e) sem órfão → exit 0', ok.status === 0, `status=${ok.status}`);
  check('(e) total_orfaos = 0', parsed2 && parsed2.total_orfaos === 0, JSON.stringify(parsed2 && parsed2.total_orfaos));
}

// ── (f) bundle_source/visual_source como perna da cadeia de protótipo ─────────
// Por que este grupo existe (2026-09-09): o gate resolvia o protótipo por
// blueprint_cowork → cowork-map → Refs, e NÃO conhecia `bundle_source` — o campo que o
// `scripts/design/ancora.mjs` PREFERE. Medido: 36 charters declaram bundle/visual sem
// blueprint, e o cowork-map (22 chaves) não casa nenhum deles → todos caíam em vácuo.
// Cada caso abaixo pina UMA decisão do fix; sem elas o fix regride em silêncio.
{
  const bundleGrid = '<div class="grid grid-cols-3"><article class="card">x</article></div>';
  const charterBundle = (linhas) => [
    '---', 'page: /demo', 'tier: A', ...linhas,
    'mwart_pattern_reuse:', '  divergence_from_blueprint: "none"',
    '---', '', '# Charter', '', '## Goals', '', '- Grid view de cards', '',
  ].join('\n');

  const protoDe = (root, extra = []) => {
    const r = run(root, ['--json', ...extra]);
    let p = null;
    try { p = JSON.parse(r.stdout); } catch { /* */ }
    const res = p && p.results && p.results[0];
    return { res, proto: res && res.prototipo, r };
  };

  // (f1) BITE — bundle_source sozinho resolve, e o protótipo é de fato LIDO (não só apontado).
  {
    const root = makeRepo({
      charter: charterBundle(['bundle_source: demo-page.jsx']),
      tsx: tsxGrid,
      prototypeFiles: { 'prototipo-ui/cowork/Wagner/demo-page.jsx': bundleGrid },
    });
    const { res, proto } = protoDe(root);
    const slot5 = res && res.cells.find((c) => c.slot === 5);
    check('(f1) bundle_source sai do vácuo', proto && proto.presente === true, JSON.stringify(proto));
    check('(f1) fonte nomeia bundle_source', proto && proto.fonte === 'frontmatter:bundle_source', proto && proto.fonte);
    check('(f1) protótipo é LIDO (slot 5 = grid)', slot5 && slot5.prototipo_mostra === 'grid', slot5 && slot5.prototipo_mostra);
    check('(f1) sem ponteiro órfão', proto && proto.orfaos.length === 0, JSON.stringify(proto && proto.orfaos));
  }

  // (f2) casamento por BASENAME: 1 dos 54 `-page.jsx` do espelho real vive em subpasta.
  //      Resolver por path flat (`cowork/<valor>`) acertaria 53/54 e erraria o 54º calado.
  {
    const root = makeRepo({
      charter: charterBundle(['bundle_source: demo-page.jsx']),
      tsx: tsxGrid,
      prototypeFiles: { 'prototipo-ui/cowork/Wagner/prototipos/demo-ui/demo-page.jsx': bundleGrid },
    });
    const { proto } = protoDe(root);
    check('(f2) resolve em SUBPASTA do espelho (basename, não path flat)',
      proto && proto.presente === true && proto.existentes[0].includes('prototipos/demo-ui/'),
      JSON.stringify(proto));
  }

  // (f3) bundle declarado mas AUSENTE do espelho → ponteiro órfão VISÍVEL, nunca silêncio.
  {
    const root = makeRepo({
      charter: charterBundle(['bundle_source: fantasma-page.jsx']),
      tsx: tsxGrid,
    });
    const { proto } = protoDe(root);
    check('(f3) bundle ausente → não fica presente', proto && proto.presente === false, JSON.stringify(proto));
    check('(f3) bundle ausente → órfão listado com o alvo canônico',
      proto && proto.orfaos.some((o) => o.includes('prototipo-ui/cowork/Wagner/fantasma-page.jsx')),
      JSON.stringify(proto && proto.orfaos));
  }

  // (f4) valor com COMENTÁRIO inline — caso real do Produto/Index no main.
  {
    const root = makeRepo({
      charter: charterBundle(['bundle_source: demo-page.jsx  # [C]: nota longa citando outro-page.tsx']),
      tsx: tsxGrid,
      prototypeFiles: { 'prototipo-ui/cowork/Wagner/demo-page.jsx': bundleGrid },
    });
    const { proto } = protoDe(root);
    check('(f4) comentário inline não quebra a extração', proto && proto.presente === true, JSON.stringify(proto));
  }

  // (f5) `visual_source` vale igual — mesma precedência do ancora.mjs.
  {
    const root = makeRepo({
      charter: charterBundle(['visual_source: demo-page.jsx']),
      tsx: tsxGrid,
      prototypeFiles: { 'prototipo-ui/cowork/Wagner/demo-page.jsx': bundleGrid },
    });
    const { proto } = protoDe(root);
    check('(f5) visual_source resolve igual a bundle_source', proto && proto.presente === true, JSON.stringify(proto));
  }

  // (f6) CONTROLE NEGATIVO — valor que NÃO é `-page.jsx` não vira âncora de bundle
  //      (regra do `mockupJsx`, fonte única). Sem isto o fix viraria "qualquer string".
  {
    const root = makeRepo({
      charter: charterBundle(['bundle_source: demo-forms.jsx']),
      tsx: tsxGrid,
      prototypeFiles: { 'prototipo-ui/cowork/Wagner/demo-forms.jsx': bundleGrid },
    });
    const { proto } = protoDe(root);
    check('(f6) valor não-`-page.jsx` NÃO vira âncora de bundle',
      proto && proto.presente === false && proto.fonte === 'nenhum', JSON.stringify(proto));
  }

  // (f7) NÃO-REGRESSÃO — com os dois campos, `blueprint_cowork` continua sendo a 1ª perna.
  {
    const root = makeRepo({
      charter: charterBundle(['bundle_source: demo-page.jsx', 'blueprint_cowork: "prototipo-ui/cowork/Wagner/outro-page.jsx"']),
      tsx: tsxGrid,
      prototypeFiles: {
        'prototipo-ui/cowork/Wagner/demo-page.jsx': bundleGrid,
        'prototipo-ui/cowork/Wagner/outro-page.jsx': bundleGrid,
      },
    });
    const { proto } = protoDe(root);
    check('(f7) blueprint_cowork segue 1º na cadeia',
      proto && proto.fonte.startsWith('frontmatter:blueprint_cowork'), proto && proto.fonte);
    check('(f7) bundle_source entra como perna adicional',
      proto && proto.fonte.includes('frontmatter:bundle_source'), proto && proto.fonte);
  }
}

console.log('');
if (fails) { console.error(`✗ ${fails} asserção(ões) falharam.`); process.exit(1); }
console.log('✓ reconcile-triplet.test.mjs: todas as asserções passaram.');
process.exit(0);
