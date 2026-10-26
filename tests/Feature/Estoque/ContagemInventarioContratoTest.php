<?php
// @covers-us US-ESTOQUE-002

declare(strict_types=1);

use App\Services\Estoque\ContagemInventarioException;
use App\Services\Estoque\ContagemInventarioService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * US-ESTOQUE-002 (parte 1) — abrir, contar, fechar e ver a divergência. Nada aqui muda saldo.
 *
 * Âncora: aceite da US-ESTOQUE-002 em memory/requisitos/Estoque/SPEC.md.
 *
 * Números feitos à mão (local L, custo de compra 12,50):
 *   saldo 10, contado 7  → diferença −3, valor −37,50 (falta)
 *   saldo 4,  contado 6  → diferença +2, valor +25,00 (sobra)
 *   bipado 3 vezes com 1 (somar) → contado 3; depois substituir por 5 → contado 5
 *   venda depois da contagem (saldo 10 → 8) não muda o saldo_no_momento (segue 10)
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('estoque_contagens')) {
        $this->markTestSkipped('Schema UltimatePOS/estoque_contagens ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->user = EstoqueFixture::userId($this->biz);
    $this->loc = EstoqueFixture::locationId($this->biz, 'CONT');
    $this->svc = app(ContagemInventarioService::class);

    $this->produto = function (float $saldo, string $custo = '12.5000') {
        $p = EstoqueFixture::singleProduct($this->biz);
        EstoqueFixture::setStock($p, 0, $this->loc, $saldo);
        DB::table('variations')->where('id', $p->variations[0]['variation_id'])->update(['default_purchase_price' => $custo]);

        return $p;
    };
    $this->saldoAntes = fn ($p) => EstoqueFixture::currentStock($p, 0, $this->loc);
});

it('abre contagem no local e recusa uma segunda aberta no mesmo local', function () {
    $id = $this->svc->abrir($this->biz, $this->loc, $this->user, 'Balanço outubro');
    expect(DB::table('estoque_contagens')->where('id', $id)->value('status'))->toBe('aberta');

    expect(fn () => $this->svc->abrir($this->biz, $this->loc, $this->user))
        ->toThrow(ContagemInventarioException::class, "contagem #{$id}");
});

it('calcula falta e sobra contra o saldo do momento e não mexe no estoque', function () {
    $falta = ($this->produto)(10);
    $sobra = ($this->produto)(4);
    $id = $this->svc->abrir($this->biz, $this->loc, $this->user);

    $this->svc->registrar($this->biz, $id, $falta->variations[0]['variation_id'], '7', $this->user, 'substituir');
    $this->svc->registrar($this->biz, $id, $sobra->variations[0]['variation_id'], '6', $this->user, 'substituir');

    $div = collect($this->svc->divergencias($this->biz, $id))->keyBy('variation_id');
    $f = $div[$falta->variations[0]['variation_id']];
    $s = $div[$sobra->variations[0]['variation_id']];
    expect([$f['saldo_no_momento'], $f['qtd_contada'], $f['diferenca'], $f['valor_diferenca']])
        ->toBe(['10.0000', '7.0000', '-3.0000', '-37.50']);
    expect([$s['saldo_no_momento'], $s['qtd_contada'], $s['diferenca'], $s['valor_diferenca']])
        ->toBe(['4.0000', '6.0000', '2.0000', '25.00']);

    // Contar não é ajustar: o saldo segue o mesmo.
    expect(($this->saldoAntes)($falta))->toBe(10.0);
    expect(($this->saldoAntes)($sobra))->toBe(4.0);
});

it('bipar soma; digitar substitui; o saldo do momento fica congelado na primeira contagem', function () {
    $p = ($this->produto)(10);
    $var = $p->variations[0]['variation_id'];
    $id = $this->svc->abrir($this->biz, $this->loc, $this->user);

    foreach ([1, 2, 3] as $_) {
        $this->svc->registrar($this->biz, $id, $var, '1', $this->user);
    }
    expect(DB::table('estoque_contagem_itens')->where('contagem_id', $id)->value('qtd_contada'))->toBe('3.0000');

    // Venda depois da contagem: o saldo cai para 8, o saldo do momento segue 10.
    EstoqueFixture::setStock($p, 0, $this->loc, 8);
    expect($this->svc->registrar($this->biz, $id, $var, '5', $this->user, 'substituir'))->toBe('5.0000');
    expect(DB::table('estoque_contagem_itens')->where('contagem_id', $id)->value('saldo_no_momento'))->toBe('10.0000');
});

it('acha a variação pelo SKU lido no coletor', function () {
    $p = ($this->produto)(1);
    $sku = DB::table('variations')->where('id', $p->variations[0]['variation_id'])->value('sub_sku');

    expect($this->svc->resolverCodigo($this->biz, $sku))->toBe($p->variations[0]['variation_id']);
    expect($this->svc->resolverCodigo($this->biz, 'NAO-EXISTE-' . uniqid()))->toBeNull();
});

it('recusa quantidade negativa, produto sem controle de estoque e contagem fechada', function () {
    $p = ($this->produto)(1);
    $semEstoque = EstoqueFixture::singleProduct($this->biz, false);
    $id = $this->svc->abrir($this->biz, $this->loc, $this->user);

    expect(fn () => $this->svc->registrar($this->biz, $id, $p->variations[0]['variation_id'], '-1', $this->user))
        ->toThrow(ContagemInventarioException::class);
    expect(fn () => $this->svc->registrar($this->biz, $id, $semEstoque->variations[0]['variation_id'], '1', $this->user))
        ->toThrow(ContagemInventarioException::class, 'não controla estoque');

    $this->svc->fechar($this->biz, $id, $this->user);
    expect(fn () => $this->svc->registrar($this->biz, $id, $p->variations[0]['variation_id'], '1', $this->user))
        ->toThrow(ContagemInventarioException::class, 'não está aberta');
});

it('[T0] outra empresa não conta, não fecha e não vê a contagem', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Só um business semeado.');
    }
    $p = ($this->produto)(10);
    $id = $this->svc->abrir($this->biz, $this->loc, $this->user);
    $this->svc->registrar($this->biz, $id, $p->variations[0]['variation_id'], '7', $this->user);

    expect(fn () => $this->svc->registrar($outro, $id, $p->variations[0]['variation_id'], '1', $this->user))
        ->toThrow(ContagemInventarioException::class, 'não encontrada');
    expect(fn () => $this->svc->fechar($outro, $id, $this->user))->toThrow(ContagemInventarioException::class);
    expect($this->svc->divergencias($outro, $id))->toBe([]);
    // Produto de outra empresa não entra numa contagem minha.
    $alheio = EstoqueFixture::singleProduct($outro);
    expect(fn () => $this->svc->registrar($this->biz, $id, $alheio->variations[0]['variation_id'], '1', $this->user))
        ->toThrow(ContagemInventarioException::class, 'não encontrado nesta empresa');
    expect(fn () => $this->svc->abrir($outro, $this->loc, $this->user))
        ->toThrow(ContagemInventarioException::class, 'Local não encontrado');
});

// ── Parte 2: aprovar o ajuste (escreve no estoque — regra mestre) ─────────────────────────────
//
// Números feitos à mão, custo 12,50:
//   saldo 10, contado 7, nada vendido depois  → saldo final 7   · ajuste de falta 3 × 12,50 = 37,50
//   saldo 4,  contado 6                       → saldo final 6   · entrada de sobra 2 × 12,50 = 25,00
//   saldo 10, contado 7, 2 vendidos depois    → saldo final 5   (8 de agora − 3 de falta)

function contagemLote($teste, $p, string $qtd): void
{
    // Lote de compra para o ajuste de falta casar no FIFO, como na loja real.
    $tx = DB::table('transactions')->insertGetId([
        'business_id' => $teste->biz, 'location_id' => $teste->loc, 'type' => 'purchase', 'status' => 'received',
        'payment_status' => 'paid', 'transaction_date' => now()->subDay(), 'final_total' => 0,
        'created_by' => $teste->user, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $tx, 'product_id' => $p->productId, 'variation_id' => $p->variations[0]['variation_id'],
        'quantity' => $qtd, 'pp_without_discount' => '12.5000', 'purchase_price' => '12.5000',
        'purchase_price_inc_tax' => '12.5000', 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('aprovar tira a falta e lança a sobra, com os valores pelo custo', function () {
    $falta = ($this->produto)(10);
    $sobra = ($this->produto)(4);
    contagemLote($this, $falta, '10');
    $id = $this->svc->abrir($this->biz, $this->loc, $this->user);
    $this->svc->registrar($this->biz, $id, $falta->variations[0]['variation_id'], '7', $this->user, 'substituir');
    $this->svc->registrar($this->biz, $id, $sobra->variations[0]['variation_id'], '6', $this->user, 'substituir');
    $this->svc->fechar($this->biz, $id, $this->user);

    $r = $this->svc->aprovar($this->biz, $id, $this->user);

    expect([$r['itens_falta'], $r['itens_sobra'], $r['valor_falta'], $r['valor_sobra']])->toBe([1, 1, '37.50', '25.00']);
    expect(($this->saldoAntes)($falta))->toBe(7.0);
    expect(($this->saldoAntes)($sobra))->toBe(6.0);

    $ajuste = DB::table('transactions')->where('id', $r['ajuste_transaction_id'])->first();
    expect([$ajuste->type, $ajuste->business_id, (string) $ajuste->final_total])->toBe(['stock_adjustment', $this->biz, '37.5000']);
    $linha = DB::table('stock_adjustment_lines')->where('transaction_id', $ajuste->id)->first();
    expect([(string) $linha->quantity, (string) $linha->unit_price])->toBe(['3.0000', '12.5000']);
    // A falta casou no lote de compra (FIFO), como o ajuste manual.
    expect(DB::table('transaction_sell_lines_purchase_lines')->where('stock_adjustment_line_id', $linha->id)->sum('quantity'))->toEqual(3);

    $entrada = DB::table('transactions')->where('id', $r['entrada_transaction_id'])->first();
    expect([$entrada->type, $entrada->opening_stock_product_id, (string) $entrada->final_total])->toBe(['opening_stock', null, '25.0000']);
    expect((string) DB::table('purchase_lines')->where('transaction_id', $entrada->id)->value('quantity'))->toBe('2.0000');

    expect(DB::table('estoque_contagens')->where('id', $id)->value('status'))->toBe('ajustada');
});

it('aprovar aplica a diferença sobre o saldo de agora: o que foi vendido depois não é cobrado de novo', function () {
    $p = ($this->produto)(10);
    contagemLote($this, $p, '10');
    $id = $this->svc->abrir($this->biz, $this->loc, $this->user);
    $this->svc->registrar($this->biz, $id, $p->variations[0]['variation_id'], '7', $this->user, 'substituir');
    EstoqueFixture::setStock($p, 0, $this->loc, 8); // 2 vendidos depois da contagem
    $this->svc->fechar($this->biz, $id, $this->user);

    $this->svc->aprovar($this->biz, $id, $this->user);

    expect(($this->saldoAntes)($p))->toBe(5.0);
});

it('aprovar só vale para contagem fechada, e uma vez só', function () {
    $p = ($this->produto)(4);
    $id = $this->svc->abrir($this->biz, $this->loc, $this->user);
    $this->svc->registrar($this->biz, $id, $p->variations[0]['variation_id'], '6', $this->user, 'substituir');

    expect(fn () => $this->svc->aprovar($this->biz, $id, $this->user))->toThrow(ContagemInventarioException::class, 'fechada');

    $this->svc->fechar($this->biz, $id, $this->user);
    $this->svc->aprovar($this->biz, $id, $this->user);
    expect(fn () => $this->svc->aprovar($this->biz, $id, $this->user))->toThrow(ContagemInventarioException::class);
    expect(($this->saldoAntes)($p))->toBe(6.0);
});

it('[T0] outra empresa não aprova a minha contagem', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Só um business semeado.');
    }
    $p = ($this->produto)(4);
    $id = $this->svc->abrir($this->biz, $this->loc, $this->user);
    $this->svc->registrar($this->biz, $id, $p->variations[0]['variation_id'], '6', $this->user, 'substituir');
    $this->svc->fechar($this->biz, $id, $this->user);

    expect(fn () => $this->svc->aprovar($outro, $id, $this->user))->toThrow(ContagemInventarioException::class, 'não encontrada');
    expect(($this->saldoAntes)($p))->toBe(4.0);
});
