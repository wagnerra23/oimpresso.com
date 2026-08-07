<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato da sub-tela CATEGORIAS do Catálogo Unificado
 * (`GET /products/unificado` → `Produto/Unificado/Index`, closure `categorias`).
 *
 * ÂNCORA (contrato, NÃO implementação):
 *   1. CHARTER  — resources/js/Pages/Produto/Unificado/Index.charter.md
 *                 §Goals: "Categorias — tree view 1 nível com count produtos"
 *                 §Métricas vivas: "isolates products by business_id across all 5 sub-views"
 *   2. UPOS     — a convenção de raiz de categoria é `parent_id = 0`, declarada em TRÊS lugares
 *                 independentes de App\Category: `catAndSubCategories()` (`$category['parent_id'] == 0`),
 *                 `forDropdown()` (`->where('parent_id', 0)`) e `scopeOnlyParent()` (idem).
 *                 O schema fecha: `categories`.`parent_id` é `int(11) NOT NULL` — nunca NULL.
 *   3. ADR 0093 — multi-tenant Tier 0.
 *
 * ⚠️ Failing-first: os asserts saem do contrato acima, não do código. Em `origin/main` este
 *    arquivo nasce VERMELHO — a closure chama `Category::withCount('products')` e `App\Category`
 *    não declara `products()` (varredura contada do arquivo: os únicos métodos são
 *    `getActivitylogOptions`, `catAndSubCategories`, `forDropdown`, `sub_categories`,
 *    `scopeOnlyParent`). O vermelho É o achado.
 *
 * ⚠️ ANTI-VÁCUO: `categorias` é closure do render INICIAL, então ela roda em qualquer sub-tela.
 *    Um caso que só perguntasse "a lista veio vazia?" passaria por acidente — lista vazia é
 *    exatamente o sintoma. Todo caso negativo aqui vem PAREADO com o positivo que prova que a
 *    consulta produziu linha.
 *
 * ⛔ Escopo honesto: a tela ainda não é alcançável em prod (nenhum item de menu aponta pra rota,
 *    e ela segue sem `can:product.view` — TODO no código). Vermelho aqui é bloqueador de
 *    migração, não incidente de produção.
 *
 * ⛔ Multi-tenant: biz seedado canônico + o 2º business seedado como cross-tenant.
 *    NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

function produtoUnificadoInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/**
 * `GET /products/unificado` no branch Inertia. Sem `X-Inertia-Partial-*`: todas as props
 * são avaliadas, que é o caminho real do primeiro carregamento da tela.
 *
 * @return array<string,mixed> o objeto `props` da página
 */
function produtoUnificadoProps(object $test, array $query = []): array
{
    $url = '/products/unificado' . ($query ? '?' . http_build_query($query) : '');

    $response = $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => produtoUnificadoInertiaVersion(),
    ])->get($url);

    $response->assertStatus(200);

    $page = json_decode($response->getContent(), true);
    expect($page)->toBeArray()->toHaveKey('props');

    return $page['props'];
}

/**
 * Cria categoria de produto. `parent_id` explícito porque a coluna é NOT NULL e a raiz
 * é `0` (nunca NULL) — ver §ÂNCORA item 2.
 */
function criarCategoriaProduto(int $bizId, int $createdBy, string $nome, int $parentId = 0, ?string $slug = null): int
{
    return (int) DB::table('categories')->insertGetId([
        'name' => $nome,
        'business_id' => $bizId,
        'parent_id' => $parentId,
        'category_type' => 'product',
        'slug' => $slug,
        'created_by' => $createdBy,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function criarProdutoNaCategoria(int $bizId, int $categoriaId, string $nome): void
{
    DB::table('products')->insert([
        'name' => $nome,
        'business_id' => $bizId,
        'category_id' => $categoriaId,
        'type' => 'single',
        'unit_id' => EstoqueFixture::unitId($bizId),
        'tax_type' => 'exclusive',
        'enable_stock' => 0,
        'sku' => 'UNIF-' . strtoupper(bin2hex(random_bytes(5))),
        'barcode_type' => 'C128',
        'created_by' => EstoqueFixture::userId($bizId),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** @return array<string,mixed>|null a linha da categoria na prop, ou null se ausente */
function linhaDaCategoria(array $categorias, int $categoriaId): ?array
{
    foreach ($categorias as $linha) {
        if ((int) $linha['id'] === $categoriaId) {
            return $linha;
        }
    }

    return null;
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL.');
    }

    try {
        $this->business = $this->seededTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql.');
    }

    $this->user = User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'user.id' => $this->user->id,
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.view', 'web');
    $this->user->givePermissionTo(['product.view']);
});

// =============================================================================
// C1 — a tela ABRE. Regressão direta do 500 que a rota devolve desde o commit de origem.
// =============================================================================

it('C1 · a rota responde 200 e entrega a prop categorias (não 500)', function () {
    $bizId = (int) $this->business->id;
    $createdBy = EstoqueFixture::userId($bizId);

    // ANTI-VÁCUO: garante que existe categoria raiz pra consulta ter o que devolver.
    // Sem isto, "200 com lista vazia" não distinguiria consulta certa de consulta morta.
    criarCategoriaProduto($bizId, $createdBy, 'ZZ Contrato C1 ' . strtoupper(bin2hex(random_bytes(3))));

    $props = produtoUnificadoProps($this);

    expect($props)->toHaveKey('categorias');
    expect($props['categorias'])->toBeArray();
    expect(count($props['categorias']))->toBeGreaterThan(0);
});

// =============================================================================
// C2 — a contagem conta. Charter §Goals: "tree view 1 nível com count produtos".
// =============================================================================

it('C2 · conta os produtos da categoria e preserva o slug gravado', function () {
    $bizId = (int) $this->business->id;
    $createdBy = EstoqueFixture::userId($bizId);

    $slug = 'contrato-c2-' . bin2hex(random_bytes(3));
    $catId = criarCategoriaProduto($bizId, $createdBy, 'ZZ Contrato C2 ' . strtoupper(bin2hex(random_bytes(3))), 0, $slug);

    criarProdutoNaCategoria($bizId, $catId, 'ZZ C2 produto A');
    criarProdutoNaCategoria($bizId, $catId, 'ZZ C2 produto B');
    criarProdutoNaCategoria($bizId, $catId, 'ZZ C2 produto C');

    $props = produtoUnificadoProps($this);
    $linha = linhaDaCategoria($props['categorias'], $catId);

    // ANTI-VÁCUO: a categoria PRECISA estar na lista antes de a contagem significar algo.
    expect($linha)->not->toBeNull('A categoria raiz recém-criada não apareceu na sub-tela Categorias.');

    expect((int) $linha['count'])->toBe(3);
    expect($linha['label'])->toBeString();
    // O slug gravado é preservado; só cai no slug-do-nome quando a coluna está nula.
    expect($linha['slug'])->toBe($slug);
});

// =============================================================================
// C3 — Tier 0 (ADR 0093). Charter §Métricas vivas: isolamento por business_id.
// =============================================================================

it('C3 · categoria de outro business não aparece', function () {
    $bizId = (int) $this->business->id;
    $outroBizId = EstoqueFixture::secondBusinessId();

    if ($outroBizId === null) {
        $this->markTestSkipped('Sem 2º business seedado — cross-tenant não é verificável nesta lane.');
    }

    $catPropria = criarCategoriaProduto($bizId, EstoqueFixture::userId($bizId), 'ZZ Contrato C3 propria');
    $catAlheia = criarCategoriaProduto((int) $outroBizId, EstoqueFixture::userId((int) $outroBizId), 'ZZ Contrato C3 alheia');

    $props = produtoUnificadoProps($this);

    // ANTI-VÁCUO: a própria aparece — prova que a consulta rodou e devolve categoria.
    // Sem este par, "a alheia não veio" seria satisfeito por uma lista vazia.
    expect(linhaDaCategoria($props['categorias'], $catPropria))->not->toBeNull();
    expect(linhaDaCategoria($props['categorias'], $catAlheia))->toBeNull(
        'Categoria de outro business vazou na sub-tela Categorias (ADR 0093 Tier 0).'
    );
});

// =============================================================================
// C4 — "tree view 1 nível": só raiz. A convenção UPOS de raiz é `parent_id = 0` (NOT NULL),
//      não NULL — ver §ÂNCORA item 2. Um filtro por NULL não casa linha nenhuma e esvazia
//      a sub-tela inteira, o que este par detecta.
// =============================================================================

it('C4 · lista a categoria raiz e omite a sub-categoria', function () {
    $bizId = (int) $this->business->id;
    $createdBy = EstoqueFixture::userId($bizId);

    $raizId = criarCategoriaProduto($bizId, $createdBy, 'ZZ Contrato C4 raiz ' . strtoupper(bin2hex(random_bytes(3))));
    $subId = criarCategoriaProduto($bizId, $createdBy, 'ZZ Contrato C4 sub ' . strtoupper(bin2hex(random_bytes(3))), $raizId);

    $props = produtoUnificadoProps($this);

    expect(linhaDaCategoria($props['categorias'], $raizId))->not->toBeNull(
        'A categoria RAIZ (parent_id = 0) não apareceu — filtro de nível não casa a convenção UPOS.'
    );
    expect(linhaDaCategoria($props['categorias'], $subId))->toBeNull(
        'A SUB-categoria apareceu na lista de 1 nível.'
    );
});
