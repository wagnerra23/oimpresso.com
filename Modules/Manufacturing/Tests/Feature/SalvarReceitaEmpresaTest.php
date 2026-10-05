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
 * "Salvar receita" (POST /manufacturing/recipe → RecipeController::store) só grava dado da
 * PRÓPRIA empresa — Tier 0, ADR 0093.
 *
 * O POST traz três ids que a validação (`exists:variations,id`) não confere contra a empresa.
 * Até 2026-10-05 o `store()` os buscava sem tenant, e cada um abria uma ESCRITA cruzada:
 *  - `variation_id` → `MfgRecipe::updateOrCreate` pela variação: criava ou sobrescrevia a receita
 *    de um produto de outra empresa;
 *  - `ingredients.*.ingredient_id` → o produto de outra empresa entrava como ingrediente;
 *  - `ingredients.*.ingredient_line_id` → a linha de outra receita era alterada e MOVIDA para esta.
 *
 * O caso da própria empresa é a âncora positiva: prova que o POST chega ao código que grava,
 * para a ausência de escrita nos outros casos não ser requisição que nem passou.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a "outra empresa". NUNCA biz=4. Tudo roda dentro de
 * transação desfeita no fim (§5 2026-09-18: mutação no CT 100 persiste).
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Services/RecipeBomService::variacaoDaEmpresa()
 * @see Modules/Manufacturing/Http/Controllers/RecipeController::store()
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

/** POST do formulário de ingredientes com UMA linha. */
function mfgEmpSalvar($test, $user, int $variationId, int $ingredienteId, ?int $linhaId = null, string $quantidade = '3')
{
    $linha = ['ingredient_id' => $ingredienteId, 'quantity' => $quantidade, 'waste_percent' => '0', 'sort_order' => 1];
    if ($linhaId !== null) {
        $linha['ingredient_line_id'] = $linhaId;
    }
    $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];

    return $test->actingAs($user)->withSession($sessao)->post('/manufacturing/recipe', [
        'variation_id' => $variationId,
        'ingredients' => [$linha],
        'total' => '0', 'total_quantity' => '1', 'ingredients_cost' => '0',
        'waste_percent' => '0', 'extra_cost' => '0', 'production_cost_type' => 'fixed',
        'instructions' => 'gravado pelo teste de salvar',
    ]);
}

describe('Salvar receita — só grava dado da própria empresa (Tier 0)', function () {
    it('variacaoDaEmpresa encontra a variação da empresa e recusa a de outra', function () {
        $user = mfgEmpUsuario('mfg_salvar_receita_test');
        [, $propria] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto proprio', $user->id);
        [, $alheia] = mfgEmpProduto(MFG_EMP_OUTRO, 'Produto alheio', $user->id);

        $service = new RecipeBomService();

        expect((int) $service->variacaoDaEmpresa($propria, MFG_EMP_BIZ)->id)->toBe($propria);
        expect((int) $service->variacaoDaEmpresa($alheia, MFG_EMP_OUTRO)->id)->toBe($alheia);
        expect(fn () => $service->variacaoDaEmpresa($alheia, MFG_EMP_BIZ))
            ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });

    it('salva a receita com produto e ingrediente da própria empresa (âncora positiva)', function () {
        $user = mfgEmpUsuario('mfg_salvar_receita_test');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto novo da empresa', $user->id);
        [, $insumo] = mfgEmpProduto(MFG_EMP_BIZ, 'Insumo da empresa', $user->id, 4.0);

        mfgEmpSalvar($this, $user, $alvo, $insumo)->assertRedirect();

        $recipe = MfgRecipe::where('variation_id', $alvo)->first();
        expect($recipe)->not->toBeNull();
        expect($recipe->instructions)->toBe('gravado pelo teste de salvar');
        expect(array_map('intval', MfgRecipeIngredient::where('mfg_recipe_id', $recipe->id)->pluck('variation_id')->all()))->toBe([$insumo]);
    });

    it('não cria nem sobrescreve receita de produto de outra empresa', function () {
        $user = mfgEmpUsuario('mfg_salvar_receita_test');
        [$receitaAlheia, $produtoAlheio, $linhaAlheia] = mfgEmpReceita(MFG_EMP_OUTRO, $user->id, 'Insumo da outra');
        [, $insumo] = mfgEmpProduto(MFG_EMP_BIZ, 'Insumo da empresa', $user->id, 4.0);

        mfgEmpSalvar($this, $user, $produtoAlheio, $insumo);

        $alheia = MfgRecipe::find($receitaAlheia);
        expect($alheia->instructions)->toBe('mfg-receita-empresa-test');
        expect(array_map('intval', MfgRecipeIngredient::where('mfg_recipe_id', $receitaAlheia)->pluck('id')->all()))->toBe([$linhaAlheia]);
        expect((float) MfgRecipeIngredient::find($linhaAlheia)->quantity)->toBe(2.0);
    });

    it('não põe produto de outra empresa como ingrediente e não grava nada pela metade', function () {
        $user = mfgEmpUsuario('mfg_salvar_receita_test');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto novo da empresa', $user->id);
        [, $insumoAlheio] = mfgEmpProduto(MFG_EMP_OUTRO, 'Insumo da outra', $user->id, 9.0);

        mfgEmpSalvar($this, $user, $alvo, $insumoAlheio);

        // Segurança: o produto alheio não vira ingrediente de receita nenhuma desta empresa.
        expect(MfgRecipeIngredient::where('variation_id', $insumoAlheio)->count())->toBe(0);
        // Atomicidade: recusado o ingrediente, o cabeçalho da receita também não fica gravado.
        expect(MfgRecipe::where('variation_id', $alvo)->exists())->toBeFalse();
    });

    it('não altera nem move linha de ingrediente de receita de outra empresa', function () {
        $user = mfgEmpUsuario('mfg_salvar_receita_test');
        [$receitaAlheia, , $linhaAlheia] = mfgEmpReceita(MFG_EMP_OUTRO, $user->id, 'Insumo da outra');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto novo da empresa', $user->id);
        [, $insumo] = mfgEmpProduto(MFG_EMP_BIZ, 'Insumo da empresa', $user->id, 4.0);

        mfgEmpSalvar($this, $user, $alvo, $insumo, $linhaAlheia, '5');

        $linha = MfgRecipeIngredient::find($linhaAlheia);
        expect((int) $linha->mfg_recipe_id)->toBe($receitaAlheia);
        expect((float) $linha->quantity)->toBe(2.0);
    });
});
