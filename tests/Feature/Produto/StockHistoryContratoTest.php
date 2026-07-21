<?php

declare(strict_types=1);
// Cobre UC-PSTK-01, UC-PSTK-02, UC-PSTK-03 (StockHistory.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato de comportamento do Kardex / Histórico de estoque (/products/stock-history/{id}).
 *
 * ÂNCORA (contrato, NÃO implementação): CU-PROD-11 — "Kardex real na tela React" `[must]`
 * em memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md §6.1:
 *   1. `[must][reg]` Controller passa `movements` via `Inertia::defer` — data · operação · qty ·
 *      stock_before/after · ref clicável. Hoje `undefined` (fachada, G-01)                → UC-PSTK-01
 *   2. `[reg]` Append-only (sem mutação em GET); cor semântica                            → UC-PSTK-01 (kind)
 *   4. `[T0]` Kardex só do business; `[perf]` `defer` < 600ms                              → UC-PSTK-02 / UC-PSTK-03
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * O `StockHistory.tsx` já tinha o `<Deferred data="movements">` + tabela desde 2026-05-31, mas o
 * controller (`productStockHistory`) NUNCA declarava a prop `movements` no branch Inertia — só
 * `product/variations/businessLocations/permissions`. Resultado: a prop ficava `undefined` pra
 * sempre → EmptyState eterno ("fachada", SDD grade 47 / G-01). Este teste prova o contrato do
 * CU-PROD-11.1 (a timeline chega) e o defer (`[perf]`: não vem eager no render inicial).
 *
 * ⛔ TEST-ONLY: não altera cálculo de estoque. Caracteriza o payload do endpoint. O branch ajax()
 *    legado faz um UPDATE em VariationLocationDetails dentro do GET; o branch Inertia/defer é
 *    read-only por design (CU-PROD-11.2 append-only) — este teste não exercita o path legado.
 */
uses(DatabaseTransactions::class);

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

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.view', 'web');
    $this->user->givePermissionTo(['product.view']);
});

// =============================================================================
// UC-PSTK-01 — CU-PROD-11.1 `[must][reg]`: o partial reload resolve a timeline real,
//   e uma venda vira um movimento de SAÍDA (kind + sinal semânticos).
// =============================================================================

it('UC-PSTK-01 · partial reload retorna a timeline real (venda → saída)', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);
    $variationId = $produto->variationId();
    $locationId = EstoqueFixture::locationId($bizId);

    // Uma venda de 3 un na variação/local → 1 movimento na getVariationStockHistory (status final).
    EstoqueFixture::saleWithLine($produto, 0, $locationId, 3.0);

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => '1',
        'X-Inertia-Partial-Component' => 'Produto/StockHistory',
        'X-Inertia-Partial-Data' => 'movements',
    ])->get("/products/stock-history/{$produto->productId}?variation_id={$variationId}&location_id={$locationId}");

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    // O CONTRATO: a prop existe (não é mais `undefined`) e traz a movimentação real.
    expect($page['props'])->toHaveKey('movements', 'O controller não expôs `movements` no partial reload — fachada (CU-PROD-11.1).');
    $movements = $page['props']['movements'];
    expect($movements)->toBeArray();
    expect(count($movements))->toBeGreaterThanOrEqual(1);

    $saida = collect($movements)->firstWhere('kind', 'saida');
    expect($saida)->not->toBeNull('A venda não apareceu como movimento de saída.');
    expect((float) $saida['quantity'])->toEqualWithDelta(-3.0, 0.0001);
    expect($saida['quantityLabel'])->toContain('-'); // sinal semântico de saída
});

// =============================================================================
// UC-PSTK-02 — CU-PROD-11.4 `[T0]`: Kardex de produto de outro business → 404.
//   (é o `it('Controller cross-tenant retorna 404')` que o charter promete no §Pest GUARD)
// =============================================================================

it('UC-PSTK-02 · produto de outro business retorna 404 (multi-tenant Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    // Produto vive no OUTRO business; a sessão é do business seeded.
    $produtoAlheio = EstoqueFixture::singleProduct($outroBizId);

    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->get("/products/stock-history/{$produtoAlheio->productId}")
        ->assertStatus(404);
});

// =============================================================================
// UC-PSTK-03 — CU-PROD-11.1/.4 `[perf]`: `movements` é DEFERIDO — não vem no render
//   inicial (só em partial reload). Prova que a timeline não é eager (defer < 600ms).
// =============================================================================

it('UC-PSTK-03 · movements é deferido — ausente no render Inertia inicial', function () {
    $bizId = (int) $this->business->id;
    $produto = EstoqueFixture::singleProduct($bizId);

    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
        ->get("/products/stock-history/{$produto->productId}");

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    // Deferido: a prop cara NÃO viaja no primeiro response (só sob partial reload — UC-PSTK-01).
    expect($page['props'])->not->toHaveKey('movements', '`movements` veio eager no render inicial — perdeu o defer (CU-PROD-11.4 `[perf]`).');
    // Mas os filtros (baratos) vêm eager, senão o front crasha ao ler filters.variationId.
    expect($page['props'])->toHaveKey('filters');
    expect($page['props']['filters'])->toHaveKey('variationId');
});
