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
 * Wave 33 — Insumos: impacto reverso + simulador de preço (Manufacturing/Insumos),
 * em `/manufacturing/v2/insumos`.
 *
 * Contrato = os UC de `resources/js/Pages/Manufacturing/Insumos.casos.md`, derivados do §4.4
 * e do §18.3 do handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" — NÃO do `.tsx`.
 *
 * É a onda que trouxe o backend que o §18.3 declarava faltar. O que os testes defendem é o
 * que ela arrisca: vazamento de tenant, e a simulação virar o atalho aditivo do protótipo.
 *
 * Tenant fictício: NUNCA biz=4 (ROTA LIVRE — Larissa em produção). ADR 0358.
 *
 * @covers-us US-MANU-005
 *
 * @see resources/js/Pages/Manufacturing/Insumos.casos.md
 * @see memory/requisitos/Manufacturing/RUNBOOK-insumos.md
 */

defined('BIZ_FICTICIO_MFG') || define('BIZ_FICTICIO_MFG', 98);

/**
 * Receita em memória — nome próprio pra não colidir com as helpers das ondas 29/30, que são
 * funções globais e já estão declaradas quando a suíte carrega os dois arquivos.
 *
 * `$ingredientes`: cada item é [quantidade, preço, multiplicador da sub-unidade].
 */
function mfgReceitaInsumoFake(array $attrs, array $ingredientes): MfgRecipe
{
    $recipe = new MfgRecipe(array_merge([
        'total_quantity' => 10,
        'waste_percent' => 0,
        'extra_cost' => 0,
        'production_cost_type' => 'fixed',
        'final_price' => 0,
    ], $attrs));

    $itens = collect($ingredientes)->map(function (array $i) {
        $ing = new MfgRecipeIngredient(['quantity' => $i['quantity']]);
        $ing->variation_id = $i['variation_id'] ?? 501;

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

describe('UC-INS-02 — a simulação recalcula, não soma delta (DB-less)', function () {

    // UC-INS-02 — o ponto da onda. O protótipo aproxima com `total + qtd × preço × pct`;
    // essa conta ERRA quando o custo extra é percentual, porque o extra acompanha o
    // ingrediente. Aqui os dois caminhos são calculados com números concretos e o teste
    // exige que DIFIRAM em `percentage` e COINCIDAM em `fixed`.
    it('UC-INS-02 recalculo e atalho aditivo divergem em percentage e coincidem em fixed', function () {
        $bom = new RecipeBomService();

        $qtd = 10.0;
        $preco = 9.20;          // ingredientes = 92,00
        $pct = 10.0;            // insumo sobe 10%
        $deltaAditivo = $qtd * $preco * ($pct / 100); // 9,20 — o que o protótipo soma

        // ── percentage: extra = 18% dos ingredientes ──
        $pctRecipe = mfgReceitaInsumoFake(
            ['total_quantity' => 10, 'extra_cost' => 18, 'production_cost_type' => 'percentage'],
            [['quantity' => $qtd, 'preco' => $preco]],
        );
        $pctRecipeNovo = mfgReceitaInsumoFake(
            ['total_quantity' => 10, 'extra_cost' => 18, 'production_cost_type' => 'percentage'],
            [['quantity' => $qtd, 'preco' => $preco * (1 + $pct / 100)]],
        );

        $atualPct = $bom->calculateCost($pctRecipe);          // 92 + 16,56 = 108,56
        $recalculadoPct = $bom->calculateCost($pctRecipeNovo); // 101,20 + 18,216 = 119,416
        $atalhoPct = $atualPct + $deltaAditivo;                // 108,56 + 9,20 = 117,76

        expect(round($atualPct, 2))->toBe(108.56);
        expect(round($recalculadoPct, 3))->toBe(119.416);
        expect(round($atalhoPct, 2))->toBe(117.76);

        // O atalho SUBESTIMA — e é exatamente por isso que esta tela não o usa.
        expect($recalculadoPct)->toBeGreaterThan(
            $atalhoPct,
            'O recálculo deveria superar o atalho aditivo em receita de custo percentual.'
        );

        // ── fixed: o extra não varia com o preço do insumo ──
        $fixRecipe = mfgReceitaInsumoFake(
            ['total_quantity' => 10, 'extra_cost' => 18, 'production_cost_type' => 'fixed'],
            [['quantity' => $qtd, 'preco' => $preco]],
        );
        $fixRecipeNovo = mfgReceitaInsumoFake(
            ['total_quantity' => 10, 'extra_cost' => 18, 'production_cost_type' => 'fixed'],
            [['quantity' => $qtd, 'preco' => $preco * (1 + $pct / 100)]],
        );

        $recalculadoFix = $bom->calculateCost($fixRecipeNovo);
        $atalhoFix = $bom->calculateCost($fixRecipe) + $deltaAditivo;

        expect(round($recalculadoFix, 6))->toBe(round($atalhoFix, 6));
    });

    // UC-INS-02 (2ª perna) — o código do Service tem que USAR o recálculo. O teste acima prova
    // a aritmética; este prova que `usosDoInsumo` chama o caminho certo, e não soma delta.
    it('UC-INS-02 usosDoInsumo delega ao recalculo, sem somar delta', function () {
        $fonte = file_get_contents(base_path('Modules/Manufacturing/Services/RecipeBomService.php'));

        $ini = strpos($fonte, 'public function usosDoInsumo');
        $fim = strpos($fonte, 'public function listInsumosComUso');
        expect($ini)->not->toBeFalse();
        expect($fim)->not->toBeFalse();

        $corpo = substr($fonte, $ini, $fim - $ini);

        expect($corpo)->toContain('calculateCostComPrecoSimulado');
        // O atalho do protótipo somaria o delta na cara do total — não pode aparecer aqui.
        expect($corpo)->not->toContain('$custoAtual +');
    });
});

describe('UC-INS-03/04 — consumo e divisão por zero (DB-less)', function () {

    // UC-INS-03 — sub-unidade multiplica e linha repetida SOMA. Aqui a prova é pelo custo,
    // que é onde o multiplicador entra: 0,02 + 0,03 = 0,05 × 5 = 0,25 na unidade base.
    //
    // ⚠️ O que este teste NÃO prova: o campo `qtd` que o payload devolve (esse caminho passa
    // por DB). Ele prova que a MESMA regra de multiplicador/soma vale na fórmula que a
    // simulação usa.
    it('UC-INS-03 sub-unidade multiplica e linhas repetidas do mesmo insumo somam', function () {
        $bom = new RecipeBomService();

        // 2 linhas do mesmo insumo, galão de 5 L a 108,00/L.
        $recipe = mfgReceitaInsumoFake(
            ['total_quantity' => 1, 'extra_cost' => 0, 'production_cost_type' => 'fixed'],
            [
                ['quantity' => 0.02, 'preco' => 108.0, 'multiplicador' => 5, 'variation_id' => 501],
                ['quantity' => 0.03, 'preco' => 108.0, 'multiplicador' => 5, 'variation_id' => 501],
            ],
        );

        // (0,02 + 0,03) × 5 × 108 = 27,00 — não 5,40 (sem multiplicador) nem 10,80 (1 linha só).
        expect(round($bom->calculateCost($recipe), 2))->toBe(27.0);
    });

    // UC-INS-04 — denominador zero devolve 0, nunca NaN/Infinity.
    it('UC-INS-04 total_quantity zero devolve custo unitario 0 e margem 0', function () {
        $bom = new RecipeBomService();

        $recipe = mfgReceitaInsumoFake(
            ['total_quantity' => 0, 'final_price' => 0, 'extra_cost' => 0, 'production_cost_type' => 'fixed'],
            [['quantity' => 3, 'preco' => 10.0]],
        );

        $unit = $bom->calculateUnitCost($recipe);
        $venda = (float) $recipe->final_price;
        $margem = $venda > 0 ? ($venda - $unit) / $venda * 100 : 0.0;

        expect($unit)->toBe(0.0);
        expect($margem)->toBe(0.0);
        expect(is_finite($unit) && is_finite($margem))->toBeTrue();
    });
});

describe('UC-INS-05 — o clamp do variacao_pct (DB-less)', function () {

    // UC-INS-05 — a faixa do §4.4 é do SERVIDOR, não do slider. Sem o clamp, uma URL com
    // ?variacao_pct=999 simularia um aumento de 999%.
    it('UC-INS-05 o controller clampa variacao_pct em -30..60', function () {
        $fonte = file_get_contents(base_path('Modules/Manufacturing/Http/Controllers/RecipeController.php'));

        expect($fonte)->toContain('max(-30.0, min(60.0, $pct))');
    });
});

describe('UC-INS-01 — isolamento de tenant (schema MySQL real)', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompatível: depende do schema MySQL UltimatePOS (variations/products).');
        }
        if (! Schema::hasTable('mfg_recipes') || ! Schema::hasTable('variation_location_details')) {
            $this->markTestSkipped('Schema Manufacturing/UltimatePOS ausente neste ambiente.');
        }
    });

    // UC-INS-01 — tenant fictício sem receita nenhuma: as duas leituras voltam vazias.
    it('UC-INS-01 listInsumosComUso e usosDoInsumo nao vazam de outro business', function () {
        $bom = new RecipeBomService();

        expect($bom->listInsumosComUso(BIZ_FICTICIO_MFG))->toBe([]);
        expect($bom->usosDoInsumo(999999, BIZ_FICTICIO_MFG, 10.0))->toBe([]);
    });

    // A rota aditiva existe e é do RecipeController — oráculo é o registry, não o arquivo.
    it('UC-INS-01 a rota /manufacturing/v2/insumos esta registrada no runtime', function () {
        $rota = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'manufacturing/v2/insumos' && in_array('GET', $r->methods(), true));

        expect($rota)->not->toBeNull('A rota GET /manufacturing/v2/insumos sumiu do registry.');
        expect($rota->getActionName())->toContain('RecipeController');
    });
});
