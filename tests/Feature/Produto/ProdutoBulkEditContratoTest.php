<?php

declare(strict_types=1);
// Cobre UC-PBULK-01, UC-PBULK-02, UC-PBULK-03, UC-PBULK-04, UC-PBULK-05, UC-PBULK-06
// (resources/js/Pages/Produto/BulkEdit.casos.md) — G-2 rastreabilidade caso↔teste (ADR 0264).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;
use Tests\Support\EstoqueProduto;

/**
 * Contrato de comportamento da EDIÇÃO EM MASSA (`POST /products/bulk-edit` → `Produto/BulkEdit`
 * e `POST /products/bulk-update` → writer).
 *
 * ÂNCORA (contrato, NÃO implementação) — triangulada nas 3 fontes (ADR 0351):
 *   1. CANON  — `CU-PROD-06` (importação Excel + bulk-edit + mass-ops) e `CU-PROD-10` `[T0]` do
 *               SDD §6.1 (memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) +
 *               `BulkEdit.charter.md` (§Goals "Multi-tenant: business_id scope nas queries") +
 *               `_telas/RUNBOOK-produto-bulk-edit.md` §1 ("business_id isola" · "sem permissão → 403").
 *   2. BLADE  — `resources/views/product/bulk-edit.blade.php` + partials `bulk_edit_product_row` /
 *               `bulk_edit_variation_row` (o OUTRO branch do MESMO método `bulkEdit`), que é quem
 *               define o payload REAL que o writer `bulkUpdate` consome: 5 campos numéricos por
 *               variação (`default_purchase_price` · `dpp_inc_tax` · `profit_percent` ·
 *               `default_sell_price` · `sell_price_inc_tax`) + `group_prices[price_group_id]`.
 *   3. DELPHI — `AR-PROD-006`/`AR-PROD-007`/`AR-PROD-008` `[V0]` (Custo é âncora; Margem% =
 *               (Valor/Custo − 1) × 100; parser pt-BR sem inflar ×100) da
 *               ANTI-REGRESSAO-cadastro-produto-legacy.md (Office Comercial 2026.1.1.38).
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * A tela tinha charter desde 2026-05-15 e ZERO teste de comportamento: os dois que existem
 * (`Wave2BulkEditBaselineTest` / `Wave2BulkEditInertiaTest`) só fazem grep de string no fonte
 * ("contém `Inertia::render('Produto/BulkEdit'`", "importa AppShellV2") — não exercitam request
 * nenhuma. E o charter §Pest GUARD PROMETE `it('Controller cross-tenant não inclui produtos
 * biz=99')`, teste que nunca existiu (varredura contada nos 2 arquivos: 0 ocorrências de
 * cross-tenant/biz). Buraco Tier 0 documentado como se estivesse coberto — o mesmo padrão que
 * o `TabelaPrecoContratoTest` desmentiu no `SellingPrices` (#4300).
 *
 * ⚠️ Failing-first (proibicoes §5, 2026-06-05): os asserts saem do contrato (CU/AR/Blade/charter),
 *    NÃO do `BulkEdit.tsx` nem do `bulkUpdate()`. Se nascer vermelho, o vermelho É o achado —
 *    não se ajusta o teste ao código. Eixo VALOR → o fix é decisão [W] sob a REGRA MESTRE
 *    (2 caminhos + tabela antes→depois), nunca conserto silencioso aqui.
 *
 * ⚠️ ANTI-VÁCUO (proibicoes §5, 2026-07-24): todo caso que afirma "X não aconteceu" carrega
 *    pré-condição provando que a OPERAÇÃO aconteceu (linha legítima gravada / fase 1 persistiu).
 *    Sem isso o teste mede não-execução e chama de contrato cumprido — foi assim que o
 *    `ProdutoEditPayloadContratoTest` quase passou verde no vácuo.
 *
 * ⛔ Escopo honesto (3 fatos medidos em 2026-07-26, sha 6cd0fbc4f2):
 *    (a) o botão que leva a esta tela está atrás de `config('constants.enable_product_bulk_edit')`,
 *        hardcoded **`false`** em `config/constants.php:84` com a nota upstream "Will be depreciated
 *        in future" → hoje NENHUM operador chega aqui pela UI;
 *    (b) a `BulkEdit.tsx` submete pra `/products/mass-update`, rota que **não existe** (varredura
 *        contada: 3 ocorrências do literal em todo o repo — o .tsx, o charter e o RUNBOOK; 0 em
 *        `routes/`). O writer real é `POST /products/bulk-update`;
 *    (c) as telas React do Produto seguem inalcançáveis em prod (sidebar usa `<a href>` puro, sem
 *        `X-Inertia` → cai no Blade).
 *    Logo: vermelho aqui é **bloqueador de migração** (gate MWART F5, ADR 0104) e defeito de
 *    endpoint vivo — não incidente de produção em curso.
 *
 * ⛔ Multi-tenant Tier 0 (ADR 0101): biz=1 canônico; cross-tenant contra o 2º business seedado.
 *    NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

/** Version que o servidor usa pro check Inertia (mesmo helper dos irmãos Show/StockHistory). */
function produtoBulkInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Cria uma tabela de preço (SellingPriceGroup) ativa no business. */
function bulkTabelaPrecoAtiva(int $businessId, string $nome): int
{
    return (int) DB::table('selling_price_groups')->insertGetId([
        'name' => $nome,
        'business_id' => $businessId,
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Preço gravado para o par (variação × tabela), ou null se não persistiu. */
function bulkPrecoGravado(int $variationId, int $priceGroupId): ?float
{
    $row = DB::table('variation_group_prices')
        ->where('variation_id', $variationId)
        ->where('price_group_id', $priceGroupId)
        ->first();

    return $row ? (float) $row->price_inc_tax : null;
}

/**
 * Bloco de UMA variação no formato que a **Blade legada** envia (o caller real do writer):
 * os 5 campos numéricos como TEXTO pt-BR (`@num_format`), mais `group_prices` opcional.
 * É o INPUT do contrato, não o assert.
 */
function bulkVariacaoBlade(array $over = []): array
{
    return array_merge([
        'default_purchase_price' => '10,00',
        'dpp_inc_tax' => '10,00',
        'profit_percent' => '0,00',
        'default_sell_price' => '20,00',
        'sell_price_inc_tax' => '20,00',
    ], $over);
}

/** Bloco de UM produto no formato da Blade legada. */
function bulkProdutoBlade(EstoqueProduto $p, array $variacoes, ?int $locationId = null): array
{
    return [
        'category_id' => null,
        'sub_category_id' => null,
        'brand_id' => null,
        'tax' => null,
        'product_locations' => $locationId ? [$locationId] : [],
        'variations' => $variacoes,
    ];
}

/** Lê o estado numérico corrente de uma variação (fonte-de-verdade dos asserts de valor). */
function bulkVariacaoDb(int $variationId): object
{
    return DB::table('variations')->where('id', $variationId)->first();
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

    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'user.id' => $this->user->id,
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.update', 'web');
    $this->user->givePermissionTo(['product.update']);
});

// =============================================================================
// UC-PBULK-01 — Blade `bulk_edit_variation_row` (renderiza `default_sell_price` E
//   `sell_price_inc_tax` com o VALOR do banco) + AR-PROD-008 `[V0]` (R$ Valor é o campo
//   editável destacado): a tela precisa OFERECER o preço de venda corrente pra edição.
// =============================================================================

it('UC-PBULK-01 · a matriz entrega o preço de venda corrente da variação', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::variableProduct($bizId, 1);
    $variationId = (int) $produto->variations[0]['variation_id'];

    // SENTINELAS: valores improváveis (o fixture grava 10/20, que colidem com id/qty/etc).
    // Semeio as DUAS colunas de venda — a líquida e a com imposto — porque o contrato é
    // "algum preço de venda real chega", e não quero escolher a base (exc vs inc) por mim:
    // essa escolha é decisão [W] (mesma pendência aberta em Show.casos.md §UC-PSHOW-04).
    $custo = 137.77;
    $vendaLiquida = 233.11;
    $vendaComImposto = 256.42;

    DB::table('variations')->where('id', $variationId)->update([
        'default_purchase_price' => $custo,
        'dpp_inc_tax' => 151.55,
        'default_sell_price' => $vendaLiquida,
        'sell_price_inc_tax' => $vendaComImposto,
    ]);

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => produtoBulkInertiaVersion(),
    ])->post('/products/bulk-edit', ['selected_products' => (string) $produto->productId]);

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a matriz chegou COM a variação semeada. Sem isto, "não tem
    // preço de venda" seria verdade só porque não veio linha nenhuma — verde por não-execução.
    expect($page['props'] ?? [])->toHaveKey('products');
    expect($page['props']['products'])->toBeArray();
    expect(count($page['props']['products']))->toBeGreaterThanOrEqual(1);
    $linha = $page['props']['products'][0];
    expect($linha['variations'] ?? [])->toBeArray();
    expect(count($linha['variations']))->toBeGreaterThanOrEqual(1);

    // O CONTRATO: o preço de venda corrente viaja pra tela. Desacoplado da CHAVE (lição
    // 2026-07-26): asserir `toHaveKey('defaultSellPrice')` passaria mesmo com o campo valendo
    // ZERO, e reprovaria um fix legítimo que renomeasse a chave. O contrato é "o preço aparece".
    $valores = [];
    foreach ($linha['variations'] as $v) {
        foreach ((array) $v as $campo) {
            if (is_numeric($campo)) {
                $valores[] = round((float) $campo, 2);
            }
        }
    }

    $temPrecoDeVenda = in_array(round($vendaLiquida, 2), $valores, true)
        || in_array(round($vendaComImposto, 2), $valores, true);

    expect($temPrecoDeVenda)->toBeTrue(
        'Nenhum campo da variação carrega o preço de venda corrente (' . $vendaLiquida . ' nem '
        . $vendaComImposto . '). A tela de edição em massa oferece pra editar um preço que não é o '
        . 'do produto — e o writer grava o que a tela devolver. Blade renderiza os dois campos com '
        . 'o valor do banco; Delphi destaca o R$ Valor (AR-PROD-008).'
    );
});

// =============================================================================
// UC-PBULK-02 — CU-PROD-06.4 `[T0]` ("Bulk valida business_id de CADA ID") +
//   RUNBOOK §1 ("business_id isola: produtos cross-tenant não aparecem") +
//   charter §Goals ("Multi-tenant: business_id scope nas queries").
//   É o `it('Controller cross-tenant não inclui produtos biz=99')` que o charter
//   §Pest GUARD promete e que nunca existiu.
// =============================================================================

it('UC-PBULK-02 · produto de outro business não entra na matriz (multi-tenant Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $bizId = (int) $this->business->id;
    $meu = EstoqueFixture::singleProduct($bizId);
    $alheio = EstoqueFixture::singleProduct($outroBizId);

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => produtoBulkInertiaVersion(),
    ])->post('/products/bulk-edit', [
        'selected_products' => $meu->productId . ',' . $alheio->productId,
    ]);

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    $ids = array_map(static fn ($p) => (int) $p['id'], $page['props']['products'] ?? []);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: o MEU produto entrou. Sem isto, "o alheio não veio" seria
    // verdade só porque a matriz voltou vazia (ex.: parâmetro ignorado) — vácuo verde.
    expect($ids)->toContain($meu->productId);

    // O CONTRATO: o produto do outro tenant não vaza — nem como linha editável, nem como
    // atalho pra escrever nele depois (o `bulk-update` grava o que a matriz listar).
    expect(in_array($alheio->productId, $ids, true))->toBeFalse(
        'Produto de OUTRO business entrou na matriz de edição em massa (id ' . $alheio->productId . ').'
    );
});

// =============================================================================
// UC-PBULK-03 — CU-PROD-10.1 `[T0]`: o MESMO buraco que o `UC-PTAB-04` provou vermelho em
//   `saveSellingPrices` (#4300) — `price_group_id` cru da chave do array do request, e o
//   `VariationGroupPrice` não tem global scope próprio (`$guarded = ['id']`).
//   O `saveSellingPrices` ganhou o guard `$allowedPriceGroupIds`; o `bulkUpdate` NÃO.
//   O SDD já avisa: "o próximo model pendurado em Product nasce com o mesmo buraco".
// =============================================================================

it('UC-PBULK-03 · tabela de preço de outro business não grava row no bulk-update (Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $bizId = (int) $this->business->id;
    $meuProduto = EstoqueFixture::variableProduct($bizId, 1);
    $variationId = (int) $meuProduto->variations[0]['variation_id'];

    $minhaTabela = bulkTabelaPrecoAtiva($bizId, 'Varejo UC-PBULK-03');
    $tabelaAlheia = bulkTabelaPrecoAtiva($outroBizId, 'Atacado alheio UC-PBULK-03');

    $this->post('/products/bulk-update', [
        'products' => [
            $meuProduto->productId => bulkProdutoBlade($meuProduto, [
                $variationId => bulkVariacaoBlade([
                    'group_prices' => [
                        $minhaTabela => '11,00',
                        $tabelaAlheia => '99,00',
                    ],
                ]),
            ]),
        ],
    ]);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a linha da MINHA tabela existe → o laço de `group_prices`
    // REALMENTE rodou. Sem isto, "a alheia não gravou" poderia ser só o request abortando
    // antes (o `bulkUpdate` engole toda exceção num `catch` genérico + rollback).
    expect(bulkPrecoGravado($variationId, $minhaTabela))->not->toBeNull(
        'PRÉ-CONDIÇÃO do UC-PBULK-03: nem o preço da MINHA tabela persistiu — o writer abortou '
        . 'antes do laço de group_prices, então este UC NÃO foi exercido (verde seria vácuo).'
    );

    // O CONTRATO: a tabela do outro tenant não recebe linha. Tier 0 — ADR 0093.
    expect(bulkPrecoGravado($variationId, $tabelaAlheia))->toBeNull(
        'Gravou preço da MINHA variação numa tabela de preço de OUTRO business (price_group_id '
        . $tabelaAlheia . '). É o mesmo defeito do UC-PTAB-04 (#4300), que em saveSellingPrices '
        . 'foi fechado com $allowedPriceGroupIds e aqui segue aberto.'
    );
});

// =============================================================================
// UC-PBULK-04 — CU-PROD-06.4 `[T0]` ("valida business_id de CADA ID antes de aplicar"):
//   um lote que MISTURA produto meu com produto alheio não pode deixar meia-edição gravada.
// =============================================================================

it('UC-PBULK-04 · lote com produto de outro business não persiste nada (rollback total)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $bizId = (int) $this->business->id;
    $meu = EstoqueFixture::variableProduct($bizId, 1);
    $meuVariationId = (int) $meu->variations[0]['variation_id'];

    $alheio = EstoqueFixture::variableProduct($outroBizId, 1);
    $alheioVariationId = (int) $alheio->variations[0]['variation_id'];

    // FASE 1 (pré-condição anti-vácuo): o MESMO payload, só com o meu produto, PERSISTE.
    // Prova que o caminho de escrita funciona — senão o "nada mudou" da fase 2 não significa nada.
    $this->post('/products/bulk-update', [
        'products' => [
            $meu->productId => bulkProdutoBlade($meu, [
                $meuVariationId => bulkVariacaoBlade(['default_purchase_price' => '55,55']),
            ]),
        ],
    ]);

    expect((float) bulkVariacaoDb($meuVariationId)->default_purchase_price)->toEqualWithDelta(
        55.55,
        0.001,
        'PRÉ-CONDIÇÃO do UC-PBULK-04: o lote só-com-produto-meu não persistiu — o writer está '
        . 'abortando por outro motivo e a fase 2 mediria vácuo.'
    );

    // FASE 2: lote MISTO — meu produto (novo valor) + produto de outro business.
    $this->post('/products/bulk-update', [
        'products' => [
            $meu->productId => bulkProdutoBlade($meu, [
                $meuVariationId => bulkVariacaoBlade(['default_purchase_price' => '66,66']),
            ]),
            $alheio->productId => bulkProdutoBlade($alheio, [
                $alheioVariationId => bulkVariacaoBlade(['default_purchase_price' => '77,77']),
            ]),
        ],
    ]);

    // O CONTRATO (duas pernas):
    // (a) o produto alheio NÃO foi tocado;
    expect((float) bulkVariacaoDb($alheioVariationId)->default_purchase_price)->not->toEqualWithDelta(
        77.77,
        0.001,
        'Um lote submetido pelo business A gravou preço em variação do business B.'
    );
    // (b) e a MINHA metade também não ficou meio-aplicada (a transação cobre o lote inteiro).
    expect((float) bulkVariacaoDb($meuVariationId)->default_purchase_price)->toEqualWithDelta(
        55.55,
        0.001,
        'O lote misto deixou meia-edição gravada: a transação não cobre o lote inteiro, então o '
        . 'operador fica com parte dos produtos alterados e uma mensagem de erro genérica.'
    );
});

// =============================================================================
// UC-PBULK-05 — charter §Goals ("Submit … atualiza os N produtos") + Blade (o payload
//   completo do caller real): o que a tela React edita tem que CHEGAR gravado.
//   A tela manda 2 campos por variação; o writer lê 5 (`dpp_inc_tax`, `profit_percent`,
//   `sell_price_inc_tax` sem `??`) — ausência vira erro engolido OU zero silencioso.
//   Os dois desfechos violam a REGRA MESTRE (valor mudando sem ninguém ver).
// =============================================================================

it('UC-PBULK-05 · o payload que a tela React edita persiste sem zerar o resto', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::variableProduct($bizId, 1);
    $variationId = (int) $produto->variations[0]['variation_id'];
    $locationId = EstoqueFixture::locationId($bizId);

    // Estado de partida conhecido (INSERT direto — não pelo mutador sob teste).
    DB::table('variations')->where('id', $variationId)->update([
        'default_purchase_price' => 10,
        'dpp_inc_tax' => 12,
        'default_sell_price' => 20,
        'sell_price_inc_tax' => 24,
    ]);

    // PAYLOAD EXATO do `useForm` de `BulkEdit.tsx` (números JS crus, 2 campos por variação).
    $this->post('/products/bulk-update', [
        'products' => [
            $produto->productId => [
                'category_id' => null,
                'sub_category_id' => null,
                'brand_id' => null,
                'tax' => null,
                'product_locations' => [$locationId],
                'variations' => [
                    $variationId => [
                        'default_purchase_price' => 137.77,
                        'default_sell_price' => 233.11,
                    ],
                ],
            ],
        ],
    ]);

    $depois = bulkVariacaoDb($variationId);

    // CONTRATO (a): a edição do operador chegou ao banco.
    expect((float) $depois->default_purchase_price)->toEqualWithDelta(
        137.77,
        0.001,
        'A edição da tela React não persistiu. O writer lê 5 chaves por variação e a tela manda 2 — '
        . 'a ausência vira ErrorException, o `catch (\Exception)` genérico a engole e o operador '
        . 'recebe "algo deu errado" com o lote inteiro revertido.'
    );

    // CONTRATO (b): e não zerou em silêncio o que a tela não edita. Custo COM imposto nunca
    // pode ficar MENOR que o custo líquido — se ficar, a ausência da chave virou zero
    // (o remédio "usa `?? 0`" trocaria um defeito barulhento por um silencioso, que é pior:
    // REGRA MESTRE, valor mudando sem ninguém ver).
    expect((float) $depois->dpp_inc_tax)->toBeGreaterThanOrEqual(
        (float) $depois->default_purchase_price,
        'O custo com imposto ficou MENOR que o custo líquido — sinal de que a chave ausente no '
        . 'payload da tela foi persistida como zero.'
    );
});

// =============================================================================
// UC-PBULK-06 — CU-PROD-06.3 `[V0]` ("preço/custo passa pelo mesmo guard `num_uf`") +
//   AR-PROD-006/008 `[V0]` (parser pt-BR sem inflar ×100) + REGRA MESTRE (incidente
//   2026-06-05, `num_uf` strippando o ponto decimal).
//   A Blade manda TEXTO formatado (`@num_format` → "1.234,56"); o writer tem que parsear.
// =============================================================================

it('UC-PBULK-06 · preço em pt-BR ("1.234,56") persiste 1234.56 — sem inflar (V0)', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::variableProduct($bizId, 1);
    $variationId = (int) $produto->variations[0]['variation_id'];

    $this->post('/products/bulk-update', [
        'products' => [
            $produto->productId => bulkProdutoBlade($produto, [
                $variationId => bulkVariacaoBlade([
                    'default_purchase_price' => '1.234,56',
                    'dpp_inc_tax' => '1.234,56',
                    'default_sell_price' => '2.000,00',
                    'sell_price_inc_tax' => '2.000,00',
                ]),
            ]),
        ],
    ]);

    $depois = bulkVariacaoDb($variationId);

    // O CONTRATO: milhar pt-BR não vira ×100 (o incidente que gerou a REGRA MESTRE) e o
    // decimal não é engolido. Vale pros dois lados do par custo/venda.
    expect((float) $depois->default_purchase_price)->toEqualWithDelta(
        1234.56,
        0.001,
        'Custo em pt-BR não fez round-trip pelo `num_uf` na edição em massa (REGRA MESTRE).'
    );
    expect((float) $depois->default_sell_price)->toEqualWithDelta(
        2000.00,
        0.001,
        'Preço de venda em pt-BR não fez round-trip pelo `num_uf` na edição em massa (REGRA MESTRE).'
    );
});
