<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Trocar o local do rascunho leva a ordem inteira para o local novo: o acabado entra e o insumo sai do
 * MESMO local.
 *
 * Contrato (não o código): handoff Fabricação §6 (o formulário tem um campo Local, um só por ordem) e
 * §9 (finalizar é uma transação atômica: ordem + entrada do produto + baixa dos ingredientes; "meio
 * caminho deixa estoque mentindo"). Uma ordem com entrada num local e baixa em outro é exatamente o
 * meio caminho.
 *
 * Os dois caminhos de cada caso: o saldo dos dois locais em `variation_location_details` e o local da
 * venda de produção (`production_sell`) que a ordem gera.
 *
 * Tenant 98 (fictício, ADR 0358). NUNCA biz=4. Tudo roda dentro de transação desfeita no fim.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Http/Controllers/ProductionController::update()
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a baixa de estoque depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'units', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients', 'transactions', 'business_locations', 'invoice_schemes', 'invoice_layouts', 'variation_location_details', 'purchase_lines', 'transaction_sell_lines'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

function mfgLocUsuario(): \App\User
{
    $user = mfgEmpUsuario('mfg_ordem_troca_local_test');
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'manufacturing.access_production', 'guard_name' => 'web']));

    return $user;
}

function mfgLocLocal(): int
{
    mfgEmpNegocio(MFG_EMP_BIZ);
    $esquema = DB::table('invoice_schemes')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $layout = DB::table('invoice_layouts')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'name' => 'Local troca '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Tubarão', 'zip_code' => '88700000', 'invoice_scheme_id' => $esquema, 'invoice_layout_id' => $layout, 'is_active' => 1,
    ]);
}

function mfgLocPayload(int $variationId, int $localId, array $ingredientes, array $extra = []): array
{
    $empresa = DB::table('business')->where('id', MFG_EMP_BIZ)->first(['date_format', 'time_format']);
    $formato = $empresa->date_format.((string) $empresa->time_format === '12' ? ' h:i A' : ' H:i');

    return array_merge([
        'transaction_date' => now()->format($formato),
        'location_id' => $localId,
        'final_total' => '15',
        'variation_id' => $variationId,
        'quantity' => '3',
        'ingredients' => $ingredientes,
    ], $extra);
}

/** Saldo e compra recebida do insumo no local. */
function mfgLocAbertura(int $variationId, int $localId, float $quantidade, int $userId): void
{
    $v = DB::table('variations')->where('id', $variationId)->first(['product_id', 'product_variation_id']);
    DB::table('products')->where('id', $v->product_id)->update(['enable_stock' => 1]);
    DB::table('variation_location_details')->insert([
        'product_id' => $v->product_id, 'product_variation_id' => $v->product_variation_id,
        'variation_id' => $variationId, 'location_id' => $localId, 'qty_available' => $quantidade,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $abertura = DB::table('transactions')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'location_id' => $localId, 'type' => 'opening_stock', 'status' => 'received',
        'payment_status' => 'paid', 'transaction_date' => now()->subDay(), 'final_total' => 0,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $abertura, 'product_id' => $v->product_id, 'variation_id' => $variationId,
        'quantity' => $quantidade, 'pp_without_discount' => 7.5, 'purchase_price' => 7.5, 'purchase_price_inc_tax' => 7.5,
        'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function mfgLocSaldo(int $variationId, int $localId): float
{
    return (float) DB::table('variation_location_details')->where('variation_id', $variationId)->where('location_id', $localId)->sum('qty_available');
}

/**
 * Rascunho gravado pela rota no local A, com o insumo e o acabado controlando estoque e o insumo com
 * saldo nos dois locais. Devolve [ordem, linha de venda, acabado, insumo, local A, local B].
 */
function mfgLocRascunho($teste, \App\User $user): array
{
    [, $acabado, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo troca de local');
    DB::table('products')->where('id', DB::table('variations')->where('id', $acabado)->value('product_id'))->update(['enable_stock' => 1]);
    $insumo = (int) DB::table('mfg_recipe_ingredients')->where('id', $linhaReceita)->value('variation_id');
    $localA = mfgLocLocal();
    $localB = mfgLocLocal();
    mfgLocAbertura($insumo, $localA, 20, $user->id);
    mfgLocAbertura($insumo, $localB, 20, $user->id);

    $teste->actingAs($user)
        ->post('/manufacturing/production', mfgLocPayload($acabado, $localA, [$linhaReceita => ['quantity' => '6']]))
        ->assertRedirect('/manufacturing/production');

    $ordem = (int) DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->orderByDesc('id')->value('id');
    $venda = DB::table('transactions')->where('mfg_parent_production_purchase_id', $ordem)->value('id');
    $linhaVenda = DB::table('transaction_sell_lines')->where('transaction_id', $venda)->value('id');

    return [$ordem, $linhaVenda, $acabado, $insumo, $localA, $localB];
}

describe('Ordem de produção — trocar o local do rascunho (UC-OP-18)', function () {
    it('UC-OP-18 — PUT finalizando no mesmo local: entrada e baixa no local da ordem (âncora positiva)', function () {
        $user = mfgLocUsuario();
        [$ordem, $linhaVenda, $acabado, $insumo, $localA, $localB] = mfgLocRascunho($this, $user);

        $resposta = $this->actingAs($user)->put('/manufacturing/production/'.$ordem, mfgLocPayload($acabado, $localA, [$linhaVenda => ['quantity' => '6']], ['finalize' => 1]));

        $resposta->assertRedirect('/manufacturing/production');
        expect(session('status')['success'])->toBe(1);
        expect(mfgLocSaldo($acabado, $localA))->toBe(3.0);
        expect(mfgLocSaldo($insumo, $localA))->toBe(14.0);
        expect(mfgLocSaldo($insumo, $localB))->toBe(20.0);
    });

    it('UC-OP-18 — PUT finalizando em outro local: acabado entra e insumo sai do local NOVO, o antigo fica intacto', function () {
        $user = mfgLocUsuario();
        [$ordem, $linhaVenda, $acabado, $insumo, $localA, $localB] = mfgLocRascunho($this, $user);

        $resposta = $this->actingAs($user)->put('/manufacturing/production/'.$ordem, mfgLocPayload($acabado, $localB, [$linhaVenda => ['quantity' => '6']], ['finalize' => 1]));

        $resposta->assertRedirect('/manufacturing/production');
        expect(session('status')['success'])->toBe(1);
        // Caminho 1: saldo dos dois locais.
        expect(mfgLocSaldo($acabado, $localB))->toBe(3.0);
        expect(mfgLocSaldo($insumo, $localB))->toBe(14.0);
        expect(mfgLocSaldo($acabado, $localA))->toBe(0.0);
        expect(mfgLocSaldo($insumo, $localA))->toBe(20.0);
        // Caminho 2: a ordem e a venda de produção dela estão no mesmo local.
        expect((int) DB::table('transactions')->where('id', $ordem)->value('location_id'))->toBe($localB);
        expect((int) DB::table('transactions')->where('mfg_parent_production_purchase_id', $ordem)->value('location_id'))->toBe($localB);
    });

    it('UC-OP-18 — PUT trocando o local sem finalizar leva a venda de produção junto e não mexe no estoque', function () {
        $user = mfgLocUsuario();
        [$ordem, $linhaVenda, $acabado, $insumo, $localA, $localB] = mfgLocRascunho($this, $user);

        $resposta = $this->actingAs($user)->put('/manufacturing/production/'.$ordem, mfgLocPayload($acabado, $localB, [$linhaVenda => ['quantity' => '6']]));

        $resposta->assertRedirect('/manufacturing/production');
        expect((int) DB::table('transactions')->where('mfg_parent_production_purchase_id', $ordem)->value('location_id'))->toBe($localB);
        expect(mfgLocSaldo($insumo, $localA))->toBe(20.0);
        expect(mfgLocSaldo($insumo, $localB))->toBe(20.0);
        expect(mfgLocSaldo($acabado, $localB))->toBe(0.0);
    });
});
