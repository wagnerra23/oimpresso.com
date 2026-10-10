<?php

declare(strict_types=1);
// Cobre UC-PIDX-01, UC-PIDX-02, UC-PIDX-03, UC-PIDX-04, UC-PIDX-05, UC-PIDX-06
// (resources/js/Pages/Produto/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use App\Product;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de comportamento da LISTA do catálogo (`GET /products` → `Produto/Index`).
 *
 * ÂNCORA (contrato, NÃO implementação) — triangulada nas 3 fontes (ADR 0351):
 *   1. CANON     — `CU-PROD-15` (consultar/listar) + `CU-PROD-10` `[T0]` (multi-tenant) do SDD §6.1
 *                  (memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md)
 *   2. BLADE     — o MESMO método `ProductController@index`, no branch `request()->ajax()`
 *                  (DataTables server-side) e `view('product.index')`
 *   3. DELPHI    — `AR-PROD-003` (inativo não some do cadastro), `AR-PROD-015` (custo/margem SOMEM
 *                  sem permissão), `AR-PROD-022` (inativo volta por FILTRO), `AR-PROD-023`
 *                  (Consultar = busca/listagem) da ANTI-REGRESSAO-cadastro-produto-legacy.md
 *
 * REESCRITO 2026-10-10 para a lista no desenho do protótipo (#9119 dados · #9124 tela): a Page
 * recebe a prop deferida `lista` (paginador do Laravel, mesma consulta da lista Blade) e os
 * filtros pelos MESMOS nomes do DataTable (`q`, `active_state`, `per_page`…). As props da tela
 * de cards (`rows`, `kpis`, `categorias`, `busca`, `mostrarInativos`) saíram. Os 6 contratos são
 * os MESMOS — só a forma de perguntar ao servidor mudou. A versão anterior nasceu failing-first e
 * provou o corte em 200, a busca e o filtro de inativos ignorados no servidor; o histórico está na
 * trilha do `Index.casos.md`.
 *
 * ⚠️ ANTI-VÁCUO (proibicoes §5, 2026-07-24): todo filtro é provado pelo par (o que casa VEM + o
 *    que não casa FICA DE FORA), e toda prop deferida tem pré-condição de que CHEGOU.
 *
 * ⚠️ Locais permitidos: a consulta da lista Blade só mostra produto dos locais que o usuário pode
 *    ver. O usuário do teste recebe `access_all_locations`; sem ela a lista vem vazia e "o preço
 *    não vazou" seria verdade por ausência de linha.
 *
 * ⛔ Multi-tenant Tier 0 (ADR 0358): tenant semeado do CI; cross-tenant contra outro business.
 *    NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

/**
 * Version que o servidor usa pro check Inertia (mesmo helper do ProdutoShowContratoTest):
 * mandar a MESMA version evita o 409 (version mismatch) antes de o controller rodar.
 */
function produtoIndexInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/**
 * GET /products no branch Inertia, pedindo a prop deferida `lista` por partial reload.
 * Cabeçalhos POR REQUISIÇÃO (2º argumento do get), nunca `withHeaders()`, que gruda no caso.
 *
 * @return array<string,mixed>  a prop `lista` (paginador)
 */
function produtoIndexLista(object $test, array $query = []): array
{
    $url = '/products' . ($query ? '?' . http_build_query($query) : '');

    $response = $test->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => produtoIndexInertiaVersion(),
        'X-Inertia-Partial-Component' => 'Produto/Index',
        'X-Inertia-Partial-Data' => 'lista',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertStatus(200);

    $page = json_decode($response->getContent(), true);
    expect($page)->toBeArray()->toHaveKey('props');
    expect($page['props'])->toHaveKey('lista');

    return $page['props']['lista'];
}

function produtoIndexIds(array $lista): array
{
    return array_map('intval', array_column($lista['data'], 'id'));
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }

    try {
        $this->business = $this->seededTenant(); // tenant canônico do CI (ADR 0358 — nunca biz=4).
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql no CT 100.');
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
    Permission::findOrCreate('access_all_locations', 'web');
    $this->user->givePermissionTo(['product.view', 'access_all_locations']);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

// =============================================================================
// UC-PIDX-01 — Blade DataTables (sem limit, paginação server-side) + AR-PROD-022/023:
//   todo produto do catálogo é alcançável pela lista; o que sai da vista sai por FILTRO
//   declarado, nunca por corte invisível.
// =============================================================================

it('UC-PIDX-01 · lista alcança todo o catálogo (paginação no servidor, sem corte silencioso)', function () {
    $bizId = (int) $this->business->id;

    // Produto do FIM do alfabeto — é exatamente o que um corte por nome derrubaria.
    $marcador = 'ZZZZ-ULTIMO-' . strtoupper(bin2hex(random_bytes(3)));
    $ultimo = EstoqueFixture::singleProduct($bizId, false);
    Product::where('id', $ultimo->productId)->update(['name' => $marcador]);

    $pagina1 = produtoIndexLista($this, ['per_page' => 25]);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a prop chegou e tem o formato do paginador.
    expect($pagina1)->toHaveKeys(['data', 'total', 'current_page', 'last_page', 'links']);
    expect((int) $pagina1['total'])->toBeGreaterThan(0);

    // O CONTRATO (a): a tela entrega tudo o que anuncia — OU oferece o caminho pro resto. Se o
    // total passa de uma página, existe a próxima (`last_page` > 1 e link para ela).
    if ((int) $pagina1['total'] > 25) {
        expect((int) $pagina1['last_page'])->toBeGreaterThan(1);
        expect(collect($pagina1['links'])->pluck('url')->filter()->count())->toBeGreaterThan(1);
    }

    // O CONTRATO (b): pedindo "todos", vem o catálogo inteiro — inclusive o último do alfabeto.
    $todos = produtoIndexLista($this, ['per_page' => -1]);
    expect(count($todos['data']))->toBe((int) $todos['total']);
    expect(produtoIndexIds($todos))->toContain($ultimo->productId);

    // O CONTRATO (c): e a busca no servidor o acha sem precisar paginar.
    expect(produtoIndexIds(produtoIndexLista($this, ['q' => $marcador])))->toBe([$ultimo->productId]);
});

// =============================================================================
// UC-PIDX-02 — Blade filterColumn('products.sku'): whereHas variations sub_sku + CU-PROD-02:
//   a busca roda no SERVIDOR e enxerga o SKU da variação, não só o do produto-pai.
// =============================================================================

it('UC-PIDX-02 · busca é resolvida no servidor e acha pelo SKU da variação', function () {
    $bizId = (int) $this->business->id;

    $alvo = EstoqueFixture::variableProduct($bizId, 2);          // sub_sku = "<sku>-1", "<sku>-2"
    $ruido = EstoqueFixture::singleProduct($bizId, false);        // não casa com o termo

    $subSku = (string) DB::table('variations')
        ->where('id', $alvo->variations[0]['variation_id'])
        ->value('sub_sku');

    // PRÉ-CONDIÇÃO ANTI-VÁCUO (a): o cenário existe — há sub_sku e ele é distinto do ruído.
    expect($subSku)->not->toBeEmpty();
    $skuRuido = (string) Product::where('id', $ruido->productId)->value('sku');
    expect(str_contains($skuRuido, $subSku))->toBeFalse();

    $ids = produtoIndexIds(produtoIndexLista($this, ['q' => $subSku]));

    // O CONTRATO (a): quem casa VEM — buscar pela etiqueta da variação acha o produto.
    expect($ids)->toContain($alvo->productId);

    // O CONTRATO (b): quem NÃO casa FICA DE FORA — a busca filtrou de verdade.
    expect($ids)->not->toContain($ruido->productId);
});

// =============================================================================
// UC-PIDX-03 — Blade index.blade.php (@can ×2) + AR-PROD-015 (o campo SOME):
//   preço de venda e custo não viajam pra quem não tem direito de vê-los.
// =============================================================================

it('UC-PIDX-03 · lista não entrega preço nem custo a quem não pode vê-los', function () {
    // PRÉ-CONDIÇÃO ANTI-VÁCUO: se o user seedado JÁ tem os direitos, este teste mediria o caso
    // errado e "passaria" sem provar nada.
    if ($this->user->can('view_purchase_price') && $this->user->can('access_default_selling_price')) {
        $this->markTestSkipped('User seedado JÁ tem as duas permissões de preço — sem cenário pra provar o gate.');
    }

    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);

    // VALORES SENTINELA: asserir só por CHAVE deixaria o vazamento PASSAR se alguém renomeasse o
    // campo. O contrato é "o valor não viaja" — então gravamos valores improváveis e varremos a
    // linha por VALOR. `dpp_inc_tax`/`sell_price_inc_tax` são as colunas que a consulta lê
    // (`MIN(v.dpp_inc_tax)`, `MIN(v.sell_price_inc_tax)` em `consultaListaProdutos`).
    $custoSentinela = 137.77;
    $vendaSentinela = 293.31;
    DB::table('variations')
        ->whereIn('id', array_column($produto->variations, 'variation_id'))
        ->update([
            'default_purchase_price' => $custoSentinela,
            'dpp_inc_tax' => $custoSentinela,
            'default_sell_price' => $vendaSentinela,
            'sell_price_inc_tax' => $vendaSentinela,
        ]);
    $sku = (string) Product::where('id', $produto->productId)->value('sku');

    $lista = produtoIndexLista($this, ['q' => $sku]);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a linha do produto CHEGOU — "não tem preço" não pode ser
    // verdade só porque não veio linha nenhuma.
    $linha = collect($lista['data'])->firstWhere('id', $produto->productId);
    expect($linha)->not->toBeNull();

    $valoresDaLinha = array_values(array_filter(
        array_map(
            static fn ($v) => is_numeric($v) ? round((float) $v, 2) : null,
            array_values((array) $linha)
        ),
        static fn ($v) => $v !== null
    ));

    if (! $this->user->can('view_purchase_price')) {
        expect($linha)->not->toHaveKey('compra_min');
        expect(in_array(round($custoSentinela, 2), $valoresDaLinha, true))->toBeFalse(
            "O custo vazou na linha da lista para usuário SEM view_purchase_price (sentinela {$custoSentinela})."
        );
    }

    if (! $this->user->can('access_default_selling_price')) {
        expect($linha)->not->toHaveKey('venda_min');
        expect(in_array(round($vendaSentinela, 2), $valoresDaLinha, true))->toBeFalse(
            "O preço de venda vazou na linha para usuário SEM access_default_selling_price (sentinela {$vendaSentinela})."
        );
    }
});

// =============================================================================
// UC-PIDX-04 — CU-PROD-10 `[T0]` + ADR 0093 + charter §Anti-hooks
//   ("❌ Não acessa produto de outro business_id"): a lista não enxerga o vizinho.
//   GUARD — o valor é TRAVAR, porque `App\Product` não tem global scope.
// =============================================================================

it('UC-PIDX-04 · lista e total só enxergam o business atual (multi-tenant Tier 0)', function () {
    $bizId = (int) $this->business->id;
    $outroBizId = (int) DB::table('business')->where('id', '!=', $bizId)->orderBy('id')->value('id');
    if ($outroBizId === 0) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $meu = EstoqueFixture::singleProduct($bizId);
    $alheio = EstoqueFixture::singleProduct($outroBizId);

    $lista = produtoIndexLista($this, ['per_page' => -1]);
    $ids = produtoIndexIds($lista);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: o payload chegou COM o meu produto.
    expect($ids)->toContain($meu->productId);

    // O CONTRATO (a): nenhuma linha do vizinho.
    expect($ids)->not->toContain($alheio->productId);

    // O CONTRATO (b): o total conta o MEU catálogo (o mesmo recorte da lista Blade: sem
    // `modifier`, com variação viva), não a soma dos dois.
    $totalDoBusiness = (int) Product::where('business_id', $bizId)
        ->where('type', '!=', 'modifier')
        ->whereHas('variations')
        ->count();
    expect((int) $lista['total'])->toBe($totalDoBusiness);
});

// =============================================================================
// UC-PIDX-05 — Index.charter §Automation Anti-hooks + AR-PROD-064 (append-only):
//   abrir a lista é leitura pura.
//   GUARD — o irmão `productStockHistory` FAZ UPDATE dentro de um GET (CU-PROD-11).
// =============================================================================

it('UC-PIDX-05 · abrir a lista não escreve no banco (GET é leitura pura)', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);

    $antes = (string) Product::where('id', $produto->productId)->value('updated_at');
    expect($antes)->not->toBeEmpty();

    $lista = produtoIndexLista($this, ['per_page' => -1]);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a prop deferida foi REALMENTE resolvida (a closure rodou).
    expect(produtoIndexIds($lista))->toContain($produto->productId);

    $depois = (string) Product::where('id', $produto->productId)->value('updated_at');
    expect($depois)->toBe($antes);
});

// =============================================================================
// UC-PIDX-06 — Blade `active_state` server-side + AR-PROD-003/022
//   ("some da lista, mas fica acessível por FILTRO"): o filtro é do SERVIDOR.
// =============================================================================

it('UC-PIDX-06 · filtro de inativos (Situação) é resolvido no servidor', function () {
    $bizId = (int) $this->business->id;

    $ativo = EstoqueFixture::singleProduct($bizId, false);
    $inativo = EstoqueFixture::singleProduct($bizId, false);
    Product::where('id', $inativo->productId)->update(['is_inactive' => 1]);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: o cenário está montado como o caso descreve.
    expect((int) Product::where('id', $inativo->productId)->value('is_inactive'))->toBe(1);

    // Situação = Ativo.
    $ids = produtoIndexIds(produtoIndexLista($this, ['active_state' => 'active', 'per_page' => -1]));

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: o ativo VEIO — senão a ausência do inativo não prova filtro.
    expect($ids)->toContain($ativo->productId);

    // O CONTRATO (a): só ativos, o inativo não vem no payload.
    expect($ids)->not->toContain($inativo->productId);

    // O CONTRATO (b): Situação = Inativo traz ele de volta — AR-PROD-022 ("acessível por filtro"),
    // marcado como inativo; e o ativo fica de fora.
    $comInativos = produtoIndexLista($this, ['active_state' => 'inactive', 'per_page' => -1]);
    $linha = collect($comInativos['data'])->firstWhere('id', $inativo->productId);
    expect($linha)->not->toBeNull();
    expect($linha['inativo'])->toBeTrue();
    expect(produtoIndexIds($comInativos))->not->toContain($ativo->productId);
});
