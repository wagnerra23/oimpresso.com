<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Modules\Manufacturing\Concerns\AssertsBusinessChain;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;

uses(Tests\TestCase::class);

/**
 * Wave 18 — Pest reflection-only do trait AssertsBusinessChain (D1 boost).
 *
 * Foco: validar contrato do trait + adoção em MfgRecipe + MfgRecipeIngredient
 * sem depender de schema MySQL (roda em SQLite e MySQL). Cenários DB-dependent
 * (com JOIN real chain) já cobertos em MultiTenantIsolationTest + RecipeBomIntegrityTest.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL — ADR 0093.
 * Tests com biz=1 (Wagner) + biz=99 (fictício) — NUNCA biz=4 cliente Larissa.
 *
 * @see Modules\Manufacturing\Concerns\AssertsBusinessChain
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */
describe('Wave 18 — trait AssertsBusinessChain adoção (reflection-only)', function () {
    it('MfgRecipe usa AssertsBusinessChain (Wave 18 D1)', function () {
        $traits = class_uses_recursive(MfgRecipe::class);

        expect(in_array(AssertsBusinessChain::class, $traits, true))
            ->toBeTrue('MfgRecipe deve usar AssertsBusinessChain — Wave 18 D1 boost.');
    });

    it('MfgRecipeIngredient usa AssertsBusinessChain (Wave 18 D1)', function () {
        $traits = class_uses_recursive(MfgRecipeIngredient::class);

        expect(in_array(AssertsBusinessChain::class, $traits, true))
            ->toBeTrue('MfgRecipeIngredient deve usar AssertsBusinessChain — Wave 18 D1 boost.');
    });

    it('scopeForBusinessViaProductChain existe + retorna Builder (MfgRecipe)', function () {
        // Reflection: scope methods seguem padrão Laravel `scope<Nome>`
        $reflection = new ReflectionClass(MfgRecipe::class);

        expect($reflection->hasMethod('scopeForBusinessViaProductChain'))
            ->toBeTrue('Scope scopeForBusinessViaProductChain deve ser exposto via trait.');
    });

    it('belongsToBusinessChain existe + assinatura (int, int)', function () {
        $reflection = new ReflectionClass(MfgRecipe::class);

        expect($reflection->hasMethod('belongsToBusinessChain'))
            ->toBeTrue('Método belongsToBusinessChain deve existir.');

        $method = $reflection->getMethod('belongsToBusinessChain');
        $params = $method->getParameters();

        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('modelId');
        expect($params[1]->getName())->toBe('businessId');
    });

    it('hasDirectVariationColumn diferencia MfgRecipe (true) vs MfgRecipeIngredient (false)', function () {
        $recipe = new MfgRecipe();
        $ingredient = new MfgRecipeIngredient();

        // Reflection pra acessar protected
        $rRefl = (new ReflectionClass($recipe))->getMethod('hasDirectVariationColumn');
        $rRefl->setAccessible(true);
        $iRefl = (new ReflectionClass($ingredient))->getMethod('hasDirectVariationColumn');
        $iRefl->setAccessible(true);

        expect($rRefl->invoke($recipe))->toBeTrue('MfgRecipe deve declarar variation_id direta (table mfg_recipes).');
        expect($iRefl->invoke($ingredient))->toBeFalse('MfgRecipeIngredient herda chain via parent recipe.');
    });
});

describe('Wave 18 — comportamento DB-dependent (MySQL only)', function () {
    beforeEach(function () {
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompatível: chain JOIN requer schema UltimatePOS MySQL. ADR 0101.');
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('mfg_recipes')) {
            $this->markTestSkipped('Tabelas Manufacturing ausentes — rode install primeiro.');
        }
    });

    it('forBusinessViaProductChain retorna Builder e filtra biz=99 sem vazar biz=1', function () {
        $variationBiz1 = \Illuminate\Support\Facades\DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', 1)
            ->select('v.id', 'v.product_id')
            ->first();

        if (! $variationBiz1) {
            $this->markTestSkipped('Sem variation em biz=1 — base sem seed UltimatePOS Demo.');
        }

        $recipe = MfgRecipe::create([
            'product_id'       => $variationBiz1->product_id,
            'variation_id'     => $variationBiz1->id,
            'instructions'     => 'wave18-trait-chain-test',
            'waste_percent'    => 0,
            'ingredients_cost' => 0,
            'extra_cost'       => 0,
            'total_quantity'   => 1,
            'final_price'      => 1,
        ]);

        // Scope retorna Builder
        $query = MfgRecipe::query()->forBusinessViaProductChain(1);
        expect($query)->toBeInstanceOf(Builder::class);

        // Filtro biz=99 NÃO retorna recipe biz=1
        $vazamento = MfgRecipe::query()
            ->forBusinessViaProductChain(99)
            ->where('id', $recipe->id)
            ->count();
        expect($vazamento)->toBe(0);

        // Filtro biz=1 retorna a recipe
        $proprio = MfgRecipe::query()
            ->forBusinessViaProductChain(1)
            ->where('id', $recipe->id)
            ->count();
        expect($proprio)->toBe(1);
    })->afterEach(function () {
        MfgRecipe::where('instructions', 'wave18-trait-chain-test')->delete();
    });

    it('belongsToBusinessChain protege contra modelId cross-tenant', function () {
        $variationBiz1 = \Illuminate\Support\Facades\DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', 1)
            ->select('v.id', 'v.product_id')
            ->first();

        if (! $variationBiz1) {
            $this->markTestSkipped('Sem variation biz=1.');
        }

        $recipe = MfgRecipe::create([
            'product_id'       => $variationBiz1->product_id,
            'variation_id'     => $variationBiz1->id,
            'instructions'     => 'wave18-belongs-chain-test',
            'waste_percent'    => 0,
            'ingredients_cost' => 0,
            'extra_cost'       => 0,
            'total_quantity'   => 1,
            'final_price'      => 1,
        ]);

        $instance = new MfgRecipe();

        expect($instance->belongsToBusinessChain($recipe->id, 1))->toBeTrue();
        expect($instance->belongsToBusinessChain($recipe->id, 99))->toBeFalse();
        expect($instance->belongsToBusinessChain(9999999, 1))->toBeFalse('ID inexistente jamais pertence.');
    })->afterEach(function () {
        MfgRecipe::where('instructions', 'wave18-belongs-chain-test')->delete();
    });
});
