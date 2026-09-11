<?php

declare(strict_types=1);

use App\PurchaseLine;
use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Services\ProductionService;
use Modules\Manufacturing\Services\RecipeBomService;

uses(Tests\TestCase::class);

/**
 * Wave 32 — as 8 colunas das Ordens de produção (Manufacturing/Index) em
 * `/manufacturing/v2/production`.
 *
 * Contrato = os UC de `resources/js/Pages/Manufacturing/Index.casos.md`, derivados do §4.5
 * do handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" — NÃO do `.tsx` (§5 tautológico).
 *
 * A tela é da Wave J; esta onda é EMENDA (5 → 8 colunas). O que os testes defendem é
 * exatamente o que a emenda arrisca: divisão por zero na coluna nova, uma SEGUNDA fórmula de
 * custo, e N+1 no enriquecimento.
 *
 * Tenant fictício: NUNCA biz=4 (ROTA LIVRE — Larissa em produção). ADR 0358.
 *
 * @covers-us US-MANU-004
 *
 * @see resources/js/Pages/Manufacturing/Index.casos.md
 * @see memory/requisitos/Manufacturing/RUNBOOK-producao.md
 */

defined('BIZ_FICTICIO_MFG') || define('BIZ_FICTICIO_MFG', 98);

/**
 * Ordem de produção em memória — zero DB. `variation_id` aponta pra uma variação que não
 * existe de propósito nos casos onde o que importa é a ARITMÉTICA da linha, não o join.
 */
function mfgOrdemFake(float $finalTotal, float $quantidade, int $isFinal = 0, ?int $variationId = 999999): Transaction
{
    $ordem = new Transaction([
        'ref_no' => 'OP-TESTE',
        'final_total' => $finalTotal,
        'mfg_is_final' => $isFinal,
    ]);
    $ordem->id = 1;
    $ordem->created_by = null;
    $ordem->transaction_date = now();

    $linha = new PurchaseLine(['quantity' => $quantidade]);
    $linha->variation_id = $variationId;

    $ordem->setRelation('purchase_lines', collect([$linha]));
    $ordem->setRelation('location', null);

    return $ordem;
}

describe('UC-OP-02 — o custo é o GRAVADO, não uma segunda fórmula (DB-less)', function () {

    // UC-OP-02 — o enriquecimento NÃO pode chamar o RecipeBomService: a fórmula que
    // recalcula pelo preço de hoje é do Relatório (US-MANU-002). Duas fórmulas de custo
    // na mesma base é o anti-padrão que este assert mata.
    it('UC-OP-02 enrichProductionRows nao recalcula custo pelo RecipeBomService', function () {
        $fonte = file_get_contents(base_path('Modules/Manufacturing/Services/ProductionService.php'));

        $inicio = strpos($fonte, 'public function enrichProductionRows');
        $fim = strpos($fonte, 'public function summary');

        expect($inicio)->not->toBeFalse();
        expect($fim)->not->toBeFalse();

        $corpo = substr($fonte, $inicio, $fim - $inicio);

        expect($corpo)->not->toContain('calculateUnitCost');
        expect($corpo)->not->toContain('bomService');
        // E o número mostrado vem do campo gravado.
        expect($corpo)->toContain('$ordem->final_total');
    });

    // UC-OP-04 — a contagem de ingredientes é leftJoin: ordem sem receita CONTINUA na lista.
    it('UC-OP-04 a contagem de ingredientes usa leftJoin, nao join', function () {
        $fonte = file_get_contents(base_path('Modules/Manufacturing/Services/ProductionService.php'));

        $inicio = strpos($fonte, 'public function enrichProductionRows');
        $corpo = substr($fonte, $inicio, strpos($fonte, 'public function summary') - $inicio);

        expect($corpo)->toContain("leftJoin('mfg_recipe_ingredients as i'");
    });
});

describe('UC-OP-01/03/04/05 — comportamento (schema MySQL real)', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompatível: depende do schema MySQL UltimatePOS (variations/products/users).');
        }
        if (! Schema::hasTable('mfg_recipes') || ! Schema::hasTable('variations')) {
            $this->markTestSkipped('Schema Manufacturing/UltimatePOS ausente neste ambiente.');
        }
    });

    // UC-OP-01 — quantidade zero devolve custo unitário 0.0, nunca INF/NaN.
    it('UC-OP-01 custo unitario e 0.0 quando a quantidade da ordem e zero', function () {
        $service = new ProductionService(null, new RecipeBomService());

        $linhas = $service->enrichProductionRows(
            collect([mfgOrdemFake(finalTotal: 250.0, quantidade: 0.0)]),
            BIZ_FICTICIO_MFG
        );

        expect($linhas)->toHaveCount(1);
        expect($linhas[0]['custo_unitario'])->toBe(0.0);
        expect(is_finite($linhas[0]['custo_unitario']))->toBeTrue();
        expect(is_nan($linhas[0]['custo_unitario']))->toBeFalse();
    });

    // UC-OP-01 (par positivo) — com quantidade > 0 a conta é total / quantidade.
    it('UC-OP-01 custo unitario e total dividido pela quantidade produzida', function () {
        $service = new ProductionService(null, new RecipeBomService());

        // 250,00 / 4 = 62,50
        $linhas = $service->enrichProductionRows(
            collect([mfgOrdemFake(finalTotal: 250.0, quantidade: 4.0)]),
            BIZ_FICTICIO_MFG
        );

        expect($linhas[0]['custo_unitario'])->toBe(62.5);
        expect($linhas[0]['final_total'])->toBe(250.0);
    });

    // UC-OP-04 — ordem cuja variação não tem receita continua na lista, com 0 ingredientes.
    it('UC-OP-04 ordem sem receita continua na lista com 0 ingredientes', function () {
        $service = new ProductionService(null, new RecipeBomService());

        $linhas = $service->enrichProductionRows(
            collect([mfgOrdemFake(finalTotal: 100.0, quantidade: 2.0, variationId: 999999)]),
            BIZ_FICTICIO_MFG
        );

        expect($linhas)->toHaveCount(1, 'A ordem sumiu da lista por não ter receita — leftJoin virou join?');
        expect($linhas[0]['n_ingredientes'])->toBe(0);
    });

    // UC-OP-03 — o enriquecimento é em LOTE: o nº de queries NÃO cresce com o nº de ordens.
    it('UC-OP-03 o numero de queries nao cresce com o numero de ordens', function () {
        $service = new ProductionService(null, new RecipeBomService());

        $contar = function (int $quantasOrdens) use ($service): int {
            $ordens = collect(range(1, $quantasOrdens))
                ->map(fn ($i) => mfgOrdemFake(finalTotal: 100.0 * $i, quantidade: 2.0, variationId: 900000 + $i));

            $queries = 0;
            DB::listen(function () use (&$queries) {
                $queries++;
            });

            $service->enrichProductionRows($ordens, BIZ_FICTICIO_MFG);

            return $queries;
        };

        $comUma = $contar(1);
        $comCinco = $contar(5);

        // Cada chamada tem seu PRÓPRIO contador (closure nova), então cada número é o custo
        // daquela rodada — não acumulado. Em lote os dois têm que ser IGUAIS; com N+1 o
        // segundo cresce. Comparar por "<=" aqui deixaria N+1 passar (4 vs 8 → delta 4 ≤ 4),
        // e um teste que não pode falhar não é teste.
        expect($comCinco)->toBe(
            $comUma,
            "5 ordens custaram {$comCinco} queries contra {$comUma} de 1 ordem — o enriquecimento virou N+1."
        );
    });

    // UC-OP-05 — Tier 0: tenant fictício sem ordens devolve lista vazia.
    it('UC-OP-05 listProductions nao devolve ordem de outro business', function () {
        $service = new ProductionService(null, new RecipeBomService());

        expect($service->listProductions(BIZ_FICTICIO_MFG))->toHaveCount(0);
    });
});
