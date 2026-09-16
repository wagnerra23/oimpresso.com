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


// ── (e2) 2a raiz: memory/requisitos — orfao MUDO conta, orfao DATADO nao ──────
// Por que este caso existe: o #7335 pagou A MAO 61 docs de requisitos que apontavam pro
// `prototipo-ui/prototipos` morto ha meses. O gate nao via, porque so olhava charters.
// Aqui o bite-test prova as DUAS pernas: morde o mudo E LIBERA o que ja declara a morte
// (registro datado nao e divida — se essa perna quebrar, o gate passa a punir quem
// documentou direito, que e o falso-positivo de 29% medido antes de ligar).
{
  const root = mkdtempSync(join(tmpdir(), 'reqptr-'));
  mkdirSync(join(root, 'resources', 'js', 'Pages'), { recursive: true });
  mkdirSync(join(root, 'memory', 'requisitos', 'Demo'), { recursive: true });
  mkdirSync(join(root, 'prototipo-ui', 'cowork', 'Wagner'), { recursive: true });
  writeFileSync(join(root, 'prototipo-ui', 'cowork', 'Wagner', 'vivo-page.jsx'), '<div/>');
  writeFileSync(join(root, 'memory', 'requisitos', 'Demo', 'RUNBOOK-demo.md'), [
    '---', 'tela: Demo/Index', '---', '',
    '# RUNBOOK', '',
    '- vivo:   `prototipo-ui/cowork/Wagner/vivo-page.jsx`',
    '- mudo:   `prototipo-ui/prototipos/fantasma/page.jsx`',
    '- datado: `prototipo-ui/prototipos/outro/page.jsx` (removido em 2026-05-20, 1070e3759b7)',
    '- externo: `ui_kits/cowork-2026-04-27/x.jsx` _(nunca versionado no repo — artefato externo do Cowork)_',
    '- placeholder: `ui_kits/cowork-YYYY-MM-DD/` (notacao generica, nao caminho)',
    '- indeterminado: `prototipo-ui/sumido/x.jsx` _(alvo não resolve no repo — proveniência não determinada)_',
    '',
  ].join('\n'));
  const r = spawnSync('node', [POINTERS, '--json'], { cwd: root, encoding: 'utf8' });
  let j = null;
  try { j = JSON.parse(r.stdout); } catch { /* */ }
  check('(e2) morde o orfao MUDO de memory/requisitos',
    j && j.requisitos_total_orfaos === 1, JSON.stringify(j && j.requisitos_total_orfaos));
  check('(e2) o orfao DATADO nao e cobrado (controle negativo)',
    j && JSON.stringify(j.requisitos_detalhe || []).includes('fantasma')
      && !JSON.stringify(j.requisitos_detalhe || []).includes('outro/page.jsx'),
    JSON.stringify(j && j.requisitos_detalhe));
  check('(e2) o ponteiro VIVO nao e cobrado',
    j && !JSON.stringify(j.requisitos_detalhe || []).includes('vivo-page.jsx'),
    JSON.stringify(j && j.requisitos_detalhe));
  check('(e2) o "nunca versionado" tambem nao e cobrado',
    j && !JSON.stringify(j.requisitos_detalhe || []).includes('cowork-2026-04-27'),
    JSON.stringify(j && j.requisitos_detalhe));
  check('(e2) placeholder (YYYY-MM-DD) nao e tratado como caminho',
    j && !JSON.stringify(j.requisitos_detalhe || []).includes('YYYY-MM-DD'),
    JSON.stringify(j && j.requisitos_detalhe));
  check('(e2) "proveniencia nao determinada" tambem nao e cobrado',
    j && !JSON.stringify(j.requisitos_detalhe || []).includes('sumido'),
    JSON.stringify(j && j.requisitos_detalhe));
  // a 2a raiz e ADVISORY: nao pode mudar o veredito do --strict (que e dos charters)
  const st = spawnSync('node', [POINTERS, '--strict'], { cwd: root, encoding: 'utf8' });
  check('(e2) 2a raiz NAO entra no --strict (advisory)', st.status === 0, `status=${st.status}`);
}

// -- (e3) notacao generica <x>/abreviada e destino DECLARADO ---------------------
// Por que existe: a r3 do GT-G5 no #7377 refutou 16 anotacoes. 7 delas eram do gate nao
// saber ler duas notacoes que o repo ja usava: segmento-template (`<tela>`, `<modulo>`),
// path abreviado com reticencias, e a frase que declara o DESTINO ("renomeada pra X") --
// que e mais informativa que um tombstone, e estava sendo tratada como ausencia.
// As 4 pernas: morde o morto MUDO e libera as 3 notacoes. Mutacao provada: revertendo
// ehPlaceholder/declaraMorte, requisitos_total_orfaos vai de 1 para 4.
{
  const root = mkdtempSync(join(tmpdir(), 'reqnot-'));
  mkdirSync(join(root, 'resources', 'js', 'Pages'), { recursive: true });
  mkdirSync(join(root, 'memory', 'requisitos', 'Demo'), { recursive: true });
  const RET = String.fromCharCode(8230);
  writeFileSync(join(root, 'memory', 'requisitos', 'Demo', 'RUNBOOK-notacao.md'), [
    '---', 'tela: Demo/Notacao', '---', '',
    '# RUNBOOK', '',
    '- template:  `prototipo-ui/cowork/Wagner/legado/<tela>/visual-source.html`',
    '- abreviado: `prototipo-ui/cowork/_ds/office-impresso-design-system-019dd02f' + RET + '/`',
    '- renomeado: aponta `ui_kits/cowork-2026-04-27/` que foi renomeada pra',
    '  `_BACKUP-NAO-USAR-cowork-2026-04-27/`.',
    '- morto:     `prototipo-ui/prototipos/so-esse/page.jsx`',
    '',
  ].join(String.fromCharCode(10)));
  const r = spawnSync('node', [POINTERS, '--json'], { cwd: root, encoding: 'utf8' });
  let j = null;
  try { j = JSON.parse(r.stdout); } catch { /* */ }
  const det = JSON.stringify((j && j.requisitos_detalhe) || []);
  check('(e3) BITE — o unico path morto MUDO e cobrado',
    j && j.requisitos_total_orfaos === 1 && det.includes('so-esse'), String(j && j.requisitos_total_orfaos) + ' ' + det);
  check('(e3) segmento-template <tela> nao e caminho (controle negativo)',
    j && !det.includes('<tela>'), det);
  check('(e3) path abreviado com reticencias nao e caminho (controle negativo)',
    j && !det.includes('019dd02f'), det);
  check('(e3) "renomeada pra" declara o destino e sai da cobranca (controle negativo)',
    j && !det.includes('cowork-2026-04-27'), det);
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

// -- (e4) C1: removido x MUDOU DE CASA -----------------------------------------
// Por que existe: a familia que MAIS reincidiu nas 5 rodadas do GT-G5 no #7377 foi carimbar
// remocao onde o conteudo so trocou de endereco — os 4 gaps Essentials afirmavam que um
// contrato fora removido enquanto ele vive em main com blob IDENTICO. O predicado e
// DETERMINISTICO (igualdade de hash), nao heuristica: por isso escapa da familia de guard
// sintatico que o §5 ja enterrou 8x. Fixture com repo git PROPRIO — nao depende de sha do
// repo real, que o GC pode levar: c1 cria os 2 arquivos, c2 MOVE um e APAGA o outro, c3
// escreve o doc com os 2 tombstones apontando o MESMO sha. So o movido pode ser acusado.
{
  const root = mkdtempSync(join(tmpdir(), 'c1casa-'));
  const git = (...a) => spawnSync('git', a, { cwd: root, encoding: 'utf8' });
  git('init', '-q', '-b', 'main', '.');
  git('config', 'user.email', 't@t'); git('config', 'user.name', 't');
  mkdirSync(join(root, 'prototipo-ui', 'velho'), { recursive: true });
  mkdirSync(join(root, 'memory', 'requisitos', 'Demo'), { recursive: true });
  mkdirSync(join(root, 'resources', 'js', 'Pages'), { recursive: true });
  writeFileSync(join(root, 'prototipo-ui', 'velho', 'arq.json'), 'x'.repeat(400));
  writeFileSync(join(root, 'prototipo-ui', 'velho', 'morre.json'), 'y'.repeat(400));
  git('add', '-A'); git('commit', '-qm', 'c1');
  mkdirSync(join(root, 'prototipo-ui', 'novo'), { recursive: true });
  git('mv', 'prototipo-ui/velho/arq.json', 'prototipo-ui/novo/arq.json');
  git('rm', '-q', 'prototipo-ui/velho/morre.json');
  git('commit', '-qm', 'c2');
  const sha = git('rev-parse', '--short', 'HEAD').stdout.trim();
  writeFileSync(join(root, 'memory', 'requisitos', 'Demo', 'RUNBOOK-casa.md'), [
    '---', 'tela: Demo/Casa', '---', '',
    '- mudou:  `prototipo-ui/velho/arq.json` _(removido em 2026-01-01, ' + sha + ')_',
    '- morreu: `prototipo-ui/velho/morre.json` _(removido em 2026-01-01, ' + sha + ')_',
    '- curada: `prototipo-ui/velho/arq.json` _(path removido em 2026-01-01, ' + sha + '; o CONTEÚDO vive em `prototipo-ui/novo/arq.json`)_',
    '- movida: `prototipo-ui/velho/arq.json` _(path removido em 2026-01-01, ' + sha + '; foi movido para `prototipo-ui/novo/arq.json`)_',
    '',
  ].join(String.fromCharCode(10)));
  git('add', '-A'); git('commit', '-qm', 'c3');
  git('update-ref', 'refs/remotes/origin/main', 'HEAD');

  const r = spawnSync('node', [POINTERS, '--json', '--todos'], { cwd: root, encoding: 'utf8' });
  let j = null;
  try { j = JSON.parse(r.stdout); } catch { /* */ }
  const ac = (j && j.mudou_de_casa) || [];
  check('(e4) BITE — acusa o tombstone cujo conteudo MUDOU DE CASA',
    ac.length === 1 && ac[0].ponteiro.includes('arq.json'), JSON.stringify(ac));
  check('(e4) CONTROLE NEGATIVO — remocao real NAO e acusada',
    !ac.some((c) => c.ponteiro.includes('morre')), JSON.stringify(ac));
  check('(e4) declara que MEDIU — nao-medicao nunca vira nada a reportar',
    j && j.mudou_de_casa_medido === true, String(j && j.mudou_de_casa_medido));
  check('(e4-b) EXCECAO — linha que declara o destino nao e acusada de novo',
    ac.length === 1, JSON.stringify(ac));
  check('(e4-c) DISPENSA e por DESTINO, nao por vocabulario (foi movido para tambem sai)',
    ac.length === 1, JSON.stringify(ac));

  // (e4-d) O MODO QUE O BITE NAO EXERCITAVA. Os asserts acima passam `--todos`, e com ele
  // `requisitosDocs()` nunca volta vazio — o ramo `!docs.length` ficava sem cobertura. Sem
  // `--todos` o universo vem de `docsDoDiffC1()`, que aqui e VAZIO (origin/main == HEAD), e
  // era ai que o `return []` (em vez de {achados, naoResolvidos}) explodia o `--json` com
  // "Cannot read properties of undefined". Caso COMUM em producao: todo PR que nao toca
  // memory/requisitos. O modo TEXTO nao le `c1`, entao o CI passava verde — gate sao num
  // modo e quebrado no outro (§5 2026-07-28, eixo MODO). Mordida provada por mutacao:
  // revertendo os early-returns para `[]`, este assert cai e os (e4) acima seguem verdes.
  const rSemTodos = spawnSync('node', [POINTERS, '--json'], { cwd: root, encoding: 'utf8' });
  let jSemTodos = null;
  try { jSemTodos = JSON.parse(rSemTodos.stdout); } catch { /* */ }
  check('(e4-d) BITE — `--json` sem `--todos` (universo vazio) nao crasha e devolve a forma',
    rSemTodos.status === 0 && jSemTodos !== null
      && Array.isArray(jSemTodos.mudou_de_casa)
      && jSemTodos.mudou_de_casa_nao_resolvidos === 0,
    `exit=${rSemTodos.status} stderr=${(rSemTodos.stderr || '').split(String.fromCharCode(10))[0]}`);
}

// -- (e5) C1: o predicado e >=1, nao fracao; e o piso de 200B vale DENTRO do tree ---------
// Por que existe: o fixture (e4) usa ponteiros de ARQUIVO, entao `blobsDe` devolve 1 blob e a
// fracao e sempre 1.0 — com ele, `>=1` e `fracao>=0.5` sao indistinguiveis. Este usa TREE, que
// e onde as duas regras divergem:
//   dir-baixo/  3 arquivos >=200B -> 1 movido, 2 apagados = 1/3 = 0.33
//               fracao>=0.5 NAO acusaria; >=1 acusa. (era o falso-negativo medido: 8 de 8)
//   dir-piso/   1 arquivo >=200B apagado + 1 arquivo <200B movido
//               sem piso-em-tree o trivial sobrevive e ACUSA sozinho; com piso, 0 sobreviventes.
// Os destinos ficam FORA da linha do tombstone de proposito, senao `jaDeclaraDestino` dispensa.
{
  const root = mkdtempSync(join(tmpdir(), 'c1pred-'));
  const git = (...a) => spawnSync('git', a, { cwd: root, encoding: 'utf8' });
  git('init', '-q', '-b', 'main', '.');
  git('config', 'user.email', 't@t'); git('config', 'user.name', 't');
  mkdirSync(join(root, 'prototipo-ui', 'dir-baixo'), { recursive: true });
  mkdirSync(join(root, 'prototipo-ui', 'dir-piso'), { recursive: true });
  mkdirSync(join(root, 'memory', 'requisitos', 'Demo'), { recursive: true });
  mkdirSync(join(root, 'resources', 'js', 'Pages'), { recursive: true });
  for (const n of ['a', 'b', 'c']) {
    writeFileSync(join(root, 'prototipo-ui', 'dir-baixo', n + '.json'), n.repeat(400));
  }
  writeFileSync(join(root, 'prototipo-ui', 'dir-piso', 'grande.json'), 'G'.repeat(400));
  writeFileSync(join(root, 'prototipo-ui', 'dir-piso', 'trivial.json'), 'T');   // < 200B
  git('add', '-A'); git('commit', '-qm', 'c1');

  mkdirSync(join(root, 'guardado'), { recursive: true });
  git('mv', 'prototipo-ui/dir-baixo/a.json', 'guardado/a.json');      // 1 de 3 sobrevive
  git('rm', '-q', 'prototipo-ui/dir-baixo/b.json');
  git('rm', '-q', 'prototipo-ui/dir-baixo/c.json');
  git('mv', 'prototipo-ui/dir-piso/trivial.json', 'guardado/trivial.json');  // so o trivial
  git('rm', '-q', 'prototipo-ui/dir-piso/grande.json');
  git('commit', '-qm', 'c2');
  const sha = git('rev-parse', '--short', 'HEAD').stdout.trim();

  writeFileSync(join(root, 'memory', 'requisitos', 'Demo', 'RUNBOOK-pred.md'), [
    '---', 'tela: Demo/Pred', '---', '',
    '- baixo: `prototipo-ui/dir-baixo` _(removido em 2026-01-01, ' + sha + ')_',
    '- piso:  `prototipo-ui/dir-piso` _(removido em 2026-01-01, ' + sha + ')_',
    '',
  ].join(String.fromCharCode(10)));
  git('add', '-A'); git('commit', '-qm', 'c3');
  git('update-ref', 'refs/remotes/origin/main', 'HEAD');

  const r = spawnSync('node', [POINTERS, '--json', '--todos'], { cwd: root, encoding: 'utf8' });
  let j = null;
  try { j = JSON.parse(r.stdout); } catch { /* */ }
  const ac = (j && j.mudou_de_casa) || [];
  const baixo = ac.find((a) => a.ponteiro.includes('dir-baixo'));
  const piso = ac.find((a) => a.ponteiro.includes('dir-piso'));

  check('(e5) BITE — >=1 acusa onde a FRACAO (1/3 = 0.33) nao acusaria',
    !!baixo && baixo.sobrevivem === 1 && baixo.total === 3, JSON.stringify(baixo || ac));
  check('(e5) o achado CARREGA a dispersao (o numero sozinho viraria veredito)',
    !!baixo && typeof baixo.destino_concentracao === 'number' && baixo.destino_dirs >= 1,
    JSON.stringify(baixo || {}));
  check('(e5-b) CONTROLE NEGATIVO — piso de 200B DENTRO do tree: blob trivial nao acusa',
    piso === undefined, JSON.stringify(piso || 'ausente, como esperado'));

  const rTxt = spawnSync('node', [POINTERS, '--todos'], { cwd: root, encoding: 'utf8' });
  check('(e5-c) o C1 sai no modo TEXTO — e o modo que a lane do CI roda',
    rTxt.status === 0 && /C1 \(advisory\)/.test(rTxt.stdout) && /dir-baixo/.test(rTxt.stdout),
    `exit=${rTxt.status}`);
}

// -- (e6) C1: sem indice de origin/main, NAO MEDI != nao ha nada ------------------------
// Por que existe: `blobsVivosEmMain` e `sh('git ls-tree -r origin/main')` e o `sh()` engole
// stderr. Se a ref nao existe no checkout (fetch parcial, fetch-depth curto, runner que nao
// buscou a base) o Map sai VAZIO — e ate 2026-09-16 o early-return devolvia `{achados:[],
// naoResolvidos:[]}`, que faz o chamador (`c1 !== null`) reportar `medido: true` e o texto
// imprimir `✓ nenhum.`. Verde indistinguivel de saude tendo percorrido ZERO (§5 2026-07-29 ·
// 2026-08-11). Com o predicado `>=1` o custo subiu: achados reais viram silencio verde.
//
// A COMBINACAO importa e foi a unica que expoe o caso: `docs` NAO-vazio + `vivos` vazio.
// Por isso o fixture NAO cria `refs/remotes/origin/main` e roda com `--todos` — assim o
// universo vem de `requisitosDocs()` (que nao depende da ref) e o fluxo chega ao
// `!vivos.size` de fato. Sem `--todos` cairia antes no `if (!base) return null` do
// `docsDoDiffC1`, dando um `medido:false` por OUTRO motivo — um verde enganoso.
{
  const root = mkdtempSync(join(tmpdir(), 'c1semidx-'));
  const git = (...a) => spawnSync('git', a, { cwd: root, encoding: 'utf8' });
  git('init', '-q', '-b', 'main', '.');
  git('config', 'user.email', 't@t'); git('config', 'user.name', 't');
  mkdirSync(join(root, 'prototipo-ui', 'velho'), { recursive: true });
  mkdirSync(join(root, 'memory', 'requisitos', 'Demo'), { recursive: true });
  mkdirSync(join(root, 'resources', 'js', 'Pages'), { recursive: true });
  writeFileSync(join(root, 'prototipo-ui', 'velho', 'arq.json'), 'z'.repeat(400));
  git('add', '-A'); git('commit', '-qm', 'c1');
  mkdirSync(join(root, 'guardado'), { recursive: true });
  git('mv', 'prototipo-ui/velho/arq.json', 'guardado/arq.json');
  git('commit', '-qm', 'c2');
  const sha = git('rev-parse', '--short', 'HEAD').stdout.trim();
  writeFileSync(join(root, 'memory', 'requisitos', 'Demo', 'RUNBOOK-idx.md'), [
    '---', 'tela: Demo/Idx', '---', '',
    '- x: `prototipo-ui/velho/arq.json` _(removido em 2026-01-01, ' + sha + ')_',
    '',
  ].join(String.fromCharCode(10)));
  git('add', '-A'); git('commit', '-qm', 'c3');
  // DE PROPOSITO: nenhum `update-ref refs/remotes/origin/main` aqui.

  const r = spawnSync('node', [POINTERS, '--json', '--todos'], { cwd: root, encoding: 'utf8' });
  let j = null;
  try { j = JSON.parse(r.stdout); } catch { /* */ }
  check('(e6) BITE — sem indice de origin/main, `medido` e FALSE (nao "medi e nao achei")',
    j !== null && j.mudou_de_casa_medido === false,
    `medido=${j && j.mudou_de_casa_medido} exit=${r.status}`);
  check('(e6) os contadores viram null, nao 0 — 0 afirmaria uma contagem que nao houve',
    j !== null && j.mudou_de_casa_nao_resolvidos === null,
    String(j && j.mudou_de_casa_nao_resolvidos));

  const rTxt = spawnSync('node', [POINTERS, '--todos'], { cwd: root, encoding: 'utf8' });
  check('(e6-b) o texto diz NAO MEDIDO e NAO imprime o check verde `✓ nenhum`',
    rTxt.status === 0 && /NAO MEDIDO/.test(rTxt.stdout)
      && !/C1 \(advisory\)/.test(rTxt.stdout),
    `exit=${rTxt.status}`);
}

console.log('');
if (fails) { console.error(`✗ ${fails} asserção(ões) falharam.`); process.exit(1); }
console.log('✓ reconcile-triplet.test.mjs: todas as asserções passaram.');
process.exit(0);
