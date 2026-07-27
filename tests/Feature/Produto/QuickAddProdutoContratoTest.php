<?php

declare(strict_types=1);
// Cobre UC-PQCK-01, UC-PQCK-02, UC-PQCK-03, UC-PQCK-04
// (memory/requisitos/Produto/_telas/quick-add.casos.md) — rastreabilidade caso↔teste.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de comportamento do CADASTRO RÁPIDO INLINE
 * (`GET /products/quick_add` → modal; `POST /products/save_quick_product` → writer).
 *
 * ⚠️ FLUXO SEM TELA REACT, E CHAMADO DE OUTROS MÓDULOS. Varredura contada (2026-07-27,
 *    sha 16606e35c4): o botão que abre este modal aparece em **10 Blades** —
 *    `purchase/create` · `purchase/edit` · `purchase_order/create` · `purchase_order/edit` ·
 *    `sale_pos/create_old` · `sale_pos/edit_old` · `sale_pos/partials/pos_form` ·
 *    `sale_pos/partials/pos_form_edit` · `sell/create` · `sell/edit` — mais 1 chamada
 *    JS direta (`public/js/purchase.js:191`, `/products/quick_add?product_name=`).
 *    Em React: **0** consumidores (o `quickAdd` que existe em `Sells/` é de CLIENTE e de
 *    VEÍCULO, não de produto). Ou seja: quem cria produto no meio de uma venda ou de uma
 *    compra passa por AQUI, e este caminho nunca teve teste.
 *
 * ÂNCORA (contrato, NÃO implementação) — triangulada nas 3 fontes (ADR 0351):
 *   1. CANON  — `CU-PROD-08` (quick-add inline), `CU-PROD-01` (cadastro simples: SKU
 *               server-side + parser pt-BR) e `CU-PROD-10` `[T0]` do SDD §6.1.
 *   2. BLADE  — `resources/views/product/partials/quick_add_product.blade.php` (o formulário
 *               real: `name` obrigatório, `sku` opcional, `unit_id`/`barcode_type`/`tax_type`
 *               obrigatórios, `category_id`/`brand_id`/`tax` opcionais vindos de dropdowns
 *               JÁ ESCOPADOS ao business) + `single_product_form_part` (`single_dpp`/`single_dsp`).
 *   3. DELPHI — `AR-PROD-008` `[V0]` (parser pt-BR sem inflar ×100) e `AR-PROD-006` `[V0]`
 *               (precisão do custo) da ANTI-REGRESSAO-cadastro-produto-legacy.md.
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * `CU-PROD-08` estava ✅ no SDD e sem **nenhum** UC (lacuna do painel). É o caminho de
 * cadastro com MENOS validação do módulo (o `saveQuickProduct` monta o payload com
 * `$request->only($form_fields)` e joga direto no `Product::create`), e é o que roda no
 * meio de uma venda — onde o operador está com pressa e o cliente na frente.
 *
 * ⚠️ Failing-first (proibicoes §5, 2026-06-05): os asserts saem do contrato (CU/AR/Blade),
 *    NÃO do `saveQuickProduct()`. Eixo VALOR → o fix é decisão [W] sob a REGRA MESTRE.
 *
 * ⚠️ ANTI-VÁCUO (proibicoes §5, 2026-07-24): o produto criado é sempre relido do banco e o
 *    caso confirma que a criação ACONTECEU antes de afirmar o que ela não fez.
 *
 * ⛔ Multi-tenant Tier 0 (ADR 0101): biz=1 canônico; cross-tenant contra o 2º business
 *    seedado. NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

/**
 * Payload MÍNIMO do `quick_add_product_form` (a Blade real). É o INPUT do contrato:
 * números como TEXTO pt-BR, como o `input_number` da tela os entrega.
 */
function quickAddPayload(int $unitId, array $over = []): array
{
    return array_merge([
        'name' => 'Quick UC ' . strtoupper(bin2hex(random_bytes(4))),
        'unit_id' => $unitId,
        'barcode_type' => 'C128',
        'tax_type' => 'exclusive',
        'type' => 'single',
        'sku' => '',
        'enable_stock' => 1,
        'single_dpp' => '10,00',
        'single_dpp_inc_tax' => '10,00',
        'profit_percent' => '0,00',
        'single_dsp' => '20,00',
        'single_dsp_inc_tax' => '20,00',
    ], $over);
}

/** Produto recém-criado pelo nome sentinela (leitura CRUA — sem global scope). */
function quickAddProdutoPorNome(string $nome): ?object
{
    return DB::table('products')->where('name', $nome)->first();
}

/** Categoria válida de um business (usada pra montar o vetor cross-tenant). */
function quickAddCategoria(int $businessId, int $createdBy): int
{
    return (int) DB::table('categories')->insertGetId([
        'name' => 'CAT-UC-PQCK-' . strtoupper(bin2hex(random_bytes(3))),
        'business_id' => $businessId,
        'parent_id' => 0,
        'created_by' => $createdBy,
        'category_type' => 'product',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
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
    Permission::findOrCreate('product.create', 'web');
    $this->user->givePermissionTo(['product.create']);
});

// =============================================================================
// UC-PQCK-01 — CU-PROD-08.3 `[T0]` ("produto criado no business atual") + ADR 0093.
//   O quick-add roda dentro da venda/compra, onde o operador NÃO escolhe o tenant:
//   quem carimba é a sessão. Este UC trava o carimbo — e, junto, prova que o produto
//   nasce ALCANÇÁVEL (com variação), porque produto sem variação não entra na venda que
//   o originou e o operador fica sem entender por quê.
// =============================================================================

it('UC-PQCK-01 · quick-add carimba o business da sessão e cria a variação vendável', function () {
    $bizId = (int) $this->business->id;
    $unitId = EstoqueFixture::unitId($bizId);
    $payload = quickAddPayload($unitId);

    $this->post('/products/save_quick_product', $payload);

    $produto = quickAddProdutoPorNome($payload['name']);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: o produto EXISTE. Sem isto, "não vazou tenant" seria
    // verdade só porque nada foi criado (o writer engole toda exceção num `catch` genérico
    // e devolve `success: 0` com HTTP 200).
    expect($produto)->not->toBeNull(
        'O quick-add não criou o produto — os asserts seguintes mediriam vácuo.'
    );

    // O CONTRATO (a): o tenant vem da sessão, nunca do request.
    expect((int) $produto->business_id)->toBe(
        $bizId,
        'Produto criado pelo quick-add não carimbou o business da sessão.'
    );

    // O CONTRATO (b): nasce com variação — senão não é selecionável na venda de origem.
    expect(DB::table('variations')->where('product_id', $produto->id)->count())->toBeGreaterThanOrEqual(
        1,
        'Produto do quick-add nasceu SEM variação: o operador cria no meio da venda e não '
        . 'consegue adicioná-lo à venda que motivou o cadastro (CU-PROD-08.2 — não perder o fluxo).'
    );
});

// =============================================================================
// UC-PQCK-02 — CU-PROD-10.1 `[T0]`: MESMA família do `UC-PTAB-04` (#4300, `price_group_id`),
//   do `UC-PBULK-03` (`bulkUpdate`) e do `UC-PBOM-02` (`component_variation_id`) —
//   id que chega CRU do request e é gravado sem checar de quem é.
//   Aqui o writer monta o payload com `$request->only($form_fields)` e joga em
//   `Product::create` — `category_id`, `brand_id`, `unit_id` e `tax` entram sem
//   nenhuma consulta de tenant. Os dropdowns da Blade JÁ são escopados
//   (`Category::forDropdown($business_id)`), então o contrato de UI existe; o que falta
//   é o servidor não confiar no que a UI mandou.
// =============================================================================

it('UC-PQCK-02 · categoria de outro business não é aceita no quick-add (Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $bizId = (int) $this->business->id;
    $unitId = EstoqueFixture::unitId($bizId);
    $usuarioAlheio = EstoqueFixture::userId($outroBizId);
    $categoriaAlheia = quickAddCategoria($outroBizId, $usuarioAlheio);

    // FASE 1 (pré-condição anti-vácuo): com categoria LEGÍTIMA o cadastro persiste a
    // categoria. Prova que o campo chega ao banco — senão a fase 2 seria vácuo.
    $categoriaMinha = quickAddCategoria($bizId, (int) $this->user->id);
    $legitimo = quickAddPayload($unitId, ['category_id' => $categoriaMinha]);
    $this->post('/products/save_quick_product', $legitimo);

    $criado = quickAddProdutoPorNome($legitimo['name']);
    expect($criado)->not->toBeNull('PRÉ-CONDIÇÃO do UC-PQCK-02: o cadastro legítimo falhou.');
    expect((int) $criado->category_id)->toBe(
        $categoriaMinha,
        'PRÉ-CONDIÇÃO do UC-PQCK-02: a categoria legítima não persistiu — o campo nem chega ao '
        . 'banco, então a fase 2 mediria vácuo.'
    );

    // FASE 2: mesma requisição, categoria de OUTRO business.
    $vetor = quickAddPayload($unitId, ['category_id' => $categoriaAlheia]);
    $this->post('/products/save_quick_product', $vetor);

    $produto = quickAddProdutoPorNome($vetor['name']);

    // O CONTRATO: o produto do meu catálogo não fica classificado por taxonomia alheia.
    // (rejeitar a requisição OU ignorar o campo são dois remédios legítimos — o assert
    // não escolhe entre eles, só nega o vazamento.)
    $classificouAlheio = $produto !== null && (int) $produto->category_id === $categoriaAlheia;

    expect($classificouAlheio)->toBeFalse(
        'O quick-add gravou meu produto na categoria de OUTRO business (category_id '
        . $categoriaAlheia . '). `$request->only($form_fields)` → `Product::create` sem '
        . 'validação de tenant: mesma família do UC-PTAB-04 (#4300), UC-PBULK-03 e UC-PBOM-02.'
    );
});

// =============================================================================
// UC-PQCK-03 — CU-PROD-01.4 `[V0]` ("preço de custo e venda passam pelo parser pt-BR sem
//   ×100") + `AR-PROD-006`/`AR-PROD-008` `[V0]` + REGRA MESTRE (proibicoes.md Tier 0 —
//   origem: incidente 2026-06-05, `num_uf` strippando o ponto decimal e inflando venda
//   ~×100k em biz=4). O quick-add é o pior lugar pra errar isto: o preço digitado às
//   pressas vira o preço da venda que está aberta na tela.
// =============================================================================

it('UC-PQCK-03 · preço em pt-BR ("1.234,56") persiste 1234.56 no quick-add (V0)', function () {
    $bizId = (int) $this->business->id;
    $unitId = EstoqueFixture::unitId($bizId);

    $payload = quickAddPayload($unitId, [
        'single_dpp' => '999,99',
        'single_dpp_inc_tax' => '999,99',
        'single_dsp' => '1.234,56',
        'single_dsp_inc_tax' => '1.234,56',
    ]);

    $this->post('/products/save_quick_product', $payload);

    $produto = quickAddProdutoPorNome($payload['name']);
    expect($produto)->not->toBeNull('PRÉ-CONDIÇÃO do UC-PQCK-03: o quick-add não criou o produto.');

    $variacao = DB::table('variations')->where('product_id', $produto->id)->first();
    expect($variacao)->not->toBeNull('PRÉ-CONDIÇÃO do UC-PQCK-03: produto sem variação — sem preço pra medir.');

    // O CONTRATO: milhar pt-BR não vira ×100 e o decimal não é engolido, nos DOIS lados
    // do par custo/venda.
    expect((float) $variacao->default_sell_price)->toEqualWithDelta(
        1234.56,
        0.001,
        'Preço de venda "1.234,56" digitado no quick-add não fez round-trip pelo `num_uf` '
        . '(REGRA MESTRE — foi assim que uma venda de biz=4 virou ~100k em 2026-06-05).'
    );
    expect((float) $variacao->default_purchase_price)->toEqualWithDelta(
        999.99,
        0.001,
        'Custo "999,99" digitado no quick-add não fez round-trip pelo `num_uf` (AR-PROD-006).'
    );
});

// =============================================================================
// UC-PQCK-04 — CU-PROD-08.1 ("cadastra mínimo: nome+SKU+preço") + CU-PROD-01.2
//   ("SKU vazio → gerado server-side"). O modal do quick-add deixa o SKU em branco por
//   desenho (a Blade não o marca `required`), então gerar é o caminho NORMAL, não a
//   exceção. SKU em branco quebra etiqueta, leitura de código de barras e busca por
//   código — e o produto acabou de entrar numa venda.
// =============================================================================

it('UC-PQCK-04 · SKU vazio no quick-add é gerado pelo servidor (não fica em branco)', function () {
    $bizId = (int) $this->business->id;
    $unitId = EstoqueFixture::unitId($bizId);
    $payload = quickAddPayload($unitId, ['sku' => '']);

    $this->post('/products/save_quick_product', $payload);

    $produto = quickAddProdutoPorNome($payload['name']);
    expect($produto)->not->toBeNull('PRÉ-CONDIÇÃO do UC-PQCK-04: o quick-add não criou o produto.');

    // O CONTRATO (a): o produto tem SKU utilizável — não vazio, não só espaço.
    expect(trim((string) $produto->sku))->not->toBe(
        '',
        'Produto criado pelo quick-add ficou com SKU em branco. O writer grava `sku = " "` '
        . '(espaço) como marcador e só depois gera — se a geração não acontecer, o produto '
        . 'entra na venda sem código (CU-PROD-01.2).'
    );

    // O CONTRATO (b): a variação criada herda o MESMO código — senão a etiqueta da
    // variação e a busca por `sub_sku` apontam pra strings diferentes.
    $variacao = DB::table('variations')->where('product_id', $produto->id)->first();
    expect($variacao)->not->toBeNull('PRÉ-CONDIÇÃO do UC-PQCK-04: produto sem variação.');
    expect(trim((string) $variacao->sub_sku))->not->toBe(
        '',
        'A variação do quick-add ficou com `sub_sku` em branco: a busca da venda procura em '
        . '`variations.sub_sku`, então o produto recém-criado não é encontrável pelo código.'
    );
});
