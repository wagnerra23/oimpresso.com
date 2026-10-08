<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Modules\Manufacturing\Services\RecipeBomService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * "Salvar receita" (POST /manufacturing/recipe → RecipeController::store) — o que o SERVIDOR garante.
 *
 * Fonte: handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §5 regra 1 e §9
 * (`prototipo-ui/cowork/Felipe/handoff_fabricacao/README.md`), SPEC US-MANU-006.
 * Os casos derivam dessas regras, não do código.
 *
 * Até 2026-10-08:
 *  - `final_price` e `ingredients_cost` eram gravados com o número que o navegador mandava;
 *  - receita sem ingrediente não gravava nada e respondia "salvo com sucesso";
 *  - quantidade 0 ou negativa era aceita;
 *  - qualquer unidade da empresa passava como sub-unidade do insumo (e o multiplicador dela entrava no custo);
 *  - trocar TODOS os ingredientes deixava os antigos gravados junto com os novos.
 *
 * Os números foram escolhidos para a conta ser feita de cabeça, e cada caso confere por dois
 * caminhos: o valor à mão no próprio teste e o `RecipeBomService::calculateCost`.
 *
 * Tenant 98 (fictício, ADR 0358). NUNCA biz=4. Tudo dentro de transação desfeita no fim.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Http/Controllers/RecipeController::store()
 * @see Modules/Manufacturing/Http/Requests/StoreRecipeRequest.php
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

/** Sub-unidade de `$unidadeBase` na empresa 98 (ex.: "caixa com 5"). */
function mfgSrvSubUnidade(int $unidadeBase, float $multiplicador, int $userId): int
{
    return DB::table('units')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'actual_name' => 'Sub-unidade x'.$multiplicador, 'short_name' => 'SU',
        'allow_decimal' => 1, 'base_unit_id' => $unidadeBase, 'base_unit_multiplier' => $multiplicador,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** POST do formulário de ingredientes. `$linhas` no formato do formulário. */
function mfgSrvSalvar($test, $user, int $variationId, array $linhas, array $extra = [])
{
    $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];

    return $test->actingAs($user)->withSession($sessao)->post('/manufacturing/recipe', array_merge([
        'variation_id' => $variationId,
        'ingredients' => $linhas,
        'total' => '0', 'total_quantity' => '1', 'ingredients_cost' => '0',
        'waste_percent' => '0', 'extra_cost' => '0', 'production_cost_type' => 'fixed',
        'instructions' => 'salvar receita servidor',
    ], $extra));
}

function mfgSrvLinha(int $insumo, string $quantidade, ?int $subUnidade = null, int $ordem = 1): array
{
    return array_filter([
        'ingredient_id' => $insumo, 'quantity' => $quantidade, 'waste_percent' => '0',
        'sort_order' => $ordem, 'sub_unit_id' => $subUnidade,
    ], fn ($v) => $v !== null);
}

function mfgSrvReceita(int $variationId): ?MfgRecipe
{
    return MfgRecipe::with(['ingredients.variation', 'ingredients.sub_unit'])->where('variation_id', $variationId)->first();
}

describe('Salvar receita — o servidor garante (handoff §5 regra 1 e §9)', function () {
    it('UC-RECIPE-14: grava o custo calculado no servidor, não o total que veio do navegador', function () {
        $user = mfgEmpUsuario('mfg_salvar_servidor_test');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto custo servidor', $user->id);
        [, $insumo] = mfgEmpProduto(MFG_EMP_BIZ, 'Insumo custo servidor', $user->id, 4.0);

        // POST forjado: diz que o total é 999.999,00 e os ingredientes 999999.
        mfgSrvSalvar($this, $user, $alvo, [mfgSrvLinha($insumo, '3')], [
            'total' => '999.999,00', 'ingredients_cost' => '999999',
            'extra_cost' => '10', 'production_cost_type' => 'percentage',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $recipe = mfgSrvReceita($alvo);
        expect($recipe)->not->toBeNull();

        // À mão: 3 × 4,00 = 12,00 de ingredientes; 10% de 12,00 = 1,20; total 13,20.
        expect((float) $recipe->ingredients_cost)->toBe(12.0);
        expect((float) $recipe->final_price)->toBe(13.2);
        // Segundo caminho: a mesma conta que a tela de Receitas faz na leitura.
        $service = new RecipeBomService();
        expect($service->custoDosIngredientes($recipe))->toBe(12.0);
        expect(round($service->calculateCost($recipe), 4))->toBe(13.2);
    });

    it('UC-RECIPE-15: receita sem ingrediente é recusada, avisa o porquê e não grava nada', function () {
        $user = mfgEmpUsuario('mfg_salvar_servidor_test');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto sem ingrediente', $user->id);

        mfgSrvSalvar($this, $user, $alvo, [])
            ->assertSessionHasErrors('ingredients')
            ->assertSessionHas('status.msg', 'A receita precisa de pelo menos 1 ingrediente.');

        expect(MfgRecipe::where('variation_id', $alvo)->exists())->toBeFalse();
    });

    it('UC-RECIPE-16: quantidade zero ou negativa é recusada', function (string $quantidade) {
        $user = mfgEmpUsuario('mfg_salvar_servidor_test');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto quantidade invalida', $user->id);
        [, $insumo] = mfgEmpProduto(MFG_EMP_BIZ, 'Insumo quantidade invalida', $user->id, 4.0);

        mfgSrvSalvar($this, $user, $alvo, [mfgSrvLinha($insumo, $quantidade)])
            ->assertSessionHasErrors('ingredients.0.quantity');

        expect(MfgRecipe::where('variation_id', $alvo)->exists())->toBeFalse();
    })->with(['zero' => '0', 'zero com vírgula' => '0,000', 'negativa' => '-1']);

    it('UC-RECIPE-17: aceita a sub-unidade do insumo e multiplica o custo; recusa unidade de outro produto', function () {
        $user = mfgEmpUsuario('mfg_salvar_servidor_test');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto sub-unidade', $user->id);
        [, $insumo, $unidadeInsumo] = mfgEmpProduto(MFG_EMP_BIZ, 'Insumo sub-unidade', $user->id, 4.0);
        [, , $unidadeOutro] = mfgEmpProduto(MFG_EMP_BIZ, 'Outro produto', $user->id, 1.0);
        $caixaDoInsumo = mfgSrvSubUnidade($unidadeInsumo, 5.0, $user->id);
        $caixaDoOutro = mfgSrvSubUnidade($unidadeOutro, 1000.0, $user->id);

        // Unidade de outro produto: recusada, nada gravado.
        mfgSrvSalvar($this, $user, $alvo, [mfgSrvLinha($insumo, '0,5', $caixaDoOutro)])
            ->assertSessionHasErrors('ingredients.0.sub_unit_id');
        expect(MfgRecipe::where('variation_id', $alvo)->exists())->toBeFalse();

        // Sub-unidade do próprio insumo: aceita.
        mfgSrvSalvar($this, $user, $alvo, [mfgSrvLinha($insumo, '0,5', $caixaDoInsumo)], [
            'extra_cost' => '2,50', 'production_cost_type' => 'fixed',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $recipe = mfgSrvReceita($alvo);
        expect((int) $recipe->ingredients->first()->sub_unit_id)->toBe($caixaDoInsumo);
        // À mão: 0,5 caixa × 5 un × 4,00 = 10,00; + 2,50 fixo = 12,50.
        expect((float) $recipe->ingredients_cost)->toBe(10.0);
        expect((float) $recipe->final_price)->toBe(12.5);
        expect(round((new RecipeBomService())->calculateCost($recipe), 4))->toBe(12.5);
    });

    it('UC-RECIPE-18: trocar todos os ingredientes não deixa os antigos na receita nem no custo', function () {
        $user = mfgEmpUsuario('mfg_salvar_servidor_test');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto troca ingredientes', $user->id);
        [, $antigo] = mfgEmpProduto(MFG_EMP_BIZ, 'Insumo antigo', $user->id, 9.0);
        [, $novo] = mfgEmpProduto(MFG_EMP_BIZ, 'Insumo novo', $user->id, 4.0);

        mfgSrvSalvar($this, $user, $alvo, [mfgSrvLinha($antigo, '2')])->assertSessionHasNoErrors();
        $linhaAntiga = (int) MfgRecipeIngredient::where('variation_id', $antigo)->value('id');
        expect($linhaAntiga)->toBeGreaterThan(0);

        // Segundo salvar: a linha antiga saiu do formulário (sem ingredient_line_id), entrou só a nova.
        mfgSrvSalvar($this, $user, $alvo, [mfgSrvLinha($novo, '3')])->assertSessionHasNoErrors();

        $recipe = mfgSrvReceita($alvo);
        expect(array_map('intval', $recipe->ingredients->pluck('variation_id')->all()))->toBe([$novo]);
        expect(MfgRecipeIngredient::whereKey($linhaAntiga)->exists())->toBeFalse();
        // À mão: só o novo, 3 × 4,00 = 12,00 (com o antigo seriam mais 18,00).
        expect((float) $recipe->final_price)->toBe(12.0);
    });
});
