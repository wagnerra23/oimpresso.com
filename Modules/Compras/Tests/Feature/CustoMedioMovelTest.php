<?php

declare(strict_types=1);

use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
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

/*
 * D5 da auditoria 2026-11-05 — compra recebida que sai de "recebida" (cancelada ou excluída)
 * estorna o custo médio. Todos passam pelo observer do Transaction, o caminho real.
 *
 * Arranjo comum — semente 10,00 com 10 em estoque; C1 = compra de 10 a 20,00, estoque 20:
 *   entrada C1 = (10,00 × 10 + 20,00 × 10) / 20 = 15,0000. Estorno volta a 10,0000.
 */
function custoMedioSaldo(int $variationId, float $qtd): void
{
    DB::table('variation_location_details')->where('variation_id', $variationId)->update(['qty_available' => $qtd]);
}

/** A compra sai de recebida por Eloquent, para o observer disparar depois do commit. */
function custoMedioMudarCompra(int $id, callable $acao): void
{
    DB::transaction(fn () => $acao(Transaction::withoutGlobalScopes()->findOrFail($id))); // SUPERADMIN: fixture lida por id
}

function custoMedioComSementeEC1(object $t): int
{
    config(['compras.custo_medio_movel' => true]);
    ($t->estoque)(10);
    $t->servico->semear($t->biz->id, $t->variationId, 10.00);
    $c1 = ($t->lote)($t->biz->id, 'purchase', 'received', 10, 20.00);
    custoMedioSaldo($t->variationId, 20);
    $t->servico->registrarEntrada($c1);

    return $c1;
}

it('UC-CMED-09 · compra recebida que volta a pendente estorna: 15,0000 volta a 10,0000', function () {
    $c1 = custoMedioComSementeEC1($this);
    expect(($this->guardado)())->toBe('15.0000');

    custoMedioMudarCompra($c1, fn ($tx) => $tx->update(['status' => 'pending']));

    expect(($this->guardado)())->toBe('10.0000')
        ->and(DB::table('compras_custo_medio_movimentos')->where('transaction_id', $c1)->whereNotNull('estornado_em')->count())->toBe(1);
});

it('UC-CMED-10 · compra recebida excluída estorna: 15,0000 volta a 10,0000', function () {
    $c1 = custoMedioComSementeEC1($this);

    custoMedioMudarCompra($c1, fn ($tx) => $tx->delete());

    expect(($this->guardado)())->toBe('10.0000');
});

it('UC-CMED-11 · sem custo guardado antes da compra, o estorno some com o custo e o histórico fica', function () {
    config(['compras.custo_medio_movel' => true]);
    $c1 = ($this->lote)($this->biz->id, 'purchase', 'received', 10, 20.00);
    ($this->estoque)(10);
    $this->servico->registrarEntrada($c1);
    expect(($this->guardado)())->toBe('20.0000');

    custoMedioMudarCompra($c1, fn ($tx) => $tx->update(['status' => 'ordered']));

    expect(($this->guardado)())->toBeNull()
        ->and($this->servico->noMomento($this->biz->id, $this->variationId, now()->addDay()->toDateTimeString()))->toBeNull()
        ->and(DB::table('compras_custo_medio_movimentos')->where('transaction_id', $c1)->count())->toBe(1);
});

it('UC-CMED-12 · venda entre a compra e o cancelamento não muda o estorno: volta a 10,0000', function () {
    $c1 = custoMedioComSementeEC1($this);
    custoMedioSaldo($this->variationId, 5); // vendeu 15; venda não muda o custo médio

    custoMedioMudarCompra($c1, fn ($tx) => $tx->update(['status' => 'pending']));

    expect(($this->guardado)())->toBe('10.0000');
});

it('UC-CMED-13 · outra entrada depois é recalculada como se a compra cancelada não existisse', function () {
    $c1 = custoMedioComSementeEC1($this);
    $c2 = ($this->lote)($this->biz->id, 'purchase', 'received', 20, 30.00);
    custoMedioSaldo($this->variationId, 40);
    $this->servico->registrarEntrada($c2);
    // Com C1: (15,00 × 20 + 30,00 × 20) / 40 = 900 / 40 = 22,5000
    expect(($this->guardado)())->toBe('22.5000');

    custoMedioMudarCompra($c1, fn ($tx) => $tx->update(['status' => 'pending']));

    // Sem C1: (10,00 × 10 + 30,00 × 20) / 30 = 700 / 30 = 23,3333
    expect(custoMedioMovelEsperado(10, 10.00, 20, 30.00))->toBe('23.3333')
        ->and(($this->guardado)())->toBe('23.3333');
    $c2Mov = DB::table('compras_custo_medio_movimentos')->where('transaction_id', $c2)->first();
    expect($c2Mov->qtd_antes)->toBe('10.0000')
        ->and($c2Mov->custo_antes)->toBe('10.0000')
        ->and($c2Mov->custo_depois)->toBe('23.3333');
});

it('UC-CMED-14 · limite: as unidades canceladas já tinham saído antes da entrada seguinte, ela fica só com o próprio custo', function () {
    $c1 = custoMedioComSementeEC1($this);
    custoMedioSaldo($this->variationId, 5); // vendeu 15 (mais do que as 10 de antes de C1)
    $c2 = ($this->lote)($this->biz->id, 'purchase', 'received', 10, 30.00);
    custoMedioSaldo($this->variationId, 15);
    $this->servico->registrarEntrada($c2);
    // Com C1: (15,00 × 5 + 30,00 × 10) / 15 = 375 / 15 = 25,0000
    expect(($this->guardado)())->toBe('25.0000');

    custoMedioMudarCompra($c1, fn ($tx) => $tx->update(['status' => 'pending']));

    // Sem C1 o estoque antes de C2 seria 5 − 10 < 0: não há o que ponderar, vale o custo de C2.
    expect(custoMedioMovelEsperado(0, 10.00, 10, 30.00))->toBe('30.0000')
        ->and(($this->guardado)())->toBe('30.0000');
});

it('UC-CMED-15 · a mesma compra pode ser recebida de novo depois de cancelada', function () {
    $c1 = custoMedioComSementeEC1($this);
    custoMedioMudarCompra($c1, fn ($tx) => $tx->update(['status' => 'pending']));
    expect(($this->guardado)())->toBe('10.0000');

    custoMedioMudarCompra($c1, fn ($tx) => $tx->update(['status' => 'received']));

    expect(($this->guardado)())->toBe('15.0000')
        ->and(DB::table('compras_custo_medio_movimentos')->where('transaction_id', $c1)->count())->toBe(2);
});

it('UC-CMED-16 · com a flag desligada o cancelamento não estorna (a reconciliação pega depois)', function () {
    $c1 = custoMedioComSementeEC1($this);
    config(['compras.custo_medio_movel' => false]);

    custoMedioMudarCompra($c1, fn ($tx) => $tx->update(['status' => 'pending']));

    expect(($this->guardado)())->toBe('15.0000');
});

it('UC-CMED-17 · Tier 0: o estorno só alcança a empresa da compra e não repete', function () {
    $c1 = custoMedioComSementeEC1($this);
    expect($this->servico->estornarEntrada($this->outra->id, $c1))->toBe([])
        ->and(($this->guardado)())->toBe('15.0000');

    expect($this->servico->estornarEntrada($this->biz->id, $c1))->toHaveCount(1)
        ->and(($this->guardado)())->toBe('10.0000')
        ->and($this->servico->estornarEntrada($this->biz->id, $c1))->toBe([])
        ->and(($this->guardado)())->toBe('10.0000');
});

/*
 * compras:custo-medio-reconciliar. A compra órfã nasce por query direta, sem passar pelo observer:
 * é o estado que sobra quando o estorno falhou ou a flag estava desligada.
 */
function custoMedioReconciliar(array $opcoes = []): array
{
    $rc = Artisan::call('compras:custo-medio-reconciliar', $opcoes);

    return [$rc, Artisan::output()];
}

function custoMedioCompraOrfa(int $id): void
{
    DB::table('transactions')->where('id', $id)->update(['status' => 'pending']);
}

it('UC-CMED-18 · reconciliar sem --aplicar mostra o delta e não grava nada', function () {
    $c1 = custoMedioComSementeEC1($this);
    custoMedioCompraOrfa($c1);

    [$rc, $saida] = custoMedioReconciliar(['--business' => $this->biz->id]);

    expect($rc)->toBe(0)
        ->and($saida)->toContain('15,0000')
        ->and($saida)->toContain('10,0000')
        ->and($saida)->toContain('-5,0000')
        ->and($saida)->toContain('Só leitura')
        ->and(($this->guardado)())->toBe('15.0000')
        ->and(DB::table('compras_custo_medio_movimentos')->where('transaction_id', $c1)->whereNull('ativo')->count())->toBe(0);
});

it('UC-CMED-19 · --aplicar sem --business é recusado e não grava', function () {
    $c1 = custoMedioComSementeEC1($this);
    custoMedioCompraOrfa($c1);

    [$rc] = custoMedioReconciliar(['--aplicar' => true]);

    expect($rc)->toBe(1)
        ->and(($this->guardado)())->toBe('15.0000');
});

it('UC-CMED-20 · --aplicar estorna a compra órfã, com o mesmo número do dry-run, e a segunda rodada não acha nada', function () {
    $c1 = custoMedioComSementeEC1($this);
    custoMedioCompraOrfa($c1);
    expect(custoMedioMovelEsperado(10, 10.00, 0, 0))->toBe('10.0000');

    [$rc] = custoMedioReconciliar(['--business' => $this->biz->id, '--aplicar' => true]);
    expect($rc)->toBe(0)
        ->and(($this->guardado)())->toBe('10.0000');

    [, $saida] = custoMedioReconciliar(['--business' => $this->biz->id]);
    expect($saida)->not->toContain('compra órfã estornada');
});

it('UC-CMED-21 · custo guardado que difere do histórico volta ao último movimento', function () {
    custoMedioComSementeEC1($this);
    DB::table('compras_custo_medio')->where('variation_id', $this->variationId)->update(['custo_medio' => 99]);

    custoMedioReconciliar(['--business' => $this->biz->id, '--aplicar' => true]);

    expect(($this->guardado)())->toBe('15.0000');
});

it('UC-CMED-22 · Tier 0: reconciliar outra empresa não toca a desta', function () {
    $c1 = custoMedioComSementeEC1($this);
    custoMedioCompraOrfa($c1);

    [$rc] = custoMedioReconciliar(['--business' => $this->outra->id, '--aplicar' => true]);

    expect($rc)->toBe(0)
        ->and(($this->guardado)())->toBe('15.0000')
        ->and(DB::table('compras_custo_medio')->where('business_id', $this->outra->id)->count())->toBe(0);
});

it('UC-CMED-23 · entrada perdida é apontada e nunca gravada', function () {
    custoMedioComSementeEC1($this);
    $perdida = ($this->lote)($this->biz->id, 'purchase', 'received', 3, 40.00);

    [, $saida] = custoMedioReconciliar(['--business' => $this->biz->id, '--aplicar' => true]);

    expect($saida)->toContain('Entradas perdidas')
        ->and($saida)->toContain((string) $perdida)
        ->and(($this->guardado)())->toBe('15.0000')
        ->and(DB::table('compras_custo_medio_movimentos')->where('transaction_id', $perdida)->count())->toBe(0);
});
