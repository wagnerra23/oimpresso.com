<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Modules\Manufacturing\Services\ProductionService;
use Modules\Manufacturing\Services\RecipeBomService;

uses(Tests\TestCase::class);

/**
 * Wave 18 — D5 Journey real biz=1 Manufacturing (2026-05-16).
 *
 * Cobre fluxo end-to-end: criar Recipe → adicionar Ingredients → resolver BOM →
 * calcular custo total + unitário → list produções → summary agregado. Validado
 * com isolamento biz=1 vs biz=99 (ADR 0101 — nunca biz=4 cliente).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ({@see ADR 0093}). Não vaza recipe biz=1 quando
 * RecipeBomService.resolveBom é chamado com businessId=99.
 *
 * @see Modules\Manufacturing\Services\RecipeBomService
 * @see Modules\Manufacturing\Services\ProductionService
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS MySQL. ADR 0101.');
    }
    if (! Schema::hasTable('mfg_recipes') || ! Schema::hasTable('mfg_recipe_ingredients')) {
        $this->markTestSkipped('Tabelas Manufacturing ausentes — rode install primeiro.');
    }
});

it('journey biz=1: criar recipe + 2 ingredients + resolver BOM via Service', function () {
    $variationBiz1 = DB::table('variations as v')
        ->join('products as p', 'p.id', '=', 'v.product_id')
        ->where('p.business_id', 1)
        ->select('v.id', 'v.product_id')
        ->first();

    if (! $variationBiz1) {
        $this->markTestSkipped('Sem variation biz=1.');
    }

    // Cria recipe pai
    $recipe = MfgRecipe::create([
        'product_id'       => $variationBiz1->product_id,
        'variation_id'     => $variationBiz1->id,
        'instructions'     => 'wave18-journey-bom',
        'waste_percent'    => 0,
        'ingredients_cost' => 15.0000,
        'extra_cost'       => 5.0000,
        'total_quantity'   => 10.0000,
        'final_price'      => 20.0000,
        'production_cost_type' => 'fixed',
    ]);

    // 2 ingredients via Eloquent — usa variation biz=1 mesmo
    MfgRecipeIngredient::create([
        'mfg_recipe_id' => $recipe->id,
        'variation_id'  => $variationBiz1->id,
        'quantity'      => 2.0000,
        'sort_order'    => 0,
    ]);
    MfgRecipeIngredient::create([
        'mfg_recipe_id' => $recipe->id,
        'variation_id'  => $variationBiz1->id,
        'quantity'      => 3.0000,
        'sort_order'    => 1,
    ]);

    // Service resolver BOM biz=1 → deve retornar 2
    /** @var RecipeBomService $svc */
    $svc = app(RecipeBomService::class);
    $bom = $svc->resolveBom($recipe->id, 1);
    expect($bom->count())->toBe(2);

    // Mesma chamada biz=99 → vazio (Tier 0)
    $bomVazio = $svc->resolveBom($recipe->id, 99);
    expect($bomVazio->count())->toBe(0);
})->afterEach(function () {
    MfgRecipe::where('instructions', 'wave18-journey-bom')->delete();
});

it('journey biz=1: calculateCost + calculateUnitCost respeitam total_quantity', function () {
    $variationBiz1 = DB::table('variations as v')
        ->join('products as p', 'p.id', '=', 'v.product_id')
        ->where('p.business_id', 1)
        ->select('v.id', 'v.product_id')
        ->first();

    if (! $variationBiz1) {
        $this->markTestSkipped('Sem variation biz=1.');
    }

    // Custo dinâmico depende de variation.dpp_inc_tax — sem isso, custo=0 mas service não quebra
    $recipe = MfgRecipe::create([
        'product_id'       => $variationBiz1->product_id,
        'variation_id'     => $variationBiz1->id,
        'instructions'     => 'wave18-journey-cost',
        'waste_percent'    => 0,
        'ingredients_cost' => 0,
        'extra_cost'       => 10.0000, // fixed cost
        'total_quantity'   => 5.0000,
        'final_price'      => 0,
        'production_cost_type' => 'fixed',
    ]);

    MfgRecipeIngredient::create([
        'mfg_recipe_id' => $recipe->id,
        'variation_id'  => $variationBiz1->id,
        'quantity'      => 1.0000,
        'sort_order'    => 0,
    ]);

    /** @var RecipeBomService $svc */
    $svc = app(RecipeBomService::class);
    $recipe->load('ingredients.variation', 'ingredients.sub_unit');
    $cost = $svc->calculateCost($recipe);
    $unit = $svc->calculateUnitCost($recipe);

    // Custo total ≥ extra_cost (5) — independe de dpp_inc_tax existir
    expect($cost)->toBeGreaterThanOrEqual(10.0);
    expect($unit)->toBe($cost / 5.0);
})->afterEach(function () {
    MfgRecipe::where('instructions', 'wave18-journey-cost')->delete();
});

it('journey biz=1: ProductionService::summary retorna agregado per business', function () {
    /** @var ProductionService $prod */
    $prod = app(ProductionService::class);
    $summary = $prod->summary(1);

    expect($summary)->toBeArray();
    expect($summary)->toHaveKeys(['total_count', 'final_count', 'pending_count', 'total_value']);
    expect($summary['total_count'])->toBeInt();
    expect($summary['final_count'])->toBeInt();
    expect($summary['pending_count'])->toBeInt();
});

it('journey: ProductionService::listProductions biz=99 retorna vazio (isolamento)', function () {
    /** @var ProductionService $prod */
    $prod = app(ProductionService::class);
    $rows = $prod->listProductions(99, []);

    expect($rows->count())->toBe(0); // biz=99 fictício — sem productions
});
