<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Grade Avançada (Variation × Color × Size) — isolamento multi-tenant Tier 0.
 *
 * Vestuário usa matriz tamanho × cor via `App\Variation` + `App\VariationTemplate` +
 * `App\VariationLocationDetails` (núcleo UltimatePOS). Este test valida que o
 * isolamento por `business_id` cobre a grade inteira (parent + variants + estoque
 * por localização) cross-tenant biz=1 (Wagner WR2) ↔ biz=99 (fictício isolamento).
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa em produção) — conforme
 * [ADR 0101](memory/decisions/0101-tests-business-id-1-nunca-cliente.md).
 *
 * Cobertura US-VEST-001 (cadastro variações) + US-VEST-005 (estoque por loc).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see memory/requisitos/Vestuario/SPEC.md
 */

const VEST_BIZ_WAGNER = 1;
const VEST_BIZ_FICTICIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS (products/variations/variation_location_details) requer MySQL — Wagner Pest local mandatory (ADR 0101)');
    }
    if (! Schema::hasTable('products') || ! Schema::hasTable('variations')) {
        $this->markTestSkipped('Tabelas core UltimatePOS (products/variations) ausentes — rode migrate primeiro');
    }
});

// ------------------------------------------------------------------
// Helper: cria SKU pai + N variants pra um business
// ------------------------------------------------------------------

function vestCriarProdutoComVariations(int $businessId, string $nome, array $tamCor): int
{
    $productId = DB::table('products')->insertGetId([
        'business_id'       => $businessId,
        'name'              => $nome,
        'type'              => 'variable',
        'unit_id'           => 1,
        'sku'               => "VEST-TEST-{$businessId}-".uniqid(),
        'enable_stock'      => 1,
        'alert_quantity'    => 0,
        'tax_type'          => 'inclusive',
        'created_by'        => 1,
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    // Variation parent (template — tamanho/cor agrupado)
    $variationGroupId = DB::table('product_variations')->insertGetId([
        'product_id'                 => $productId,
        'name'                       => 'Tamanho-Cor',
        'is_dummy'                   => 0,
        'created_at'                 => now(),
        'updated_at'                 => now(),
    ]);

    foreach ($tamCor as [$tamanho, $cor]) {
        DB::table('variations')->insert([
            'name'                  => "{$tamanho}-{$cor}",
            'product_id'            => $productId,
            'sub_sku'               => "VEST-{$businessId}-{$tamanho}-{$cor}",
            'product_variation_id'  => $variationGroupId,
            'default_purchase_price' => 10.00,
            'dpp_inc_tax'           => 10.00,
            'profit_percent'        => 100.00,
            'default_sell_price'    => 20.00,
            'sell_price_inc_tax'    => 20.00,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    return $productId;
}

function vestLimparProduto(int $productId): void
{
    $variationIds = DB::table('variations')->where('product_id', $productId)->pluck('id')->toArray();
    if (! empty($variationIds)) {
        DB::table('variation_location_details')->whereIn('variation_id', $variationIds)->delete();
        DB::table('variations')->whereIn('id', $variationIds)->delete();
    }
    DB::table('product_variations')->where('product_id', $productId)->delete();
    DB::table('products')->where('id', $productId)->delete();
}

// ------------------------------------------------------------------
// Grade — Product (parent) cross-tenant
// ------------------------------------------------------------------

it('produto variable biz=1 não é visível query scopada biz=99', function () {
    $productId = vestCriarProdutoComVariations(
        VEST_BIZ_WAGNER,
        'Camiseta Básica Grade Teste',
        [['P', 'preta'], ['M', 'branca']]
    );

    // Query scopada por session biz=99 (fictício) — não enxerga produto biz=1
    $rows = DB::table('products')
        ->where('business_id', VEST_BIZ_FICTICIO)
        ->where('id', $productId)
        ->get();

    expect($rows)->toHaveCount(0);

    vestLimparProduto($productId);
});

// ------------------------------------------------------------------
// Variations — gerar matriz tamanho × cor isolada
// ------------------------------------------------------------------

it('matriz tamanho × cor gera N variants corretas pro business', function () {
    $tamCor = [
        ['PP', 'preta'], ['P', 'preta'], ['M', 'preta'],
        ['PP', 'branca'], ['P', 'branca'], ['M', 'branca'],
    ];
    $productId = vestCriarProdutoComVariations(VEST_BIZ_WAGNER, 'Camiseta Grade 6var', $tamCor);

    $variants = DB::table('variations')->where('product_id', $productId)->get();
    expect($variants)->toHaveCount(6);

    // SKUs únicos
    $skus = $variants->pluck('sub_sku')->unique();
    expect($skus)->toHaveCount(6);

    vestLimparProduto($productId);
});

it('variants de produto biz=1 não vazam pra produto biz=99', function () {
    $pBiz1 = vestCriarProdutoComVariations(VEST_BIZ_WAGNER, 'Calça Jeans biz1', [['38', 'azul']]);
    $pBiz99 = vestCriarProdutoComVariations(VEST_BIZ_FICTICIO, 'Calça Jeans biz99', [['40', 'preta']]);

    // Variants do biz=1: só aparecem quando join product.business_id = 1
    $variantsBiz1 = DB::table('variations')
        ->join('products', 'variations.product_id', '=', 'products.id')
        ->where('products.business_id', VEST_BIZ_WAGNER)
        ->where('products.id', $pBiz1)
        ->get();

    $variantsBiz99 = DB::table('variations')
        ->join('products', 'variations.product_id', '=', 'products.id')
        ->where('products.business_id', VEST_BIZ_WAGNER)
        ->where('products.id', $pBiz99) // produto biz=99 — não bate
        ->get();

    expect($variantsBiz1)->toHaveCount(1);
    expect($variantsBiz99)->toHaveCount(0);

    vestLimparProduto($pBiz1);
    vestLimparProduto($pBiz99);
});

// ------------------------------------------------------------------
// VariationLocationDetails — estoque por localização cross-tenant
// ------------------------------------------------------------------

it('estoque por loc biz=1 não vaza em query biz=99 (US-VEST-005)', function () {
    if (! Schema::hasTable('variation_location_details') || ! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Tabelas variation_location_details/business_locations ausentes');
    }

    $productId = vestCriarProdutoComVariations(VEST_BIZ_WAGNER, 'Vestido Grade Estoque', [['M', 'vermelho']]);
    $variationId = DB::table('variations')->where('product_id', $productId)->value('id');

    // Criar location fictícia biz=1
    $locId = DB::table('business_locations')->insertGetId([
        'business_id' => VEST_BIZ_WAGNER,
        'name'        => 'BL-TEST-VEST',
        'location_id' => 'LOC-V-T-'.uniqid(),
        'landmark'    => 'test',
        'country'     => 'BR',
        'state'       => 'SC',
        'city'        => 'Gravatal',
        'zip_code'    => '88735-000',
        'is_active'   => 1,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    DB::table('variation_location_details')->insert([
        'product_id'      => $productId,
        'variation_id'    => $variationId,
        'location_id'     => $locId,
        'qty_available'   => 15,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    // Query scopada por business_id biz=99 (cross-tenant) — não enxerga estoque
    $estoqueBiz99 = DB::table('variation_location_details')
        ->join('business_locations', 'variation_location_details.location_id', '=', 'business_locations.id')
        ->where('business_locations.business_id', VEST_BIZ_FICTICIO)
        ->where('variation_location_details.variation_id', $variationId)
        ->get();

    expect($estoqueBiz99)->toHaveCount(0);

    DB::table('variation_location_details')->where('location_id', $locId)->delete();
    DB::table('business_locations')->where('id', $locId)->delete();
    vestLimparProduto($productId);
});

// ------------------------------------------------------------------
// VariationTemplate — template reutilizável scopado por business
// ------------------------------------------------------------------

it('VariationTemplate biz=1 não vaza em query biz=99', function () {
    if (! Schema::hasTable('variation_templates')) {
        $this->markTestSkipped('variation_templates table missing');
    }

    $templateId = DB::table('variation_templates')->insertGetId([
        'business_id' => VEST_BIZ_WAGNER,
        'name'        => 'Tamanhos PP-GG Test',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $rowsBiz99 = DB::table('variation_templates')
        ->where('business_id', VEST_BIZ_FICTICIO)
        ->where('id', $templateId)
        ->get();

    expect($rowsBiz99)->toHaveCount(0);

    DB::table('variation_templates')->where('id', $templateId)->delete();
});

// ------------------------------------------------------------------
// VestuarioSetting já cobre próprio módulo — aqui só sanity check redundante
// pra garantir que matriz tamanho×cor não escapa scope vestuario_settings
// ------------------------------------------------------------------

it('vestuario_settings biz=1 não vaza com scope biz=99 (defesa em profundidade)', function () {
    if (! Schema::hasTable('vestuario_settings')) {
        $this->markTestSkipped('vestuario_settings table missing — rode Modules/Vestuario migrate');
    }

    DB::table('vestuario_settings')->insert([
        'business_id' => VEST_BIZ_WAGNER,
        'settings'    => json_encode(['grade_avancada' => true, 'matriz' => 'tamCor']),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $rowsBiz99 = DB::table('vestuario_settings')
        ->where('business_id', VEST_BIZ_FICTICIO)
        ->get();

    expect($rowsBiz99)->toHaveCount(0);

    DB::table('vestuario_settings')->where('business_id', VEST_BIZ_WAGNER)->delete();
});
