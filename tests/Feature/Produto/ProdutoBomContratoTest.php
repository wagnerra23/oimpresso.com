<?php

declare(strict_types=1);
// Cobre UC-PBOM-01, UC-PBOM-02, UC-PBOM-03, UC-PBOM-04
// (memory/requisitos/Produto/_telas/bom-combo.casos.md) — rastreabilidade caso↔teste.

use App\Domain\Inventory\Services\BomResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de comportamento do BOM / KIT (`GET|POST /api/products/{id}/bom`,
 * `DELETE /api/products/{id}/bom/{bom_id}` → `Inventory\ProductBomController`)
 * e do consumo (`App\Domain\Inventory\Services\BomResolver`).
 *
 * ⚠️ FLUXO SEM TELA. Varredura contada (2026-07-27, sha 16606e35c4):
 *    · os `.tsx` de `resources/js/Pages/Produto/` citam `combo` **3×** e as três são a mesma
 *      união de tipo TypeScript (`'single' | 'variable' | 'combo'` em `Create.tsx:48,79` e
 *      `Edit.tsx:38`) — nenhuma tela React monta, edita ou exibe composição;
 *    · a UI drag-drop é a `US-PROD-025`, `status: todo` (SDD §6.1 CU-PROD-05: *"UI
 *      drag-drop pendente"*).
 *    Logo o contrato cobre o que EXISTE — a API + o resolver — e **não** antecipa a UI
 *    (UC pra código inexistente é órfão; proibicoes §5 2026-07-16).
 *
 * ÂNCORA (contrato, NÃO implementação) — triangulada nas 3 fontes (ADR 0351):
 *   1. CANON  — `CU-PROD-05` (combo/kit + BOM) e `CU-PROD-10` `[T0]` do SDD §6.1; o item
 *               `CU-PROD-05.4` (*"BOM `ScopeByBusiness` + `firstOrFail` cross-tenant"*)
 *               está marcado **⬜ não verificado** — este teste é o que o verifica.
 *   2. BLADE  — `resources/views/product/partials/combo_product_form_part.blade.php` +
 *               `combo_product_entry_row.blade.php` (o caminho legado `type='combo'` →
 *               `variations.combo_variations`, que o resolver ainda aceita por fallback).
 *   3. DELPHI — Parte 4 da ANTI-REGRESSAO-cadastro-produto-legacy.md: `AR-PROD-150..168`.
 *               O BOM legado é **multi-nível** (`PRODUTO_COMPOSICAO.ORDEM_ARVORE` = árvore)
 *               com quantidade por fórmula/dimensão. O `ProductBom` é CRUD plano — o gap de
 *               paridade está catalogado; o que ESTE teste trava é o pouco que já existe:
 *               árvore resolvível + isolamento + quantidade multiplicada (`AR-PROD-158`).
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * O `CU-PROD-05` não tinha **nenhum** UC (lacuna do painel `_STATUS-GENERATED.md`), e a
 * cobertura que parecia existir NÃO RODA: `tests/Feature/Domain/Inventory/BomResolverTest.php`
 * e `ReservarEstoqueBomTest.php` fazem `markTestSkipped` quando `config('database.default')
 * !== 'sqlite'` — mas **não estão** em `.github/ci-sqlite-pest.list` (a allowlist da única
 * lane sqlite per-PR) e **não estão** em lane nenhuma (varredura contada de `Domain/Inventory`
 * em `.github/` + `scripts/`: **0**). No nightly do CT 100 (`DB_CONNECTION=mysql`,
 * `scripts/tests/ct100-fullsuite.sh`) eles auto-pulam. Resultado medido: skip-as-pass em
 * todo lugar. Este arquivo roda em MySQL real, onde `product_bom` existe no schema baseline.
 *
 * ⚠️ Failing-first (proibicoes §5, 2026-06-05): os asserts saem do contrato (CU/AR), NÃO do
 *    `ProductBomController`. Se nascer vermelho, o vermelho É o achado.
 *
 * ⚠️ ANTI-VÁCUO (proibicoes §5, 2026-07-24): todo caso que afirma "X não foi gravado" carrega
 *    pré-condição provando que a gravação LEGÍTIMA aconteceu.
 *
 * ⛔ Multi-tenant Tier 0 (ADR 0101): biz=1 canônico; cross-tenant contra o 2º business
 *    seedado. NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

/** Rows de BOM do produto pai, lidas SEM global scope (fonte-de-verdade dos asserts). */
function bomRows(int $parentProductId): \Illuminate\Support\Collection
{
    return DB::table('product_bom')->where('parent_product_id', $parentProductId)->get();
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
// UC-PBOM-01 — CU-PROD-05.4 `[T0]` + ADR 0093: o COMPONENTE também é produto, e um kit
//   não pode ser montado com peça de outro tenant. O controller resolve o componente com
//   `Product::where('id', …)->where('business_id', …)->firstOrFail()` — este UC trava
//   esse guard (hoje sem teste algum: `ProductBomController` tem 0 testes).
// =============================================================================

it('UC-PBOM-01 · componente de outro business não entra no BOM (Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $bizId = (int) $this->business->id;
    $kit = EstoqueFixture::singleProduct($bizId);
    $componenteMeu = EstoqueFixture::singleProduct($bizId);
    $componenteAlheio = EstoqueFixture::singleProduct($outroBizId);

    // FASE 1 (pré-condição anti-vácuo): o componente LEGÍTIMO entra. Sem isto, "o alheio
    // não entrou" seria verdade só porque o endpoint está quebrado pra todo mundo.
    $this->postJson("/api/products/{$kit->productId}/bom", [
        'component_id' => $componenteMeu->productId,
        'qty_required' => 2,
    ])->assertStatus(201);

    expect(bomRows($kit->productId)->count())->toBe(
        1,
        'PRÉ-CONDIÇÃO do UC-PBOM-01: o componente legítimo não gravou — a fase 2 mediria vácuo.'
    );

    // FASE 2: componente de OUTRO business.
    $this->postJson("/api/products/{$kit->productId}/bom", [
        'component_id' => $componenteAlheio->productId,
        'qty_required' => 1,
    ]);

    // O CONTRATO: nenhuma linha de BOM aponta pra produto de outro tenant.
    $componentes = bomRows($kit->productId)->pluck('component_product_id')->map(fn ($v) => (int) $v)->all();

    expect(in_array($componenteAlheio->productId, $componentes, true))->toBeFalse(
        'Um kit do business A recebeu componente do business B (component_product_id '
        . $componenteAlheio->productId . '). O consumo do kit baixaria estoque cross-tenant.'
    );
});

// =============================================================================
// UC-PBOM-02 — CU-PROD-10.1 `[T0]`: o MESMO buraco que o `UC-PTAB-04` provou vermelho em
//   `saveSellingPrices` (#4300) e que o `UC-PBULK-03` reencontrou no `bulkUpdate` —
//   um id que vem CRU do request e é gravado sem checar de quem é.
//   Aqui o id é `component_variation_id` (e o irmão `parent_variation_id`): o controller
//   valida `component_id` (o PRODUTO) contra o business, mas as VARIAÇÕES passam só por
//   `'nullable|integer'` e vão direto pro `ProductBom::create`.
//   O SDD já avisava: *"o próximo model pendurado em Product nasce com o mesmo buraco"*.
// =============================================================================

it('UC-PBOM-02 · variação de outro business não vira componente do kit (Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $bizId = (int) $this->business->id;
    $kit = EstoqueFixture::singleProduct($bizId);
    $componenteMeu = EstoqueFixture::variableProduct($bizId, 1);
    $produtoAlheio = EstoqueFixture::variableProduct($outroBizId, 1);
    $variacaoAlheia = $produtoAlheio->variationId(0);

    // FASE 1 (pré-condição anti-vácuo): componente + variação MEUS gravam.
    $this->postJson("/api/products/{$kit->productId}/bom", [
        'component_id' => $componenteMeu->productId,
        'component_variation_id' => $componenteMeu->variationId(0),
        'qty_required' => 1,
    ])->assertStatus(201);

    expect(bomRows($kit->productId)->count())->toBe(
        1,
        'PRÉ-CONDIÇÃO do UC-PBOM-02: o par componente+variação legítimo não gravou — vácuo.'
    );

    // FASE 2: produto componente MEU, mas apontando pra variação de OUTRO business.
    // (o `component_id` passa no guard; o `component_variation_id` é o vetor)
    $this->postJson("/api/products/{$kit->productId}/bom", [
        'component_id' => $componenteMeu->productId,
        'component_variation_id' => $variacaoAlheia,
        'qty_required' => 1,
    ]);

    // O CONTRATO: nenhuma linha do meu kit referencia variação de outro tenant.
    $variacoes = bomRows($kit->productId)
        ->pluck('component_variation_id')
        ->filter()
        ->map(fn ($v) => (int) $v)
        ->all();

    expect(in_array($variacaoAlheia, $variacoes, true))->toBeFalse(
        'O BOM gravou `component_variation_id` de OUTRO business (variation_id ' . $variacaoAlheia
        . '). O `component_id` é validado contra o tenant; a VARIAÇÃO passa só por '
        . '"nullable|integer". Mesmo defeito do UC-PTAB-04 (#4300) e do UC-PBULK-03, terceiro eixo.'
    );
});

// =============================================================================
// UC-PBOM-03 — CU-PROD-05.2 (*"BOM (`ProductBom`) CRUD API multi-tenant funciona"*) +
//   CU-PROD-05.3 (*"baixa-de-componente do kit no PDV"*) + `AR-PROD-158` `[V0]`
//   (o valor/quantidade da linha é a quantidade da receita × o multiplicador do kit).
//   O contrato de IDA-E-VOLTA: o que a API grava é o que o resolver (quem baixa estoque)
//   lê. Hoje as duas metades não têm nenhum teste em comum — a API não tem teste algum e
//   o `BomResolverTest` monta o próprio schema sqlite e não roda em lane nenhuma.
// =============================================================================

it('UC-PBOM-03 · o BOM gravado pela API é o que o resolver baixa, com a quantidade multiplicada', function () {
    $bizId = (int) $this->business->id;
    $kit = EstoqueFixture::singleProduct($bizId);
    $parafuso = EstoqueFixture::singleProduct($bizId);
    $chapa = EstoqueFixture::singleProduct($bizId);

    $this->postJson("/api/products/{$kit->productId}/bom", [
        'component_id' => $parafuso->productId,
        'qty_required' => 4,
    ])->assertStatus(201);

    $this->postJson("/api/products/{$kit->productId}/bom", [
        'component_id' => $chapa->productId,
        'qty_required' => 0.5,
    ])->assertStatus(201);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a API gravou as duas linhas (senão o resolver devolveria
    // "produto simples = ele mesmo" e o assert de composição mediria não-execução).
    expect(bomRows($kit->productId)->count())->toBe(2);

    // 3 kits vendidos → cada componente sai multiplicado (AR-PROD-158).
    $folhas = app(BomResolver::class)->resolve($bizId, $kit->productId, null, 3.0);

    $porProduto = [];
    foreach ($folhas as $folha) {
        $pid = (int) $folha['product_id'];
        $porProduto[$pid] = ($porProduto[$pid] ?? 0.0) + (float) $folha['qty'];
    }

    // O CONTRATO (a): o kit deixa de ser folha — ele se decompõe nos componentes.
    expect(array_key_exists($kit->productId, $porProduto))->toBeFalse(
        'O resolver devolveu o KIT como folha: o BOM gravado pela API não foi enxergado, '
        . 'e o PDV baixaria estoque do kit em vez dos componentes.'
    );

    // O CONTRATO (b): cada componente sai pela quantidade da receita × kits.
    expect($porProduto[$parafuso->productId] ?? null)->toEqualWithDelta(
        12.0,
        0.0001,
        'Componente com qty_required=4 em 3 kits não resolveu 12 (AR-PROD-158).'
    );
    expect($porProduto[$chapa->productId] ?? null)->toEqualWithDelta(
        1.5,
        0.0001,
        'Componente FRACIONÁRIO (qty_required=0,5) em 3 kits não resolveu 1,5 — quantidade de '
        . 'receita fracionada é a regra, não a exceção, em composição por dimensão (AR-PROD-156).'
    );
});

// =============================================================================
// UC-PBOM-04 — CU-PROD-05.4 `[T0]` LITERAL: *"BOM `ScopeByBusiness` + `firstOrFail`
//   cross-tenant"*. O SDD marca este item **⬜ não verificado** ("o ✅ da v1.0.0 valia
//   por leitura de código, não por execução"). Este UC é a execução.
//   Cobre os dois verbos de leitura/remoção: `GET` (listar o kit alheio) e `DELETE`
//   (remover componente de kit alheio) — porque quem consegue listar consegue mapear
//   o catálogo do concorrente, e quem consegue deletar sabota a receita dele.
// =============================================================================

it('UC-PBOM-04 · kit de outro business não é listado nem tem componente removido (Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $bizId = (int) $this->business->id;
    $meuKit = EstoqueFixture::singleProduct($bizId);
    $meuComponente = EstoqueFixture::singleProduct($bizId);

    $kitAlheio = EstoqueFixture::singleProduct($outroBizId);
    $componenteAlheio = EstoqueFixture::singleProduct($outroBizId);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO (parte 1): o GET funciona pro MEU kit — senão "o alheio deu
    // 404" seria verdade só porque o endpoint está fora do ar.
    $this->postJson("/api/products/{$meuKit->productId}/bom", [
        'component_id' => $meuComponente->productId,
        'qty_required' => 1,
    ])->assertStatus(201);

    $this->getJson("/api/products/{$meuKit->productId}/bom")->assertStatus(200);

    // Linha de BOM real no kit ALHEIO, criada por fora do controller (é o estado que o
    // outro tenant teria). Sem ela o DELETE não teria alvo — vácuo.
    $bomAlheioId = (int) DB::table('product_bom')->insertGetId([
        'business_id' => $outroBizId,
        'parent_product_id' => $kitAlheio->productId,
        'parent_variation_id' => null,
        'component_product_id' => $componenteAlheio->productId,
        'component_variation_id' => null,
        'qty_required' => 1,
        'is_optional' => 0,
        'allow_substitution' => 0,
        'notes' => null,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // O CONTRATO (a): listar o BOM de produto alheio → 404 (não 200 com a receita dele).
    // Se falhar, a receita de produção do concorrente vaza pelo endpoint.
    $this->getJson("/api/products/{$kitAlheio->productId}/bom")->assertStatus(404);

    // O CONTRATO (b): remover componente de kit alheio não apaga nada.
    $this->deleteJson("/api/products/{$kitAlheio->productId}/bom/{$bomAlheioId}");

    expect(DB::table('product_bom')->where('id', $bomAlheioId)->exists())->toBeTrue(
        'O DELETE removeu uma linha de BOM de OUTRO business (bom_id ' . $bomAlheioId
        . ') — sabotagem cross-tenant da receita de produção.'
    );
});
