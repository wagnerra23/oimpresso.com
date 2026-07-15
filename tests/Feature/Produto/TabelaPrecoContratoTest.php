<?php

declare(strict_types=1);
// Cobre UC-PTAB-01, UC-PTAB-02, UC-PTAB-03, UC-PTAB-04, UC-PTAB-05 (SellingPrices.casos.md) - G-2 rastreabilidade caso-teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de comportamento da Tabela de Preço do produto (/products/add-selling-prices/{id}).
 *
 * ÂNCORA (contrato, NÃO implementação): CU-PROD-03 — "Preço por tabela (SellingPriceGroup)" `[must]`
 * em memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md §6.1:
 *   1. `[must]` Matriz grupo × variação salva preço por tabela (`variation_group_prices`)  → UC-PTAB-01
 *   2. `[V0][reg]` Multiplicador/markup por tabela (mult=1.00 hardcoded)                    → SEM UC (backlog)
 *   3. `[V0]` Markup aplicado recalcula preço da tabela sem divergir do financeiro          → parcial (ver abaixo)
 *   4. `[T0]` Tabelas só do business atual                                                  → UC-PTAB-02
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * A tela tinha charter desde 2026-05-15 e ZERO teste de comportamento. Os dois que existem
 * (Wave2SellingPricesInertiaTest / Wave2SellingPricesBaselineTest) só fazem grep de string no
 * fonte ("contém `variations.map`", "importa AppShellV2") — não exercitam nada. Pior: o charter
 * PROMETIA `it('Controller cross-tenant retorna 404')` no §Pest GUARD e esse teste NUNCA existiu;
 * o que havia era um grep procurando a string `session()->get('user.business_id')` no controller.
 * Buraco Tier 0 documentado como se estivesse coberto.
 *
 * O item 2 do CU-PROD-03 (multiplicador) NÃO vira UC aqui: `SellingPriceGroup.mult` é hardcoded
 * `1.00` (ADR ARQ-0001 produto, proposed / US-PROD-022). Um teste afirmando que funciona sairia
 * vermelho. Fica no `## Backlog de casos (sem id)` do casos.md até a US implementar — é pra isso
 * que aquela seção existe (G-2: UC declarado sem teste = órfão).
 *
 * ⛔ TEST-ONLY: não altera cálculo nenhum. Caracteriza o contrato atual do endpoint.
 *    Mudar preço/markup em prod é US separada sob REGRA MESTRE (dupla-confirmação + antes→depois).
 */
uses(DatabaseTransactions::class);

/** Cria uma tabela de preço (SellingPriceGroup) ativa no business. */
function tabelaPrecoAtiva(int $businessId, string $nome): int
{
    return (int) DB::table('selling_price_groups')->insertGetId([
        'name' => $nome,
        'business_id' => $businessId,
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Preço unitário que o PDV monta pra variação — lido do row real do POS.
 *
 * O `hidden_base_unit_sell_price` da view `sale_pos.product_row` (linha 294) imprime
 * `$product->default_sell_price / $multiplier` CRU (sem `num_format`), e `$multiplier` é 1
 * quando o produto não tem sub-unidade. É exatamente o campo que o guard do
 * `SellPosController::getSellLineRow` sobrescreve quando há preço de tabela — por isso é ele
 * que responde "com que preço esta venda sai?", que é o que o UC-PTAB-05 pergunta.
 */
function precoUnitarioNoPdv(string $html): ?float
{
    if (! preg_match('/class="hidden_base_unit_sell_price" value="([^"]*)"/', $html, $m)) {
        return null;
    }

    return (float) $m[1];
}

/** Preço gravado para o par (variação × tabela), ou null se não persistiu. */
function precoGravado(int $variationId, int $priceGroupId): ?array
{
    $row = DB::table('variation_group_prices')
        ->where('variation_id', $variationId)
        ->where('price_group_id', $priceGroupId)
        ->first();

    return $row ? ['price_inc_tax' => (float) $row->price_inc_tax, 'price_type' => $row->price_type] : null;
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }

    try {
        // biz=1 canônico (ADR 0101 — NUNCA biz=4, que é cliente real ROTA LIVRE).
        $this->business = $this->seededTenant();
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

    // O seed não atribui permissões ao user seeded; givePermissionTo com STRING exige a
    // permission pré-existente no Spatie v6 (pattern de ClienteDrawerCadastroAutosaveTest).
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.create', 'web');
    $this->user->givePermissionTo(['product.create']);
});

// =============================================================================
// UC-PTAB-01 — CU-PROD-03.1: a matriz salva o preço por (variação × tabela)
// =============================================================================

it('UC-PTAB-01 · salvar a matriz persiste o preço por (variação × tabela)', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);
    $variationId = $produto->variationId();
    $tabelaId = tabelaPrecoAtiva($bizId, 'Atacado UC-PTAB-01');

    expect(precoGravado($variationId, $tabelaId))->toBeNull(); // pré-condição: ainda sem preço

    $response = $this->post('/products/save-selling-prices', [
        'product_id' => $produto->productId,
        'group_prices' => [
            $tabelaId => [
                $variationId => ['price' => '150,00', 'price_type' => 'fixed'],
            ],
        ],
    ]);

    $response->assertStatus(302); // redirect('products') — contrato do controller

    $gravado = precoGravado($variationId, $tabelaId);
    expect($gravado)->not->toBeNull('O par (variação × tabela) não persistiu em variation_group_prices.');
    expect($gravado['price_type'])->toBe('fixed');
    expect($gravado['price_inc_tax'])->toEqualWithDelta(150.00, 0.0001);
});

// =============================================================================
// UC-PTAB-02 — CU-PROD-03.4 `[T0]`: tabelas só do business atual
//   (é o teste que o charter prometia no §Pest GUARD e nunca existiu)
// =============================================================================

it('UC-PTAB-02 · produto de outro business retorna 404 (multi-tenant Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    // Produto vive no OUTRO business; a sessão é do business seeded.
    $produtoAlheio = EstoqueFixture::singleProduct($outroBizId);

    $this->get('/products/add-selling-prices/' . $produtoAlheio->productId)
        ->assertStatus(404);
});

/**
 * ⚠️ O invariante aqui é ISOLAMENTO (CU-PROD-03.4 — "tabelas só do business atual"), NÃO o
 * código HTTP. A 1ª versão deste teste assertava 404 no POST — copiando a promessa do §Pest GUARD
 * do charter — e o CI reprovou na 1ª execução (#4300):
 *
 *     Expected response status code [404] but received 302.
 *
 * CAUSA: em `saveSellingPrices` o `findOrFail` roda DENTRO de `try { } catch (\Exception $e)`.
 * A ModelNotFoundException é engolida pelo catch genérico → `redirect('products')` com
 * `success: 0` + "something went wrong". O GET (`addSellingPrices`) devolve 404 porque NÃO tem
 * try/catch em volta. Ou seja: a promessa do charter valia pra metade do contrato.
 *
 * O isolamento em si NÃO vaza (a exceção aborta antes de qualquer write + rollback) — por isso
 * o teste assere o que o CONTRATO pede (nada gravado + não reporta sucesso) em vez do proxy
 * errado. A divergência 302-vs-404 está registrada no §Backlog do casos.md pra [W] decidir se
 * vira US (404 honesto) ou Non-Goal (302 genérico aceito).
 */
it('UC-PTAB-02 · salvar preço em produto de outro business não grava nada (multi-tenant Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $produtoAlheio = EstoqueFixture::singleProduct($outroBizId);
    $variationAlheia = $produtoAlheio->variationId();
    $tabelaAlheia = tabelaPrecoAtiva($outroBizId, 'Atacado alheio UC-PTAB-02');

    $response = $this->post('/products/save-selling-prices', [
        'product_id' => $produtoAlheio->productId,
        'group_prices' => [
            $tabelaAlheia => [
                $variationAlheia => ['price' => '999,00', 'price_type' => 'fixed'],
            ],
        ],
    ]);

    // (a) O INVARIANTE: nada pode ter sido gravado no business alheio.
    expect(precoGravado($variationAlheia, $tabelaAlheia))
        ->toBeNull('Preço foi gravado em produto de OUTRO business — vazamento cross-tenant Tier 0.');

    // (b) E a operação não pode reportar sucesso (hoje: 302 + status.success=0 — ver docblock).
    $response->assertStatus(302);
    expect(session('status.success'))->not->toBe(1, 'Cross-tenant reportou sucesso ao operador.');
});

// =============================================================================
// UC-PTAB-04 — CU-PROD-10.1/.2 `[T0]`: o OUTRO eixo do cross-tenant
//
//   O UC-PTAB-02 prova o eixo do PRODUTO (produto alheio). Este prova o eixo da
//   TABELA: produto MEU + price_group ALHEIO. O `saveSellingPrices` escopa por
//   business_id apenas o `Product::findOrFail`; o price_group_id vem CRU da chave
//   do array do request (`foreach ($request->input('group_prices') as $key => ...)`),
//   sem validate/exists. E o VariationGroupPrice NÃO tem global scope ($guarded=['id']).
//
//   CU-PROD-10 está marcado "✅ (reusa guard)" no SDD §6.1 e promete:
//     1. [must][T0] `App\Product` global scope em TODA query
//     2. [T0] Cross-tenant por ID → 404 (não 403)
//   O guard reusado cobre `Product`. A tabela de preço não é `Product`.
// =============================================================================

it('UC-PTAB-04 · price_group de outro business não grava row (multi-tenant Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $bizId = (int) $this->business->id;

    // Produto MEU (passa no findOrFail escopado) + tabela de preço do OUTRO business.
    $meuProduto = EstoqueFixture::singleProduct($bizId);
    $minhaVariation = $meuProduto->variationId();
    $tabelaAlheia = tabelaPrecoAtiva($outroBizId, 'Atacado alheio UC-PTAB-04');

    $this->post('/products/save-selling-prices', [
        'product_id' => $meuProduto->productId,
        'group_prices' => [
            $tabelaAlheia => [
                $minhaVariation => ['price' => '1,00', 'price_type' => 'fixed'],
            ],
        ],
    ]);

    // O INVARIANTE (CU-PROD-10.1 — escopo em TODA query, não só em Product):
    // nenhuma linha pode ligar a minha variação a uma tabela de preço de outro business.
    expect(precoGravado($minhaVariation, $tabelaAlheia))->toBeNull(
        'Gravou (minha variação × price_group de OUTRO business). O price_group_id entra cru do '
        .'request, sem validate/exists escopado, e VariationGroupPrice não tem global scope — '
        .'o "✅ reusa guard" do CU-PROD-10 cobre Product, não a tabela de preço.'
    );
});

// =============================================================================
// UC-PTAB-03 — `[V0]` REGRA MESTRE: o preço da tabela passa pelo num_uf
//   saveSellingPrices: price_inc_tax = productUtil->num_uf($value[...]['price'])
//   Mesmo parser do incidente 2026-06-05 (16 vendas ×100k). Este caminho não tinha teste.
// =============================================================================

it('UC-PTAB-03 · preço pt-BR com milhar e decimal grava o valor certo', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);
    $variationId = $produto->variationId();
    $tabelaId = tabelaPrecoAtiva($bizId, 'Varejo UC-PTAB-03 ptbr');

    $this->post('/products/save-selling-prices', [
        'product_id' => $produto->productId,
        'group_prices' => [
            $tabelaId => [$variationId => ['price' => '1.234,56', 'price_type' => 'fixed']],
        ],
    ])->assertStatus(302);

    expect(precoGravado($variationId, $tabelaId)['price_inc_tax'])
        ->toEqualWithDelta(1234.56, 0.0001, 'Preço pt-BR "1.234,56" não virou 1234.56.');
});

it('UC-PTAB-03 · preço fracionário com ponto NÃO infla ×100k (vetor do incidente num_uf)', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);
    $variationId = $produto->variationId();
    $tabelaId = tabelaPrecoAtiva($bizId, 'Varejo UC-PTAB-03 vetor');

    // "204.99605" — a string exata da classe do incidente 2026-06-05: se o parser ler o "."
    // como separador de milhar, vira 20.499.605. Separador de milhar tem SEMPRE 3 dígitos.
    $this->post('/products/save-selling-prices', [
        'product_id' => $produto->productId,
        'group_prices' => [
            $tabelaId => [$variationId => ['price' => '204.99605', 'price_type' => 'fixed']],
        ],
    ])->assertStatus(302);

    $gravado = precoGravado($variationId, $tabelaId)['price_inc_tax'];

    expect($gravado)->toBeLessThan(
        1000.0,
        "Preço da tabela inflou: gravou {$gravado} a partir de '204.99605' — vetor num_uf do incidente 2026-06-05."
    );
    expect($gravado)->toEqualWithDelta(204.996, 0.01);
});

// =============================================================================
// UC-PTAB-05 — `[V0]` REGRA MESTRE: célula não preenchida da matriz não vende a zero
//
//   ÂNCORA (contrato, NÃO implementação): CU-PROD-03 item 1 + REGRA MESTRE valor/estoque
//   (`proibicoes.md`). O contrato é de DINHEIRO: a venda não pode sair a R$ 0,00 porque o
//   operador deixou uma célula da matriz em branco. Nenhum operador aceitaria isso — é o
//   mesmo invariante do UC-PTAB-03 (preço não pode virar o número errado), no outro sentido.
//
//   POR QUE ESTE CASO EXISTE — o fato que o motiva (verificado, não lido de passagem):
//   a UI pré-preenche célula sem preço com 0 e ENVIA. React `SellingPrices.tsx:73`
//   (`row[v.id] = existing ?? { price: 0, price_type: 'fixed' }`) e Blade
//   `add-selling-prices.blade.php:50` (`... : 0`). E o `saveSellingPrices` GRAVA: o laço
//   filtra por `isset($value[$variation->id])`, não por valor — zero passa. Logo "sem preço
//   nesta tabela" vira, no banco, "row com preço 0". Os dois casos abaixo são as duas formas
//   que esse mesmo estado de negócio assume.
//
//   ⛔ TEST-ONLY: não altera cálculo nenhum. Se ficar vermelho, o vermelho é o achado —
//      a correção é US separada sob REGRA MESTRE (dupla-confirmação + antes→depois + [W]).
//
//   ⚠️ FAILING-FIRST DECLARADO (US-PROD-027, escrito 2026-07-16): a US afirma que o 0-row é
//   inofensivo "por sorte do PHP" — `SellPosController:1792` guarda com
//   `!empty($variation_group_prices['price_inc_tax'])` e `!empty(0)` é `false`. Ao escrever
//   este caso, a premissa NÃO foi verificada e há motivo concreto pra duvidar dela:
//   `variation_group_prices.price_inc_tax` é `decimal(22,4)` e `App\VariationGroupPrice` não
//   declara `$casts` — PDO/MySQL devolve DECIMAL como STRING pra preservar precisão. Em PHP
//   só `"0"` e `""` são strings falsy: `empty("0.0000")` é `false`. Se for esse o caso, o
//   guard NÃO segura e a venda sai a zero hoje, em produção.
//   Este teste não decide isso lendo — ele assere o CONTRATO e deixa a lane responder.
//   Escrevê-lo ancorado no comportamento observado o faria nascer verde documentando o bug
//   como se fosse regra (a tautologia de `proibicoes.md` §5, entrada 2026-06-05).
// =============================================================================

it('UC-PTAB-05 · célula da matriz salva com 0 não faz a venda sair a zero', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId); // default_sell_price = 20
    $variationId = $produto->variationId();
    $locationId = EstoqueFixture::locationId($bizId);
    $tabelaId = tabelaPrecoAtiva($bizId, 'Atacado UC-PTAB-05 zero');

    // O caminho REAL: salvar a tela com a célula como a UI a manda quando ninguém digitou nada.
    $this->post('/products/save-selling-prices', [
        'product_id' => $produto->productId,
        'group_prices' => [
            $tabelaId => [$variationId => ['price' => '0', 'price_type' => 'fixed']],
        ],
    ])->assertStatus(302);

    // Pré-condição do caso: a row zerada existe mesmo (senão o teste não prova nada).
    $gravado = precoGravado($variationId, $tabelaId);
    expect($gravado)->not->toBeNull('A UI manda 0 e o controller grava — se sumiu, a premissa do caso mudou.');
    expect($gravado['price_inc_tax'])->toEqualWithDelta(0.0, 0.0001);

    // O PDV monta a linha da venda aplicando essa tabela.
    $response = $this->get("/sells/pos/get_product_row/{$variationId}/{$locationId}?price_group={$tabelaId}");
    $response->assertOk();

    $preco = precoUnitarioNoPdv((string) $response->json('html_content'));
    expect($preco)->not->toBeNull('Não achei o preço unitário no row do POS — a view mudou de forma.');

    // O INVARIANTE (REGRA MESTRE): célula em branco = "esta tabela não define preço" =
    // usa o preço padrão da variação. Nunca R$ 0,00.
    expect($preco)->toEqualWithDelta(20.0, 0.0001,
        "A venda saiu a {$preco} aplicando uma tabela cuja célula a UI preencheu com 0. "
        .'Célula em branco na matriz NÃO é preço zero — é ausência de preço, e ausência cai no '
        .'padrão da variação (R$ 20,00). Ver US-PROD-027 + REGRA MESTRE valor/estoque.'
    );
});

it('UC-PTAB-05 · tabela sem row pra variação usa o preço padrão (caso normal)', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId); // default_sell_price = 20
    $variationId = $produto->variationId();
    $locationId = EstoqueFixture::locationId($bizId);
    $tabelaId = tabelaPrecoAtiva($bizId, 'Atacado UC-PTAB-05 sem row');

    // Nada é salvo: a tabela existe, mas não define preço pra esta variação.
    expect(precoGravado($variationId, $tabelaId))->toBeNull();

    $response = $this->get("/sells/pos/get_product_row/{$variationId}/{$locationId}?price_group={$tabelaId}");
    $response->assertOk();

    $preco = precoUnitarioNoPdv((string) $response->json('html_content'));
    expect($preco)->not->toBeNull('Não achei o preço unitário no row do POS — a view mudou de forma.');

    // Este é o caso NORMAL (produto sem preço naquela tabela) — e é o mesmo retorno (`''`) que
    // LabelsController:143 e WoocommerceUtil:341,731 consomem SEM guardar. Aqui só o PDV está
    // sob contrato; os outros 3 consumidores não têm CU (§Pendência de CONTRATO do casos.md).
    expect($preco)->toEqualWithDelta(20.0, 0.0001,
        "A venda saiu a {$preco} aplicando uma tabela que não define preço pra esta variação. "
        .'Sem row = sem preço na tabela = preço padrão da variação (R$ 20,00).'
    );
});
