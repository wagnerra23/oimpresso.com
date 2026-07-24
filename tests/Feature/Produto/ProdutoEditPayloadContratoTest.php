<?php

declare(strict_types=1);
// Cobre UC-PEDIT-05, UC-PEDIT-06, UC-PEDIT-07 (resources/js/Pages/Produto/Edit.casos.md)
// — G-2 rastreabilidade caso↔teste (ADR 0264).

use App\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato do PAYLOAD de Editar Produto (`PUT /products/{id}`).
 *
 * ÂNCORA (contrato, NÃO implementação)
 * ─────────────────────────────────────────────────────────────────────────────
 * - `AR-PROD-051` / `AR-PROD-056` — paridade Delphi (Office Comercial 2026.1.1.38):
 *   o cadastro guarda "controla estoque" como atributo do produto; editar a ficha
 *   NÃO é o gesto que liga/desliga controle de estoque.
 * - `proibicoes.md` §REGRA MESTRE (CÁLCULO DE VALOR ou ESTOQUE) — toda alteração que
 *   possa mexer em ESTOQUE precisa ser deliberada e provada. Uma edição de NOME que
 *   apaga o controle de estoque é o oposto: muda estoque em silêncio.
 * - `Edit.charter.md` §Goals — "editar a ficha do produto"; desligar estoque não é Goal.
 * - `CU-PROD-02` (SDD §6.1) pro ramo `variable`.
 *
 * Os asserts saem daí — **não** do `ProductController@update`. Teste derivado do código
 * é tautológico: passa mesmo com o comportamento errado e trava o desvio em vez de
 * pegá-lo (`proibicoes.md` §5, entrada 2026-06-05).
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * Achado do B1-controle 2026-07-24 (1º run real do agent `sdd-from-source`, ADR 0351 —
 * evidência em `memory/requisitos/Produto/_b1-controle-Edit.casos.agent.md`), verificado
 * de forma independente por leitura: o `useForm` de `Edit.tsx` manda **18 chaves**; o
 * `update()` lê **33+** (via `$request->only([...])` mais `$request->input(...)`), e o
 * padrão do controller é **ausência → zero**:
 *
 *   ProductController@update  L76-79   enable_stock     ausente → 0
 *                             L82      not_for_selling  ausente → 0
 *                             L101-104 enable_sr_no     ausente → 0
 *                             L71      sub_unit_ids     ausente → null
 *                             L1111-12 single_variation_id ausente → Variation::find(null)
 *                                      → atribuição em null → \Error (o catch (\Exception) não pega) → 500
 *
 * ⚠️ **Não é incidente de produção.** As telas React do Produto são duais
 * (`if (request()->header('X-Inertia'))` — `ProductController:342/909`) e **inalcançáveis
 * hoje**: a sidebar do cockpit usa `<a href>` puro (`Sidebar.tsx:489`), que não manda o
 * header, e todos os `<Link href="/products">` vivem dentro das próprias páginas do
 * Produto (circular, sem porta de entrada). O que roda em prod é o Blade, que manda o
 * payload completo — confirmado por [F] 2026-07-24. Isto é **bloqueador de migração**
 * (MWART F5 — [ADR 0104]): define quando a tela React pode ser ligada.
 *
 * ⚠️ Failing-first (padrão #4300 / #4417): se nascer vermelho, o vermelho É o achado.
 * NÃO se ajusta o teste ao código. E como o eixo é ESTOQUE, o fix é decisão [W] sob a
 * REGRA MESTRE (2 caminhos + tabela antes→depois), não conserto silencioso aqui.
 *
 * ⚠️ São TRÊS defeitos independentes, não uma raiz (`proibicoes.md` §5, 2026-07-15):
 * contrato do payload (o que a tela manda) · contrato do writer (ausência→zero) ·
 * ausência de validação. Consertar um não conserta os outros, e as correções podem
 * brigar entre si — por isso cada UC tem seu próprio teste.
 *
 * ⛔ Multi-tenant Tier 0 (ADR 0101): biz=1 canônico. NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

/**
 * Payload EXATO do `useForm` de `resources/js/Pages/Produto/Edit.tsx` (18 chaves).
 * Reproduz o que a tela React envia hoje — é o INPUT do contrato, não o assert.
 */
function payloadDaTelaReact(Product $produto): array
{
    return [
        'name' => $produto->name . ' (editado)',
        'sku' => $produto->sku,
        'brand_id' => $produto->brand_id ?? '',
        'unit_id' => $produto->unit_id,
        'category_id' => $produto->category_id ?? '',
        'sub_category_id' => $produto->sub_category_id ?? '',
        'tax' => $produto->tax ?? '',
        'tax_type' => $produto->tax_type,
        'barcode_type' => $produto->barcode_type,
        'alert_quantity' => $produto->alert_quantity ?? '',
        'weight' => $produto->weight ?? '',
        'product_description' => $produto->product_description ?? '',
        'product_locations' => [],
        'warranty_id' => $produto->warranty_id ?? '',
        'product_custom_field1' => $produto->product_custom_field1 ?? '',
        'product_custom_field2' => $produto->product_custom_field2 ?? '',
        'product_custom_field3' => $produto->product_custom_field3 ?? '',
        'product_custom_field4' => $produto->product_custom_field4 ?? '',
    ];
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
// UC-PEDIT-05 `[V0]` — AR-PROD-051/056 + REGRA MESTRE:
//   editar a ficha NÃO altera o controle de estoque do produto.
//   Usa `variable` de propósito: é o ramo que (per B1) SALVA — logo o assert mede
//   o cabeçalho, não é mascarado pelo 500 do ramo `single` (UC-PEDIT-06).
// =============================================================================

it('UC-PEDIT-05 · editar produto não desliga o controle de estoque (enable_stock)', function () {
    $p = EstoqueFixture::variableProduct($this->business->id, 2);

    $produto = Product::findOrFail($p->productId);
    $produto->enable_stock = 1;
    $produto->save();

    expect((int) Product::findOrFail($p->productId)->enable_stock)
        ->toBe(1, 'pré-condição: o produto nasce controlando estoque');

    $this->put("/products/{$p->productId}", payloadDaTelaReact($produto));

    expect((int) Product::findOrFail($p->productId)->enable_stock)->toBe(
        1,
        'AR-PROD-051/056 + REGRA MESTRE: editar a ficha não pode desligar o controle de estoque. '
        . 'A tela não manda `enable_stock`, e o writer trata ausência como 0 (update() L76-79).'
    );
});

// =============================================================================
// UC-PEDIT-07 — AR-PROD-003/042: editar não apaga o que a tela não toca.
//   Flags/campos ausentes do payload devem ser PRESERVADOS, não zerados.
// =============================================================================

it('UC-PEDIT-07 · editar não apaga flags que a tela não envia', function () {
    $p = EstoqueFixture::variableProduct($this->business->id, 2);

    $produto = Product::findOrFail($p->productId);
    $produto->not_for_selling = 1;
    $produto->enable_sr_no = 1;
    $produto->save();

    $this->put("/products/{$p->productId}", payloadDaTelaReact($produto));

    $depois = Product::findOrFail($p->productId);

    expect((int) $depois->not_for_selling)->toBe(
        1,
        'AR-PROD-003/042: `not_for_selling` não está no payload da tela; ausência não é "desmarcar" (update() L82).'
    );
    expect((int) $depois->enable_sr_no)->toBe(
        1,
        'AR-PROD-003/042: `enable_sr_no` não está no payload da tela; ausência não é "desmarcar" (update() L101-104).'
    );
});

// =============================================================================
// UC-PEDIT-06 — Edit.charter §Goals: "Salvar" persiste.
//   Ramo `single`: o writer lê `single_variation_id` de um `only()` que não a contém
//   → Variation::find(null) → atribuição em null → \Error → 500.
//   Defeito INDEPENDENTE do UC-PEDIT-05 (§5 2026-07-15) — por isso teste próprio.
// =============================================================================

it('UC-PEDIT-06 · editar produto single persiste em vez de estourar 500', function () {
    $p = EstoqueFixture::singleProduct($this->business->id, enableStock: true);

    $produto = Product::findOrFail($p->productId);
    $nomeNovo = $produto->name . ' (editado)';

    $resposta = $this->put("/products/{$p->productId}", payloadDaTelaReact($produto));

    expect($resposta->getStatusCode())->not->toBe(
        500,
        'Edit.charter §Goals: "Salvar" é um Goal da tela. O payload da tela React não manda '
        . '`single_variation_id`; o writer o lê de um `only()` que não o contém (update() L1111-12).'
    );

    expect(Product::findOrFail($p->productId)->name)->toBe(
        $nomeNovo,
        'Edit.charter §Goals: se a tela diz que salvou, o banco tem que refletir.'
    );
});
