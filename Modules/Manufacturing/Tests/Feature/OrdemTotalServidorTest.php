<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Services\ProductionService;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * O total da ordem de produção (`final_total`) é calculado no servidor; o valor que o navegador manda não
 * entra.
 *
 * Contrato (não o código): SDD Fabricação §4 ("Dinheiro recalculado no servidor — o cliente nunca é fonte
 * de `final_total`") · handoff §9 ("Todo cálculo de custo recalculado no servidor antes de gravar. Se o
 * cliente mandar `final_total`, um POST forjado grava custo arbitrário numa transaction") · handoff §6
 * regra 4 (o custo congelado e o custo da mesma receita hoje são "dois números do mesmo fato" e precisam
 * fechar) · ADR 0448 (o `final_total` do cliente é defeito herdado da Blade).
 *
 * A conta é a da tela antiga (formulário de ordem e `show()`): ingredientes = Σ consumo × custo do insumo ×
 * multiplicador da sub-unidade; custo extra = % sobre os ingredientes, ou valor × quantidade produzida
 * (com as perdidas), ou valor fixo.
 *
 * Dois caminhos em cada caso: a conta à mão e o "total de hoje" do painel (`detalheOrdem`), que no dia da
 * gravação tem de bater com o gravado.
 *
 * Tenant 98 (fictício, ADR 0358). NUNCA biz=4. Tudo roda dentro de transação desfeita no fim.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Http/Controllers/ProductionController::store()
 * @see Modules/Manufacturing/Http/Controllers/ProductionController::update()
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a ordem depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'units', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients', 'transactions', 'business_locations', 'invoice_schemes', 'invoice_layouts', 'purchase_lines', 'transaction_sell_lines'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

function mfgTotUsuario(): \App\User
{
    $user = mfgEmpUsuario('mfg_ordem_total_servidor_test');
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'manufacturing.access_production', 'guard_name' => 'web']));

    return $user;
}

function mfgTotLocal(): int
{
    mfgEmpNegocio(MFG_EMP_BIZ);
    $esquema = DB::table('invoice_schemes')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $layout = DB::table('invoice_layouts')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'name' => 'Local total '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Tubarão', 'zip_code' => '88700000', 'invoice_scheme_id' => $esquema, 'invoice_layout_id' => $layout, 'is_active' => 1,
    ]);
}

/** O `final_total` vai FORJADO em todo payload: 99999. Nenhum caso pode gravá-lo. */
function mfgTotPayload(int $variationId, int $localId, array $ingredientes, array $extra = []): array
{
    $empresa = DB::table('business')->where('id', MFG_EMP_BIZ)->first(['date_format', 'time_format']);
    $formato = $empresa->date_format.((string) $empresa->time_format === '12' ? ' h:i A' : ' H:i');

    return array_merge([
        'transaction_date' => now()->format($formato),
        'location_id' => $localId,
        'final_total' => '99999',
        'variation_id' => $variationId,
        'quantity' => '3',
        'ingredients' => $ingredientes,
    ], $extra);
}

function mfgTotOrdem(): int
{
    return (int) DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->orderByDesc('id')->value('id');
}

function mfgTotGravado(int $ordem): float
{
    return (float) DB::table('transactions')->where('id', $ordem)->value('final_total');
}

function mfgTotHoje(int $ordem): float
{
    return (float) (new ProductionService())->detalheOrdem(MFG_EMP_BIZ, $ordem)['custo']['total_hoje'];
}

describe('Ordem de produção — total calculado no servidor (UC-OP-19)', function () {
    it('UC-OP-19 — sem custo extra: grava 6 × 7,5 = 45, não o total forjado', function () {
        $user = mfgTotUsuario();
        [, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo total sem extra');

        $this->actingAs($user)->post('/manufacturing/production', mfgTotPayload($acabado, mfgTotLocal(), [$linha => ['quantity' => '6']]))
            ->assertRedirect('/manufacturing/production');

        $ordem = mfgTotOrdem();
        expect(mfgTotGravado($ordem))->toBe(45.0);
        expect(mfgTotGravado($ordem))->toEqualWithDelta(mfgTotHoje($ordem), 0.0001);
        // O custo unitário do acabado sai do total do servidor: 45 / 3.
        expect((float) DB::table('purchase_lines')->where('transaction_id', $ordem)->value('purchase_price_inc_tax'))->toBe(15.0);
    });

    it('UC-OP-19 — custo extra em %: 45 + 10% = 49,5', function () {
        $user = mfgTotUsuario();
        [, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo total percentual');

        $this->actingAs($user)->post('/manufacturing/production', mfgTotPayload($acabado, mfgTotLocal(), [$linha => ['quantity' => '6']], ['production_cost' => '10', 'mfg_production_cost_type' => 'percentage']))
            ->assertRedirect('/manufacturing/production');

        $ordem = mfgTotOrdem();
        expect(mfgTotGravado($ordem))->toBe(49.5);
        expect(mfgTotGravado($ordem))->toEqualWithDelta(mfgTotHoje($ordem), 0.0001);
    });

    it('UC-OP-19 — custo extra por unidade conta as perdidas: 45 + 2 × (3 + 1) = 53', function () {
        $user = mfgTotUsuario();
        [, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo total por unidade');

        $this->actingAs($user)->post('/manufacturing/production', mfgTotPayload($acabado, mfgTotLocal(), [$linha => ['quantity' => '6']], ['quantity' => '4', 'mfg_wasted_units' => '1', 'production_cost' => '2', 'mfg_production_cost_type' => 'per_unit']))
            ->assertRedirect('/manufacturing/production');

        $ordem = mfgTotOrdem();
        expect(mfgTotGravado($ordem))->toBe(53.0);
        expect(mfgTotGravado($ordem))->toEqualWithDelta(mfgTotHoje($ordem), 0.0001);
    });

    it('UC-OP-19 — custo extra fixo: 45 + 4 = 49', function () {
        $user = mfgTotUsuario();
        [, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo total fixo');

        $this->actingAs($user)->post('/manufacturing/production', mfgTotPayload($acabado, mfgTotLocal(), [$linha => ['quantity' => '6']], ['production_cost' => '4', 'mfg_production_cost_type' => 'fixed']))
            ->assertRedirect('/manufacturing/production');

        $ordem = mfgTotOrdem();
        expect(mfgTotGravado($ordem))->toBe(49.0);
        expect(mfgTotGravado($ordem))->toEqualWithDelta(mfgTotHoje($ordem), 0.0001);
    });

    it('UC-OP-19 — insumo em sub-unidade de 5: 2 × 5 × 7,5 = 75', function () {
        $user = mfgTotUsuario();
        [, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo total em embalagem');
        $insumo = (int) DB::table('mfg_recipe_ingredients')->where('id', $linha)->value('variation_id');
        $base = (int) DB::table('products')->where('id', DB::table('variations')->where('id', $insumo)->value('product_id'))->value('unit_id');
        $caixa = DB::table('units')->insertGetId([
            'business_id' => MFG_EMP_BIZ, 'actual_name' => 'Caixa', 'short_name' => 'CX', 'allow_decimal' => 1,
            'base_unit_id' => $base, 'base_unit_multiplier' => 5, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->post('/manufacturing/production', mfgTotPayload($acabado, mfgTotLocal(), [$linha => ['quantity' => '2', 'sub_unit_id' => $caixa]]))
            ->assertRedirect('/manufacturing/production');

        $ordem = mfgTotOrdem();
        expect(mfgTotGravado($ordem))->toBe(75.0);
        expect(mfgTotGravado($ordem))->toEqualWithDelta(mfgTotHoje($ordem), 0.0001);
    });

    it('UC-OP-19 — PUT do rascunho recalcula com o consumo novo e ignora o total forjado: 4 × 7,5 = 30', function () {
        $user = mfgTotUsuario();
        [, $acabado, $linha] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo total put');
        $local = mfgTotLocal();
        $this->actingAs($user)->post('/manufacturing/production', mfgTotPayload($acabado, $local, [$linha => ['quantity' => '6']]))
            ->assertRedirect('/manufacturing/production');
        $ordem = mfgTotOrdem();
        $venda = DB::table('transactions')->where('mfg_parent_production_purchase_id', $ordem)->value('id');
        $linhaVenda = DB::table('transaction_sell_lines')->where('transaction_id', $venda)->value('id');

        $this->actingAs($user)->put('/manufacturing/production/'.$ordem, mfgTotPayload($acabado, $local, [$linhaVenda => ['quantity' => '4']]))
            ->assertRedirect('/manufacturing/production');

        expect(mfgTotGravado($ordem))->toBe(30.0);
        expect(mfgTotGravado($ordem))->toEqualWithDelta(mfgTotHoje($ordem), 0.0001);
        // A venda de produção filha carrega o mesmo total.
        expect((float) DB::table('transactions')->where('id', $venda)->value('final_total'))->toBe(30.0);
    });
});
