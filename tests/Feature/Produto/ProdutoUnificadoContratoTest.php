<?php

declare(strict_types=1);
// Cobre UC-PUNI-01, UC-PUNI-02, UC-PUNI-03, UC-PUNI-04, UC-PUNI-05, UC-PUNI-06
// (resources/js/Pages/Produto/Unificado/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use App\Product;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato do CATÁLOGO UNIFICADO (`GET /products/unificado` → `Produto/Unificado/Index`).
 *
 * ÂNCORA (contrato, NÃO implementação):
 *   1. CANON  — `CU-PROD-15` (consultar/listar) + `CU-PROD-10` `[T0]` (multi-tenant) do SDD §6.1
 *   2. BLADE  — `resources/views/product/index.blade.php:287,294` gateia preço de compra e de venda
 *               atrás de `@can('view_purchase_price')` / `@can('access_default_selling_price')`
 *   3. DELPHI — `AR-PROD-015`: custo e margem **somem** da tela sem permissão (ausência, não
 *               read-only)
 *   4. IRMÃOS — `UC-PIDX-03` (lista) e `UC-PSHOW-01` (ficha) já contratam a MESMA regra. Esta tela
 *               era a única da família sem casos.
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * O Unificado reúne numa rota só o que as outras telas gateiam separadamente: custo, preço de
 * venda, tabelas de preço e (em breve) composição. Medido em `ProdutoUnificadoController`:
 * `produtos()` monta `price`/`cost`/`margin` pra TODA linha (:122-124) e a varredura contada de
 * `view_purchase_price|access_default_selling_price` no arquivo devolve 0 ocorrências. A rota
 * (`routes/web.php:450`) não tem middleware de permissão — o TODO está declarado na linha acima.
 *
 * ⚠️ Failing-first (proibicoes §5): os asserts saem do contrato, não do código. Se nascer vermelho,
 *    o vermelho É o achado — não se ajusta o teste ao código. Por isso o arquivo entra no bloco (A)
 *    de `.github/estoque-pest-quarantine.list`, como os 6 irmãos.
 *
 * ⚠️ ANTI-VÁCUO (proibicoes §5): todo assert de ausência tem pré-condição provando que a prop
 *    CHEGOU e que a linha do produto semeado ESTÁ nela. Sem isso, "não tem custo" passaria só
 *    porque não veio linha nenhuma — mediria não-execução.
 *
 * ⚠️ SENTINELA POR VALOR: asserir `not->toHaveKey('cost')` deixa o vazamento passar se alguém
 *    renomear a chave. O contrato é "o valor não viaja" — então semeamos valores improváveis e
 *    varremos o payload inteiro por VALOR.
 *
 * ⛔ Multi-tenant Tier 0 (ADR 0101): biz=1 canônico; cross-tenant contra o 2º business seedado.
 *    NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

function unificadoContratoInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/**
 * GET /products/unificado no branch Inertia, pedindo as props por partial reload.
 *
 * @param  list<string>  $props
 * @param  array<string,mixed>  $query
 * @return array<string,mixed>  o objeto `props` da página
 */
function unificadoContratoProps(object $test, array $props, array $query = []): array
{
    $url = '/products/unificado' . ($query ? '?' . http_build_query($query) : '');

    $response = $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => unificadoContratoInertiaVersion(),
        'X-Inertia-Partial-Component' => 'Produto/Unificado/Index',
        'X-Inertia-Partial-Data' => implode(',', $props),
    ])->get($url);

    $response->assertStatus(200);

    $page = json_decode($response->getContent(), true);
    expect($page)->toBeArray()->toHaveKey('props');

    return $page['props'];
}

/**
 * Todos os números do payload, arredondados a 2 casas — pra varrer por VALOR, não por chave.
 *
 * @return list<float>
 */
function unificadoContratoNumeros(mixed $payload): array
{
    $out = [];
    array_walk_recursive(
        (array) json_decode((string) json_encode($payload), true),
        static function ($v) use (&$out) {
            if (is_numeric($v)) {
                $out[] = round((float) $v, 2);
            }
        }
    );

    return $out;
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }

    try {
        $this->business = $this->seededTenant(); // biz=1 (ADR 0101 — nunca biz=4).
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
    $this->user->givePermissionTo(['product.view']);
});

// =============================================================================
// UC-PUNI-01 — AR-PROD-015 (o campo SOME) + UC-PIDX-03 (mesma regra na lista):
//   o custo não viaja pra quem não tem direito de vê-lo. `margin` cai junto.
// =============================================================================

it('UC-PUNI-01 · catálogo unificado não entrega custo a quem não pode vê-lo', function () {
    if ($this->user->can('view_purchase_price')) {
        $this->markTestSkipped('User seedado JÁ tem view_purchase_price — sem cenário pra provar o gate.');
    }

    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);

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

    $props = unificadoContratoProps($this, ['produtos']);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a prop chegou E a linha do produto está nela.
    expect($props)->toHaveKey('produtos');
    $linha = collect($props['produtos'])->firstWhere('id', $produto->productId);
    expect($linha)->not->toBeNull(
        'A linha do produto semeado não veio — sem ela, "não tem custo" mediria não-execução.'
    );

    $numeros = unificadoContratoNumeros($linha);

    expect(in_array(round($custoSentinela, 2), $numeros, true))->toBeFalse(
        "O custo vazou na linha do catálogo unificado para usuário SEM view_purchase_price "
        . "(sentinela {$custoSentinela}). Renomear a chave não faz este assert passar — ele varre por valor."
    );

    // `margin` é derivada do custo (ProdutoUnificadoController:124) — entregá-la entrega o custo
    // por dedução. O controller grava com round(..., 4), então a comparação é no mesmo grão.
    $margemDerivada = round(($vendaSentinela - $custoSentinela) / $vendaSentinela, 2);
    expect(in_array($margemDerivada, $numeros, true))->toBeFalse(
        "A margem ({$margemDerivada}) entrega o custo por dedução, mesmo sem o campo de custo explícito."
    );
});

// =============================================================================
// UC-PUNI-02 — Blade index.blade.php:294 (@can('access_default_selling_price')):
//   preço de venda não viaja — nem na linha, nem pela porta lateral do Histórico.
// =============================================================================

it('UC-PUNI-02 · catálogo unificado não entrega preço de venda a quem não pode vê-lo', function () {
    if ($this->user->can('access_default_selling_price')) {
        $this->markTestSkipped('User seedado JÁ tem access_default_selling_price — sem cenário pra provar o gate.');
    }

    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);

    $vendaSentinela = 411.19;
    DB::table('variations')
        ->whereIn('id', array_column($produto->variations, 'variation_id'))
        ->update([
            'default_sell_price' => $vendaSentinela,
            'sell_price_inc_tax' => $vendaSentinela,
        ]);

    $props = unificadoContratoProps($this, ['produtos']);

    expect($props)->toHaveKey('produtos');
    $linha = collect($props['produtos'])->firstWhere('id', $produto->productId);
    expect($linha)->not->toBeNull();

    expect(in_array(round($vendaSentinela, 2), unificadoContratoNumeros($linha), true))->toBeFalse(
        "O preço de venda vazou na linha para usuário SEM access_default_selling_price (sentinela {$vendaSentinela})."
    );
});

it('UC-PUNI-02b · a porta lateral do Histórico também não entrega preço de venda', function () {
    if ($this->user->can('access_default_selling_price')) {
        $this->markTestSkipped('User seedado JÁ tem access_default_selling_price — sem cenário pra provar o gate.');
    }

    // O Histórico devolve `value` = qty × unit_price_inc_tax (ProdutoUnificadoController:249,260).
    // Gatear a lista e deixar o histórico aberto entrega o MESMO dado por outro caminho.
    $props = unificadoContratoProps($this, ['historico'], ['tela' => 'historico']);

    expect($props)->toHaveKey('historico');

    foreach ((array) $props['historico'] as $linha) {
        expect(array_key_exists('value', (array) $linha) || array_key_exists('unit_price', (array) $linha))
            ->toBeFalse(
                'O Histórico de uso entrega valor de venda por linha para usuário sem '
                . 'access_default_selling_price — é a porta lateral do UC-PUNI-02.'
            );
    }
});

// =============================================================================
// UC-PUNI-03 — decisão 2026-08-11: tabelas de preço seguem o MESMO gate do preço.
//   A separação em permissão própria foi considerada e adiada (ver casos.md).
// =============================================================================

it('UC-PUNI-03 · tabelas de preço seguem o mesmo gate do preço de venda', function () {
    if ($this->user->can('access_default_selling_price')) {
        $this->markTestSkipped('User seedado JÁ tem access_default_selling_price — sem cenário pra provar o gate.');
    }

    $props = unificadoContratoProps($this, ['tabelas'], ['tela' => 'tabelas']);

    expect($props)->toHaveKey('tabelas');
    expect($props['tabelas'])->toBeArray()->toBeEmpty(
        'A sub-tela Tabelas de preço entregou os SellingPriceGroup do business para usuário sem '
        . 'access_default_selling_price. Decisão 2026-08-11: tabela de preço segue o gate do preço '
        . 'de venda — permissão própria foi considerada e adiada (Index.casos.md · UC-PUNI-03).'
    );
});

// =============================================================================
// UC-PUNI-04 — camada 1 (módulo) + camada 3 (permissão). PREVENTIVO: o controller
//   ainda não serve BOM (bomCount é literal 0). O caso existe pra nascer gated.
// =============================================================================

it('UC-PUNI-04 · composição (BOM) não chega sem manufacturing.access_recipe', function () {
    Permission::findOrCreate('manufacturing.access_recipe', 'web');

    if ($this->user->can('manufacturing.access_recipe')) {
        $this->markTestSkipped('User seedado JÁ tem manufacturing.access_recipe — sem cenário pra provar o gate.');
    }

    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);

    $props = unificadoContratoProps($this, ['produtos']);
    $linha = (array) collect($props['produtos'])->firstWhere('id', $produto->productId);
    expect($linha)->not->toBeEmpty();

    // O ingrediente da receita é uma VARIAÇÃO de produto (mfg_recipe_ingredients:23) — a composição
    // é a estrutura de custo do produto. Sem módulo + permissão, nem a contagem sai.
    expect(($linha['bomCount'] ?? 0))->toBe(
        0,
        'A contagem de itens da composição chegou ao navegador sem manufacturing.access_recipe. '
        . 'Visibilidade da composição usa as camadas canônicas (módulo no pacote + permissão), '
        . 'nunca hardcode por business.'
    );

    $props = unificadoContratoProps($this, ['insumos'], ['tela' => 'insumos']);
    expect($props['insumos'])->toBeArray()->toBeEmpty(
        'A sub-tela Insumos entregou a lista sem manufacturing.access_recipe.'
    );
});

// =============================================================================
// UC-PUNI-05 `[T0]` — seis props numa rota só; nenhuma pode enxergar outro business.
// =============================================================================

it('UC-PUNI-05 · nenhuma prop do unificado enxerga produto de outro business', function () {
    $outroBiz = (int) DB::table('business')
        ->where('id', '!=', $this->business->id)
        ->value('id');

    if (! $outroBiz) {
        $this->markTestSkipped('Só há 1 business no seed — sem cenário cross-tenant pra provar o isolamento.');
    }

    $intruso = EstoqueFixture::singleProduct($outroBiz);

    $props = unificadoContratoProps($this, ['produtos', 'kpis', 'categorias']);

    $idsVisiveis = collect($props['produtos'])->pluck('id')->all();
    expect(in_array($intruso->productId, $idsVisiveis, true))->toBeFalse(
        'Produto de OUTRO business apareceu no catálogo unificado — Tier 0 IRREVOGÁVEL (ADR 0093).'
    );

    // A contagem de categorias não escopa o lado `products` do leftJoin por business_id
    // (residual declarado em ProdutoUnificadoController:151-154). Se inflar, é aqui que aparece.
    $totalDoBusiness = Product::where('business_id', $this->business->id)->count();
    $somaDasCategorias = collect($props['categorias'])->sum('count');
    expect($somaDasCategorias)->toBeLessThanOrEqual(
        $totalDoBusiness,
        'A contagem por categoria somou mais produtos do que o business tem — o leftJoin está '
        . 'contando produto de outro tenant (residual declarado no controller :151-154).'
    );
});

// =============================================================================
// UC-PUNI-06 — a rota não tem middleware de permissão (routes/web.php:449 TODO).
// =============================================================================

it('UC-PUNI-06 · a tela exige product.view (ou product.create, como a lista irmã)', function () {
    // A semântica canônica NÃO é middleware: `ProductController@index:66` gateia DENTRO do
    // controller e aceita `product.view` OU `product.create` — quem pode cadastrar produto
    // precisa alcançar o catálogo. Revogar só `product.view` deixaria o teste passar/falhar
    // por motivo errado se o user seedado tiver `product.create`.
    Permission::findOrCreate('product.create', 'web');
    $this->user->revokePermissionTo('product.view');
    if ($this->user->can('product.create')) {
        $this->user->revokePermissionTo('product.create');
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => unificadoContratoInertiaVersion(),
    ])->get('/products/unificado');

    expect($response->getStatusCode())->toBe(
        403,
        'Usuário sem product.view NEM product.create recebeu a página do catálogo unificado. '
        . 'A lista irmã (/products) aborta 403 em ProductController@index:66; o unificado não gateia '
        . 'nada — o TODO está em routes/web.php:449.'
    );
});
