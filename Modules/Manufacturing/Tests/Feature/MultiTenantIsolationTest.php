<?php

declare(strict_types=1);

use App\Concerns\HasBusinessScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Scopes\ScopeByBusiness;
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

defined('BIZ_WAGNER') || define('BIZ_WAGNER', 1);
defined('BIZ_FICTICIO') || define('BIZ_FICTICIO', 99);

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

// Até 2026-11-11 estes três casos filtravam `where('business_id', 99)` no próprio teste: provavam
// que o SQL filtra, não que a aplicação isola. Agora a consulta vai SEM filtro, como o código do
// módulo faz, e quem recorta é o ScopeByBusiness do model (sessão de um usuário da empresa 98).
// Âncora positiva: o grupo da própria empresa aparece pela mesma consulta.

if (! function_exists('mfgIsoGrupo')) {
    function mfgIsoGrupo(int $biz, string $nome): int
    {
        // SUPERADMIN: fixture grava direto, a sessão ainda não existe.
        return (int) MfgIngredientGroup::withoutGlobalScopes()->insertGetId([
            'business_id' => $biz, 'name' => $nome, 'description' => 'mfg-iso-grupo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

if (! function_exists('mfgIsoEntrarNa98')) {
    function mfgIsoEntrarNa98($test): void
    {
        $dono = (int) DB::table('users')->min('id');
        if (! DB::table('business')->where('id', 98)->exists()) {
            (new App\Business)->forceFill(['id' => 98, 'name' => 'Tenant fictício mfg 98', 'currency_id' => 1, 'owner_id' => $dono])->save();
        }
        $user = App\User::firstOrCreate(['username' => 'mfg_iso_grupo_98'], [
            'email' => 'mfg_iso_grupo_98@test.local', 'password' => bcrypt('secret'),
            'business_id' => 98, 'first_name' => 'Iso', 'last_name' => 'Grupo',
            'user_type' => 'user', 'allow_login' => 1,
        ]);
        $test->actingAs($user);
        session(['user.business_id' => 98]);
    }
}

it('MfgIngredientGroup de outra empresa não aparece na consulta sem filtro da empresa 98', function () {
    $alheio = mfgIsoGrupo(BIZ_FICTICIO, 'Grupo Iso Alheio');
    $proprio = mfgIsoGrupo(98, 'Grupo Iso Proprio');
    mfgIsoEntrarNa98($this);

    expect(MfgIngredientGroup::find($alheio))->toBeNull();
    expect(MfgIngredientGroup::find($proprio))->not->toBeNull();
})->after(function () {
    // SUPERADMIN: limpeza de teste, as duas empresas.
    MfgIngredientGroup::withoutGlobalScopes()->where('description', 'mfg-iso-grupo')->delete();
});

it('MfgIngredientGroup da própria empresa aparece com o business certo', function () {
    $proprio = mfgIsoGrupo(98, 'Grupo Iso Visivel');
    mfgIsoEntrarNa98($this);

    $resultado = MfgIngredientGroup::where('id', $proprio)->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->name)->toBe('Grupo Iso Visivel');
    expect((int) $resultado->first()->business_id)->toBe(98);
})->after(function () {
    // SUPERADMIN: limpeza de teste.
    MfgIngredientGroup::withoutGlobalScopes()->where('description', 'mfg-iso-grupo')->delete();
});

it('listagem MfgIngredientGroup da empresa 98 não traz grupos da 99', function () {
    $alheios = [];
    foreach (['Lote-Iso-A', 'Lote-Iso-B', 'Lote-Iso-C'] as $nome) {
        $alheios[] = mfgIsoGrupo(BIZ_FICTICIO, $nome);
    }
    $proprio = mfgIsoGrupo(98, 'Lote-Iso-Proprio');
    mfgIsoEntrarNa98($this);

    $ids = MfgIngredientGroup::where('description', 'mfg-iso-grupo')->pluck('id')->map(fn ($i) => (int) $i)->all();

    expect($ids)->toBe([$proprio]);
    expect(array_intersect($ids, $alheios))->toBe([]);
})->after(function () {
    // SUPERADMIN: limpeza de teste.
    MfgIngredientGroup::withoutGlobalScopes()->where('description', 'mfg-iso-grupo')->delete();
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
})->after(function () {
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
})->after(function () {
    // FK cascade delete já remove os ingredients quando a recipe é apagada
    MfgRecipe::where('instructions', 'recipe-teste-ingredient-cascade')->delete();
});

}); // describe('Isolamento multi-tenant Manufacturing ...')
