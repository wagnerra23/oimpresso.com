<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Services\ProductionService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Ordem produzida numa sub-unidade (pacote de 10): a lista fala a mesma unidade do painel, e o relatório
 * não multiplica o custo pelo multiplicador.
 *
 * Contrato (não o código): handoff Fabricação §7 ponto 4 (sub-unidade multiplica a linha; a linha mostra a
 * equivalência na base) · UC-OP-09 (lista e painel mostram a mesma quantidade e as mesmas perdidas) ·
 * UC-REPORT-03 (custo da ordem = custo unitário da receita × quantidade produzida) · `show()` da tela antiga
 * (divide a linha do produto pelo multiplicador e mostra a sub-unidade).
 *
 * A linha do produto grava a quantidade NA BASE (o core multiplica: 3 pacotes = 30). A perda
 * (`mfg_wasted_units`) e o custo unitário da receita (`total_quantity` na sub-unidade da receita) estão na
 * unidade da ordem. Misturar as duas é o defeito que estes casos defendem.
 *
 * Dois caminhos em cada caso: a conta à mão e o painel (`detalheOrdem`) ou a soma dos ingredientes.
 *
 * Tenant 98 (fictício, ADR 0358). NUNCA biz=4. Tudo roda dentro de transação desfeita no fim.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Services/ProductionService::enrichProductionRows()
 * @see Modules/Manufacturing/Services/ProductionService::reportByProduct()
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a ordem depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'units', 'products', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients', 'transactions', 'purchase_lines', 'transaction_sell_lines'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

/** Pacote = sub-unidade da unidade do produto da variação. `null` no multiplicador imita a receita de produção que aponta a própria unidade. */
function mfgSubPacote(int $variationId, ?float $multiplicador, int $userId): int
{
    $base = (int) DB::table('products')->where('id', DB::table('variations')->where('id', $variationId)->value('product_id'))->value('unit_id');

    return DB::table('units')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'actual_name' => 'Pacote', 'short_name' => 'PCT', 'allow_decimal' => 1,
        'base_unit_id' => $multiplicador === null ? null : $base, 'base_unit_multiplier' => $multiplicador,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/**
 * Ordem finalizada do acabado: linha do produto com `$quantidadeBase` na base, na sub-unidade dada, e
 * `$perdidas` na unidade da ordem. Devolve o id da ordem.
 */
function mfgSubOrdem(int $acabado, float $quantidadeBase, ?int $subUnidade, float $perdidas, float $total, int $userId): int
{
    $produto = (int) DB::table('variations')->where('id', $acabado)->value('product_id');
    $ordem = DB::table('transactions')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'type' => 'production_purchase', 'status' => 'received',
        'transaction_date' => '2026-10-01 10:00:00', 'created_by' => $userId, 'essentials_duration' => 0,
        'ref_no' => 'OP-SUB-'.random_int(1000, 9999), 'final_total' => $total,
        'mfg_production_cost' => 0, 'mfg_production_cost_type' => 'percentage',
        'mfg_wasted_units' => $perdidas, 'mfg_is_final' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $ordem, 'product_id' => $produto, 'variation_id' => $acabado, 'sub_unit_id' => $subUnidade,
        'quantity' => $quantidadeBase, 'purchase_price' => 1, 'purchase_price_inc_tax' => 1, 'item_tax' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $ordem;
}

function mfgSubLinhaLista(int $ordem): array
{
    $service = new ProductionService();

    return collect($service->enrichProductionRows($service->listProductions(MFG_EMP_BIZ, [], 500), MFG_EMP_BIZ))->keyBy('id')[$ordem];
}

function mfgSubLinhaRelatorio(int $receita): array
{
    $linhas = collect(app(ProductionService::class)->reportByProduct(MFG_EMP_BIZ, ['is_final' => true])['linhas']);

    return $linhas->firstWhere('recipe_id', $receita);
}

describe('Ordem em sub-unidade — lista igual ao painel (UC-OP-09)', function () {
    it('UC-OP-09 — 4 pacotes com 1 perdido: a lista mostra 3 PCT e 1 perdida, como o painel', function () {
        $user = mfgEmpUsuario('mfg_producao_sub_unidade_test');
        [, $acabado] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo da lista em pacote');
        $pacote = mfgSubPacote($acabado, 10, $user->id);
        // 3 pacotes entraram no estoque = 30 na base; 1 pacote se perdeu.
        $ordem = mfgSubOrdem($acabado, 30, $pacote, 1, 60, $user->id);

        $linha = mfgSubLinhaLista($ordem);
        $painel = (new ProductionService())->detalheOrdem(MFG_EMP_BIZ, $ordem);

        // (a) à mão.
        expect($linha['quantidade'])->toEqualWithDelta(3.0, 0.0001);
        expect($linha['unidade'])->toBe('PCT');
        expect($linha['perdidas'])->toEqualWithDelta(1.0, 0.0001);
        expect($linha['custo_unitario'])->toEqualWithDelta(20.0, 0.0001);
        // (b) o painel da mesma ordem.
        expect($linha['quantidade'])->toEqualWithDelta($painel['quantidade'], 0.0001);
        expect($linha['unidade'])->toBe($painel['unidade']);
        expect($linha['perdidas'])->toEqualWithDelta($painel['perdidas'], 0.0001);
    });

    it('UC-OP-09 — sem sub-unidade nada muda: 5 na base, 1 perdida (âncora positiva)', function () {
        $user = mfgEmpUsuario('mfg_producao_sub_unidade_test');
        [, $acabado] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo da lista sem pacote');
        $ordem = mfgSubOrdem($acabado, 5, null, 1, 15, $user->id);

        $linha = mfgSubLinhaLista($ordem);

        expect($linha['quantidade'])->toEqualWithDelta(5.0, 0.0001);
        expect($linha['unidade'])->toBe('UR');
        expect($linha['custo_unitario'])->toEqualWithDelta(3.0, 0.0001);
    });
});

describe('Relatório com receita em sub-unidade (UC-REPORT-05)', function () {
    it('UC-REPORT-05 — receita de 1 pacote de 10: 30 na base são 3 receitas, custo = 3 × custo da receita', function () {
        $user = mfgEmpUsuario('mfg_producao_sub_unidade_test');
        // Receita: 1 pacote consome 2 do insumo a 7,5 = 15 por pacote.
        [$receita, $acabado] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo do relatorio em pacote');
        $pacote = mfgSubPacote($acabado, 10, $user->id);
        DB::table('mfg_recipes')->where('id', $receita)->update(['sub_unit_id' => $pacote, 'total_quantity' => 1, 'extra_cost' => 0, 'production_cost_type' => 'percentage']);
        mfgSubOrdem($acabado, 30, $pacote, 0, 45, $user->id);

        $linha = mfgSubLinhaRelatorio($receita);

        // (a) à mão: 3 pacotes × 15.
        expect($linha['custo_total'])->toEqualWithDelta(45.0, 0.0001);
        // (b) pelos ingredientes: 3 pacotes × (2 × 7,5).
        expect($linha['custo_total'])->toEqualWithDelta(3 * 2 * 7.5, 0.0001);
        // A quantidade do relatório segue na base, com o custo médio por unidade base.
        expect($linha['quantidade'])->toEqualWithDelta(30.0, 0.0001);
        expect($linha['custo_medio'])->toEqualWithDelta(1.5, 0.0001);
    });

    it('UC-REPORT-05 — receita na própria unidade, sem multiplicador gravado (caso de produção): custo = quantidade × custo da receita', function () {
        $user = mfgEmpUsuario('mfg_producao_sub_unidade_test');
        [$receita, $acabado] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo do relatorio sem multiplicador');
        $propria = mfgSubPacote($acabado, null, $user->id);
        DB::table('mfg_recipes')->where('id', $receita)->update(['sub_unit_id' => $propria, 'total_quantity' => 1, 'extra_cost' => 0, 'production_cost_type' => 'percentage']);
        mfgSubOrdem($acabado, 4, null, 0, 60, $user->id);

        $linha = mfgSubLinhaRelatorio($receita);

        expect($linha['custo_total'])->toEqualWithDelta(60.0, 0.0001);
        expect($linha['custo_total'])->toEqualWithDelta(4 * 2 * 7.5, 0.0001);
    });
});
