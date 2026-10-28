<?php

declare(strict_types=1);

use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Compras\Services\CustoMedioMovel;
use Tests\Support\WithSeededTenant;

/**
 * US-PROD-024 fase 2 — custo médio ponderado móvel recalculado a cada compra recebida.
 *
 * Âncora: memory/requisitos/Produto/SPEC.md US-PROD-024 + Modules/Compras/Services/CustoMedioMovel.php
 * (docblock com a fórmula). Regra mestre de valor (proibicoes.md): cada caso confere o número por
 * um segundo caminho em PHP (custoMedioMovelEsperado, sem SQL) e por um valor fixo calculado à mão.
 *
 * Arranjo — variação V do tenant 98, um local da empresa:
 *   L0  saldo inicial  10 un × 10,00  (lote, restante 10)
 *   C1  compra recebida 10 un × 20,00  ← a que entra
 * Estoque depois de receber C1: 20.
 *   móvel  = (10,00 × 10 + 20,00 × 10) / 20 = 300 / 20 = 15,0000
 * Se o saldo inicial já tinha sido todo vendido (lote restante 0, estoque depois de C1 = 10):
 *   móvel  = 20,0000 (nada antes) · a média de todas as compras daria 15,00 — é a diferença do método.
 *
 * @covers-us US-PROD-024
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage().' — rode no MySQL real (lane compras-pest).');
    }

    $this->user = User::factory()->create(['business_id' => $this->biz->id, 'username' => 'custo_medio_'.uniqid()]);
    $agora = now();
    $sku = 'CMEDIO-'.uniqid();

    $unitId = (int) (DB::table('units')->where('business_id', $this->biz->id)->value('id')
        ?? DB::table('units')->insertGetId([
            'business_id' => $this->biz->id, 'actual_name' => 'Unidade '.$sku, 'short_name' => 'un',
            'allow_decimal' => 0, 'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
        ]));
    $this->productId = DB::table('products')->insertGetId([
        'business_id' => $this->biz->id, 'name' => 'Lona '.$sku, 'type' => 'single', 'unit_id' => $unitId,
        'sku' => $sku, 'enable_stock' => 1, 'alert_quantity' => 0, 'tax_type' => 'exclusive',
        'barcode_type' => 'C128', 'created_by' => $this->user->id, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $grupo = DB::table('product_variations')->insertGetId([
        'product_id' => $this->productId, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $this->variationId = DB::table('variations')->insertGetId([
        'product_id' => $this->productId, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku.'-1',
        'default_purchase_price' => 20, 'dpp_inc_tax' => 20, 'profit_percent' => 0, 'default_sell_price' => 30, 'sell_price_inc_tax' => 30,
        'created_at' => $agora, 'updated_at' => $agora,
    ]);

    $local = function (int $biz, string $nome) use ($agora): int {
        $scheme = DB::table('invoice_schemes')->value('id') ?? DB::table('invoice_schemes')->insertGetId([
            'business_id' => $biz, 'name' => 'CI '.$nome, 'scheme_type' => 'blank', 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $layout = DB::table('invoice_layouts')->value('id') ?? DB::table('invoice_layouts')->insertGetId([
            'business_id' => $biz, 'name' => 'CI '.$nome, 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        return DB::table('business_locations')->insertGetId([
            'business_id' => $biz, 'name' => $nome, 'location_id' => 'CM'.substr(uniqid(), -6),
            'country' => 'BR', 'state' => 'SC', 'city' => 'Teste', 'zip_code' => '0000000',
            'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout, 'is_active' => 1,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
    };
    $this->localId = $local($this->biz->id, 'Depósito custo médio');
    $this->localOutra = $local($this->outra->id, 'Depósito outra empresa');

    $this->estoque = function (float $qtd, ?int $localId = null) use ($grupo, $agora): void {
        DB::table('variation_location_details')->insert([
            'product_id' => $this->productId, 'product_variation_id' => $grupo, 'variation_id' => $this->variationId,
            'location_id' => $localId ?? $this->localId, 'qty_available' => $qtd, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
    };

    $this->lote = function (int $biz, string $tipo, string $status, float $qtd, float $preco, float $vendido = 0): int {
        $tid = DB::table('transactions')->insertGetId([
            'business_id' => $biz, 'type' => $tipo, 'status' => $status, 'payment_status' => 'due',
            'transaction_date' => '2026-03-01 09:00:00', 'ref_no' => 'CMEDIO-'.uniqid(), 'final_total' => $qtd * $preco,
            'total_before_tax' => $qtd * $preco, 'tax_amount' => 0, 'discount_amount' => 0,
            'created_by' => $this->user->id, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('purchase_lines')->insert([
            'transaction_id' => $tid, 'product_id' => $this->productId, 'variation_id' => $this->variationId,
            'quantity' => $qtd, 'quantity_sold' => $vendido, 'quantity_adjusted' => 0, 'quantity_returned' => 0, 'mfg_quantity_used' => 0,
            'pp_without_discount' => $preco, 'purchase_price' => $preco, 'purchase_price_inc_tax' => $preco, 'item_tax' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $tid;
    };

    $this->servico = app(CustoMedioMovel::class);
    $this->guardado = fn () => DB::table('compras_custo_medio')
        ->where('business_id', $this->biz->id)->where('variation_id', $this->variationId)->value('custo_medio');
});

/** Segundo caminho: a fórmula do médio móvel em PHP, sem SQL. */
function custoMedioMovelEsperado(float $qtdAntes, ?float $custoAntes, float $qtdEntrada, float $custoEntrada): string
{
    $depois = ($qtdAntes > 0 && $custoAntes !== null)
        ? ($custoAntes * $qtdAntes + $custoEntrada * $qtdEntrada) / ($qtdAntes + $qtdEntrada)
        : $custoEntrada;

    return number_format(round($depois, 4), 4, '.', '');
}

it('UC-CMED-01 · compra recebida pondera o estoque que havia: 10 a 10,00 + 10 a 20,00 = 15,0000', function () {
    ($this->lote)($this->biz->id, 'opening_stock', 'received', 10, 10.00);
    $c1 = ($this->lote)($this->biz->id, 'purchase', 'received', 10, 20.00);
    ($this->estoque)(20);

    expect(custoMedioMovelEsperado(10, 10.00, 10, 20.00))->toBe('15.0000');

    $feito = $this->servico->registrarEntrada($c1);

    expect(($this->guardado)())->toBe('15.0000')
        ->and($feito)->toHaveCount(1)
        ->and($feito[0]['qtd_antes'])->toBe(10.0)
        ->and($feito[0]['custo_antes'])->toBe(10.0)
        ->and($feito[0]['custo_depois'])->toBe(15.0);
});

it('UC-CMED-02 · o que já saiu do estoque não pesa: saldo vendido + compra a 20,00 = 20,0000', function () {
    ($this->lote)($this->biz->id, 'opening_stock', 'received', 10, 10.00, 10);
    $c1 = ($this->lote)($this->biz->id, 'purchase', 'received', 10, 20.00);
    ($this->estoque)(10);

    expect(custoMedioMovelEsperado(0, null, 10, 20.00))->toBe('20.0000');

    $this->servico->registrarEntrada($c1);

    expect(($this->guardado)())->toBe('20.0000');
});

it('UC-CMED-03 · segunda compra parte do médio guardado e a mesma compra não entra duas vezes', function () {
    ($this->lote)($this->biz->id, 'opening_stock', 'received', 10, 10.00);
    $c1 = ($this->lote)($this->biz->id, 'purchase', 'received', 10, 20.00);
    ($this->estoque)(20);
    $this->servico->registrarEntrada($c1);
    $this->servico->registrarEntrada($c1);

    // 20 em estoque a 15,0000 + 20 a 30,00 → (300 + 600) / 40 = 22,5000
    $c2 = ($this->lote)($this->biz->id, 'purchase', 'received', 20, 30.00);
    DB::table('variation_location_details')->where('variation_id', $this->variationId)->update(['qty_available' => 40]);
    expect(custoMedioMovelEsperado(20, 15.00, 20, 30.00))->toBe('22.5000');

    $this->servico->registrarEntrada($c2);

    expect(($this->guardado)())->toBe('22.5000')
        ->and(DB::table('compras_custo_medio_movimentos')->where('variation_id', $this->variationId)->count())->toBe(2);
});

it('UC-CMED-04 · compra não recebida não mexe no custo médio', function () {
    $c1 = ($this->lote)($this->biz->id, 'purchase', 'pending', 10, 20.00);
    ($this->estoque)(0);

    expect($this->servico->registrarEntrada($c1))->toBe([])
        ->and(($this->guardado)())->toBeNull();
});

it('UC-CMED-05 · Tier 0: estoque de local de outra empresa não entra e compra de outra empresa não usa a variação', function () {
    ($this->lote)($this->biz->id, 'opening_stock', 'received', 10, 10.00);
    $c1 = ($this->lote)($this->biz->id, 'purchase', 'received', 10, 20.00);
    ($this->estoque)(20);
    ($this->estoque)(500, $this->localOutra);

    $this->servico->registrarEntrada($c1);
    expect(($this->guardado)())->toBe('15.0000');

    $alheia = ($this->lote)($this->outra->id, 'purchase', 'received', 10, 99.00);
    expect($this->servico->registrarEntrada($alheia))->toBe([])
        ->and(DB::table('compras_custo_medio')->where('business_id', $this->outra->id)->count())->toBe(0);
});

it('UC-CMED-06 · o custo médio de um momento é o último recálculo até ele', function () {
    ($this->lote)($this->biz->id, 'opening_stock', 'received', 10, 10.00);
    $c1 = ($this->lote)($this->biz->id, 'purchase', 'received', 10, 20.00);
    ($this->estoque)(20);
    $this->servico->registrarEntrada($c1);
    DB::table('compras_custo_medio_movimentos')->where('variation_id', $this->variationId)
        ->update(['registrado_em' => '2026-03-10 12:00:00']);

    expect($this->servico->noMomento($this->biz->id, $this->variationId, '2026-03-10 11:59:59'))->toBeNull()
        ->and($this->servico->noMomento($this->biz->id, $this->variationId, '2026-03-11 00:00:00'))->toBe(15.0)
        ->and($this->servico->noMomento($this->outra->id, $this->variationId, '2026-03-11 00:00:00'))->toBeNull();
});

it('UC-CMED-07 · flag desligada: receber compra não grava nada; ligada: grava depois do commit', function () {
    ($this->lote)($this->biz->id, 'opening_stock', 'received', 10, 10.00);
    ($this->estoque)(20);

    config(['compras.custo_medio_movel' => false]);
    DB::transaction(function () {
        $id = ($this->lote)($this->biz->id, 'purchase', 'pending', 10, 20.00);
        Transaction::withoutGlobalScopes()->findOrFail($id)->update(['status' => 'received']); // SUPERADMIN: fixture lida por id
    });
    expect(($this->guardado)())->toBeNull();

    config(['compras.custo_medio_movel' => true]);
    DB::transaction(function () {
        $id = ($this->lote)($this->biz->id, 'purchase', 'pending', 10, 20.00);
        Transaction::withoutGlobalScopes()->findOrFail($id)->update(['status' => 'received']); // SUPERADMIN: fixture lida por id
    });
    expect(($this->guardado)())->not->toBeNull();
});

it('UC-CMED-08 · semente: sem --aplicar só mostra; com --aplicar grava o custo do estoque restante', function () {
    ($this->lote)($this->biz->id, 'opening_stock', 'received', 10, 10.00, 4);   // restam 6 a 10,00
    ($this->lote)($this->biz->id, 'purchase', 'received', 4, 25.00);            // restam 4 a 25,00
    ($this->estoque)(10);
    // (6 × 10 + 4 × 25) / 10 = 160 / 10 = 16,0000
    expect(round((6 * 10 + 4 * 25) / 10, 4))->toBe(16.0);

    $this->artisan('compras:custo-medio-semear', ['business' => $this->biz->id])->assertSuccessful();
    expect(($this->guardado)())->toBeNull();

    $this->artisan('compras:custo-medio-semear', ['business' => $this->biz->id, '--aplicar' => true])->assertSuccessful();
    expect(($this->guardado)())->toBe('16.0000')
        ->and(DB::table('variations')->where('id', $this->variationId)->value('dpp_inc_tax'))->toBe('20.0000');
});
