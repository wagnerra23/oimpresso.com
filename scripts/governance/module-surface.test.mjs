// @ts-check
/**
 * module-surface.test.mjs — self-test do gerador de Superfície de código.
 * Roda: node --test scripts/governance/module-surface.test.mjs
 * Testa a LÓGICA PURA (classificação por papel + montagem determinística), não a árvore
 * viva (que muda) — assim o teste não apodrece junto com o repo.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PAPEIS, montar, CORE_APP_MODULES } from './module-surface.mjs';

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

test('papel volumoso (listar:false) mostra contagem + dir, NÃO lista arquivos', () => {
  const grupos = [{ rot: 'Testes (Pest)', listar: false, files: ['Modules/X/Tests/Feature/AT.php', 'Modules/X/Tests/Feature/BT.php'] }];
  const md = montar('X', grupos, []);
  assert.match(md, /## Testes \(Pest\) — 2/);
  assert.match(md, /2 arquivos em \[Modules\/X\/Tests\/Feature\/\]/);
  assert.doesNotMatch(md, /\[AT\.php\]/); // não lista os arquivos individuais
});
