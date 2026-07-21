// @ts-check
/**
 * module-surface.test.mjs — self-test do gerador de Superfície de código.
 * Roda: node --test scripts/governance/module-surface.test.mjs
 * Testa a LÓGICA PURA (classificação por papel + montagem determinística), não a árvore
 * viva (que muda) — assim o teste não apodrece junto com o repo.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PAPEIS, montar, CORE_APP_MODULES, RAIZES_GERAIS } from './module-surface.mjs';

/** Primeira regra de PAPEIS que casa (mesma ordem do gerador). */
function classify(path) {
  const g = PAPEIS.find((p) => p.re.test(path) && (!p.aceita || p.aceita(path)));
  return g ? g.rot : null;
}

test('classifica cada arquivo no papel certo (1ª regra que casa vence)', () => {
  assert.equal(classify('Modules/Financeiro/Http/Controllers/CaixaController.php'), 'Controllers');
  assert.equal(classify('Modules/Financeiro/Http/Requests/StoreX.php'), 'Requests (validação)');
  assert.equal(classify('Modules/Financeiro/Services/TituloService.php'), 'Services');
  assert.equal(classify('Modules/Financeiro/Models/Concerns/HasX.php'), 'Models / Entities');
  assert.equal(classify('Modules/Financeiro/Observers/TituloObserver.php'), 'Observers');
  assert.equal(classify('Modules/Financeiro/Database/Migrations/2026_x.php'), 'Migrations (schema)');
  assert.equal(classify('Modules/Financeiro/Console/Commands/BackfillX.php'), 'Console / Commands');
  assert.equal(classify('resources/js/Pages/Financeiro/Unificado/Index.tsx'), 'Telas (Inertia/React)');
  assert.equal(classify('resources/js/Pages/Financeiro/Index.charter.md'), 'Charters (lei da tela)');
  assert.equal(classify('resources/js/Pages/Financeiro/Index.casos.md'), 'Casos (contrato UC)');
  assert.equal(classify('Modules/Financeiro/Tests/Feature/XTest.php'), 'Testes (Pest)');
});

test('charter/casos .md NÃO caem em Telas (ordem das regras protege)', () => {
  // .charter.md e .casos.md são .md, não .tsx — a regra de Telas é /\.tsx$/, então não colidem.
  assert.notEqual(classify('resources/js/Pages/Financeiro/Index.charter.md'), 'Telas (Inertia/React)');
});

test('componentes co-localizados ficam no índice, mas NÃO são classificados como telas', () => {
  assert.equal(classify('resources/js/Pages/Financeiro/components/Filtro.tsx'), 'Componentes / apoio de tela');
  assert.equal(classify('resources/js/Pages/Financeiro/Unificado/_components/Card.tsx'), 'Componentes / apoio de tela');
  assert.equal(classify('resources/js/Pages/Financeiro/hooks/useSaldo.tsx'), 'Componentes / apoio de tela');
  assert.equal(classify('resources/js/Pages/Financeiro/Unificado/Index.tsx'), 'Telas (Inertia/React)');
});

test('CLASSE B: paths do core app/ classificam no papel certo', () => {
  assert.equal(classify('app/Http/Controllers/SellController.php'), 'Controllers');
  assert.equal(classify('app/Http/Requests/StoreSell.php'), 'Requests (validação)');
  assert.equal(classify('app/Utils/TransactionUtil.php'), 'Motor (Utils/Domínio)');
  assert.equal(classify('app/Domain/Fsm/Support/FsmAuthorizationFlag.php'), 'Motor (Utils/Domínio)');
  assert.equal(classify('app/Transaction.php'), 'Models / Entities');
  assert.equal(classify('resources/views/sale_pos/create.blade.php'), 'Views (Blade)');
});

test('contexto _Geral classifica componentes, layouts e templates herdáveis', () => {
  assert.equal(classify('resources/js/Components/shared/PageHeader.tsx'), 'Componentes compartilhados (React)');
  assert.equal(classify('resources/js/Layouts/AppShellV2.tsx'), 'Layouts herdados (React)');
  assert.equal(classify('resources/views/components/flash.blade.php'), 'Componentes compartilhados (Blade)');
  assert.equal(classify('resources/views/layouts/app.blade.php'), 'Layouts herdados (Blade)');
  assert.equal(classify('memory/requisitos/_DesignSystem/templates/PageHeader-canon-v3-1.md'), 'Templates de construção (Design System)');
  assert.deepEqual(RAIZES_GERAIS, [
    'resources/js/Components',
    'resources/js/Layouts',
    'resources/views/components',
    'resources/views/layouts',
    'memory/requisitos/_DesignSystem/templates',
  ]);
});

test('montar() _Geral declara herança compartilhada sem autorizar reuso cego', () => {
  const grupos = [{ rot: 'Layouts herdados (React)', listar: true, files: ['resources/js/Layouts/AppShellV2.tsx'] }];
  const md = montar('_Geral', grupos, []);
  assert.match(md, /porta geral para componentes, layouts e templates herdáveis/);
  assert.match(md, /O que NÃO é.*autorização para importar/);
  assert.match(md, /reuse-index\.mjs/);
});

test('CLASSE B: regra larga app/ NÃO rouba um controller de módulo (Modules vence quando aplicável)', () => {
  // membership é a semente curada; mas a classificação por papel não deve confundir os dois mundos.
  assert.equal(classify('Modules/Financeiro/Http/Controllers/CaixaController.php'), 'Controllers');
  // um .php de raiz do app que não é model-like (tem subpasta) não cai em Models/Entities
  assert.equal(classify('app/Http/Middleware/Auth.php'), 'Middleware');
});

test('CORE_APP_MODULES.Sells declara semente + tabelas-âncora (não vazio)', () => {
  assert.ok(CORE_APP_MODULES.Sells);
  assert.ok(CORE_APP_MODULES.Sells.prefixos.length >= 5);
  assert.ok(CORE_APP_MODULES.Sells.tabelas.includes('transactions'));
  // a semente NÃO inclui SellingPriceGroupController (é domínio Produto)
  assert.ok(!CORE_APP_MODULES.Sells.prefixos.some((p) => p.includes('SellingPriceGroup')));
});

test('CORE_APP_MODULES.Produto declara semente curada + tabelas-âncora (Classe B)', () => {
  const p = CORE_APP_MODULES.Produto;
  assert.ok(p, 'Produto deve existir em CORE_APP_MODULES');
  assert.ok(p.prefixos.length >= 5);
  // núcleo: o motor + o model + o controller (o trio que a task cita)
  assert.ok(p.prefixos.includes('app/Utils/ProductUtil.php'));
  assert.ok(p.prefixos.includes('app/Product.php'));
  assert.ok(p.prefixos.includes('app/Http/Controllers/ProductController.php'));
  // tabela de preço vive no Produto (o seed de Sells defere SellingPriceGroup pra cá)
  assert.ok(p.prefixos.includes('app/Http/Controllers/SellingPriceGroupController.php'));
  // tabelas-âncora reais (products confirmado por migration)
  assert.ok(p.tabelas.includes('products'));
  assert.ok(p.tabelas.includes('variations'));
  // NÃO invade Venda (Transaction* é domínio Sells) nem o compartilhado Despesa (Taxonomy/Category)
  assert.ok(!p.prefixos.some((x) => x.includes('Transaction')));
  assert.ok(!p.prefixos.some((x) => x.includes('Taxonomy') || x.endsWith('app/Category.php')));
});

test('CLASSE B Produto: paths do core classificam no papel certo', () => {
  assert.equal(classify('app/Http/Controllers/ProductController.php'), 'Controllers');
  assert.equal(classify('app/Utils/ProductUtil.php'), 'Motor (Utils/Domínio)');
  assert.equal(classify('app/Product.php'), 'Models / Entities');
  assert.equal(classify('app/VariationGroupPrice.php'), 'Models / Entities');
  assert.equal(classify('resources/views/product/index.blade.php'), 'Views (Blade)');
});

test('montar() CLASSE B emite tabelas_dominio no frontmatter + nota de metadado-âncora', () => {
  const grupos = [{ rot: 'Controllers', listar: true, files: ['app/Http/Controllers/SellController.php'] }];
  const md = montar('Sells', grupos, []);
  assert.match(md, /tabelas_dominio: \["transactions"/);
  assert.match(md, /metadado-ÂNCORA declarado/);
  assert.match(md, /CLASSE B/);
});

test('montar() é determinístico (mesmo input → bytes idênticos)', () => {
  const grupos = [
    { rot: 'Controllers', listar: true, files: ['Modules/X/Http/Controllers/AController.php'] },
    { rot: 'Testes (Pest)', listar: false, files: ['Modules/X/Tests/Feature/AT.php', 'Modules/X/Tests/Feature/BT.php'] },
  ];
  const a = montar('X', grupos, []);
  const b = montar('X', grupos, []);
  assert.equal(a, b);
});

test('montar() carimba frontmatter gerado + título + papéis', () => {
  const grupos = [{ rot: 'Controllers', listar: true, files: ['Modules/X/Http/Controllers/AController.php'] }];
  const md = montar('X', grupos, []);
  assert.match(md, /^---\n/);
  assert.match(md, /authority: generated/);
  assert.match(md, /type: reference/);
  assert.match(md, /# 🗺️ Superfície de código — X/);
  assert.match(md, /## Controllers — 1/);
  assert.match(md, /\[AController\.php\]\(\.\.\/\.\.\/\.\.\/Modules\/X\/Http\/Controllers\/AController\.php\)/);
});

test('Total mapeado inclui Outros tanto nos arquivos quanto nos papéis', () => {
  const grupos = [{ rot: 'Controllers', listar: true, files: ['Modules/X/Http/Controllers/AController.php'] }];
  const outros = ['Modules/X/Support/Helper.php', 'Modules/X/Legacy/Foo.php'];
  const md = montar('X', grupos, outros);
  assert.match(md, /\*\*Total mapeado:\*\* 3 arquivos em 2 papéis\./);
  assert.match(md, /## Outros \(raiz\/misc\) — 2/);
});

test('papel volumoso (listar:false) mostra contagem + dir, NÃO lista arquivos', () => {
  const grupos = [{ rot: 'Testes (Pest)', listar: false, files: ['Modules/X/Tests/Feature/AT.php', 'Modules/X/Tests/Feature/BT.php'] }];
  const md = montar('X', grupos, []);
  assert.match(md, /## Testes \(Pest\) — 2/);
  assert.match(md, /2 arquivos em \[Modules\/X\/Tests\/Feature\/\]/);
  assert.doesNotMatch(md, /\[AT\.php\]/); // não lista os arquivos individuais
});
