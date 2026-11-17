<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Atualizar o preço da variação a partir da receita (`POST /manufacturing/update-product-prices`)
 * só alcança receita da PRÓPRIA empresa. Tier 0, ADR 0093 · US-MANU-008 H3 · handoff Fabricação §9
 * ("business_id pela cadeia mfg_recipes → variations → products em TODA query").
 *
 * A permissão `manufacturing.add_recipe` vale só dentro da empresa do usuário: o id de uma receita
 * alheia no corpo não pode gravar preço de custo e margem na variação dela.
 *
 * O caso da receita própria é a âncora positiva e prende a conta (custo 12,5 sobre venda 20 dá
 * margem 60%). O caso misto manda as duas receitas na MESMA chamada: a própria mudar prova que a
 * requisição chegou ao código, então a alheia ficar igual não pode ser rota que nem respondeu.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a "outra empresa". NUNCA biz=4.
 * Tudo roda dentro de transação desfeita no fim (§5 2026-09-18).
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Http/Controllers/RecipeController::updateRecipeProductPrices()
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

/** Põe na variação preços conhecidos, diferentes de tudo o que a chamada mandaria gravar. */
function mfgPrecoSemear(int $variationId, float $custo, float $venda, float $margem): array
{
    $precos = [
        'default_purchase_price' => $custo,
        'dpp_inc_tax' => $custo,
        'profit_percent' => $margem,
        'default_sell_price' => $venda,
        'sell_price_inc_tax' => $venda,
    ];
    DB::table('variations')->where('id', $variationId)->update($precos);

    return $precos;
}

/** Lê da variação as colunas de preço, em float, para comparar sem depender de escala decimal. */
function mfgPrecoLer(int $variationId): array
{
    $v = DB::table('variations')->where('id', $variationId)->first();

    return [
        'default_purchase_price' => (float) $v->default_purchase_price,
        'dpp_inc_tax' => (float) $v->dpp_inc_tax,
        'profit_percent' => (float) $v->profit_percent,
        'default_sell_price' => (float) $v->default_sell_price,
        'sell_price_inc_tax' => (float) $v->sell_price_inc_tax,
    ];
}

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

describe('Atualizar preço pela receita — só a própria empresa (Tier 0)', function () {
    it('UC-RECIPE-21 — grava custo e margem na variação da receita da própria empresa (âncora positiva)', function () {
        $user = mfgEmpUsuario('mfg_preco_receita_test');
        [$receita, $variacao] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo preco proprio');
        mfgPrecoSemear($variacao, 5.0, 20.0, 300.0);

        $resposta = $this->actingAs($user)->postJson('/manufacturing/update-product-prices', [
            'recipe_ids' => [$receita],
            'unit_prices' => [$receita => 12.5],
        ]);

        $resposta->assertOk();
        $resposta->assertJson(['success' => 1]);
        expect(mfgPrecoLer($variacao))->toBe([
            'default_purchase_price' => 12.5,
            'dpp_inc_tax' => 12.5,
            'profit_percent' => 60.0,
            'default_sell_price' => 20.0,
            'sell_price_inc_tax' => 20.0,
        ]);
    });

    it('UC-RECIPE-21 — receita de outra empresa não muda o preço da variação dela', function () {
        $user = mfgEmpUsuario('mfg_preco_receita_test');
        [$alheia, $variacaoAlheia] = mfgEmpReceita(MFG_EMP_OUTRO, $user->id, 'Insumo preco alheio');
        $antes = mfgPrecoSemear($variacaoAlheia, 5.0, 10.0, 100.0);

        $resposta = $this->actingAs($user)->postJson('/manufacturing/update-product-prices', [
            'recipe_ids' => [$alheia],
            'unit_prices' => [$alheia => 12.5],
        ]);

        $resposta->assertOk();
        expect(mfgPrecoLer($variacaoAlheia))->toBe($antes);
    });

    it('UC-RECIPE-21 — chamada com receita própria e alheia só altera a própria', function () {
        $user = mfgEmpUsuario('mfg_preco_receita_test');
        [$propria, $variacaoPropria] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo preco misto proprio');
        [$alheia, $variacaoAlheia] = mfgEmpReceita(MFG_EMP_OUTRO, $user->id, 'Insumo preco misto alheio');
        mfgPrecoSemear($variacaoPropria, 5.0, 20.0, 300.0);
        $antes = mfgPrecoSemear($variacaoAlheia, 5.0, 10.0, 100.0);

        $resposta = $this->actingAs($user)->postJson('/manufacturing/update-product-prices', [
            'recipe_ids' => [$propria, $alheia],
            'unit_prices' => [$propria => 12.5, $alheia => 33.0],
        ]);

        $resposta->assertOk();
        expect(mfgPrecoLer($variacaoPropria)['default_purchase_price'])->toBe(12.5);
        expect(mfgPrecoLer($variacaoAlheia))->toBe($antes);
    });
});
