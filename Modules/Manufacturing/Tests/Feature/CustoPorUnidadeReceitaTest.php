<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Services\RecipeBomService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * "Salvar receita" aceita o custo de produção "Por unidade".
 *
 * O formulário de ingredientes (`recipe/add_ingredients.blade.php`) oferece três formas de custo:
 * Fixo, Percentual e Por unidade. O cálculo também conhece as três — na tela (JS de
 * `common_script.blade.php`) e no servidor (`RecipeBomService::calculateCost`). Mas o
 * `StoreRecipeRequest`, o único caminho que grava a receita, aceitava só `fixed,percentage`:
 * escolher "Por unidade" voltava com erro e a receita não era salva. Receita antiga já gravada
 * com "Por unidade" também não podia mais ser editada, porque o formulário vem com a opção marcada.
 *
 * Os valores do caso foram escolhidos para separar as três fórmulas: 1 insumo de custo 4 × 3 = 12,
 * custo extra 2,5 e rendimento 4. Por unidade dá 12 + 2,5 × 4 = 22; fixo daria 14,5; percentual, 12,3.
 *
 * Tenant 98 (fictício, ADR 0358). NUNCA biz=4. Tudo dentro de transação desfeita no fim.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Http/Requests/StoreRecipeRequest.php
 * @see Modules/Manufacturing/Services/RecipeBomService::calculateCost()
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o salvar depende do schema MySQL UltimatePOS (products/variations/units).');
    }
    foreach (['business', 'users', 'units', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

/** POST do formulário de ingredientes com a forma de custo escolhida. */
function mfgCustoSalvar($test, $user, int $variationId, int $insumoId, string $tipo)
{
    $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];

    return $test->actingAs($user)->withSession($sessao)->post('/manufacturing/recipe', [
        'variation_id' => $variationId,
        'ingredients' => [['ingredient_id' => $insumoId, 'quantity' => '3', 'waste_percent' => '0', 'sort_order' => 1]],
        'total' => '22,00', 'total_quantity' => '4', 'ingredients_cost' => '12',
        'waste_percent' => '0', 'extra_cost' => '2,50', 'production_cost_type' => $tipo,
        'instructions' => 'custo por unidade',
    ]);
}

describe('Salvar receita — custo de produção "Por unidade"', function () {
    it('salva a receita com custo "Por unidade" e o custo calculado soma custo extra × rendimento', function () {
        $user = mfgEmpUsuario('mfg_custo_unidade_test');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto por unidade', $user->id);
        [, $insumo] = mfgEmpProduto(MFG_EMP_BIZ, 'Insumo por unidade', $user->id, 4.0);

        mfgCustoSalvar($this, $user, $alvo, $insumo, 'per_unit')
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $recipe = MfgRecipe::with(['ingredients.variation', 'ingredients.sub_unit'])
            ->where('variation_id', $alvo)->first();
        expect($recipe)->not->toBeNull();
        expect($recipe->production_cost_type)->toBe('per_unit');
        expect((float) $recipe->extra_cost)->toBe(2.5);
        expect((float) $recipe->total_quantity)->toBe(4.0);
        expect((float) $recipe->final_price)->toBe(22.0);

        // Servidor: o mesmo 22 que a tela mandou em `total`.
        expect((new RecipeBomService())->calculateCost($recipe))->toBe(22.0);
    });

    it('continua recusando forma de custo fora da lista', function () {
        $user = mfgEmpUsuario('mfg_custo_unidade_test');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto custo invalido', $user->id);
        [, $insumo] = mfgEmpProduto(MFG_EMP_BIZ, 'Insumo custo invalido', $user->id, 4.0);

        mfgCustoSalvar($this, $user, $alvo, $insumo, 'por_kilo')
            ->assertSessionHasErrors('production_cost_type');

        expect(MfgRecipe::where('variation_id', $alvo)->exists())->toBeFalse();
    });
});
