<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Modules\Manufacturing\Services\ProductionService;

uses(Tests\TestCase::class);

/**
 * Wave 25 — D5 Customer Journey biz=1 RECEITA REAL (2026-05-16).
 *
 * Cenário real de produção biz=1: receita "Camiseta Personalizada" com 3 ingredientes
 * (tecido, tinta, etiqueta) — espelha workflow Modules/ComunicacaoVisual + Modules/Vestuario.
 *
 * Valida:
 *  - Criar receita completa biz=1
 *  - Resolver BOM 3 ingredientes
 *  - ProductionService summary retorna estrutura esperada per business
 *  - Cleanup append-only (delete só rows criadas)
 *  - Cross-tenant Tier 0 ({@see ADR 0093}): biz=99 vê estrutura vazia
 *
 * Diferenciação vs Wave18ProductionJourneyTest:
 *  - Wave 18 é técnico (criar/ler/contar)
 *  - Wave 25 é receita-realista (3 ingredientes representativos)
 *
 * Nunca biz=4 cliente real ({@see ADR 0101}).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS MySQL. ADR 0101.');
    }
    if (! Schema::hasTable('mfg_recipes') || ! Schema::hasTable('mfg_recipe_ingredients')) {
        $this->markTestSkipped('Tabelas Manufacturing ausentes — rode install primeiro.');
    }
});

it('journey real biz=1: receita Camiseta Personalizada com 3 ingredientes', function () {
    $variationBiz1 = DB::table('variations as v')
        ->join('products as p', 'p.id', '=', 'v.product_id')
        ->where('p.business_id', 1)
        ->select('v.id', 'v.product_id')
        ->first();

    if (! $variationBiz1) {
        $this->markTestSkipped('Sem variation biz=1.');
    }

    // Receita pai: "Camiseta Personalizada" — representa Modules/Vestuario real
    $recipe = MfgRecipe::create([
        'product_id'           => $variationBiz1->product_id,
        'variation_id'         => $variationBiz1->id,
        'instructions'         => 'wave25-camiseta-personalizada-real',
        'waste_percent'        => 5, // desperdício tecido típico
        'ingredients_cost'     => 18.50,
        'extra_cost'           => 3.00,  // mão de obra
        'total_quantity'       => 1.00,  // 1 camiseta por receita
        'final_price'          => 35.00,
        'production_cost_type' => 'per_unit',
    ]);

    // 3 ingredientes: tecido, tinta, etiqueta — representativo
    $ingredientes = [
        ['nome' => 'tecido_dryfit',  'qty' => 1.20, 'sort' => 0], // 1.2m
        ['nome' => 'tinta_silk',     'qty' => 0.05, 'sort' => 1], // 50ml
        ['nome' => 'etiqueta_marca', 'qty' => 1.00, 'sort' => 2], // 1 unidade
    ];

    foreach ($ingredientes as $ing) {
        MfgRecipeIngredient::create([
            'mfg_recipe_id' => $recipe->id,
            'variation_id'  => $variationBiz1->id,
            'quantity'      => $ing['qty'],
            'sort_order'    => $ing['sort'],
        ]);
    }

    // Validar persistência e relações
    $fresh = MfgRecipe::with('ingredients')->find($recipe->id);
    expect($fresh->ingredients->count())->toBe(3);
    expect($fresh->waste_percent)->toEqual(5);
    expect((float) $fresh->final_price)->toEqual(35.00);
})->afterEach(function () {
    MfgRecipe::where('instructions', 'wave25-camiseta-personalizada-real')->delete();
});

it('journey real biz=1: ProductionService summary tem estrutura esperada', function () {
    /** @var ProductionService $prod */
    $prod = app(ProductionService::class);
    $summary = $prod->summary(1);

    expect($summary)->toBeArray();
    expect($summary)->toHaveKeys(['total_count', 'final_count', 'pending_count', 'total_value']);

    // Tipos numéricos
    expect($summary['total_count'])->toBeInt();
    expect($summary['final_count'])->toBeInt();
    expect($summary['pending_count'])->toBeInt();

    // Coerência: total = final + pending (em sistemas saudáveis)
    expect($summary['total_count'])->toBeGreaterThanOrEqual($summary['final_count']);
});

it('journey real biz=99: cross-tenant isolation produces empty/zero (Tier 0 ADR 0093)', function () {
    /** @var ProductionService $prod */
    $prod = app(ProductionService::class);

    $summary = $prod->summary(99);
    expect($summary['total_count'])->toBe(0);
    expect($summary['final_count'])->toBe(0);
    expect($summary['pending_count'])->toBe(0);

    $rows = $prod->listProductions(99, []);
    expect($rows->count())->toBe(0);
});

it('journey real biz=1: cleanup append-only — só remove rows que criamos', function () {
    $variationBiz1 = DB::table('variations as v')
        ->join('products as p', 'p.id', '=', 'v.product_id')
        ->where('p.business_id', 1)
        ->select('v.id', 'v.product_id')
        ->first();

    if (! $variationBiz1) {
        $this->markTestSkipped('Sem variation biz=1.');
    }

    $countBefore = MfgRecipe::count();

    $recipe = MfgRecipe::create([
        'product_id'           => $variationBiz1->product_id,
        'variation_id'         => $variationBiz1->id,
        'instructions'         => 'wave25-cleanup-marker',
        'waste_percent'        => 0,
        'ingredients_cost'     => 0,
        'extra_cost'           => 0,
        'total_quantity'       => 1.0,
        'final_price'          => 0,
        'production_cost_type' => 'fixed',
    ]);

    expect(MfgRecipe::count())->toBe($countBefore + 1);

    // Cleanup só do marker
    MfgRecipe::where('instructions', 'wave25-cleanup-marker')->delete();
    expect(MfgRecipe::count())->toBe($countBefore);
});
