<?php

declare(strict_types=1);
// Cobre UC-PSHOW-01, UC-PSHOW-02, UC-PSHOW-03, UC-PSHOW-06 (resources/js/Pages/Produto/Show.casos.md)
// — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de comportamento da FICHA do produto (`GET /products/{id}` → `Produto/Show`).
 *
 * ÂNCORA (contrato, NÃO implementação) — triangulada nas 3 fontes (ADR 0351):
 *   1. CANON     — `CU-PROD-14` (ficha/consulta) + `CU-PROD-10` `[T0]` do SDD §6.1
 *                  (memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md)
 *   2. BLADE     — `resources/views/product/view-modal.blade.php` (a ficha REAL que roda em prod
 *                  hoje) + partials `single|variable|combo_product_details.blade.php`
 *   3. DELPHI    — `AR-PROD-015` (custo/margem SOMEM sem permissão) e `AR-PROD-057`
 *                  (descrição do local de estoque) da ANTI-REGRESSAO-cadastro-produto-legacy.md
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * Documentar só o React CARIMBARIA a perda. A ficha Blade gateia preço de compra/venda atrás de
 * `@can('view_purchase_price')` / `@can('access_default_selling_price')` (6 ocorrências em cada um
 * dos 3 partials de detalhe; 47 no total em 15 arquivos de view), e o Delphi faz os campos
 * DESAPARECEREM sem o direito (AR-PROD-015). O branch Inertia de `ProductController@show`
 * (:801-848) consulta exatamente 3 permissões — `product.view`, `product.update`, `product.delete`
 * — e NENHUMA delas é de preço: manda `defaultPurchasePrice`/`defaultSellPrice` de toda variação
 * pra qualquer um que possa ver o produto.
 *
 * ⚠️ Failing-first (proibicoes §5, 2026-06-05): os asserts saem do contrato (CU/AR/Blade), não do
 *    código. Se nascer vermelho, o vermelho É o achado — não se ajusta o teste ao código.
 *
 * ⚠️ ANTI-VÁCUO (proibicoes §5, 2026-07-24): todo caso que afirma "X não aparece" tem pré-condição
 *    provando que a operação ACONTECEU (status 200 + prop deferida presente + cenário válido).
 *    Sem isso, o teste mede NÃO-EXECUÇÃO e chama de contrato cumprido.
 *
 * ⛔ Escopo honesto: as telas React do Produto ainda NÃO são alcançáveis em prod (a sidebar usa
 *    `<a href>` puro, sem header `X-Inertia` → cai no Blade). Vermelho aqui é BLOQUEADOR DE
 *    MIGRAÇÃO (gate MWART F5, ADR 0104), não incidente de produção.
 *
 * ⛔ Multi-tenant Tier 0 (ADR 0101): biz=1 canônico; cross-tenant contra o 2º business seedado.
 *    NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

/**
 * Version que o servidor usa pro check Inertia (mesmo helper do StockHistoryContratoTest):
 * mandar a MESMA version evita o 409 (version mismatch) antes de o controller rodar.
 */
function produtoShowInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
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
// UC-PSHOW-01 — AR-PROD-015 + Blade `@can('view_purchase_price')`:
//   a ficha NÃO entrega preço de compra a quem não tem direito de ver custo.
// =============================================================================

it('UC-PSHOW-01 · ficha não entrega preço de compra a quem não pode ver custo', function () {
    // PRÉ-CONDIÇÃO ANTI-VÁCUO (a): o cenário precisa existir. Se o user seedado JÁ tem o
    // direito, este teste mediria o caso errado e "passaria" sem provar nada.
    if ($this->user->can('view_purchase_price')) {
        $this->markTestSkipped('User seedado JÁ tem view_purchase_price — sem cenário pra provar o gate de custo.');
    }

    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::variableProduct($bizId, 2);

    // CUSTO SENTINELA: o fixture grava `default_purchase_price = 10`, que colide com qualquer
    // outro campo numérico que valha 10 (quantidade, id, etc). Um valor improvável torna o
    // assert por VALOR confiável — e é o que permite desacoplar da chave (ver contrato abaixo).
    $custoSentinela = 137.77;
    DB::table('variations')
        ->whereIn('id', array_column($produto->variations, 'variation_id'))
        ->update(['default_purchase_price' => $custoSentinela]);

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => produtoShowInertiaVersion(),
        'X-Inertia-Partial-Component' => 'Produto/Show',
        'X-Inertia-Partial-Data' => 'variations',
    ])->get("/products/{$produto->productId}");

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO (b): a prop deferida CHEGOU. Sem isto, "não tem preço de compra"
    // seria verdade só porque não veio variação nenhuma — verde por não-execução.
    expect($page['props'])->toHaveKey('variations');
    expect($page['props']['variations'])->toBeArray();
    expect(count($page['props']['variations']))->toBeGreaterThanOrEqual(1);

    // O CONTRATO: sem `view_purchase_price`, o custo não viaja. No Blade a coluna nem é
    // renderizada; no Delphi o campo SOME da tela (AR-PROD-015) — não é read-only, é ausente.
    //
    // Desacoplado da CHAVE (2026-07-26): `not->toHaveKey('defaultPurchasePrice')` passaria
    // ERRADAMENTE se alguém só RENOMEASSE o campo mantendo o custo no payload. O contrato é
    // "nenhum valor de custo viaja", não "a chave se chama X" — então asserimos sobre o VALOR.
    foreach ($page['props']['variations'] as $variacao) {
        $valores = array_values(array_filter(
            array_map(
                static fn ($v) => is_numeric($v) ? round((float) $v, 2) : null,
                array_values((array) $variacao)
            ),
            static fn ($v) => $v !== null
        ));

        // `toContain` é VARIÁDICO em Pest (cada arg extra é outro needle, não mensagem) —
        // por isso a explicação vai num `toBeFalse` com mensagem, que aceita.
        $custoVazou = in_array(round($custoSentinela, 2), $valores, true);

        expect($custoVazou)->toBeFalse(
            'O custo da variação vazou no payload da ficha para usuário SEM view_purchase_price '
            . '(valor sentinela ' . $custoSentinela . ' encontrado). Blade gateia com @can; Delphi some com o campo (AR-PROD-015).'
        );
    }
});

// =============================================================================
// UC-PSHOW-02 — CU-PROD-10.2 `[T0]`: ficha de produto de OUTRO business → 404.
//   (é o `it('Controller cross-tenant retorna 404')` que o Show.charter promete no §Pest GUARD
//    e que hoje não existe em teste nenhum — o charter prometia sem lastro.)
// =============================================================================

it('UC-PSHOW-02 · ficha de produto de outro business retorna 404 (multi-tenant Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $alheio = EstoqueFixture::singleProduct($outroBizId);

    // Guard (espera-se VERDE): `show()` resolve com where('business_id')->findOrFail (:811-813).
    // O valor do caso é travar isso — o irmão `update()` provou que a família reincide
    // (UC-PEDIT-03: first() → 500) e o `saveSellingPrices` provou o 302 (#4300).
    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => produtoShowInertiaVersion()])
        ->get("/products/{$alheio->productId}")
        ->assertStatus(404);
});

// =============================================================================
// UC-PSHOW-03 — Blade view-modal `{{$rd->name}}` + AR-PROD-057:
//   a ficha mostra o LOCAL do endereçamento de estoque, não só rack/row/position.
// =============================================================================

it('UC-PSHOW-03 · aba Estoque entrega o nome do local do rack', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);
    $locationId = EstoqueFixture::locationId($bizId);

    DB::table('product_racks')->insert([
        'business_id' => $bizId,
        'location_id' => $locationId,
        'product_id' => $produto->productId,
        'rack' => 'R1',
        'row' => 'F2',
        'position' => 'P3',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => produtoShowInertiaVersion(),
        'X-Inertia-Partial-Component' => 'Produto/Show',
        'X-Inertia-Partial-Data' => 'rackDetails',
    ])->get("/products/{$produto->productId}");

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a prop deferida chegou COM a linha que acabamos de inserir.
    expect($page['props'])->toHaveKey('rackDetails');
    expect($page['props']['rackDetails'])->toBeArray();
    expect(count($page['props']['rackDetails']))->toBeGreaterThanOrEqual(1);

    $linha = $page['props']['rackDetails'][0];

    // O CONTRATO (AR-PROD-057 + coluna "Local" do `view-modal`): o local chega IDENTIFICADO.
    //
    // Desacoplado da CHAVE (2026-07-26): asserir `toHaveKey('location_name')` escolhe um dos
    // dois fixes válidos arbitrariamente — o back passar a emitir `location_name`, OU o front
    // passar a ler a chave que o `getRackDetails` já devolve. Ambos satisfazem o contrato
    // "o local aparece"; o assert por chave literal reprovaria o segundo (falso-vermelho).
    $nomeDoLocal = (string) DB::table('business_locations')->where('id', $locationId)->value('name');
    expect($nomeDoLocal)->not->toBeEmpty('fixture inválida: a location seedada precisa ter nome.');

    $temNomeDoLocal = collect((array) $linha)
        ->contains(fn ($v) => is_string($v) && $v === $nomeDoLocal);

    expect($temNomeDoLocal)->toBeTrue(
        'Nenhuma chave da linha de estoque carrega o nome do local — a coluna "Local" da ficha '
        . 'renderiza vazia (o `select` do getRackDetails e o que a Page lê não se encontram).'
    );
});

// =============================================================================
// UC-PSHOW-06 — Show.charter §Anti-hooks ("❌ Não escreve no banco em GET") + AR-PROD-064
//   (movimento é append-only): abrir a ficha é leitura pura.
// =============================================================================

it('UC-PSHOW-06 · abrir a ficha não escreve no produto (GET é leitura pura)', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);

    $antes = DB::table('products')->where('id', $produto->productId)
        ->value('updated_at');

    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => produtoShowInertiaVersion()])
        ->get("/products/{$produto->productId}");

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a ficha REALMENTE renderizou. Sem isto, "nada mudou" seria
    // verdade só porque a request abortou antes de tocar em qualquer coisa.
    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);
    expect($page['component'])->toBe('Produto/Show');
    expect($page['props'])->toHaveKey('product');

    $depois = DB::table('products')->where('id', $produto->productId)
        ->value('updated_at');

    expect($depois)->toBe($antes);
});
