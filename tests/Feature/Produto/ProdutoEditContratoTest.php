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
