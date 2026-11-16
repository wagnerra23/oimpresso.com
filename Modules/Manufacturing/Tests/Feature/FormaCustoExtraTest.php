<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Services\RecipeBomService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-RECIPE-23 — a receita sempre tem uma forma de custo extra, e a tela mostra a forma que a conta usa.
 * Auditoria do Manufacturing, lote 2.3.
 *
 * Contrato: charter de Recipes ("❌ Aceitar `production_cost_type` fora de {fixed, percentage, per_unit}")
 * e UC-RECIPE-04 (as três fórmulas dão três resultados). Vazio está fora das três. Receita antiga que já
 * está gravada sem a forma é calculada como valor fixo (`RecipeBomService::calculateCost`, e o editor abre
 * em "fixo"); a lista tem de dizer a mesma coisa, senão o rótulo "Custo extra (2,5%)" aparece ao lado de
 * um custo que somou 2,5 inteiro.
 *
 * Números: 1 insumo de 7,5 × 2 = 15, custo extra 2,5, rendimento 1. Fixo dá 17,5; percentual daria 15,375.
 *
 * Tenant 98 (fictício, ADR 0358). NUNCA biz=4. Tudo dentro de transação desfeita.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'units', 'products', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

function mfgFormaSalvar($test, $user, int $variationId, int $insumoId, array $extra)
{
    $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];

    return $test->actingAs($user)->withSession($sessao)->post('/manufacturing/recipe', array_merge([
        'variation_id' => $variationId,
        'ingredients' => [['ingredient_id' => $insumoId, 'quantity' => '2', 'waste_percent' => '0', 'sort_order' => 1]],
        'total' => '0', 'total_quantity' => '1', 'ingredients_cost' => '0',
        'waste_percent' => '0', 'extra_cost' => '2,50', 'instructions' => 'forma do custo extra',
    ], $extra));
}

describe('UC-RECIPE-23 — salvar exige a forma do custo extra', function () {
    it('UC-RECIPE-23 sem a forma (ausente ou vazia) volta com erro no campo e não grava', function () {
        $user = mfgEmpUsuario('mfg_forma_custo_test');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto sem forma', $user->id);
        [, $insumo] = mfgEmpProduto(MFG_EMP_BIZ, 'Insumo sem forma', $user->id, 7.5);

        mfgFormaSalvar($this, $user, $alvo, $insumo, [])->assertSessionHasErrors('production_cost_type');
        mfgFormaSalvar($this, $user, $alvo, $insumo, ['production_cost_type' => ''])->assertSessionHasErrors('production_cost_type');

        expect(MfgRecipe::where('variation_id', $alvo)->exists())->toBeFalse();
    });

    it('UC-RECIPE-23 âncora: com a forma, grava e a conta é a dela (fixo: 15 + 2,5 = 17,5)', function () {
        $user = mfgEmpUsuario('mfg_forma_custo_test');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto com forma', $user->id);
        [, $insumo] = mfgEmpProduto(MFG_EMP_BIZ, 'Insumo com forma', $user->id, 7.5);

        mfgFormaSalvar($this, $user, $alvo, $insumo, ['production_cost_type' => 'fixed'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $recipe = MfgRecipe::with(['ingredients.variation', 'ingredients.sub_unit'])->where('variation_id', $alvo)->first();
        expect($recipe->production_cost_type)->toBe('fixed');
        expect((new RecipeBomService())->calculateCost($recipe))->toBe(17.5);
    });
});

describe('UC-RECIPE-23 — receita antiga sem a forma: rótulo e conta dizem a mesma coisa', function () {
    it('UC-RECIPE-23 a lista e o editor mostram "fixo", que é a forma que a conta usou (17,5)', function () {
        $user = mfgEmpUsuario('mfg_forma_custo_legado');
        [$receita, $acabado] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo receita sem forma');
        DB::table('mfg_recipes')->where('id', $receita)->update(['production_cost_type' => null, 'extra_cost' => 2.5, 'total_quantity' => 1]);

        $bom = new RecipeBomService();
        $linha = collect($bom->listRecipesWithCost(MFG_EMP_BIZ))->firstWhere('id', $receita);

        // Caminho 1: a conta à mão — 2 × 7,5 + 2,5 inteiro.
        expect($linha['custos']['total'])->toBe(17.5);
        // Caminho 2: a fórmula que o rótulo anuncia, aplicada aos mesmos números, tem de dar o mesmo total.
        $ingredientes = $linha['custos']['ingredientes'];
        $pelaForma = match ($linha['custo_tipo']) {
            'percentage' => $ingredientes + $ingredientes * $linha['extra'] / 100,
            'per_unit'   => $ingredientes + $linha['extra'] * $linha['qtd'],
            default      => $ingredientes + $linha['extra'],
        };
        expect($pelaForma)->toBe($linha['custos']['total']);
        expect($linha['custo_tipo'])->toBe('fixed');

        expect($bom->editorDaReceita($acabado, MFG_EMP_BIZ)['receita']['production_cost_type'])->toBe($linha['custo_tipo']);
    });
});
