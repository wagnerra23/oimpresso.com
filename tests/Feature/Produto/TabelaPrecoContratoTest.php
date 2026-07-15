<?php

declare(strict_types=1);
// Cobre UC-PTAB-01, UC-PTAB-02, UC-PTAB-03 (SellingPrices.casos.md) - G-2 rastreabilidade caso-teste.

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

it('UC-PTAB-02 · salvar preço em produto de outro business retorna 404 (multi-tenant Tier 0)', function () {
    $outroBizId = EstoqueFixture::secondBusinessId();
    if ($outroBizId === null) {
        $this->markTestSkipped('DB só tem 1 business — sem par cross-tenant pra provar isolamento.');
    }

    $produtoAlheio = EstoqueFixture::singleProduct($outroBizId);
    $variationAlheia = $produtoAlheio->variationId();
    $tabelaAlheia = tabelaPrecoAtiva($outroBizId, 'Atacado alheio UC-PTAB-02');

    $this->post('/products/save-selling-prices', [
        'product_id' => $produtoAlheio->productId,
        'group_prices' => [
            $tabelaAlheia => [
                $variationAlheia => ['price' => '999,00', 'price_type' => 'fixed'],
            ],
        ],
    ])->assertStatus(404);

    // E o vazamento pela porta dos fundos: nada pode ter sido gravado no business alheio.
    expect(precoGravado($variationAlheia, $tabelaAlheia))
        ->toBeNull('Preço foi gravado em produto de OUTRO business — vazamento cross-tenant Tier 0.');
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
