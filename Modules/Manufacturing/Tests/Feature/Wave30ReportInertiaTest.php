<?php

declare(strict_types=1);

use App\Variation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Modules\Manufacturing\Services\ProductionService;
use Modules\Manufacturing\Services\RecipeBomService;

uses(Tests\TestCase::class);

/**
 * Wave 30 — o relatório de produção do período (Manufacturing/Report) em
 * `/manufacturing/v2/report`.
 *
 * Contrato = os UC de `resources/js/Pages/Manufacturing/Report.casos.md`, que derivam do
 * §4.6 do handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" e do SPEC.md (US-MANU-002) —
 * NÃO do `.tsx` (§5 tautológico das proibições).
 *
 * Mesma divisão do Wave29RecipeInertiaTest (irmão desta suíte):
 *  · CÁLCULO é DB-less (models em memória via setRelation) — roda na lane sqlite, e é onde
 *    mora a defesa do dinheiro (REGRA MESTRE de VALOR, proibicoes.md: dupla prova).
 *  · ROTA precisa do schema UltimatePOS real e pula em sqlite.
 *
 * Tenant fictício: NUNCA biz=4 (ROTA LIVRE — Larissa em produção). ADR 0358.
 *
 * @covers-us US-MANU-002
 *
 * @see resources/js/Pages/Manufacturing/Report.casos.md
 * @see memory/requisitos/Manufacturing/RUNBOOK-report.md
 */

defined('BIZ_FICTICIO_MFG') || define('BIZ_FICTICIO_MFG', 98);

/**
 * Monta uma recipe em memória — mesma forma de Wave29RecipeInertiaTest::mfgReceitaFake(),
 * nome próprio pra não colidir (a função dela é global e a suite carrega os dois arquivos).
 */
function mfgReceitaRelatorioFake(array $attrs, array $ingredientes): MfgRecipe
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
        $ing->setRelation('sub_unit', null);

        return $ing;
    });

    $recipe->setRelation('ingredients', $itens);

    return $recipe;
}

describe('UC-REPORT-03 — custo da ordem = calculateUnitCost x quantidade produzida (DB-less)', function () {

    // UC-REPORT-03 — REGRA MESTRE de VALOR: dois caminhos independentes, números concretos.
    // Caminho 1 = o código real (RecipeBomService::calculateUnitCost × quantidade produzida).
    // Caminho 2 = a álgebra de consumoOP() do protótipo, reescrita aqui à mão pra cada fórmula
    // (RUNBOOK-report.md §1 tem a prova completa lado a lado).
    it('UC-REPORT-03 reproduz consumoOP() do prototipo nas 3 formulas de production_cost_type', function () {
        $bom = new RecipeBomService();

        // 10 un a 9,20 = 92,00 de ingredientes · total_quantity=10 · extra_cost=18.
        $base = [['quantity' => 10, 'preco' => 9.20]];
        $qtdProduzida = 4.0; // a ordem produziu 4 das 10 canônicas — fator = 0,4
        $ingredientesEscalados = 92.0 * ($qtdProduzida / 10); // = 36,8 — mesmo que os `linhas` do protótipo

        // ── percentage ──
        $percentual = mfgReceitaRelatorioFake(
            ['total_quantity' => 10, 'extra_cost' => 18, 'production_cost_type' => 'percentage'],
            $base,
        );
        $caminho1Pct = $bom->calculateUnitCost($percentual) * $qtdProduzida;
        $caminho2Pct = $ingredientesEscalados + $ingredientesEscalados * (18 / 100);
        expect(round($caminho1Pct, 6))->toBe(round($caminho2Pct, 6));
        expect(round($caminho1Pct, 2))->toBe(43.42); // 36,8 + 6,624 = 43,424 ≈ 43,42

        // ── per_unit ──
        $porUnidade = mfgReceitaRelatorioFake(
            ['total_quantity' => 10, 'extra_cost' => 18, 'production_cost_type' => 'per_unit'],
            $base,
        );
        $caminho1Un = $bom->calculateUnitCost($porUnidade) * $qtdProduzida;
        $caminho2Un = $ingredientesEscalados + 18 * $qtdProduzida;
        expect(round($caminho1Un, 6))->toBe(round($caminho2Un, 6));
        expect(round($caminho1Un, 2))->toBe(108.8); // 36,8 + 72,0

        // ── fixed (default) ──
        $fixo = mfgReceitaRelatorioFake(
            ['total_quantity' => 10, 'extra_cost' => 18, 'production_cost_type' => 'fixed'],
            $base,
        );
        $caminho1Fix = $bom->calculateUnitCost($fixo) * $qtdProduzida;
        $caminho2Fix = $ingredientesEscalados + 18 * ($qtdProduzida / 10);
        expect(round($caminho1Fix, 6))->toBe(round($caminho2Fix, 6));
        expect(round($caminho1Fix, 2))->toBe(44.0); // 36,8 + 7,2

        // As três precisam dar totais DIFERENTES — mesma defesa do UC-RECIPE-04 (unificar
        // as fórmulas "porque é mais limpo" é o anti-padrão que este assert mata).
        $totais = array_map(fn ($v) => round($v, 6), [$caminho1Pct, $caminho1Un, $caminho1Fix]);
        expect(count(array_unique($totais)))->toBe(3);
    });
});

describe('UC-REPORT-00 — alcance pela aba Relatório (DB-less)', function () {

    // UC-REPORT-00 — a aba do módulo aponta pra tela React do Relatório.
    //
    // ⚠️ INVERTIDO no cutover de 2026-09-04: até então a tela React vivia em `/v2/report` e o
    // endereço canônico servia Blade — o assert exigia o `/v2/` e PROIBIA o canônico. Agora o
    // canônico É a tela React (o `/v2/` virou 301), então o contrato inverte junto. O que o UC
    // defende não mudou: a aba leva à tela React, nunca ao Blade.
    it('UC-REPORT-00 a aba Relatorio de Recipes.tsx aponta pra /manufacturing/report', function () {
        $fonte = file_get_contents(base_path('resources/js/Pages/Manufacturing/Recipes.tsx'));

        expect($fonte)->toContain("href=\"/manufacturing/report\"");
        expect($fonte)->not->toContain("href=\"/manufacturing/v2/report\""); // sem rota alternativa
    });
});

describe('UC-REPORT-04 — "Só finalizadas" default ligado (DB-less)', function () {

    // UC-REPORT-04 — assert estrutural: o código tem que resolver ausência de param como
    // TRUE, nunca FALSE. `request()->boolean('is_final')` sozinho resolveria false quando o
    // param está ausente — é o bug que este assert mata.
    it('UC-REPORT-04 is_final ausente resolve para true no controller', function () {
        $fonte = file_get_contents(base_path('Modules/Manufacturing/Http/Controllers/ProductionController.php'));

        expect($fonte)->toContain("! request()->has('is_final') || request()->boolean('is_final')");
    });
});

describe('UC-REPORT-01/02 — a rota e o isolamento de tenant (schema MySQL real)', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompatível: a rota depende do schema MySQL UltimatePOS (transactions/purchase_lines/business).');
        }
        if (! Schema::hasTable('mfg_recipes') || ! Schema::hasTable('business')) {
            $this->markTestSkipped('Schema Manufacturing/UltimatePOS ausente neste ambiente.');
        }
    });

    // UC-REPORT-01 — o endereço CANÔNICO existe e é do ProductionController.
    //
    // ⚠️ Reapontado no cutover de 2026-09-04. Antes o alvo era `/v2/report`; ele continua
    // registrado, mas agora como REDIRECT (`RedirectController`), então asserir o controller
    // nele passaria a medir a coisa errada. O que interessa é quem serve o canônico.
    it('UC-REPORT-01 a rota /manufacturing/report esta registrada no runtime', function () {
        // Oráculo é o registry vivo (route collection), não a leitura do arquivo — §5 2026-07-28.
        $rota = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'manufacturing/report' && in_array('GET', $r->methods(), true));

        expect($rota)->not->toBeNull('A rota GET /manufacturing/report sumiu do registry.');
        expect($rota->getActionName())->toContain('ProductionController');
    });

    // UC-REPORT-02 — Tier 0 + §7.3. Tenant fictício sem produção nenhuma: o relatório TEM
    // que voltar vazio, sem NaN nem erro — nunca vazamento de outro business.
    it('UC-REPORT-02 reportByProduct nao vaza producao de outro business e devolve 0 sem dado', function () {
        $service = new ProductionService(null, new RecipeBomService());

        $resultado = $service->reportByProduct(BIZ_FICTICIO_MFG, ['is_final' => true]);

        expect($resultado['linhas'])->toBe([]);
        expect($resultado['total'])->toBe(0.0);
        expect(is_finite($resultado['total']))->toBeTrue();
    });
});
