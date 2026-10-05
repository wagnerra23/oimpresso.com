<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Services\RecipeBomService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * "Copiar da receita" (modal `recipe/create` → GET /manufacturing/add-ingredient) só copia
 * receita do PRÓPRIO business — Tier 0, ADR 0093.
 *
 * O `copy_recipe_id` chega pela query string. Até 2026-10-05 o controller fazia
 * `MfgRecipe::with(...)->find($id)` sem tenant: o servidor LIA a receita de OUTRA empresa, e a
 * página só não a mostrava por acaso — a unidade de cada ingrediente é buscada no business da
 * sessão com `findOrFail`, então a página dava 404 (medido: run 37316751680, este teste antes
 * da correção). Com a regra, a página abre normal (200) e sem a cópia — é o que se asserta.
 *
 * O caso da receita do próprio business é a âncora positiva: prova que a requisição chega
 * ao código da cópia, para a ausência do ingrediente alheio não ser página que nem abriu.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a "outra empresa". NUNCA biz=4.
 * Tudo roda dentro de transação desfeita no fim (§5 2026-09-18: mutação no CT 100 persiste).
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Services/RecipeBomService::receitaParaCopiar()
 * @see Modules/Manufacturing/Http/Controllers/RecipeController::addIngredients()
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a cópia depende do schema MySQL UltimatePOS (products/variations/units).');
    }
    foreach (['business', 'users', 'units', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

describe('Copiar da receita — só do próprio business (Tier 0)', function () {
    it('receitaParaCopiar devolve a receita do business e recusa a de outro', function () {
        $user = mfgEmpUsuario('mfg_copia_receita_test');
        [$propria] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo copia proprio');
        [$alheia] = mfgEmpReceita(MFG_EMP_OUTRO, $user->id, 'Insumo copia alheio');

        $service = new RecipeBomService();

        $ok = $service->receitaParaCopiar($propria, MFG_EMP_BIZ, ['ingredients']);
        expect($ok)->not->toBeNull();
        expect($ok->ingredients)->toHaveCount(1);

        // A receita existe (a outra empresa enxerga), mas não para o business 98.
        expect($service->receitaParaCopiar($alheia, MFG_EMP_OUTRO))->not->toBeNull();
        expect($service->receitaParaCopiar($alheia, MFG_EMP_BIZ))->toBeNull();
    });

    it('a página de ingredientes copia a receita do próprio business (âncora positiva)', function () {
        $user = mfgEmpUsuario('mfg_copia_receita_test');
        [$propria] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo copia proprio');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto novo sem receita', $user->id);

        $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];
        $resposta = $this->actingAs($user)->withSession($sessao)
            ->get('/manufacturing/add-ingredient?variation_id='.$alvo.'&copy_recipe_id='.$propria);

        $resposta->assertOk();
        $resposta->assertSee('Insumo copia proprio');
    });

    it('a página de ingredientes não mostra ingrediente de receita de outra empresa', function () {
        $user = mfgEmpUsuario('mfg_copia_receita_test');
        [$alheia] = mfgEmpReceita(MFG_EMP_OUTRO, $user->id, 'Insumo copia alheio');
        [, $alvo] = mfgEmpProduto(MFG_EMP_BIZ, 'Produto novo sem receita', $user->id);

        $sessao = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];
        $resposta = $this->actingAs($user)->withSession($sessao)
            ->get('/manufacturing/add-ingredient?variation_id='.$alvo.'&copy_recipe_id='.$alheia);

        $resposta->assertOk();
        $resposta->assertDontSee('Insumo copia alheio');
    });
});
