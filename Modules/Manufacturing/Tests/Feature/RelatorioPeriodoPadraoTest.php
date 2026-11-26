<?php

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-REPORT-08 — o relatório abre no mês corrente.
 *
 * Contrato: Pendências da Fabricação P-01 (medição de 2026-11-25) e o protótipo
 * (`manufacturing-producao.jsx`, datas já preenchidas com o mês), aprovado por [W] em 2026-11-25.
 * Sem nenhuma das duas datas na URL o servidor filtra do dia 1 ao último dia do mês e devolve as
 * datas para a tela mostrar. Data vazia enviada (`start_date=&end_date=`) é escolha de ver tudo.
 *
 * Âncora positiva: a ordem de 3 meses atrás some no padrão e volta quando o período é limpo; a do mês
 * aparece nos dois. Tenant 98 (ADR 0358), nunca biz=4. Transação desfeita.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see resources/js/Pages/Manufacturing/Report.casos.md UC-REPORT-08
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('mfg_recipes')) {
        $this->markTestSkipped('Schema MySQL UltimatePOS ausente.');
    }
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $mu);
});

/** Ordem finalizada de produção da receita, na data dada, com 1 unidade produzida. */
function mfgPeriodoOrdem(int $biz, int $dono, int $acabado, string $data): void
{
    $ordem = DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'type' => 'production_purchase', 'status' => 'received', 'payment_status' => 'due',
        'transaction_date' => $data, 'final_total' => 0, 'mfg_is_final' => 1,
        'ref_no' => 'OP-PER-'.random_int(1000, 9999), 'created_by' => $dono,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $ordem, 'variation_id' => $acabado,
        'product_id' => DB::table('variations')->where('id', $acabado)->value('product_id'),
        'quantity' => 1, 'purchase_price' => 0, 'purchase_price_inc_tax' => 0, 'item_tax' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function mfgPeriodoProps($resp): array
{
    $resp->assertOk();

    return (array) data_get($resp->viewData('page'), 'props', []);
}

describe('UC-REPORT-08 — o relatório abre no mês corrente', function () {
    it('UC-REPORT-08 sem datas na URL filtra o mes corrente; periodo limpo mostra tudo', function () {
        mfgEmpNegocio(MFG_EMP_BIZ);
        $dono = (int) DB::table('users')->min('id');
        [$receita, $acabado] = mfgEmpReceita(MFG_EMP_BIZ, $dono, 'Insumo do periodo padrao');
        mfgPeriodoOrdem(MFG_EMP_BIZ, $dono, $acabado, now()->startOfMonth()->addDay()->toDateTimeString());
        mfgPeriodoOrdem(MFG_EMP_BIZ, $dono, $acabado, now()->startOfMonth()->subMonths(3)->toDateTimeString());

        $usuario = $this->usuarioComPermissoes(
            ['manufacturing.access_recipe', 'manufacturing.access_production', 'view_purchase_price', 'access_all_locations'],
            \App\Business::find(MFG_EMP_BIZ),
        );
        $ordensDa = fn (array $props): int => (int) collect($props['relatorio']['linhas'])
            ->firstWhere('recipe_id', $receita)['ordens'] ?? 0;

        $padrao = mfgPeriodoProps($this->actingAs($usuario)->get('/manufacturing/report'));
        expect($padrao['filters']['start_date'])->toBe(now()->startOfMonth()->toDateString());
        expect($padrao['filters']['end_date'])->toBe(now()->endOfMonth()->toDateString());
        expect($ordensDa($padrao))->toBe(1);

        $tudo = mfgPeriodoProps($this->actingAs($usuario)->get('/manufacturing/report?start_date=&end_date='));
        expect($tudo['filters']['start_date'])->toBeNull();
        expect($tudo['filters']['end_date'])->toBeNull();
        expect($ordensDa($tudo))->toBe(2);

        $antigo = now()->startOfMonth()->subMonths(3);
        $escolhido = mfgPeriodoProps($this->actingAs($usuario)->get(
            '/manufacturing/report?start_date='.$antigo->toDateString().'&end_date='.$antigo->copy()->endOfMonth()->toDateString()
        ));
        expect($escolhido['filters']['start_date'])->toBe($antigo->toDateString());
        expect($ordensDa($escolhido))->toBe(1);
    });
});
