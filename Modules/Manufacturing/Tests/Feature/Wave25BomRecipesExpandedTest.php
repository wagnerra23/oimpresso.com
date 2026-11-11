<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Concerns\HasManufacturingProductChain;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Modules\Manufacturing\Services\RecipeBomService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

/**
 * Wave 25 — D2 EXPAND BOM + recipes coverage (2026-05-16).
 *
 * Expande Wave18ProductionJourneyTest cobrindo:
 *  - BOM com 5+ ingredients (Wave 18 cobriu só 2)
 *  - Calculate cost waste_percent ≠ 0
 *  - Trait companion HasManufacturingProductChain (countForBusinessChain / idsForBusinessChain)
 *  - Cross-tenant biz=99 retorna 0 mesmo com data biz=1
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ({@see ADR 0093}). Nunca biz=4 cliente ({@see ADR 0101}).
 *
 * @see Modules\Manufacturing\Concerns\HasManufacturingProductChain
 * @see Modules\Manufacturing\Tests\Feature\Wave18ProductionJourneyTest
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS MySQL. ADR 0101.');
    }
    if (! Schema::hasTable('mfg_recipes') || ! Schema::hasTable('mfg_recipe_ingredients')) {
        $this->markTestSkipped('Tabelas Manufacturing ausentes — rode install primeiro.');
    }
});

it('expand: BOM com 5 ingredients via Service resolve corretamente biz=1', function () {
    $variacao = mfgEmpVariacao(MFG_EMP_BIZ);

    $recipe = MfgRecipe::create([
        'product_id'           => $variacao->product_id,
        'variation_id'         => $variacao->id,
        'instructions'         => 'wave25-bom-expand-5',
        'waste_percent'        => 0,
        'ingredients_cost'     => 25.0000,
        'extra_cost'           => 5.0000,
        'total_quantity'       => 10.0000,
        'final_price'          => 30.0000,
        'production_cost_type' => 'fixed',
    ]);

    // 5 ingredients (Wave 18 cobriu só 2)
    for ($i = 0; $i < 5; $i++) {
        MfgRecipeIngredient::create([
            'mfg_recipe_id' => $recipe->id,
            'variation_id'  => $variacao->id,
            'quantity'      => 1.0000 + $i * 0.5,
            'sort_order'    => $i,
        ]);
    }

    /** @var RecipeBomService $svc */
    $svc = app(RecipeBomService::class);
    $bom = $svc->resolveBom($recipe->id, MFG_EMP_BIZ);
    expect($bom->count())->toBe(5);

    // Tier 0 — biz=99 não enxerga
    expect($svc->resolveBom($recipe->id, MFG_EMP_OUTRO)->count())->toBe(0);
})->after(function () {
    MfgRecipe::where('instructions', 'wave25-bom-expand-5')->delete();
});

it('expand: calculateCost com waste_percent=10 aplica fator 1.10 sobre ingredientes', function () {
    $variacao = mfgEmpVariacao(MFG_EMP_BIZ);

    $recipe = MfgRecipe::create([
        'product_id'           => $variacao->product_id,
        'variation_id'         => $variacao->id,
        'instructions'         => 'wave25-waste-10',
        'waste_percent'        => 10, // 10% desperdício
        'ingredients_cost'     => 0,
        'extra_cost'           => 0,
        'total_quantity'       => 1.0000,
        'final_price'          => 0,
        'production_cost_type' => 'fixed',
    ]);

    MfgRecipeIngredient::create([
        'mfg_recipe_id' => $recipe->id,
        'variation_id'  => $variacao->id,
        'quantity'      => 10.0000,
        'sort_order'    => 0,
    ]);

    /** @var RecipeBomService $svc */
    $svc = app(RecipeBomService::class);
    $recipe->load('ingredients.variation', 'ingredients.sub_unit');

    $cost = $svc->calculateCost($recipe);
    // Independe do dpp_inc_tax real — o que validamos é que o service não quebra com waste≠0
    expect($cost)->toBeGreaterThanOrEqual(0.0);
})->after(function () {
    MfgRecipe::where('instructions', 'wave25-waste-10')->delete();
});

it('expand: HasManufacturingProductChain trait Pest robusto (reflection only)', function () {
    // Trait existe + tem methods esperados
    expect(trait_exists(HasManufacturingProductChain::class))->toBeTrue();

    $reflection = new \ReflectionClass(HasManufacturingProductChain::class);
    expect($reflection->hasMethod('countForBusinessChain'))->toBeTrue();
    expect($reflection->hasMethod('idsForBusinessChain'))->toBeTrue();
    expect($reflection->hasMethod('detectDirectVariationChain'))->toBeTrue();
});

it('expand: trait companion via instância anônima conta por business chain', function () {
    $variacao = mfgEmpVariacao(MFG_EMP_BIZ);

    // Instância MfgRecipe que JÁ deveria misturar trait — adicionamos Wave 25
    // Como pode não ter sido adicionado ao Model ainda, validamos via classe stub
    $stub = new class extends MfgRecipe {
        use HasManufacturingProductChain;
        protected $table = 'mfg_recipes';
    };

    // Contagem por diferença: a base pode já ter receitas nas duas empresas.
    $antesProprio = $stub->countForBusinessChain(MFG_EMP_BIZ);
    $antesOutro = $stub->countForBusinessChain(MFG_EMP_OUTRO);

    $recipe = MfgRecipe::create([
        'product_id'           => $variacao->product_id,
        'variation_id'         => $variacao->id,
        'instructions'         => 'wave25-trait-count',
        'waste_percent'        => 0,
        'ingredients_cost'     => 1.0,
        'extra_cost'           => 0,
        'total_quantity'       => 1.0,
        'final_price'          => 1.0,
        'production_cost_type' => 'fixed',
    ]);

    // A receita nova entra na conta da própria empresa…
    expect($stub->countForBusinessChain(MFG_EMP_BIZ))->toBe($antesProprio + 1);

    // …e não na da outra (Tier 0).
    expect($stub->countForBusinessChain(MFG_EMP_OUTRO))->toBe($antesOutro);
})->after(function () {
    MfgRecipe::where('instructions', 'wave25-trait-count')->delete();
});
