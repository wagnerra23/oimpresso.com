<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;

uses(Tests\TestCase::class);

/**
 * Testa integridade BOM (Bill of Materials) do Manufacturing — multi-tenant Tier 0.
 *
 * Schema legacy UltimatePOS: `mfg_recipe_ingredients` NÃO tem coluna `business_id`
 * direta. O isolamento depende de TRÊS invariantes:
 *
 *   1. Toda ingredient.variation_id deve apontar pra variation cujo
 *      products.business_id === parent recipe.product → products.business_id
 *      (mesma "vertical" de business — nunca cross-tenant)
 *   2. FK `mfg_recipe_id ON DELETE CASCADE` garante que apagar a recipe pai
 *      apaga os ingredients filhos — sem órfãos cross-tenant
 *   3. Pattern `MfgRecipe::forDropdown($business_id)` JOIN products.business_id
 *      é o ÚNICO caminho seguro de listar recipes — fora dele há risco
 *
 * Este test valida (1) cenário válido e (2) tentativa de cross-tenant.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules/Manufacturing/Entities/MfgRecipe.php (forDropdown)
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS MySQL obrigatório. ADR 0101.');
    }
    if (! Schema::hasTable('mfg_recipes') || ! Schema::hasTable('mfg_recipe_ingredients')) {
        $this->markTestSkipped('Tabelas Manufacturing ausentes — rode install primeiro.');
    }
});

it('cenário válido: recipe e ingredient pertencem ao mesmo business via chain products.business_id', function () {
    $variationBiz1 = DB::table('variations as v')
        ->join('products as p', 'p.id', '=', 'v.product_id')
        ->where('p.business_id', 1) // biz=1 Wagner
        ->select('v.id as variation_id', 'v.product_id', 'p.business_id')
        ->first();

    if (! $variationBiz1) {
        $this->markTestSkipped('Sem variation em biz=1 — base sem seed UltimatePOS Demo.');
    }

    $recipe = MfgRecipe::create([
        'product_id'       => $variationBiz1->product_id,
        'variation_id'     => $variationBiz1->variation_id,
        'instructions'     => 'bom-integrity-cenario-valido',
        'waste_percent'    => 0,
        'ingredients_cost' => 5.0000,
        'extra_cost'       => 0,
        'total_quantity'   => 1.0000,
        'final_price'      => 5.0000,
    ]);

    $ingredient = MfgRecipeIngredient::create([
        'mfg_recipe_id' => $recipe->id,
        'variation_id'  => $variationBiz1->variation_id, // mesma variation = mesmo biz por construção
        'quantity'      => 1.0000,
    ]);

    // Valida invariante 1: chain ingredient → variation → product.business_id
    // === chain recipe → variation → product.business_id
    $bizIngredient = DB::table('mfg_recipe_ingredients as mri')
        ->join('variations as v', 'mri.variation_id', '=', 'v.id')
        ->join('products as p', 'v.product_id', '=', 'p.id')
        ->where('mri.id', $ingredient->id)
        ->value('p.business_id');

    $bizRecipe = DB::table('mfg_recipes as r')
        ->join('variations as v', 'r.variation_id', '=', 'v.id')
        ->join('products as p', 'v.product_id', '=', 'p.id')
        ->where('r.id', $recipe->id)
        ->value('p.business_id');

    expect((int) $bizIngredient)->toBe((int) $bizRecipe);
    expect((int) $bizRecipe)->toBe(1);
})->afterEach(function () {
    MfgRecipe::where('instructions', 'bom-integrity-cenario-valido')->delete();
});

it('cascade delete: apagar recipe pai remove ingredient filho (sem órfão cross-tenant)', function () {
    $variationBiz1 = DB::table('variations as v')
        ->join('products as p', 'p.id', '=', 'v.product_id')
        ->where('p.business_id', 1)
        ->select('v.id as variation_id', 'v.product_id')
        ->first();

    if (! $variationBiz1) {
        $this->markTestSkipped('Sem variation em biz=1.');
    }

    $recipe = MfgRecipe::create([
        'product_id'       => $variationBiz1->product_id,
        'variation_id'     => $variationBiz1->variation_id,
        'instructions'     => 'bom-integrity-cascade-test',
        'waste_percent'    => 0,
        'ingredients_cost' => 0,
        'extra_cost'       => 0,
        'total_quantity'   => 1,
        'final_price'      => 1,
    ]);

    $ingredientId = MfgRecipeIngredient::create([
        'mfg_recipe_id' => $recipe->id,
        'variation_id'  => $variationBiz1->variation_id,
        'quantity'      => 0.5000,
    ])->id;

    // Antes do delete: ingredient existe
    expect(MfgRecipeIngredient::where('id', $ingredientId)->count())->toBe(1);

    // Apaga recipe pai — FK cascade deve apagar ingredient filho
    $recipe->delete();

    // Pós-delete: ingredient não existe mais (sem órfão)
    expect(MfgRecipeIngredient::where('id', $ingredientId)->count())->toBe(0);
});

it('tentativa cross-tenant: ingredient apontando pra variation de outro business gera inconsistência detectável', function () {
    // Busca variation em biz=1 pra criar a recipe
    $varBiz1 = DB::table('variations as v')
        ->join('products as p', 'p.id', '=', 'v.product_id')
        ->where('p.business_id', 1)
        ->select('v.id as variation_id', 'v.product_id')
        ->first();

    // Busca variation em outro business (qualquer biz_id != 1) pra simular cross-tenant attack
    $varOutroBiz = DB::table('variations as v')
        ->join('products as p', 'p.id', '=', 'v.product_id')
        ->where('p.business_id', '!=', 1)
        ->select('v.id as variation_id', 'v.product_id', 'p.business_id')
        ->first();

    if (! $varBiz1 || ! $varOutroBiz) {
        $this->markTestSkipped('Setup multi-tenant insuficiente — precisa de variations em ≥2 businesses.');
    }

    $recipe = MfgRecipe::create([
        'product_id'       => $varBiz1->product_id,
        'variation_id'     => $varBiz1->variation_id, // recipe ancorada em biz=1
        'instructions'     => 'bom-integrity-cross-tenant-attack',
        'waste_percent'    => 0,
        'ingredients_cost' => 0,
        'extra_cost'       => 0,
        'total_quantity'   => 1,
        'final_price'      => 1,
    ]);

    // ATTACK: criar ingredient apontando pra variation de business diferente.
    // O schema PERMITE (não há FK constraint forçando match), mas o QUERY PATTERN
    // canônico (JOIN products.business_id) detecta a inconsistência.
    $ingredientMalicioso = MfgRecipeIngredient::create([
        'mfg_recipe_id' => $recipe->id,
        'variation_id'  => $varOutroBiz->variation_id, // ← variation de OUTRO biz!
        'quantity'      => 1.0000,
    ]);

    // Detector de inconsistência: business do recipe vs business do ingredient
    $bizDoRecipe = DB::table('mfg_recipes as r')
        ->join('variations as v', 'r.variation_id', '=', 'v.id')
        ->join('products as p', 'v.product_id', '=', 'p.id')
        ->where('r.id', $recipe->id)
        ->value('p.business_id');

    $bizDoIngredient = DB::table('mfg_recipe_ingredients as mri')
        ->join('variations as v', 'mri.variation_id', '=', 'v.id')
        ->join('products as p', 'v.product_id', '=', 'p.id')
        ->where('mri.id', $ingredientMalicioso->id)
        ->value('p.business_id');

    // Esse cenário PROVA que o app DEVE rejeitar essa inserção em camada Controller/Service —
    // o schema não defende sozinho. Aqui a divergência fica visível pra auditoria.
    expect((int) $bizDoRecipe)->not->toBe((int) $bizDoIngredient);

    // Quando filtrado pelo business da recipe (1), o ingredient NÃO aparece — isolamento
    // funcional preservado mesmo com row inconsistente
    $vazamento = DB::table('mfg_recipe_ingredients as mri')
        ->join('variations as v', 'mri.variation_id', '=', 'v.id')
        ->join('products as p', 'v.product_id', '=', 'p.id')
        ->where('p.business_id', 1)
        ->where('mri.id', $ingredientMalicioso->id)
        ->count();

    expect($vazamento)->toBe(0);
})->afterEach(function () {
    MfgRecipe::where('instructions', 'bom-integrity-cross-tenant-attack')->delete();
});
