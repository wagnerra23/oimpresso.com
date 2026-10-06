<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Manufacturing\Http\Controllers\ProductionController;
use Modules\Manufacturing\Services\ProductionService;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Detalhe de uma ordem de produção (painel lateral da tela de Ordens) — UC-OP-07.
 *
 * O painel mostra os ingredientes consumidos e o custo. As contas são as do detalhe da tela
 * antiga (`ProductionController::show()`), não uma fórmula nova. Por isso cada caso confere o
 * resultado por DOIS caminhos independentes:
 *  (a) a conta à mão, com os números da fixture escritos no teste;
 *  (b) as variáveis que o `show()` legado entrega à sua view.
 *
 * Números escolhidos para separar as leituras possíveis:
 *  - insumo A: preço de hoje 4, preço gravado na produção 3, quantidade 3 → 12 (com o gravado daria 9);
 *  - insumo B, no grupo "Base": preço de hoje 2,5, quantidade 2 → 5;
 *  - ingredientes = 17. Ordem produziu 5, perdeu 1. Valor gravado (final_total) = 15.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como a outra empresa. NUNCA biz=4. Tudo em transação desfeita.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Services/ProductionService::detalheOrdem()
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a ordem depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'units', 'products', 'variations', 'transactions', 'purchase_lines', 'transaction_sell_lines', 'mfg_ingredient_groups'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

function mfgDetUsuario()
{
    $user = mfgEmpUsuario('mfg_detalhe_ordem_test');
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'manufacturing.access_production', 'guard_name' => 'web']));

    return $user;
}

/**
 * Ordem de produção com 2 ingredientes, na empresa `$biz`.
 * Devolve o id da ordem (`production_purchase`).
 */
function mfgDetOrdem(int $biz, int $userId, string $tipoCusto, float $custo): int
{
    [$produto, $varProduto] = mfgEmpProduto($biz, 'Produto da ordem', $userId);
    [$prodA, $varA] = mfgEmpProduto($biz, 'Insumo A', $userId, 4.0);
    [$prodB, $varB] = mfgEmpProduto($biz, 'Insumo B', $userId, 2.5);
    $grupo = DB::table('mfg_ingredient_groups')->insertGetId([
        'name' => 'Base', 'business_id' => $biz, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $ordem = DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'type' => 'production_purchase', 'status' => 'received',
        'transaction_date' => '2026-10-01 10:00:00', 'created_by' => $userId, 'essentials_duration' => 0,
        'ref_no' => 'OP-DET-'.random_int(1000, 9999), 'final_total' => 15,
        'mfg_production_cost' => $custo, 'mfg_production_cost_type' => $tipoCusto,
        'mfg_wasted_units' => 1, 'mfg_is_final' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $ordem, 'product_id' => $produto, 'variation_id' => $varProduto,
        'quantity' => 5, 'purchase_price' => 3, 'purchase_price_inc_tax' => 3, 'item_tax' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $venda = DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'type' => 'production_sell', 'status' => 'final',
        'transaction_date' => '2026-10-01 10:00:00', 'created_by' => $userId, 'essentials_duration' => 0,
        'mfg_parent_production_purchase_id' => $ordem, 'final_total' => 15,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ([[$prodA, $varA, 3, 3.0, null], [$prodB, $varB, 2, 2.5, $grupo]] as [$p, $v, $q, $preco, $g]) {
        DB::table('transaction_sell_lines')->insert([
            'transaction_id' => $venda, 'product_id' => $p, 'variation_id' => $v, 'quantity' => $q,
            'unit_price' => $preco, 'unit_price_inc_tax' => $preco, 'item_tax' => 0,
            'mfg_ingredient_group_id' => $g, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $ordem;
}

/** Caminho (b): as variáveis que o `show()` legado passa à view, sem renderizá-la. */
function mfgDetLegado($test, $user, int $ordem): array
{
    $test->actingAs($user);
    // Fora de uma requisição HTTP o request do container não tem sessão: o show() lê
    // `request()->session()`, então liga a sessão do app nele antes.
    request()->setLaravelSession(app('session.store'));
    session(['user.business_id' => MFG_EMP_BIZ]);

    return app(ProductionController::class)->show($ordem)->getData();
}

describe('Detalhe da ordem de produção — mesmas contas da tela antiga', function () {
    it('custo percentual: ingredientes pelo preço de hoje e custo extra sobre eles', function () {
        $user = mfgDetUsuario();
        $ordem = mfgDetOrdem(MFG_EMP_BIZ, $user->id, 'percentage', 10);

        $d = (new ProductionService())->detalheOrdem(MFG_EMP_BIZ, $ordem);

        // (a) à mão: 12 + 5 = 17; 10% de 17 = 1,7; total 18,7; por unidade 18,7 / 5 = 3,74.
        expect($d['custo']['ingredientes'])->toEqualWithDelta(17.0, 0.0001);
        expect($d['custo']['extra'])->toEqualWithDelta(1.7, 0.0001);
        expect($d['custo']['total_hoje'])->toEqualWithDelta(18.7, 0.0001);
        expect($d['custo']['por_unidade'])->toEqualWithDelta(3.74, 0.0001);
        expect($d['custo']['gravado'])->toEqualWithDelta(15.0, 0.0001);
        expect($d['quantidade'])->toEqualWithDelta(5.0, 0.0001);
        expect($d['finalizada'])->toBeTrue();

        $linhas = collect($d['linhas'])->keyBy('nome');
        expect($linhas->keys()->all())->toContain('Insumo A', 'Insumo B');
        expect($linhas['Insumo A']['subtotal'])->toEqualWithDelta(12.0, 0.0001);
        expect($linhas['Insumo A']['custo_unitario'])->toEqualWithDelta(4.0, 0.0001);
        expect($linhas['Insumo A']['grupo'])->toBeNull();
        expect($linhas['Insumo B']['grupo'])->toBe('Base');

        // (b) a tela antiga chega aos mesmos números.
        $legado = mfgDetLegado($this, $user, $ordem);
        $ingLegado = collect($legado['ingredients'])->sum('total_price')
            + collect($legado['ingredient_groups'])->flatMap(fn ($g) => $g['ig_ingredients'])->sum('total_price');
        expect($ingLegado)->toEqualWithDelta($d['custo']['ingredientes'], 0.0001);
        expect((float) $legado['total_production_cost'])->toEqualWithDelta($d['custo']['extra'], 0.0001);
    });

    it('custo por unidade: custo × (produzidas + perdidas), como a tela antiga', function () {
        $user = mfgDetUsuario();
        $ordem = mfgDetOrdem(MFG_EMP_BIZ, $user->id, 'per_unit', 2);

        $d = (new ProductionService())->detalheOrdem(MFG_EMP_BIZ, $ordem);

        // (a) à mão: 2 × (5 + 1) = 12; total 17 + 12 = 29.
        expect($d['custo']['extra'])->toEqualWithDelta(12.0, 0.0001);
        expect($d['custo']['total_hoje'])->toEqualWithDelta(29.0, 0.0001);

        // (b)
        expect((float) mfgDetLegado($this, $user, $ordem)['total_production_cost'])->toEqualWithDelta(12.0, 0.0001);
    });

    it('custo fixo: o valor gravado na ordem, sem conta', function () {
        $user = mfgDetUsuario();
        $ordem = mfgDetOrdem(MFG_EMP_BIZ, $user->id, 'fixed', 10);

        $d = (new ProductionService())->detalheOrdem(MFG_EMP_BIZ, $ordem);

        expect($d['custo']['extra'])->toEqualWithDelta(10.0, 0.0001);
        expect((float) mfgDetLegado($this, $user, $ordem)['total_production_cost'])->toEqualWithDelta(10.0, 0.0001);
    });

    it('ordem de outra empresa não tem detalhe (Tier 0)', function () {
        $user = mfgDetUsuario();
        $alheia = mfgDetOrdem(MFG_EMP_OUTRO, $user->id, 'percentage', 10);
        $propria = mfgDetOrdem(MFG_EMP_BIZ, $user->id, 'percentage', 10);

        $service = new ProductionService();
        expect($service->detalheOrdem(MFG_EMP_BIZ, $propria))->not->toBeNull();
        expect($service->detalheOrdem(MFG_EMP_BIZ, $alheia))->toBeNull();
    });

    it('a tela só calcula o detalhe quando o pede, pela ordem da própria empresa', function () {
        $user = mfgDetUsuario();
        $propria = mfgDetOrdem(MFG_EMP_BIZ, $user->id, 'percentage', 10);
        $alheia = mfgDetOrdem(MFG_EMP_OUTRO, $user->id, 'percentage', 10);
        $versao = (string) app(HandleInertiaRequests::class)->version(request());
        $parcial = fn (int $id) => $this->actingAs($user)->get('/manufacturing/production?ordem='.$id, [
            'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => $versao,
            'X-Inertia-Partial-Data' => 'ordem_detalhe', 'X-Inertia-Partial-Component' => 'Manufacturing/Index',
        ]);

        $r = $parcial($propria)->assertOk();
        expect((int) $r->json('props.ordem_detalhe.id'))->toBe($propria);
        expect((float) $r->json('props.ordem_detalhe.custo.ingredientes'))->toEqualWithDelta(17.0, 0.0001);

        expect($parcial($alheia)->assertOk()->json('props.ordem_detalhe'))->toBeNull();

        // Carga normal da lista não traz o detalhe (Inertia::optional).
        $this->actingAs($user)->get('/manufacturing/production?ordem='.$propria)
            ->assertInertia(fn (AssertableInertia $pg) => $pg->missing('ordem_detalhe'));
    });
});
