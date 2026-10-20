<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Montar e gravar a ordem de produção só com receita da PRÓPRIA empresa — Tier 0, ADR 0093.
 *
 * Prova as hipóteses H1 e H2 de `memory/requisitos/Manufacturing/PARIDADE-ordem-producao-blade-vs-prototipo.md`
 * (§5, registradas em 2026-10-13 no #9086):
 *
 * - H2 · `GET /manufacturing/get-recipe-details` (`RecipeController::getRecipeDetails`) busca a receita
 *   só por `variation_id`, sem a cadeia de empresa: quem tem `manufacturing.access_recipe` lê os
 *   ingredientes e o custo da receita de outra empresa.
 * - H1 · `POST /manufacturing/production` (`ProductionController::store`) aceita `variation_id` de
 *   qualquer empresa (`exists:variations,id`) e busca a receita sem a cadeia: a ordem nasce na empresa
 *   da sessão consumindo os ingredientes da receita de outra.
 *
 * Âncora positiva em cada endpoint (a própria empresa passa), para que o 404 da outra empresa não seja
 * rota que nem respondeu. O caso alheio exige 404, e não 403: o usuário TEM a permissão, quem recusa
 * é a cadeia de empresa — mesmo contrato do `ExcluirReceitaEmpresaTest` (#9071).
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a "outra empresa". NUNCA biz=4.
 * Tudo roda dentro de transação desfeita no fim (§5 2026-09-18: mutação no CT 100 persiste).
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Http/Controllers/RecipeController::getRecipeDetails()
 * @see Modules/Manufacturing/Http/Controllers/ProductionController::store()
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a cadeia de empresa depende do schema MySQL UltimatePOS (products/variations).');
    }
    foreach (['business', 'users', 'units', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients', 'transactions'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

/** Usuário da empresa 98 com as permissões de ler receita e de produzir. */
function mfgOrdemUsuario(): \App\User
{
    $user = mfgEmpUsuario('mfg_ordem_receita_empresa_test');
    foreach (['manufacturing.access_recipe', 'manufacturing.access_production'] as $permissao) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
    }

    return $user;
}

/** Ordens de produção (`production_purchase`) gravadas na empresa da sessão. */
function mfgOrdensDaEmpresa(): int
{
    return DB::table('transactions')
        ->where('business_id', MFG_EMP_BIZ)
        ->where('type', 'production_purchase')
        ->count();
}

/** Corpo mínimo do formulário da ordem (Blade `production/create`). */
function mfgOrdemPayload(int $variationId): array
{
    return [
        'transaction_date' => now()->format('Y-m-d H:i'),
        'location_id' => 1,
        'final_total' => '15',
        'variation_id' => $variationId,
        'quantity' => '1',
    ];
}

describe('Montar a ordem — detalhes da receita só da própria empresa (Tier 0 · H2)', function () {
    it('UC-OP-12 — devolve a receita da própria empresa com os ingredientes dela (âncora positiva)', function () {
        $user = mfgOrdemUsuario();
        [, $variacao] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo detalhe proprio');

        $resposta = $this->actingAs($user)->get('/manufacturing/get-recipe-details?variation_id='.$variacao);

        $resposta->assertOk();
        expect($resposta->getContent())->toContain('Insumo detalhe proprio');
    });

    it('UC-OP-12 — recusa com 404 a receita de outra empresa e não mostra nada dela', function () {
        $user = mfgOrdemUsuario();
        [, $variacao] = mfgEmpReceita(MFG_EMP_OUTRO, $user->id, 'Insumo detalhe alheio');

        $resposta = $this->actingAs($user)->get('/manufacturing/get-recipe-details?variation_id='.$variacao);

        $resposta->assertNotFound();
        expect($resposta->getContent())->not->toContain('Insumo detalhe alheio');
    });

    it('UC-OP-12 — recusa com 404 uma variação que não existe', function () {
        $user = mfgOrdemUsuario();
        $inexistente = (int) DB::table('variations')->max('id') + 1000;

        $this->actingAs($user)->get('/manufacturing/get-recipe-details?variation_id='.$inexistente)->assertNotFound();
    });
});

describe('Gravar a ordem — só com produto da própria empresa (Tier 0 · H1)', function () {
    it('UC-OP-13 — a ordem com produto da própria empresa passa da trava (âncora positiva)', function () {
        $user = mfgOrdemUsuario();
        [, $variacao] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo ordem proprio');

        // O store devolve redirect para a lista tanto no sucesso quanto no erro que ele mesmo trata;
        // o que interessa aqui é que a trava de empresa NÃO recusou (não é 404).
        $resposta = $this->actingAs($user)->postJson('/manufacturing/production', mfgOrdemPayload($variacao));

        $resposta->assertRedirect('/manufacturing/production');
    });

    it('UC-OP-13 — recusa com 404 a ordem com produto de outra empresa, sem gravar nada', function () {
        $user = mfgOrdemUsuario();
        [, $variacao] = mfgEmpReceita(MFG_EMP_OUTRO, $user->id, 'Insumo ordem alheio');
        $antes = mfgOrdensDaEmpresa();

        $resposta = $this->actingAs($user)->postJson('/manufacturing/production', mfgOrdemPayload($variacao));

        $resposta->assertNotFound();
        expect(mfgOrdensDaEmpresa())->toBe($antes);
    });
});
