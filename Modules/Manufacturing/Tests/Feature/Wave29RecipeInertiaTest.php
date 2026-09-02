<?php

declare(strict_types=1);

use App\Unit;
use App\Variation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Modules\Manufacturing\Services\RecipeBomService;

uses(Tests\TestCase::class);

/**
 * Wave 29 — a tela de Fabricação (Manufacturing/Recipes) em `/manufacturing/recipe`.
 *
 * Contrato desta suíte = os UC de `resources/js/Pages/Manufacturing/Recipes.casos.md`,
 * que por sua vez derivam do §17 do handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1"
 * (R-11, R-12, R-13) e das proibições Tier 0 — NÃO do `.tsx` (§5 tautológico).
 *
 * Divisão deliberada:
 *  · O bloco de CÁLCULO é DB-less (models em memória via setRelation) — roda na lane
 *    sqlite e é onde mora a defesa do dinheiro.
 *  · O bloco de ROTA precisa do schema UltimatePOS real e pula em sqlite, igual ao
 *    MultiTenantIsolationTest do mesmo módulo.
 *
 * Tenant: NUNCA biz=4 (ROTA LIVRE — Larissa em produção). ADR 0358.
 *
 * @see resources/js/Pages/Manufacturing/Recipes.casos.md
 * @see memory/requisitos/Manufacturing/RUNBOOK-recipes.md
 */

defined('BIZ_FICTICIO_MFG') || define('BIZ_FICTICIO_MFG', 98);

/** Monta uma recipe em memória com N ingredientes — zero DB. */
function mfgReceitaFake(array $attrs, array $ingredientes): MfgRecipe
{
    $recipe = new MfgRecipe(array_merge([
        'total_quantity'       => 10,
        'waste_percent'        => 0,
        'extra_cost'           => 0,
        'production_cost_type' => 'fixed',
        'final_price'          => 0,
    ], $attrs));

    $itens = collect($ingredientes)->map(function (array $i) {
        $ing = new MfgRecipeIngredient(['quantity' => $i['quantity']]);

        $variation = new Variation();
        $variation->dpp_inc_tax = $i['preco'];
        $ing->setRelation('variation', $variation);

        if (! empty($i['multiplicador'])) {
            $unit = new Unit();
            $unit->base_unit_multiplier = $i['multiplicador'];
            $ing->setRelation('sub_unit', $unit);
        } else {
            $ing->setRelation('sub_unit', null);
        }

        return $ing;
    });

    $recipe->setRelation('ingredients', $itens);

    return $recipe;
}

describe('UC-RECIPE-03/04/05 — modelo de custo (§7 do handoff · DB-less)', function () {

    // UC-RECIPE-03 · R-11 — o custo unitário divide por total_quantity, NUNCA pelo rendimento.
    it('UC-RECIPE-03 divide o custo unitario por total_quantity, nao pelo rendimento', function () {
        // 10 m² a 9,20 por m² = 92,00 de ingredientes · 4% de desperdício · sem custo extra.
        $recipe = mfgReceitaFake(
            ['total_quantity' => 10, 'waste_percent' => 4, 'extra_cost' => 0, 'production_cost_type' => 'fixed'],
            [['quantity' => 10, 'preco' => 9.20]],
        );

        $service = new RecipeBomService();

        expect($service->calculateCost($recipe))->toBe(92.0);

        // 92 / 10 = 9,20 — e NÃO 92 / 9,6 = 9,5833… (que é o erro que este UC defende).
        expect($service->calculateUnitCost($recipe))->toBe(9.2);
        expect($service->calculateUnitCost($recipe))->not->toBe(92 / 9.6);
    });

    // UC-RECIPE-04 · R-12 — o MESMO `18` dá três totais diferentes conforme o tipo.
    it('UC-RECIPE-04 aplica as tres formulas de custo extra com o mesmo valor 18', function () {
        $service = new RecipeBomService();
        $base = [['quantity' => 10, 'preco' => 9.20]]; // ingredientes = 92,00 · total_quantity = 10

        $percentual = mfgReceitaFake(
            ['total_quantity' => 10, 'extra_cost' => 18, 'production_cost_type' => 'percentage'],
            $base,
        );
        $porUnidade = mfgReceitaFake(
            ['total_quantity' => 10, 'extra_cost' => 18, 'production_cost_type' => 'per_unit'],
            $base,
        );
        $fixo = mfgReceitaFake(
            ['total_quantity' => 10, 'extra_cost' => 18, 'production_cost_type' => 'fixed'],
            $base,
        );

        expect($service->calculateCost($percentual))->toBe(92.0 + (92.0 * 18 / 100)); // 108,56
        expect($service->calculateCost($porUnidade))->toBe(92.0 + (18.0 * 10));       // 272,00
        expect($service->calculateCost($fixo))->toBe(92.0 + 18.0);                    // 110,00

        // As três precisam ser DIFERENTES entre si — unificar as fórmulas é o anti-padrão.
        $totais = [
            $service->calculateCost($percentual),
            $service->calculateCost($porUnidade),
            $service->calculateCost($fixo),
        ];
        expect(count(array_unique($totais)))->toBe(3);
    });

    // UC-RECIPE-05 · R-13 — divisão por zero devolve 0, nunca NaN/INF.
    it('UC-RECIPE-05 devolve 0 no custo unitario quando total_quantity e zero', function () {
        $recipe = mfgReceitaFake(
            ['total_quantity' => 0, 'extra_cost' => 0, 'production_cost_type' => 'fixed'],
            [['quantity' => 3, 'preco' => 10.0]],
        );

        $unit = (new RecipeBomService())->calculateUnitCost($recipe);

        expect($unit)->toBe(0.0);
        expect(is_finite($unit))->toBeTrue();
        expect(is_nan($unit))->toBeFalse();
    });

    // §7.4 — o multiplicador da sub-unidade multiplica o custo da linha.
    it('UC-RECIPE-04 multiplica a linha pelo base_unit_multiplier da sub-unidade', function () {
        // 0,044 galão de 5 L a 108,00 por L = 0,044 × 108 × 5 = 23,76
        $recipe = mfgReceitaFake(
            ['total_quantity' => 1, 'production_cost_type' => 'fixed', 'extra_cost' => 0],
            [['quantity' => 0.044, 'preco' => 108.0, 'multiplicador' => 5]],
        );

        expect(round((new RecipeBomService())->calculateCost($recipe), 4))->toBe(23.76);
    });
});

describe('UC-RECIPE-00 — alcance pelo menu (DB-less)', function () {

    // UC-RECIPE-00 · o item do sidebar precisa APONTAR pra esta rota.
    //
    // O que este teste PROVA: a declaração do menu (o ghost `recipe` → /manufacturing/recipe
    // e o gate de permissão que a envolve) continua no lugar.
    // O que ele NÃO prova, e é honesto dizer: que o item APARECE pra um usuário concreto —
    // isso depende de `manufacturing.access_recipe` estar ligada numa função em
    // /roles/{id}/edit, que é dado de runtime e nenhum gate cobre. Por isso o UC fica ⬜
    // no casos.md: metade dele é verificável, metade é smoke humano.
    it('UC-RECIPE-00 o ghost do sidebar aponta pra /manufacturing/recipe', function () {
        $fonte = file_get_contents(base_path('Modules/Manufacturing/Http/Controllers/DataController.php'));

        expect($fonte)->toContain("'key' => 'recipe'");
        expect($fonte)->toContain("'href' => '/manufacturing/recipe'");

        // E o menu inteiro segue atrás do pacote + da permissão — não é item solto.
        // Aspas SIMPLES de propósito: a string carrega `$business_id`, e em aspas duplas
        // o PHP interpolaria a variável (inexistente aqui) e o assert casaria com lixo.
        expect($fonte)->toContain('hasThePermissionInSubscription($business_id, ');
        expect($fonte)->toContain("'manufacturing_module'");
        expect($fonte)->toContain('manufacturing.access_recipe');
    });
});

describe('UC-RECIPE-01/02/06/07 — a rota (schema MySQL real)', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompatível: a rota depende do schema MySQL UltimatePOS (products/variations/business).');
        }
        if (! Schema::hasTable('mfg_recipes') || ! Schema::hasTable('business')) {
            $this->markTestSkipped('Schema Manufacturing/UltimatePOS ausente neste ambiente.');
        }
    });

    // UC-RECIPE-01 — a rota que [W] pediu existe e é a do RecipeController@index.
    it('UC-RECIPE-01 a rota /manufacturing/recipe esta registrada no runtime', function () {
        // Oráculo é o registry vivo (route collection), não a leitura do arquivo — §5 2026-07-28.
        $rota = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'manufacturing/recipe' && in_array('GET', $r->methods(), true));

        expect($rota)->not->toBeNull('A rota GET /manufacturing/recipe sumiu do registry.');
        expect($rota->getActionName())->toContain('RecipeController');
    });

    // UC-RECIPE-06 — o escape `?legacy=1` que o controller ANUNCIA precisa existir de fato
    // (LC-15: mecanismo que anuncia saída sem honrá-la). Aqui é o contrato do anúncio.
    it('UC-RECIPE-06 o controller honra ?legacy=1 devolvendo a view Blade', function () {
        $fonte = file_get_contents(base_path('Modules/Manufacturing/Http/Controllers/RecipeController.php'));

        expect($fonte)->toContain("request()->boolean('legacy')");
        expect($fonte)->toContain("view('manufacturing::recipe.index')");
        expect($fonte)->toContain("Inertia::render('Manufacturing/Recipes'");
    });

    // UC-RECIPE-07 — o ramo ajax (DataTables legado) continua ANTES do render Inertia.
    it('UC-RECIPE-07 o ramo ajax do DataTables vem antes do render Inertia', function () {
        $fonte = file_get_contents(base_path('Modules/Manufacturing/Http/Controllers/RecipeController.php'));

        $posAjax = strpos($fonte, 'if (request()->ajax())');
        $posInertia = strpos($fonte, "Inertia::render('Manufacturing/Recipes'");

        expect($posAjax)->not->toBeFalse();
        expect($posInertia)->not->toBeFalse();
        expect($posAjax)->toBeLessThan(
            $posInertia,
            'O render Inertia passou na frente do ramo ajax — a tabela legada morreria em silêncio.'
        );
    });

    // UC-RECIPE-02 — Tier 0. A listagem filtra pela cadeia de tenant e não vaza.
    it('UC-RECIPE-02 listRecipesWithCost nao devolve receita de outro business', function () {
        $service = new RecipeBomService();

        // Tenant fictício sem dado nenhum: a listagem TEM que voltar vazia. Se voltar
        // qualquer linha, o JOIN de tenant não está segurando.
        $doFicticio = $service->listRecipesWithCost(BIZ_FICTICIO_MFG);

        expect($doFicticio)->toBeArray();
        expect($doFicticio)->toHaveCount(0);
    });
});
