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
 * Wave 27 — POLISH ≥90 Manufacturing (2026-05-17).
 *
 * Expande Wave 25 — testes adicionais cobrindo:
 *  - D9.a: spans novos `recipe.list_for_dropdown` + `production.average_cost`
 *    (validação source-level via Reflection — zero-cost OTel mock-friendly)
 *  - D5: customer journey biz=1 BOM com 7 ingredients (Wave 25 cobria 5)
 *  - Cross-tenant Tier 0: biz=99 vazio em todos os novos métodos
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ({@see ADR 0093}). Nunca biz=4 cliente real
 * ({@see ADR 0101}). PT-BR convenção comentários.
 *
 * @see Modules\Manufacturing\Services\RecipeBomService
 * @see Modules\Manufacturing\Services\ProductionService
 */
/**
 * Helper — testes que tocam DB precisam skip em SQLite (schema MySQL legacy UltimatePOS).
 * Testes source-level (Reflection / file_get_contents) NÃO precisam de DB.
 */
function w27ManufacturingNeedsMysql(): bool
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        return true;
    }
    if (! Schema::hasTable('mfg_recipes') || ! Schema::hasTable('mfg_recipe_ingredients')) {
        return true;
    }
    return false;
}

// Source-level (Reflection / file_get_contents) — NUNCA precisa DB
it('W27 D9.a: RecipeBomService::listForDropdown tem span manufacturing.recipe.list_for_dropdown', function () {
    $src = file_get_contents(base_path('Modules/Manufacturing/Services/RecipeBomService.php'));
    expect($src)->toContain("OtelHelper::spanBiz('manufacturing.recipe.list_for_dropdown'");
    expect($src)->toContain('by_variation_id');
});

it('W27 D9.a: ProductionService::averageProductionCost tem span manufacturing.production.average_cost', function () {
    $src = file_get_contents(base_path('Modules/Manufacturing/Services/ProductionService.php'));
    expect($src)->toContain("OtelHelper::spanBiz('manufacturing.production.average_cost'");
    expect($src)->toContain('public function averageProductionCost');
});

it('W27 D5: ProductionService::averageProductionCost retorna float biz=1', function () {
    if (w27ManufacturingNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    /** @var ProductionService $prod */
    $prod = app(ProductionService::class);
    $avg = $prod->averageProductionCost(1);

    expect($avg)->toBeFloat();
    expect($avg)->toBeGreaterThanOrEqual(0.0);
});

it('W27 D5 Tier 0: averageProductionCost(99) cross-tenant retorna 0.0', function () {
    if (w27ManufacturingNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    /** @var ProductionService $prod */
    $prod = app(ProductionService::class);
    expect($prod->averageProductionCost(99))->toBe(0.0);
});

it('W27 D5: listForDropdown biz=1 retorna collection (pode ser vazia)', function () {
    if (w27ManufacturingNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    /** @var RecipeBomService $svc */
    $svc = app(RecipeBomService::class);
    $dd = $svc->listForDropdown(1, true);

    expect($dd)->toBeInstanceOf(Illuminate\Support\Collection::class);
});

it('W27 D5 Tier 0: listForDropdown(99) cross-tenant retorna vazio', function () {
    if (w27ManufacturingNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }
    /** @var RecipeBomService $svc */
    $svc = app(RecipeBomService::class);
    $dd = $svc->listForDropdown(99, true);
    expect($dd->count())->toBe(0);
});

it('W27 D5 BOM 7 ingredients: customer journey biz=1 robusto', function () {
    if (w27ManufacturingNeedsMysql()) {
        $this->markTestSkipped('Requer MySQL UltimatePOS (ADR 0101).');
    }

    $variationBiz1 = DB::table('variations as v')
        ->join('products as p', 'p.id', '=', 'v.product_id')
        ->where('p.business_id', 1)
        ->select('v.id', 'v.product_id')
        ->first();

    if (! $variationBiz1) {
        $this->markTestSkipped('Sem variation biz=1.');
    }

    $recipe = MfgRecipe::create([
        'product_id'           => $variationBiz1->product_id,
        'variation_id'         => $variationBiz1->id,
        'instructions'         => 'wave27-bom-7-ingredients',
        'waste_percent'        => 7,
        'ingredients_cost'     => 100.0,
        'extra_cost'           => 15.0,
        'total_quantity'       => 5.0,
        'final_price'          => 50.0,
        'production_cost_type' => 'per_unit',
    ]);

    // 7 ingredientes — receita realista de produto com BOM saturado
    for ($i = 0; $i < 7; $i++) {
        MfgRecipeIngredient::create([
            'mfg_recipe_id' => $recipe->id,
            'variation_id'  => $variationBiz1->id,
            'quantity'      => 1.0 + ($i * 0.25),
            'sort_order'    => $i,
        ]);
    }

    /** @var RecipeBomService $svc */
    $svc = app(RecipeBomService::class);
    $bom = $svc->resolveBom($recipe->id, 1);

    expect($bom->count())->toBe(7);
    expect($svc->resolveBom($recipe->id, 99)->count())->toBe(0);
})->afterEach(function () {
    MfgRecipe::where('instructions', 'wave27-bom-7-ingredients')->delete();
});
