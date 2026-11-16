<?php

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Custo só chega a quem pode ver preço de compra — auditoria do Manufacturing, lote 2.6 (eixo 5 M1).
 *
 * Contrato: decisão B3 de [W] em 2026-11-09 — o custo fica atrás de `view_purchase_price`, a mesma
 * permissão que o núcleo usa para esconder preço de compra (produtos, compras). Esconder na tela não
 * basta: o dado não pode sair do servidor (a "Via de produção" da ficha escondia só no navegador).
 *
 *  - UC-RECIPE-26 Receitas: custo, margem, preço e custo médio da lista vêm vazios;
 *  - UC-INGRED-16 Editor: o custo de cada insumo vem vazio; o custo extra da receita segue (é campo do
 *                 formulário, e esvaziá-lo zeraria a receita ao salvar);
 *  - UC-INS-07   Insumos: custo do insumo e custo/margem por receita vêm vazios;
 *  - UC-OP-24    Ordens: total e custo unitário da lista, e o valor somado, vêm vazios;
 *  - UC-REPORT-07 Relatório: custos e percentuais vêm vazios.
 *
 * Cada caso tem a âncora: o mesmo pedido, com `view_purchase_price`, traz os números.
 *
 * O pacote vem de um ModuleUtil parcial; as permissões são reais (papel do tenant). Tenant 98
 * (fictício, ADR 0358). NUNCA biz=4. Tudo dentro de transação desfeita.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
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

const MFG_CUSTO_MODULO = ['manufacturing.access_recipe', 'manufacturing.access_production', 'manufacturing.add_recipe'];

function mfgCustoUsuario($teste, bool $veCusto): \App\User
{
    $perms = $veCusto ? array_merge(MFG_CUSTO_MODULO, ['view_purchase_price']) : MFG_CUSTO_MODULO;

    return $teste->usuarioComPermissoes($perms, \App\Business::find(MFG_EMP_BIZ));
}

/** Receita (insumo 7,5 × 2 = 15) e uma ordem gravada com total 15. Devolve [receita, acabado, insumo, ordem]. */
function mfgCustoFixture(): array
{
    mfgEmpNegocio(MFG_EMP_BIZ);
    $dono = (int) DB::table('users')->min('id');
    [$receita, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $dono, 'Insumo custo por permissao');
    $insumo = (int) DB::table('mfg_recipe_ingredients')->where('id', $linha)->value('variation_id');
    $ordem = DB::table('transactions')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'type' => 'production_purchase', 'status' => 'received', 'payment_status' => 'due',
        'transaction_date' => now(), 'final_total' => 15, 'mfg_is_final' => 1, 'ref_no' => 'OP-CUSTO-'.random_int(1000, 9999),
        'created_by' => $dono, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$receita, $acabado, $insumo, $ordem];
}

function mfgCustoProps($resp): array
{
    $resp->assertOk();

    return (array) data_get($resp->viewData('page'), 'props', []);
}

describe('Custo atrás de view_purchase_price (B3)', function () {
    it('UC-RECIPE-26 Receitas: sem a permissão custo, margem e preço vêm vazios; com ela, 15', function () {
        [$receita] = mfgCustoFixture();

        foreach ([false, true] as $veCusto) {
            $props = mfgCustoProps($this->actingAs(mfgCustoUsuario($this, $veCusto))->get('/manufacturing/recipe'));
            $linha = collect(data_get($props, 'recipes.data', []))->firstWhere('id', $receita);
            expect($linha)->not->toBeNull('A receita não veio na lista.');

            if ($veCusto) {
                expect((float) $linha['custos']['total'])->toBe(15.0);
                expect((float) $linha['grupos'][0]['itens'][0]['custo_unitario'])->toBe(7.5);
                expect($props['kpis']['custo_medio'])->not->toBeNull();
            } else {
                expect($linha['custos']['total'])->toBeNull();
                expect($linha['custos']['unit'])->toBeNull();
                expect($linha['custos']['margem'])->toBeNull();
                expect($linha['venda'])->toBeNull();
                expect($linha['grupos'][0]['itens'][0]['custo_unitario'])->toBeNull();
                expect($linha['grupos'][0]['subtotal'])->toBeNull();
                expect($props['kpis']['custo_medio'])->toBeNull();
            }
        }
    });

    it('UC-INGRED-16 Editor: sem a permissão o custo do insumo vem vazio, e o custo extra da receita segue', function () {
        [, $acabado] = mfgCustoFixture();

        foreach ([false, true] as $veCusto) {
            $props = mfgCustoProps($this->actingAs(mfgCustoUsuario($this, $veCusto))->get('/manufacturing/add-ingredient?variation_id='.$acabado));
            $custo = $props['grupos'][0]['itens'][0]['custo_unitario'];

            $veCusto ? expect((float) $custo)->toBe(7.5) : expect($custo)->toBeNull();
            expect($props['receita'])->toHaveKey('extra_cost');
            expect($props['perms']['custo'])->toBe($veCusto);
        }
    });

    it('UC-INS-07 Insumos: sem a permissão custo do insumo e custo por receita vêm vazios', function () {
        [, , $insumo] = mfgCustoFixture();

        foreach ([false, true] as $veCusto) {
            $props = mfgCustoProps($this->actingAs(mfgCustoUsuario($this, $veCusto))->get('/manufacturing/insumos?insumo='.$insumo));
            $linha = collect($props['insumos'])->firstWhere('variation_id', $insumo);
            $uso = $props['usos'][0] ?? null;
            expect($linha)->not->toBeNull('O insumo não veio na lista.');
            expect($uso)->not->toBeNull('O uso do insumo não veio.');

            if ($veCusto) {
                expect((float) $linha['custo'])->toBe(7.5);
                expect((float) $uso['unit_atual'])->toBe(15.0);
            } else {
                expect($linha['custo'])->toBeNull();
                expect($uso['unit_atual'])->toBeNull();
                expect($uso['unit_novo'])->toBeNull();
                expect($uso['margem_nova'])->toBeNull();
            }
        }
    });

    it('UC-OP-24 Ordens: sem a permissão o total da ordem e o valor somado vêm vazios', function () {
        [, , , $ordem] = mfgCustoFixture();

        foreach ([false, true] as $veCusto) {
            $props = mfgCustoProps($this->actingAs(mfgCustoUsuario($this, $veCusto))->get('/manufacturing/production'));
            $linha = collect($props['productions'])->firstWhere('id', $ordem);
            expect($linha)->not->toBeNull('A ordem não veio na lista.');

            if ($veCusto) {
                expect((float) $linha['final_total'])->toBe(15.0);
                expect($props['summary']['total_value'])->not->toBeNull();
            } else {
                expect($linha['final_total'])->toBeNull();
                expect($linha['custo_unitario'])->toBeNull();
                expect($props['summary']['total_value'])->toBeNull();
                expect($props['kpis']['total_value'])->toBeNull();
            }
        }
    });

    it('UC-REPORT-07 Relatório: sem a permissão o custo do período vem vazio', function () {
        mfgCustoFixture();

        foreach ([false, true] as $veCusto) {
            $props = mfgCustoProps($this->actingAs(mfgCustoUsuario($this, $veCusto))->get('/manufacturing/report'));

            $veCusto ? expect($props['relatorio']['total'])->toBeFloat() : expect($props['relatorio']['total'])->toBeNull();
            foreach ($props['relatorio']['linhas'] as $l) {
                if (! $veCusto) {
                    expect($l['custo_total'])->toBeNull();
                    expect($l['custo_medio'])->toBeNull();
                }
            }
        }
    });
});
