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
 * @covers-us US-MANU-001
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

/**
 * Uma linha já CALCULADA, no formato de `presentRecipe` — só os campos que filtro, KPI e ordem
 * leem. Os testes abaixo não recalculam custo nenhum: eles provam onde a lista é cortada.
 */
function mfgLinha(int $id, string $name, array $over = []): array
{
    return array_replace_recursive([
        'id' => $id, 'name' => $name, 'sku' => "SKU-{$id}", 'cat' => 'Impressos', 'sub' => '—',
        'waste' => 0.0, 'venda' => 10.0,
        'custos' => ['qtd_liq' => 1.0, 'total' => 1.0, 'unit' => 1.0, 'margem' => 50.0],
    ], $over);
}

/** Ids na ordem em que saíram — é o que a lista mostra. */
function mfgIds(array $linhas): array
{
    return array_map(fn ($l) => $l['id'], $linhas);
}

describe('UC-RECIPE-09/10/11 — filtro, KPI e ordem no servidor (DB-less)', function () {

    // Desde 2026-10-02 a lista pagina no SERVIDOR e estas regras saíram do navegador pra
    // `RecipeBomService::filtrarOrdenar`. Os valores de cada caso foram escolhidos pra separar
    // a regra certa da errada vizinha (44,9 × 45 · 7,99 × 8 · "10" × "9"), não pra passar.

    it('UC-RECIPE-09 a busca casa nome, SKU, categoria e subcategoria, sem diferenciar maiuscula', function () {
        $s = new RecipeBomService();
        $todas = [
            mfgLinha(1, 'Banner Lona', ['sku' => 'BN-01', 'cat' => 'Impressos', 'sub' => 'Lona']),
            mfgLinha(2, 'Adesivo', ['sku' => 'AD-02', 'cat' => 'Adesivos', 'sub' => 'Vinil']),
            mfgLinha(3, 'Placa', ['sku' => 'PL-03', 'cat' => 'Placas', 'sub' => 'ACM']),
        ];

        expect(mfgIds($s->filtrarOrdenar($todas, ['q' => 'LONA'])))->toBe([1]);   // nome e sub
        expect(mfgIds($s->filtrarOrdenar($todas, ['q' => 'ad-02'])))->toBe([2]);  // SKU
        expect(mfgIds($s->filtrarOrdenar($todas, ['q' => 'placas'])))->toBe([3]); // categoria
        expect(mfgIds($s->filtrarOrdenar($todas, ['q' => 'acm'])))->toBe([3]);    // subcategoria
        expect(mfgIds($s->filtrarOrdenar($todas, ['q' => '   '])))->toBe([2, 1, 3]);

        // Categoria é EXATA: "Adesivo" não casa "Adesivos".
        expect(mfgIds($s->filtrarOrdenar($todas, ['cat' => 'Adesivos'])))->toBe([2]);
        expect($s->filtrarOrdenar($todas, ['cat' => 'Adesivo']))->toBe([]);
    });

    it('UC-RECIPE-10 o KPI 2 e o 3 filtram; os numeros do topo contam TODAS as receitas', function () {
        $s = new RecipeBomService();
        $todas = [
            mfgLinha(1, 'a', ['custos' => ['margem' => 44.9, 'unit' => 1.0], 'waste' => 7.99]),
            mfgLinha(2, 'b', ['custos' => ['margem' => 45.0, 'unit' => 2.0], 'waste' => 8.0]),
            mfgLinha(3, 'c', ['custos' => ['margem' => 60.0, 'unit' => 6.0], 'waste' => 0.0]),
        ];

        // R-05: margem ABAIXO de 45 (45 fica fora) · desperdício a partir de 8 (7,99 fica fora).
        expect(mfgIds($s->filtrarOrdenar($todas, ['kpi' => 'margem'])))->toBe([1]);
        expect(mfgIds($s->filtrarOrdenar($todas, ['kpi' => 'custo'])))->toBe([2]);

        // Os KPIs saem da lista inteira — o filtro e a página não mexem neles (§4.2).
        expect($s->kpis($todas))->toBe([
            'total' => 3, 'custo_medio' => 3.0, 'margem_baixa' => 1, 'desperdicio' => 1,
        ]);
        expect($s->kpis([]))->toBe(['total' => 0, 'custo_medio' => 0.0, 'margem_baixa' => 0, 'desperdicio' => 0]);
    });

    it('UC-RECIPE-11 ordena pelas 7 colunas nos dois sentidos, texto por caractere como o navegador', function () {
        $s = new RecipeBomService();
        $todas = [
            mfgLinha(1, '9', ['custos' => ['unit' => 5.0]]),
            mfgLinha(2, '10', ['custos' => ['unit' => 1.0]]),
            mfgLinha(3, 'b', ['custos' => ['unit' => 5.0]]),
        ];

        // Por caractere, "10" vem antes de "9". O `<=>` do PHP compararia como NÚMERO e
        // inverteria os dois — é a diferença que este caso existe pra pegar.
        expect(mfgIds($s->filtrarOrdenar($todas, ['sort' => 'name'])))->toBe([2, 1, 3]);
        expect(mfgIds($s->filtrarOrdenar($todas, ['sort' => 'name', 'dir' => 'desc'])))->toBe([3, 1, 2]);

        // Número em ordem numérica, e o empate (1 e 3 com 5,00) mantém a ordem de chegada.
        expect(mfgIds($s->filtrarOrdenar($todas, ['sort' => 'unit'])))->toBe([2, 1, 3]);
        expect(mfgIds($s->filtrarOrdenar($todas, ['sort' => 'unit', 'dir' => 'desc'])))->toBe([1, 3, 2]);

        // Coluna desconhecida cai no nome — nunca num campo que a URL inventou.
        expect(mfgIds($s->filtrarOrdenar($todas, ['sort' => 'business_id'])))->toBe([2, 1, 3]);
        expect(RecipeBomService::ORDENAVEIS)->toBe(['name', 'cat', 'qtd', 'total', 'unit', 'venda', 'margem']);
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
    //
    // ⚠️ A agulha NÃO fecha o parêntese de propósito (ajustada 2026-09-04). Ela era
    // `'if (request()->ajax())'` — literal exato — e quebrou quando a condição ganhou o guarda
    // `&& ! request()->header('X-Inertia')` (bug em prod: a navegação SPA caía no ramo AJAX e
    // recebia JSON). O que este UC defende é a ORDEM dos dois blocos, não o texto da condição;
    // prender no literal fazia o teste reprovar um endurecimento legítimo da guarda.
    it('UC-RECIPE-07 o ramo ajax do DataTables vem antes do render Inertia', function () {
        $fonte = file_get_contents(base_path('Modules/Manufacturing/Http/Controllers/RecipeController.php'));

        $posAjax = strpos($fonte, 'if (request()->ajax()');
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
