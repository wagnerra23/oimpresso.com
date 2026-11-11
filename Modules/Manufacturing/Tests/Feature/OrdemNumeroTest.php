<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * A ordem de produção grava as quantidades e o custo que o operador informou, sem reconverter número.
 *
 * Contrato (não o código): handoff Fabricação §7 ponto 6 ("Nada é arredondado no meio da conta.
 * Arredondamento é de apresentação") · §9 ("Todo cálculo de custo recalculado no servidor antes de
 * gravar") · regra mestre de valor e estoque (`proibicoes.md`, incidente de 2026-06-05: separador lido
 * duas vezes multiplica o número por mil).
 *
 * Cada caso confere o número por dois caminhos: o valor gravado na linha contra a conta à mão, e o
 * efeito no estoque ou no total (quantidade × custo unitário = total da ordem).
 *
 * Tenant 98 (fictício, ADR 0358). NUNCA biz=4. Tudo roda dentro de transação desfeita no fim.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Manufacturing/Http/Controllers/ProductionController::store()
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a ordem depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'units', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients', 'transactions', 'business_locations', 'invoice_schemes', 'invoice_layouts', 'variation_location_details', 'purchase_lines', 'transaction_sell_lines'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

function mfgNumUsuario(): \App\User
{
    $user = mfgEmpUsuario('mfg_ordem_numero_test');
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'manufacturing.access_production', 'guard_name' => 'web']));

    return $user;
}

function mfgNumLocal(): int
{
    mfgEmpNegocio(MFG_EMP_BIZ);
    $esquema = DB::table('invoice_schemes')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $layout = DB::table('invoice_layouts')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'name' => 'Local num '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Tubarão', 'zip_code' => '88700000', 'invoice_scheme_id' => $esquema, 'invoice_layout_id' => $layout, 'is_active' => 1,
    ]);
}

function mfgNumPayload(int $variationId, int $localId, array $ingredientes, array $extra = []): array
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

/** Estoque de abertura do insumo: saldo no local e a compra recebida de onde a ordem consome. Devolve a linha de compra. */
function mfgNumAbertura(int $variationId, int $localId, float $quantidade, int $userId): int
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

    return DB::table('purchase_lines')->insertGetId([
        'transaction_id' => $abertura, 'product_id' => $v->product_id, 'variation_id' => $variationId,
        'quantity' => $quantidade, 'pp_without_discount' => 7.5, 'purchase_price' => 7.5, 'purchase_price_inc_tax' => 7.5,
        'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function mfgNumInsumo(int $linhaReceita): int
{
    return (int) DB::table('mfg_recipe_ingredients')->where('id', $linhaReceita)->value('variation_id');
}

function mfgNumOrdem(): int
{
    return (int) DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->orderByDesc('id')->value('id');
}

function mfgNumLinhaVenda(int $ordem): object
{
    $venda = DB::table('transactions')->where('mfg_parent_production_purchase_id', $ordem)->value('id');

    return DB::table('transaction_sell_lines')->where('transaction_id', $venda)->first(['quantity', 'unit_price', 'unit_price_inc_tax']);
}

function mfgNumLinhaCompra(int $ordem): object
{
    return DB::table('purchase_lines')->where('transaction_id', $ordem)->first(['quantity', 'purchase_price', 'purchase_price_inc_tax']);
}

describe('Ordem de produção — quantidade e custo gravados como informados (UC-OP-17)', function () {
    it('UC-OP-17 — consumo de 0,125 grava 0,125 na linha e baixa 0,125 do insumo', function () {
        $user = mfgNumUsuario();
        [, $acabado, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo fracionado');
        $local = mfgNumLocal();
        $insumo = mfgNumInsumo($linhaReceita);
        $compra = mfgNumAbertura($insumo, $local, 20, $user->id);

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgNumPayload($acabado, $local, [$linhaReceita => ['quantity' => '0,125']], ['finalize' => 1]));

        $resposta->assertRedirect('/manufacturing/production');
        expect(session('status')['success'])->toBe(1);
        // Caminho 1: o que a linha gravou. Caminho 2: o que saiu do saldo e da compra.
        expect((float) mfgNumLinhaVenda(mfgNumOrdem())->quantity)->toBe(0.125);
        expect((float) DB::table('variation_location_details')->where('variation_id', $insumo)->where('location_id', $local)->value('qty_available'))->toBe(19.875);
        expect((float) DB::table('purchase_lines')->where('id', $compra)->value('mfg_quantity_used'))->toBe(0.125);
    });

    it('UC-OP-17 — custo de insumo com 3 casas (12,345) grava 12,345 no preço da linha', function () {
        $user = mfgNumUsuario();
        [, $acabado, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo custo tres casas');
        $insumo = mfgNumInsumo($linhaReceita);
        DB::table('variations')->where('id', $insumo)->update(['default_purchase_price' => 12.345, 'dpp_inc_tax' => 12.345]);
        $local = mfgNumLocal();

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgNumPayload($acabado, $local, [$linhaReceita => ['quantity' => '2']]));

        $resposta->assertRedirect('/manufacturing/production');
        $linha = mfgNumLinhaVenda(mfgNumOrdem());
        expect((float) $linha->unit_price_inc_tax)->toBe(12.345);
        expect((float) $linha->unit_price)->toBe(12.345);
        // Segundo caminho: o custo da linha é quantidade × custo do insumo.
        expect(round((float) $linha->quantity * (float) $linha->unit_price_inc_tax, 4))->toBe(24.69);
    });

    it('UC-OP-17 — custo unitário do acabado não é arredondado a 2 casas: 100 por 3 grava 33,3333', function () {
        $user = mfgNumUsuario();
        [, $acabado, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo custo dizima');
        $local = mfgNumLocal();

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgNumPayload($acabado, $local, [$linhaReceita => ['quantity' => '2']], ['quantity' => '3', 'final_total' => '100']));

        $resposta->assertRedirect('/manufacturing/production');
        $compra = mfgNumLinhaCompra(mfgNumOrdem());
        expect((float) $compra->purchase_price_inc_tax)->toBe(33.3333);
        // Segundo caminho: quantidade × custo unitário volta ao total (33,33 × 3 daria 99,99).
        expect(round((float) $compra->quantity * (float) $compra->purchase_price_inc_tax, 2))->toBe(100.0);
    });

    it('UC-OP-17 — custo unitário abaixo de um centavo não vira zero nem dobra: 2 por 400 grava 0,005', function () {
        $user = mfgNumUsuario();
        [, $acabado, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo custo pequeno');
        $local = mfgNumLocal();

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgNumPayload($acabado, $local, [$linhaReceita => ['quantity' => '2']], ['quantity' => '400', 'final_total' => '2']));

        $resposta->assertRedirect('/manufacturing/production');
        $compra = mfgNumLinhaCompra(mfgNumOrdem());
        expect((float) $compra->purchase_price_inc_tax)->toBe(0.005);
        expect(round((float) $compra->quantity * (float) $compra->purchase_price_inc_tax, 2))->toBe(2.0);
    });

    it('UC-OP-17 — quantidade líquida com 3 casas (2,5 menos 0,375 perdidas) grava 2,125 no acabado', function () {
        $user = mfgNumUsuario();
        [, $acabado, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo perda fracionada');
        $local = mfgNumLocal();

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgNumPayload($acabado, $local, [$linhaReceita => ['quantity' => '2']], ['quantity' => '2,5', 'mfg_wasted_units' => '0,375', 'final_total' => '17']));

        $resposta->assertRedirect('/manufacturing/production');
        $compra = mfgNumLinhaCompra(mfgNumOrdem());
        expect((float) $compra->quantity)->toBe(2.125);
        // Segundo caminho: o custo unitário divide o total pela líquida, e o produto volta ao total.
        expect((float) $compra->purchase_price_inc_tax)->toBe(8.0);
        expect(round((float) $compra->quantity * (float) $compra->purchase_price_inc_tax, 2))->toBe(17.0);
    });
});
