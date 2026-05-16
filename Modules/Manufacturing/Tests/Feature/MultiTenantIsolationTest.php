<?php

declare(strict_types=1);

use App\Concerns\HasBusinessScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Manufacturing\Entities\MfgIngredientGroup;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;

uses(Tests\TestCase::class);

/**
 * Wave 13 — D1 boost Manufacturing (adoção HasBusinessScope).
 *
 * Asserções via reflection (não tocam DB → rodam em SQLite e MySQL):
 *  - MfgIngredientGroup recebe trait HasBusinessScope (coluna business_id direta)
 *  - MfgRecipe / MfgRecipeIngredient permanecem SEM HasBusinessScope por design
 *    (tabelas SEM coluna business_id; isolamento via JOIN products.business_id —
 *    aplicar trait causaria SQL error "Unknown column"). `na_justified`.
 *
 * @see App\Concerns\HasBusinessScope
 * @see Modules\Manufacturing\Database\Migrations\2019_11_05_115136_create_ingredient_groups_table
 */
describe('Wave 13 — adoção HasBusinessScope (reflection-only)', function () {
    it('MfgIngredientGroup usa HasBusinessScope (D1 boost)', function () {
        $traits = class_uses_recursive(MfgIngredientGroup::class);

        expect(in_array(HasBusinessScope::class, $traits, true))
            ->toBeTrue('MfgIngredientGroup precisa de HasBusinessScope (ADR 0093 — tabela mfg_ingredient_groups tem business_id direto).');
    });

    it('ScopeByBusiness está registrado como global scope em MfgIngredientGroup', function () {
        $globalScopes = (new MfgIngredientGroup())->getGlobalScopes();
        expect($globalScopes)->toHaveKey(ScopeByBusiness::class);
    });

    it('MfgRecipe e MfgRecipeIngredient permanecem sem HasBusinessScope por design (na_justified)', function () {
        // Tabelas mfg_recipes / mfg_recipe_ingredients NÃO têm coluna business_id.
        // Isolamento via JOIN products.business_id (pattern legacy UltimatePOS).
        // Aplicar HasBusinessScope nessas Models causaria "Unknown column" em SQL.
        $recipeTraits = class_uses_recursive(MfgRecipe::class);
        $ingTraits = class_uses_recursive(MfgRecipeIngredient::class);

        expect(in_array(HasBusinessScope::class, $recipeTraits, true))->toBeFalse();
        expect(in_array(HasBusinessScope::class, $ingTraits, true))->toBeFalse();
    });
});

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

const BIZ_WAGNER = 1;
const BIZ_FICTICIO = 99;

describe('Isolamento multi-tenant Manufacturing (DB-dependent — MySQL only)', function () {
    // Guard SQLite + schema. UltimatePOS Manufacturing requer schema MySQL real.
    beforeEach(function () {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompatível: Manufacturing depende de schema MySQL UltimatePOS (products/variations/business). ADR 0101.');
        }
        if (! Schema::hasTable('mfg_recipes') || ! Schema::hasTable('mfg_recipe_ingredients') || ! Schema::hasTable('mfg_ingredient_groups')) {
            $this->markTestSkipped('Tabelas Manufacturing ausentes — rode Modules/Manufacturing install primeiro.');
        }
    });

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

}); // describe('Isolamento multi-tenant Manufacturing ...')
