<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Finalizar a ordem de produção dá entrada no acabado e baixa os insumos; rascunho não mexe no estoque.
 *
 * Contrato (não o código): handoff Fabricação §6 regra 3 ("Rascunho não movimenta estoque. Só Finalizar
 * dá entrada no produto e baixa os ingredientes") · §7 ponto 4 (o multiplicador da sub-unidade multiplica a
 * linha; a baixa é na unidade base) · §9 (finalizar é atômico: ordem + entrada + baixa) · ADR 0448 D4
 * (o desperdício saiu da composição; a perda real vai para a ordem, então a baixa é o consumo declarado
 * na linha, sem reaplicar a % gravada nela) · CU-MFG-18 (o operador ajusta o consumo real da linha).
 *
 * Cada caso confere o saldo antes e depois em `variation_location_details` e, quando finaliza, o consumo
 * registrado na compra do insumo (`purchase_lines.mfg_quantity_used`), que é o segundo caminho do mesmo
 * fato: o saldo diz quanto saiu, a compra diz de onde saiu.
 *
 * Quantidades inteiras e menores que 1000 de propósito: este arquivo prova o fluxo, não a conversão de
 * número (essa é de outro caso).
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
        $this->markTestSkipped('SQLite-incompatível: a baixa de estoque depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'units', 'products', 'product_variations', 'variations', 'mfg_recipes', 'mfg_recipe_ingredients', 'transactions', 'business_locations', 'invoice_schemes', 'invoice_layouts', 'variation_location_details', 'purchase_lines', 'transaction_sell_lines'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

function mfgFinUsuario(): \App\User
{
    $user = mfgEmpUsuario('mfg_ordem_finalizar_estoque_test');
    $user->givePermissionTo(Permission::firstOrCreate(['name' => 'manufacturing.access_production', 'guard_name' => 'web']));

    return $user;
}

function mfgFinLocal(): int
{
    mfgEmpNegocio(MFG_EMP_BIZ);
    $esquema = DB::table('invoice_schemes')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $layout = DB::table('invoice_layouts')->insertGetId(['business_id' => MFG_EMP_BIZ, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'name' => 'Local fin '.uniqid(), 'country' => 'Brasil', 'state' => 'SC',
        'city' => 'Tubarão', 'zip_code' => '88700000', 'invoice_scheme_id' => $esquema, 'invoice_layout_id' => $layout, 'is_active' => 1,
    ]);
}

function mfgFinPayload(int $variationId, int $localId, array $ingredientes, array $extra = []): array
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

function mfgFinControlaEstoque(int $variationId): void
{
    $produto = DB::table('variations')->where('id', $variationId)->value('product_id');
    DB::table('products')->where('id', $produto)->update(['enable_stock' => 1]);
}

/**
 * Estoque de abertura do insumo no local: a linha de saldo e a compra recebida de onde a ordem consome.
 * Devolve o id da linha de compra.
 */
function mfgFinAbertura(int $variationId, int $localId, float $quantidade, int $userId): int
{
    mfgFinControlaEstoque($variationId);
    $v = DB::table('variations')->where('id', $variationId)->first(['product_id', 'product_variation_id']);
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

function mfgFinSaldo(int $variationId, int $localId): float
{
    return (float) DB::table('variation_location_details')->where('variation_id', $variationId)->where('location_id', $localId)->sum('qty_available');
}

function mfgFinConsumido(int $linhaCompra): float
{
    return (float) DB::table('purchase_lines')->where('id', $linhaCompra)->value('mfg_quantity_used');
}

/** Variação do insumo da linha da receita. */
function mfgFinInsumo(int $linhaReceita): int
{
    return (int) DB::table('mfg_recipe_ingredients')->where('id', $linhaReceita)->value('variation_id');
}

/** Unidade derivada (sub-unidade) da unidade do produto da variação, com o multiplicador dado. */
function mfgFinSubUnidade(int $variationId, float $multiplicador, int $userId): int
{
    $base = (int) DB::table('products')->where('id', DB::table('variations')->where('id', $variationId)->value('product_id'))->value('unit_id');

    return DB::table('units')->insertGetId([
        'business_id' => MFG_EMP_BIZ, 'actual_name' => 'Embalagem x'.$multiplicador, 'short_name' => 'EMB',
        'allow_decimal' => 1, 'base_unit_id' => $base, 'base_unit_multiplier' => $multiplicador,
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function mfgFinOrdemMaisRecente(): int
{
    return (int) DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->orderByDesc('id')->value('id');
}

describe('Ordem de produção — finalizar movimenta estoque, rascunho não (UC-EST-08b)', function () {
    it('UC-EST-08b — POST finalizando dá entrada no acabado e baixa o insumo, e a compra do insumo registra o consumo', function () {
        $user = mfgFinUsuario();
        [, $acabado, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo finalizar post');
        mfgFinControlaEstoque($acabado);
        $local = mfgFinLocal();
        $insumo = mfgFinInsumo($linhaReceita);
        $compra = mfgFinAbertura($insumo, $local, 20, $user->id);

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgFinPayload($acabado, $local, [$linhaReceita => ['quantity' => '6']], ['finalize' => 1]));

        $resposta->assertRedirect('/manufacturing/production');
        expect(session('status')['success'])->toBe(1);
        expect(mfgFinSaldo($acabado, $local))->toBe(3.0);
        expect(mfgFinSaldo($insumo, $local))->toBe(14.0);
        expect(mfgFinConsumido($compra))->toBe(6.0);
        expect((int) DB::table('transactions')->where('id', mfgFinOrdemMaisRecente())->value('mfg_is_final'))->toBe(1);
    });

    it('UC-EST-08b — POST como rascunho grava a ordem e não mexe no estoque do acabado nem do insumo', function () {
        $user = mfgFinUsuario();
        [, $acabado, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo rascunho post');
        mfgFinControlaEstoque($acabado);
        $local = mfgFinLocal();
        $insumo = mfgFinInsumo($linhaReceita);
        $compra = mfgFinAbertura($insumo, $local, 20, $user->id);
        $ordensAntes = DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->count();

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgFinPayload($acabado, $local, [$linhaReceita => ['quantity' => '6']]));

        $resposta->assertRedirect('/manufacturing/production');
        expect(DB::table('transactions')->where('business_id', MFG_EMP_BIZ)->where('type', 'production_purchase')->count())->toBe($ordensAntes + 1);
        expect(mfgFinSaldo($acabado, $local))->toBe(0.0);
        expect(mfgFinSaldo($insumo, $local))->toBe(20.0);
        expect(mfgFinConsumido($compra))->toBe(0.0);
    });

    it('UC-EST-08b — PUT finalizando o rascunho dá entrada no acabado e baixa o insumo uma vez', function () {
        $user = mfgFinUsuario();
        [, $acabado, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo finalizar put');
        mfgFinControlaEstoque($acabado);
        $local = mfgFinLocal();
        $insumo = mfgFinInsumo($linhaReceita);
        $compra = mfgFinAbertura($insumo, $local, 20, $user->id);
        $this->actingAs($user)->post('/manufacturing/production', mfgFinPayload($acabado, $local, [$linhaReceita => ['quantity' => '6']]))
            ->assertRedirect('/manufacturing/production');
        $ordem = mfgFinOrdemMaisRecente();
        $venda = DB::table('transactions')->where('mfg_parent_production_purchase_id', $ordem)->value('id');
        $linhaVenda = DB::table('transaction_sell_lines')->where('transaction_id', $venda)->value('id');
        expect(mfgFinSaldo($insumo, $local))->toBe(20.0);

        $resposta = $this->actingAs($user)->put('/manufacturing/production/'.$ordem, mfgFinPayload($acabado, $local, [$linhaVenda => ['quantity' => '6']], ['finalize' => 1]));

        $resposta->assertRedirect('/manufacturing/production');
        expect(session('status')['success'])->toBe(1);
        expect(mfgFinSaldo($acabado, $local))->toBe(3.0);
        expect(mfgFinSaldo($insumo, $local))->toBe(14.0);
        expect(mfgFinConsumido($compra))->toBe(6.0);
        expect((int) DB::table('transactions')->where('id', $ordem)->value('mfg_is_final'))->toBe(1);
    });

    it('UC-EST-08b — unidades perdidas na ordem não entram no estoque do acabado', function () {
        $user = mfgFinUsuario();
        [, $acabado, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo perdidas');
        mfgFinControlaEstoque($acabado);
        $local = mfgFinLocal();
        $insumo = mfgFinInsumo($linhaReceita);
        mfgFinAbertura($insumo, $local, 20, $user->id);

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgFinPayload($acabado, $local, [$linhaReceita => ['quantity' => '10']], ['quantity' => '5', 'mfg_wasted_units' => '1', 'finalize' => 1]));

        $resposta->assertRedirect('/manufacturing/production');
        expect(session('status')['success'])->toBe(1);
        expect(mfgFinSaldo($acabado, $local))->toBe(4.0);
        expect((float) DB::table('purchase_lines')->where('transaction_id', mfgFinOrdemMaisRecente())->value('quantity'))->toBe(4.0);
        expect(mfgFinSaldo($insumo, $local))->toBe(10.0);
    });
});

describe('Ordem de produção — consumo baixado é o declarado na linha, em unidade base (UC-OP-16)', function () {
    it('UC-OP-16 — a % de perda gravada na linha não é reaplicada: baixa o consumo declarado', function () {
        $user = mfgFinUsuario();
        [, $acabado, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo com perda');
        $local = mfgFinLocal();
        $insumo = mfgFinInsumo($linhaReceita);
        $compra = mfgFinAbertura($insumo, $local, 20, $user->id);

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgFinPayload($acabado, $local, [$linhaReceita => ['quantity' => '6', 'mfg_waste_percent' => '10']], ['finalize' => 1]));

        $resposta->assertRedirect('/manufacturing/production');
        expect(session('status')['success'])->toBe(1);
        // 6 declarado; 5,4 seria descontar 10% e 6,6 seria somar 10%.
        expect(mfgFinSaldo($insumo, $local))->toBe(14.0);
        expect(mfgFinConsumido($compra))->toBe(6.0);
        $venda = DB::table('transactions')->where('mfg_parent_production_purchase_id', mfgFinOrdemMaisRecente())->value('id');
        expect((float) DB::table('transaction_sell_lines')->where('transaction_id', $venda)->value('mfg_waste_percent'))->toBe(10.0);
    });

    it('UC-OP-16 — sub-unidade multiplica: insumo em embalagem de 5 baixa na base, acabado em embalagem de 10 entra na base', function () {
        $user = mfgFinUsuario();
        [, $acabado, $linhaReceita] = mfgEmpReceita(MFG_EMP_BIZ, $user->id, 'Insumo em embalagem');
        mfgFinControlaEstoque($acabado);
        $local = mfgFinLocal();
        $insumo = mfgFinInsumo($linhaReceita);
        $compra = mfgFinAbertura($insumo, $local, 20, $user->id);
        $caixa = mfgFinSubUnidade($insumo, 5, $user->id);
        $pacote = mfgFinSubUnidade($acabado, 10, $user->id);

        $resposta = $this->actingAs($user)->post('/manufacturing/production', mfgFinPayload(
            $acabado, $local,
            [$linhaReceita => ['quantity' => '2', 'sub_unit_id' => $caixa]],
            ['quantity' => '1', 'sub_unit_id' => $pacote, 'finalize' => 1]
        ));

        $resposta->assertRedirect('/manufacturing/production');
        expect(session('status')['success'])->toBe(1);
        // 2 embalagens × 5 = 10 na base; 1 pacote × 10 = 10 na base.
        expect(mfgFinSaldo($insumo, $local))->toBe(10.0);
        expect(mfgFinConsumido($compra))->toBe(10.0);
        expect(mfgFinSaldo($acabado, $local))->toBe(10.0);
    });
});
