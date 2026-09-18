<?php

declare(strict_types=1);
// Cobre UC-PEDIT-03 (resources/js/Pages/Produto/Edit.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de comportamento de Editar Produto (/products/{id}/edit → PUT /products/{id}).
 *
 * ÂNCORA (contrato, NÃO implementação): CU-PROD-10 `[T0]` (SDD §6.1) + Edit.charter Goal
 * "Multi-tenant: produto cross-tenant retorna 404" + [ADR 0093]. O 404 é o CONTRATO falando.
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * Achado do adversário 2026-07-24 (revisão do piloto sdd-from-source, ADR 0351): o piloto
 * marcou UC-PEDIT-03 como "🔶 não afirmado" usando LC-08 como escudo pra NÃO ler — quando
 * LC-08 MANDA varrer. A varredura que faltava (2 linhas): `edit()` (GET) usa `firstOrFail()`
 * (ProductController:872-875) → cross-tenant = 404 ✅; mas `update()` (PUT) usava `first()`
 * (:978-981) → id alheio vira `null` → atribuição de propriedade em null (:990) → `\Error`
 * → o `catch (\Exception)` (:1173) NÃO pega `\Error` → 500. O contrato "404" era FALSO no PUT.
 * Mesma família do #4300 (SellingPrices: a exceção virava redirect success:0 = 302).
 *
 * ⚠️ Failing-first (proibicoes §5, 2026-06-05): os asserts saem do CU-PROD-10, não do código.
 * Se nascer vermelho, o vermelho é o achado — não se ajusta o teste ao código. O fix
 * (firstOrFail ANTES do try no update()) entra no MESMO PR (padrão #4300/#4417).
 *
 * ⛔ Multi-tenant Tier 0 (ADR 0101): biz=1 canônico, cross-tenant contra o 2º business seeded.
 *    NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

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
    Permission::findOrCreate('product.update', 'web');
    $this->user->givePermissionTo(['product.update']);
});

// =============================================================================
// UC-PEDIT-03 — CU-PROD-10 `[T0]`: editar produto de OUTRO business → 404
//   edit() (GET) já cumpre (firstOrFail); update() (PUT) NÃO cumpria (first() → 500).
// =============================================================================

it('UC-PEDIT-03 · GET edit de produto de outro business retorna 404', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $alheio = EstoqueFixture::singleProduct($outroBizId);

    // O edit() usa firstOrFail (:872-875) — este lado já era 404 antes do fix (âncora ✅).
    $this->get('/products/' . $alheio->productId . '/edit')
        ->assertStatus(404);
});

it('UC-PEDIT-03 · PUT update de produto de outro business retorna 404 (não 500/302)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $alheio = EstoqueFixture::singleProduct($outroBizId);

    // firstOrFail resolve o produto ANTES do try → 404 independe do payload.
    // Vermelho ANTES do fix (500 pelo \Error em null, ou 302 se o catch pegasse); verde depois.
    $this->put('/products/' . $alheio->productId, ['name' => 'tentativa cross-tenant UC-PEDIT-03'])
        ->assertStatus(404);
});

// =============================================================================
// HELPERS dos UC-PEDIT-01/02/04 — prefixo `pedit` de propósito.
//
// Os irmãos desta pasta declaram funções GLOBAIS (`payloadDaTelaReact`,
// `exigeQueTenhaPersistido`, `variacaoDoProduto`...). Pest roda a pasta inteira
// num processo só, então nome repetido = "Cannot redeclare" fatal — a mesma
// classe de colisão que a `.github/estoque-pest-quarantine.list` verificou antes
// de tirar os 14 `Wave2*` da quarentena. Reusar por import não existe entre
// arquivos Pest; nome próprio é a saída.
// =============================================================================

/**
 * Payload da Edit React + a chave que destrava o `save()`.
 *
 * O `preparation_time_in_minutes` NÃO é enfeite: `ProductController` o acessa sem `??`,
 * e sem ele o `update()` estoura `ErrorException`, que o `catch (\Exception)` genérico
 * engole → redirect sem gravar. Medido no run 30122144831 (irmão
 * `ProdutoEditPayloadContratoTest`), onde dois UCs passaram VERDES **no vácuo**.
 */
function peditPayloadBase(object $produto, array $override = []): array
{
    return array_merge([
        'name' => $produto->name . ' (editado)',
        'sku' => $produto->sku,
        'brand_id' => $produto->brand_id ?? '',
        'unit_id' => $produto->unit_id,
        'category_id' => $produto->category_id ?? '',
        'tax' => $produto->tax ?? '',
        'tax_type' => $produto->tax_type,
        'barcode_type' => $produto->barcode_type,
        'alert_quantity' => $produto->alert_quantity ?? '',
        'weight' => $produto->weight ?? '',
        'product_description' => $produto->product_description ?? '',
        'product_locations' => [],
        'preparation_time_in_minutes' => '',
    ], $override);
}

/**
 * Pré-condição anti-vácuo: o PUT realmente persistiu?
 *
 * Sem isto, "a variação sobreviveu" não distingue *preservada* de *nunca escrita* —
 * verde por não-execução é o verde tautológico que este projeto bane
 * (`proibicoes.md` §5, 2026-06-05). Copiado de propósito do irmão, que pagou pra aprender.
 */
function peditExigePersistido(int $productId, string $nomeEsperado, string $uc): void
{
    expect(\App\Product::findOrFail($productId)->name)->toBe(
        $nomeEsperado,
        "PRÉ-CONDIÇÃO do {$uc}: o PUT não persistiu — este UC NÃO foi exercido. "
        . 'Verde aqui seria vácuo (§5 2026-06-05), não prova.'
    );
}

/** @return array<int,object> linhas de `variations` do produto, ordenadas (ordem estável). */
function peditVariacoes(int $productId): array
{
    return \Illuminate\Support\Facades\DB::table('variations')
        ->where('product_id', $productId)
        ->orderBy('id')
        ->get()
        ->all();
}

// =============================================================================
// UC-PEDIT-01 — CU-PROD-02 + AR-PROD-021/032: editar produto VARIÁVEL não apaga
//   a grade. O cabeçalho é editável; a grade não é gerenciada por esta tela
//   (Edit.charter Non-Goal "❌ Editar variations dinamicamente (Wave 3)").
//
// ⚠️ Escrito ANTES de ler a implementação (failing-first, §5 2026-06-05): o assert
//    sai do CU-PROD-02, não do ProductController. Se nascer vermelho, o vermelho é
//    o achado — não se ajusta o teste ao código. Era stub `test.fixme` em
//    `e2e/produto-edit.spec.ts`; o Edit.casos.md manda "virar Pest no CT100".
// =============================================================================

it('UC-PEDIT-01 · editar produto variável preserva as variações que a tela não envia', function () {
    $bizId = (int) $this->business->id;
    $p = EstoqueFixture::variableProduct($bizId, 2);

    $antes = peditVariacoes($p->productId);
    expect($antes)->toHaveCount(
        2,
        'PRÉ-CONDIÇÃO: a fixture não criou as 2 variações — sem grade, este UC não prova nada.'
    );
    $idsAntes = array_map(fn ($v) => (int) $v->id, $antes);

    $produto = \App\Product::findOrFail($p->productId);

    // O payload da Edit React NÃO manda `product_variation` nem `product_variation_edit`
    // (varredura contada em Edit.tsx: 0 ocorrências). Editar o cabeçalho é o caso real.
    $payload = peditPayloadBase($produto);
    $this->put("/products/{$p->productId}", $payload);

    peditExigePersistido($p->productId, $payload['name'], 'UC-PEDIT-01');

    $depois = peditVariacoes($p->productId);
    expect($depois)->toHaveCount(
        2,
        'CU-PROD-02: editar o cabeçalho apagou/duplicou a grade. O produto perde SKU, preço e '
        . 'estoque calado — AR-PROD-021/032: no Delphi, "Alterar" preserva o que já estava carregado.'
    );
    expect(array_map(fn ($v) => (int) $v->id, $depois))->toBe(
        $idsAntes,
        'CU-PROD-02: as variações foram RECRIADAS (ids novos). Mesmo com a contagem igual, o '
        . 'vínculo de estoque/venda por variation_id se perde.'
    );
});

// =============================================================================
// UC-PEDIT-02 — Edit.charter Non-Goal "❌ Mudar `type` após criar".
//   O React desabilita o <Input>, mas UI desabilitada NÃO impede o request de
//   mandar `type` — mesma família do furo UC-PCAD-05/UC-PTAB-04 (dropdown
//   escopado ≠ request escopado). Este UC prova o BACKEND.
// =============================================================================

it('UC-PEDIT-02 · request que manda `type` diferente não muda o tipo do produto', function () {
    $bizId = (int) $this->business->id;
    $p = EstoqueFixture::singleProduct($bizId);

    $produto = \App\Product::findOrFail($p->productId);
    expect($produto->type)->toBe('single', 'PRÉ-CONDIÇÃO: a fixture não criou um produto single.');

    // Hostil de propósito: o cliente mente o `type`, como faria um request forjado.
    //
    // As 6 chaves do bloco `single` vão junto NÃO por enfeite: cada UC isola UMA variável,
    // e aqui a variável é o `type` — não a persistência.
    //
    // Fato datado (2026-09-18, medido no CT 100 contra o `update()` de então): SEM essas
    // chaves, um produto `single` fazia o `update()` abortar antes do save — o acesso a
    // `$single_data[...]` sem `??` virava ErrorException, engolida pelo catch genérico — e
    // a pré-condição anti-vácuo reprovava com "o PUT não persistiu". Esse era o UC-PEDIT-06,
    // outro defeito: sem as chaves, este teste mediria AQUELE.
    //
    // O PR #7522 (eixo estoque) passa essas leituras para `array_key_exists`, então a partir
    // dele a ausência deixa de abortar. Mandar as chaves continua CERTO aqui de qualquer forma
    // — isolar a variável do UC não depende de qual defeito o writer tem hoje.
    $payload = peditPayloadBase($produto, [
        'type' => 'variable',
        'single_variation_id' => $p->variationId(0),
        'single_dpp' => '10,00',
        'single_dpp_inc_tax' => '10,00',
        'profit_percent' => '0',
        'single_dsp' => '20,00',
        'single_dsp_inc_tax' => '20,00',
    ]);
    $this->put("/products/{$p->productId}", $payload);

    peditExigePersistido($p->productId, $payload['name'], 'UC-PEDIT-02');

    expect(\App\Product::findOrFail($p->productId)->type)->toBe(
        'single',
        'Non-Goal do charter: o tipo mudou por request. Trocar single→variable sem recriar a grade '
        . 'deixa o produto num estado que nenhum dos dois branches do update() sabe tratar.'
    );
});

// =============================================================================
// UC-PEDIT-04 `[V0]` — CU-PROD-01.4 + REGRA MESTRE valor/estoque (proibicoes.md).
//   `update()` roda num_uf em single_dpp/single_dsp/profit_percent — O MESMO parser
//   que inflou 16 vendas ×100k na ROTA LIVRE (incidente 2026-06-05).
//
// ⛔ TEST-ONLY: caracteriza o contrato do endpoint. Se vermelho, a correção é
//    decisão [W] sob a REGRA MESTRE (dupla prova + tabela antes→depois) — NUNCA
//    conserto silencioso aqui.
//
// Par de valores idêntico ao UC-PCAD-04 (CadastroProdutoContratoTest), de propósito:
// é o mesmo parser nos dois endpoints, e o casos.md pede o mesmo par.
// =============================================================================

it('UC-PEDIT-04 · custo pt-BR com milhar e decimal grava o valor certo no update', function () {
    $bizId = (int) $this->business->id;
    $p = EstoqueFixture::singleProduct($bizId);
    $produto = \App\Product::findOrFail($p->productId);

    // update() lê as 6 chaves do bloco single SEM `??` — faltando uma, ErrorException
    // é engolida pelo catch genérico e nada grava (o vácuo do UC-PEDIT-06).
    $payload = peditPayloadBase($produto, [
        'single_variation_id' => $p->variationId(0),
        'single_dpp' => '1.234,56',
        'single_dpp_inc_tax' => '1.234,56',
        'profit_percent' => '0',
        'single_dsp' => '2.000,00',
        'single_dsp_inc_tax' => '2.000,00',
    ]);
    $this->put("/products/{$p->productId}", $payload);

    peditExigePersistido($p->productId, $payload['name'], 'UC-PEDIT-04');

    $v = \Illuminate\Support\Facades\DB::table('variations')->where('id', $p->variationId(0))->first();
    expect($v)->not->toBeNull('A variação single sumiu depois do update.');
    expect((float) $v->default_purchase_price)->toEqualWithDelta(
        1234.56,
        0.01,
        'CU-PROD-01.4 [V0]: custo pt-BR inflado ou truncado no update (num_uf).'
    );
    expect((float) $v->default_sell_price)->toEqualWithDelta(
        2000.00,
        0.01,
        'CU-PROD-01.4 [V0]: preço de venda pt-BR inflado ou truncado no update.'
    );
});

it('UC-PEDIT-04 · custo fracionário com ponto NÃO infla ×100k no update', function () {
    $bizId = (int) $this->business->id;
    $p = EstoqueFixture::singleProduct($bizId);
    $produto = \App\Product::findOrFail($p->productId);

    // A forma que o JS manda (Number.toString()): ponto = separador DECIMAL, não milhar.
    // Foi exatamente este caso que o num_uf leu como milhar em 2026-06-05, inflando o
    // total da venda em ~5 ordens de grandeza (o ×100k). Valor redigido de propósito:
    // é venda real de cliente, e vale a regra Tier 0 de BRL (proibicoes.md), que já
    // redige este mesmo incidente.
    $payload = peditPayloadBase($produto, [
        'single_variation_id' => $p->variationId(0),
        'single_dpp' => '204.99605',
        'single_dpp_inc_tax' => '204.99605',
        'profit_percent' => '0',
        'single_dsp' => '204.99605',
        'single_dsp_inc_tax' => '204.99605',
    ]);
    $this->put("/products/{$p->productId}", $payload);

    peditExigePersistido($p->productId, $payload['name'], 'UC-PEDIT-04');

    $v = \Illuminate\Support\Facades\DB::table('variations')->where('id', $p->variationId(0))->first();
    expect($v)->not->toBeNull('A variação single sumiu depois do update.');
    expect((float) $v->default_purchase_price)->toBeLessThan(
        1000.0,
        'REGRA MESTRE [V0]: 204.99605 virou ordem de grandeza maior — é o ×100k de 2026-06-05, '
        . 'agora pelo endpoint de EDIÇÃO. Correção é decisão [W], não deste teste.'
    );
});
