<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgIngredientGroup;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;

uses(Tests\TestCase::class);

/**
 * Testa isolamento multi-tenant Tier 0 do módulo Manufacturing.
 *
 * Contexto schema legacy UltimatePOS (NÃO há global scope BusinessScope nessas Models):
 *
 *   - mfg_recipes               → isolamento INDIRETO via products.business_id (JOIN variation→product)
 *   - mfg_recipe_ingredients    → isolamento INDIRETO via parent recipe (FK cascade)
 *   - mfg_ingredient_groups     → isolamento DIRETO via coluna business_id
 *
 * ADR 0093: business_id Tier 0 IRREVOGÁVEL. Cobertura por filtragem explícita
 * `where('business_id', $bizId)` ou JOIN `products.business_id`, que é o padrão
 * usado em `MfgRecipe::forDropdown()` e `ProductionController::index()`.
 *
 * NUNCA usar biz=4 (ROTA LIVRE — cliente Larissa em produção) — conforme ADR 0101.
 * Tests usam biz=1 (Wagner WR2) e biz=99 (fictício, sem dados reais).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

// Guard SQLite + schema. UltimatePOS Manufacturing requer schema MySQL real.
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Manufacturing depende de schema MySQL UltimatePOS (products/variations/business). ADR 0101.');
    }
    if (! Schema::hasTable('mfg_recipes') || ! Schema::hasTable('mfg_recipe_ingredients') || ! Schema::hasTable('mfg_ingredient_groups')) {
        $this->markTestSkipped('Tabelas Manufacturing ausentes — rode Modules/Manufacturing install primeiro.');
    }
});

const BIZ_WAGNER = 1;
const BIZ_FICTICIO = 99;

// ------------------------------------------------------------------
// MfgIngredientGroup — isolamento DIRETO via coluna business_id
// ------------------------------------------------------------------

it('MfgIngredientGroup biz=1 não aparece quando filtrado por biz=99', function () {
    $group = MfgIngredientGroup::create([ // SUPERADMIN: inserção direta de teste (sem global scope na Model)
        'business_id' => BIZ_WAGNER,
        'name'        => 'Grupo Teste Isolamento Wagner',
        'description' => 'Grupo do biz=1 que NÃO pode aparecer em queries do biz=99',
    ]);

    // Filtro explícito biz=99 — NÃO deve retornar o registro de biz=1
    $resultado = MfgIngredientGroup::where('business_id', BIZ_FICTICIO)
        ->where('id', $group->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    MfgIngredientGroup::where('name', 'Grupo Teste Isolamento Wagner')->delete();
});

it('MfgIngredientGroup biz=1 aparece quando filtrado por biz=1', function () {
    $group = MfgIngredientGroup::create([ // SUPERADMIN: inserção direta de teste
        'business_id' => BIZ_WAGNER,
        'name'        => 'Grupo Teste Visivel Wagner',
        'description' => 'Confirma que filtro biz=1 retorna registro próprio',
    ]);

    $resultado = MfgIngredientGroup::where('business_id', BIZ_WAGNER)
        ->where('id', $group->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->name)->toBe('Grupo Teste Visivel Wagner');
    expect((int) $resultado->first()->business_id)->toBe(BIZ_WAGNER);
})->afterEach(function () {
    MfgIngredientGroup::where('name', 'Grupo Teste Visivel Wagner')->delete();
});

it('listagem MfgIngredientGroup com filtro biz=99 não vaza grupos do biz=1', function () {
    // Insere 3 grupos pra biz=1
    foreach (['Lote-Iso-A', 'Lote-Iso-B', 'Lote-Iso-C'] as $nome) {
        MfgIngredientGroup::create([ // SUPERADMIN: setup de cenário
            'business_id' => BIZ_WAGNER,
            'name'        => $nome,
            'description' => 'lote-isolamento',
        ]);
    }

    // Query "as if biz=99" — escopo correto não pode pegar nada
    $vazamento = MfgIngredientGroup::where('business_id', BIZ_FICTICIO)
        ->where('description', 'lote-isolamento')
        ->count();

    expect($vazamento)->toBe(0);
})->afterEach(function () {
    MfgIngredientGroup::where('description', 'lote-isolamento')->delete();
});

// ------------------------------------------------------------------
// MfgRecipe — isolamento INDIRETO via products.business_id (JOIN chain)
// ------------------------------------------------------------------

it('MfgRecipe pertence a apenas um business via chain variation→product.business_id', function () {
    // Pré-condição: existe pelo menos 1 product em biz=1 com variation pra cravar a recipe.
    $variationBiz1 = DB::table('variations as v')
        ->join('products as p', 'p.id', '=', 'v.product_id')
        ->where('p.business_id', BIZ_WAGNER)
        ->select('v.id', 'p.business_id')
        ->first();

    if (! $variationBiz1) {
        $this->markTestSkipped('Sem variation em biz=1 — seed UltimatePOS Demo ausente pra esse teste de chain isolation.');
    }

    $recipe = MfgRecipe::create([
        'product_id'       => DB::table('variations')->where('id', $variationBiz1->id)->value('product_id'),
        'variation_id'     => $variationBiz1->id,
        'instructions'     => 'recipe-teste-isolamento-chain',
        'waste_percent'    => 0,
        'ingredients_cost' => 10.0000,
        'extra_cost'       => 0,
        'total_quantity'   => 1.0000,
        'final_price'      => 10.0000,
    ]);

    // Pattern usado em MfgRecipe::forDropdown() — JOIN explícito com p.business_id
    $vazamento = MfgRecipe::join('variations as v', 'mfg_recipes.variation_id', '=', 'v.id')
        ->join('products as p', 'v.product_id', '=', 'p.id')
        ->where('p.business_id', BIZ_FICTICIO)
        ->where('mfg_recipes.id', $recipe->id)
        ->count();

    expect($vazamento)->toBe(0);

    // Mas com filtro biz=1 a recipe aparece
    $proprio = MfgRecipe::join('variations as v', 'mfg_recipes.variation_id', '=', 'v.id')
        ->join('products as p', 'v.product_id', '=', 'p.id')
        ->where('p.business_id', BIZ_WAGNER)
        ->where('mfg_recipes.id', $recipe->id)
        ->count();

    expect($proprio)->toBe(1);
})->afterEach(function () {
    MfgRecipe::where('instructions', 'recipe-teste-isolamento-chain')->delete();
});

// ------------------------------------------------------------------
// MfgRecipeIngredient — isolamento INDIRETO via parent recipe (FK cascade)
// ------------------------------------------------------------------

it('MfgRecipeIngredient herda isolamento via parent MfgRecipe (cascade)', function () {
    $variationBiz1 = DB::table('variations as v')
        ->join('products as p', 'p.id', '=', 'v.product_id')
        ->where('p.business_id', BIZ_WAGNER)
        ->select('v.id', 'v.product_id', 'p.business_id')
        ->first();

    if (! $variationBiz1) {
        $this->markTestSkipped('Sem variation em biz=1 pra cravar parent recipe.');
    }

    $recipe = MfgRecipe::create([
        'product_id'       => $variationBiz1->product_id,
        'variation_id'     => $variationBiz1->id,
        'instructions'     => 'recipe-teste-ingredient-cascade',
        'waste_percent'    => 0,
        'ingredients_cost' => 0,
        'extra_cost'       => 0,
        'total_quantity'   => 1,
        'final_price'      => 1,
    ]);

    $ingredient = MfgRecipeIngredient::create([
        'mfg_recipe_id' => $recipe->id,
        'variation_id'  => $variationBiz1->id,
        'quantity'      => 2.5000,
    ]);

    // Ingredient com chain biz=99 NÃO retorna
    $vazamento = MfgRecipeIngredient::join('mfg_recipes as r', 'mfg_recipe_ingredients.mfg_recipe_id', '=', 'r.id')
        ->join('variations as v', 'r.variation_id', '=', 'v.id')
        ->join('products as p', 'v.product_id', '=', 'p.id')
        ->where('p.business_id', BIZ_FICTICIO)
        ->where('mfg_recipe_ingredients.id', $ingredient->id)
        ->count();

    expect($vazamento)->toBe(0);

    // Com biz=1, aparece
    $proprio = MfgRecipeIngredient::join('mfg_recipes as r', 'mfg_recipe_ingredients.mfg_recipe_id', '=', 'r.id')
        ->join('variations as v', 'r.variation_id', '=', 'v.id')
        ->join('products as p', 'v.product_id', '=', 'p.id')
        ->where('p.business_id', BIZ_WAGNER)
        ->where('mfg_recipe_ingredients.id', $ingredient->id)
        ->count();

    expect($proprio)->toBe(1);
})->afterEach(function () {
    // FK cascade delete já remove os ingredients quando a recipe é apagada
    MfgRecipe::where('instructions', 'recipe-teste-ingredient-cascade')->delete();
});
