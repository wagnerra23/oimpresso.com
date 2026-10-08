<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Excluir receita (`DELETE /manufacturing/recipe/{id}` → `RecipeController::destroy`) só apaga
 * receita do PRÓPRIO business — Tier 0, ADR 0093 · handoff Fabricação §9 ("business_id pela
 * cadeia mfg_recipes → variations → products em TODA query").
 *
 * Até 2026-10-08 o controller fazia `MfgRecipe::where('id', $id)->delete()` sem tenant: qualquer
 * usuário com `manufacturing.add_recipe` apagava a receita de outra empresa pelo id. É o botão
 * "Excluir" da lista antiga (common_script.blade.php `button.delete_recipe`) e será o da tela
 * nova (US-MANU-006 etapa 3, regra 6 do §5).
 *
 * O caso da receita própria é a âncora positiva: prova que a requisição chega ao `destroy` e que
 * ele apaga de fato — senão a receita alheia "sobreviver" poderia ser rota que nem respondeu.
 * O caso alheio exige 404 (não 403): o usuário TEM a permissão, quem recusa é a cadeia de empresa.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a "outra empresa". NUNCA biz=4.
 * Tudo roda dentro de transação desfeita no fim (§5 2026-09-18: mutação no CT 100 persiste).
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Http/Controllers/RecipeController::destroy()
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a cadeia de empresa depende do schema MySQL UltimatePOS (products/variations).');
    }
    foreach (['business', 'users', 'units', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

describe('Excluir receita — só do próprio business (Tier 0)', function () {
    it('UC-RECIPE-19 — apaga a receita do próprio business e os ingredientes dela (âncora positiva)', function () {
        $user = mfgEmpUsuario('mfg_excluir_receita_test');
        [$propria, , $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo excluir proprio');

        $resposta = $this->actingAs($user)->deleteJson('/manufacturing/recipe/'.$propria);

        $resposta->assertOk();
        $resposta->assertJson(['success' => 1]);
        $this->assertDatabaseMissing('mfg_recipes', ['id' => $propria]);
        $this->assertDatabaseMissing('mfg_recipe_ingredients', ['id' => $linha]);
    });

    it('UC-RECIPE-19 — recusa com 404 a receita de outra empresa, e ela continua no banco', function () {
        $user = mfgEmpUsuario('mfg_excluir_receita_test');
        [$alheia, , $linha] = mfgEmpReceita(MFG_EMP_OUTRO, $user->id, 'Insumo excluir alheio');

        $resposta = $this->actingAs($user)->deleteJson('/manufacturing/recipe/'.$alheia);

        $resposta->assertNotFound();
        $this->assertDatabaseHas('mfg_recipes', ['id' => $alheia]);
        $this->assertDatabaseHas('mfg_recipe_ingredients', ['id' => $linha]);
    });

    it('UC-RECIPE-19 — recusa com 404 um id que não existe', function () {
        $user = mfgEmpUsuario('mfg_excluir_receita_test');
        $inexistente = (int) DB::table('mfg_recipes')->max('id') + 1000;

        $this->actingAs($user)->deleteJson('/manufacturing/recipe/'.$inexistente)->assertNotFound();
    });
});
