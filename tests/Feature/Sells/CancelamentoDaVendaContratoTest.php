<?php
// @covers-us US-SELL-077

declare(strict_types=1);

use App\Domain\Fsm\SideEffects\CancelarVendaCascade;
use App\Services\Sells\CancelamentoDaVenda;
use App\Services\Sells\VendaNaoCancelavelException;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Fiscal\Http\Controllers\CockpitController;
use Modules\NfeBrasil\Jobs\CancelarNfeJob;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;
use Tests\Support\EstoqueFixture;

/**
 * UC-VCAN-01..07 · US-SELL-077 — cancelar a venda devolve o estoque, vira `cancelled` e cancela o
 * título; venda paga não cancela (decisões [W] 2026-10-26).
 *
 * Âncora: resources/js/Pages/Sells/Show.casos.md (UC-VCAN-*), derivado da US-SELL-077.
 *
 * Números feitos à mão (lote de compra de 10 un a 12,50; venda de 3 un a 20,00 = 60,00):
 *   saldo 7 depois da venda → cancelar → saldo 10, lote volta a 0 vendidas
 *   com 1 un devolvida antes (saldo 8, lote 2 vendidas) → cancelar → saldo 10, lote 0
 *   venda com 60,00 recebidos → recusa "R$ 60,00 recebidos", saldo segue 7
 *   orçamento com reserva consumida de 2 un (saldo 8) → cancelar → saldo 10, reserva released
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasColumn('transactions', 'cancelled_at')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->user = EstoqueFixture::userId($this->biz);
    $this->loc = EstoqueFixture::locationId($this->biz, 'CANC');
    $this->svc = app(CancelamentoDaVenda::class);

    // Venda final de 3 un, casada com um lote de compra de 10, saldo 7 depois da venda.
    $this->vendaCom = function (float $devolvida = 0) {
        $p = EstoqueFixture::singleProduct($this->biz);
        $var = $p->variations[0]['variation_id'];
        $compra = DB::table('transactions')->insertGetId([
            'business_id' => $this->biz, 'location_id' => $this->loc, 'type' => 'purchase', 'status' => 'received',
            'payment_status' => 'paid', 'transaction_date' => now()->subDay(), 'final_total' => 125,
            'created_by' => $this->user, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $lote = DB::table('purchase_lines')->insertGetId([
            'transaction_id' => $compra, 'product_id' => $p->productId, 'variation_id' => $var,
            'quantity' => 10, 'quantity_sold' => 3 - $devolvida, 'pp_without_discount' => 12.5, 'purchase_price' => 12.5,
            'purchase_price_inc_tax' => 12.5, 'item_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $venda = EstoqueFixture::saleWithLine($p, 0, $this->loc, 3.0, 20.0);
        DB::table('transactions')->where('id', $venda['transaction_id'])->update(['payment_status' => 'due']);
        DB::table('transaction_sell_lines')->where('id', $venda['sell_line_id'])->update(['quantity_returned' => $devolvida]);
        DB::table('transaction_sell_lines_purchase_lines')->insert([
            'sell_line_id' => $venda['sell_line_id'], 'purchase_line_id' => $lote, 'quantity' => 3,
            'qty_returned' => $devolvida, 'created_at' => now(), 'updated_at' => now(),
        ]);
        EstoqueFixture::setStock($p, 0, $this->loc, 7 + $devolvida);

        return ['p' => $p, 'tx' => $venda['transaction_id'], 'lote' => $lote];
    };
    $this->saldo = fn ($p) => EstoqueFixture::currentStock($p, 0, $this->loc);
    $this->vendidasNoLote = fn (int $lote) => (float) DB::table('purchase_lines')->where('id', $lote)->value('quantity_sold');
});

it('UC-VCAN-01 cancelar devolve o estoque, libera o lote e vira cancelled', function () {
    $v = ($this->vendaCom)();

    $r = $this->svc->aplicar($this->biz, $v['tx'], 'cliente desistiu');

    expect($r['estoque_linhas'])->toBe(1);
    expect(($this->saldo)($v['p']))->toBe(10.0);
    expect(($this->vendidasNoLote)($v['lote']))->toBe(0.0);
    $tx = DB::table('transactions')->where('id', $v['tx'])->first();
    expect($tx->status)->toBe('cancelled');
    expect($tx->cancelled_at)->not->toBeNull();
});

it('UC-VCAN-02 o que a devolução já repôs não volta duas vezes', function () {
    $v = ($this->vendaCom)(1);

    $this->svc->aplicar($this->biz, $v['tx'], 'x');

    expect(($this->saldo)($v['p']))->toBe(10.0);
    expect(($this->vendidasNoLote)($v['lote']))->toBe(0.0);
});

it('UC-VCAN-03 venda com dinheiro recebido não cancela e diz o que falta', function () {
    $v = ($this->vendaCom)();
    DB::table('transaction_payments')->insert([
        'business_id' => $this->biz, 'transaction_id' => $v['tx'], 'amount' => '60.0000', 'is_return' => 0,
        'method' => 'cash', 'paid_on' => now(), 'created_by' => $this->user, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => $this->svc->aplicar($this->biz, $v['tx'], 'x'))
        ->toThrow(VendaNaoCancelavelException::class, 'R$ 60,00 recebidos');
    expect(($this->saldo)($v['p']))->toBe(7.0);
    expect(DB::table('transactions')->where('id', $v['tx'])->value('status'))->toBe('final');

    // Pelo efeito da FSM a recusa também sobe, antes de cancelar notas e boletos.
    expect(fn () => (new CancelarVendaCascade)->execute(Transaction::findOrFail($v['tx']), ['motivo' => 'x']))
        ->toThrow(VendaNaoCancelavelException::class);
});

it('UC-VCAN-04 cancelar duas vezes não devolve o estoque duas vezes', function () {
    $v = ($this->vendaCom)();
    $this->svc->aplicar($this->biz, $v['tx'], 'x');

    $r = $this->svc->aplicar($this->biz, $v['tx'], 'x');

    expect($r['ja_estava_cancelada'])->toBeTrue();
    expect(($this->saldo)($v['p']))->toBe(10.0);
});

it('UC-VCAN-05 orçamento com reserva consumida pela FSM devolve a reserva', function () {
    if (! Schema::hasTable('stock_reservations')) {
        $this->markTestSkipped('stock_reservations ausente.');
    }
    $p = EstoqueFixture::singleProduct($this->biz);
    $venda = EstoqueFixture::saleWithLine($p, 0, $this->loc, 2.0, 20.0);
    DB::table('transactions')->where('id', $venda['transaction_id'])->update(['status' => 'draft', 'payment_status' => 'due']);
    EstoqueFixture::setStock($p, 0, $this->loc, 8);
    $res = DB::table('stock_reservations')->insertGetId([
        'business_id' => $this->biz, 'transaction_id' => $venda['transaction_id'], 'product_id' => $p->productId,
        'variation_id' => $p->variations[0]['variation_id'], 'location_id' => $this->loc, 'qty_reserved' => 2,
        'status' => 'consumed', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $r = $this->svc->aplicar($this->biz, $venda['transaction_id'], 'x');

    expect([$r['estoque_linhas'], $r['reservas']])->toBe([0, 1]);
    expect(($this->saldo)($p))->toBe(10.0);
    expect(DB::table('stock_reservations')->where('id', $res)->value('status'))->toBe('released');
});

it('UC-VCAN-06 2ª prova: a conferência de estoque fecha depois de cancelar', function () {
    // Conta independente: ProductUtil::getVariationStockMisMatch soma compras e vendas FINAIS e
    // devolve o saldo calculado. Cancelar tira a venda da conta e devolve o mesmo tanto ao saldo,
    // então (gravado − calculado) não muda.
    $v = ($this->vendaCom)();
    $util = app(\App\Utils\ProductUtil::class);
    $gap = function () use ($util, $v) {
        $r = $util->getVariationStockMisMatch($this->biz, $v['p']->variations[0]['variation_id'], $this->loc)->first();

        return round((float) $r->stock - (float) $r->total_stock_calculated, 4);
    };
    $antes = $gap();

    $this->svc->aplicar($this->biz, $v['tx'], 'x');

    expect($gap())->toBe($antes);
});

it('UC-VCAN-07 [T0] outra empresa não cancela a venda', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Só um business semeado.');
    }
    $v = ($this->vendaCom)();

    expect(fn () => $this->svc->aplicar($outro, $v['tx'], 'x'))->toThrow(VendaNaoCancelavelException::class, 'não encontrada');
    expect(($this->saldo)($v['p']))->toBe(7.0);
    expect(DB::table('transactions')->where('id', $v['tx'])->value('status'))->toBe('final');
});

it('UC-VCAN-08 venda sem pagamento: o título do Financeiro fica cancelado, não apagado', function () {
    if (! class_exists(\Modules\Financeiro\Services\TituloAutoService::class) || ! Schema::hasTable('fin_titulos')) {
        $this->markTestSkipped('Financeiro ausente.');
    }
    $v = ($this->vendaCom)();
    DB::table('transactions')->where('id', $v['tx'])->update(['contact_id' => DB::table('contacts')->where('business_id', $this->biz)->value('id')]);
    $titulo = app(\Modules\Financeiro\Services\TituloAutoService::class)->sincronizarDeTransacao(Transaction::findOrFail($v['tx']));
    if ($titulo === null) {
        $this->markTestSkipped('O Financeiro não gerou título para esta venda neste ambiente.');
    }

    $r = $this->svc->aplicar($this->biz, $v['tx'], 'cliente desistiu');

    expect($r['titulo_cancelado'])->toBeTrue();
    $t = DB::table('fin_titulos')->where('id', $titulo->id)->first();
    expect($t)->not->toBeNull();
    expect($t->status)->toBe('cancelado');
});

it('UC-VCAN-09 a venda cancelada sai do extrato do cliente', function () {
    // O extrato (getLedgerDetails → __transactionQuery) contava tudo que não era rascunho.
    $cliente = (int) DB::table('contacts')->insertGetId([
        'business_id' => $this->biz, 'type' => 'customer', 'name' => 'Cliente extrato', 'contact_id' => 'EX-' . uniqid(),
        'created_by' => $this->user, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $nova = fn (string $status, float $total) => DB::table('transactions')->insertGetId([
        'business_id' => $this->biz, 'location_id' => $this->loc, 'type' => 'sell', 'status' => $status,
        'payment_status' => 'due', 'contact_id' => $cliente, 'transaction_date' => now(), 'final_total' => $total,
        'created_by' => $this->user, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $nova('final', 100.0);
    $nova('cancelled', 60.0);
    $nova('draft', 30.0);
    // O método lê request()->session(); a requisição de teste não vem com a sessão ligada.
    request()->setLaravelSession(app('session.store'));
    session(['user.business_id' => $this->biz]);

    $util = app(\App\Utils\TransactionUtil::class);
    $m = new ReflectionMethod($util, '__transactionQuery');
    $soma = (float) $m->invoke($util, $cliente, null)->sum('final_total');

    expect($soma)->toBe(100.0);
});

/** NF-e da venda como a NfeService grava na autorização. */
function vcanNota(int $biz, int $tx, array $sobre = []): int
{
    return (int) DB::table('nfe_emissoes')->insertGetId($sobre + [
        'business_id' => $biz, 'transaction_id' => $tx, 'modelo' => '55', 'serie' => '977', 'numero' => random_int(1, 99_000_000),
        'chave_44' => str_repeat('4', 44), 'status' => 'autorizada', 'valor_total' => 60, 'emitido_em' => now()->subHour(),
        'metadata' => json_encode(['nProt' => '100000000000000']), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-VCAN-10 cancelar a venda dispara o job e o job chega na nota', function (string $modelo, string $tipo) {
    Bus::fake();
    $v = ($this->vendaCom)();
    $nota = vcanNota($this->biz, $v['tx'], ['modelo' => $modelo]);

    app(CancelarVendaCascade::class)->execute(Transaction::findOrFail($v['tx']), ['motivo' => 'Cliente desistiu da compra']);

    $doc = DB::table('transaction_documents')->where('business_id', $this->biz)->where('transaction_id', $v['tx'])->first();
    expect($doc->doc_type)->toBe($tipo);
    expect($doc->doc_class)->toBe(NfeEmissao::class);
    expect((int) $doc->doc_id)->toBe($nota);
    expect($doc->status)->toBe('authorized');

    $job = null;
    Bus::assertDispatched(CancelarNfeJob::class, function (CancelarNfeJob $j) use (&$job, $doc) {
        $job = $j;

        return $j->transactionDocumentId === (int) $doc->id && $j->businessId === $this->biz;
    });

    $sefaz = Mockery::mock(NfeService::class);
    $sefaz->shouldReceive('cancelar')->once()->with($this->biz, $nota, 'Cliente desistiu da compra');
    $job->handle($sefaz);

    expect(DB::table('transaction_documents')->where('id', $doc->id)->value('status'))->toBe('cancelled');
    expect(DB::table('nfe_emissoes')->where('id', $nota)->whereNull('deleted_at')->exists())->toBeTrue();
})->with([
    'NF-e' => ['55', 'nfe55'],
    'NFC-e' => ['65', 'nfce65'],
]);

it('UC-VCAN-11 nota fora do prazo recusa e a venda fica como estava', function (string $modelo, int $horas, string $prazo) {
    Bus::fake();
    $v = ($this->vendaCom)();
    vcanNota($this->biz, $v['tx'], ['modelo' => $modelo, 'emitido_em' => now()->subHours($horas)]);

    expect(fn () => app(CancelarVendaCascade::class)->execute(Transaction::findOrFail($v['tx']), ['motivo' => 'Cliente desistiu da compra']))
        ->toThrow(VendaNaoCancelavelException::class, "prazo de {$prazo}");

    expect(DB::table('transactions')->where('id', $v['tx'])->value('status'))->toBe('final');
    expect(($this->saldo)($v['p']))->toBe(7.0);
    expect(DB::table('transaction_documents')->where('transaction_id', $v['tx'])->count())->toBe(0);
    Bus::assertNotDispatched(CancelarNfeJob::class);
})->with([
    'NF-e 8 dias' => ['55', 192, '168 horas'],
    'NFC-e 25 horas' => ['65', 25, '24 horas'],
]);

it('UC-VCAN-12 nota que a SEFAZ não aceitaria cancelar recusa a venda', function (array $nota, string $texto) {
    Bus::fake();
    $v = ($this->vendaCom)();
    vcanNota($this->biz, $v['tx'], $nota);

    expect(fn () => $this->svc->aplicar($this->biz, $v['tx'], 'x'))->toThrow(VendaNaoCancelavelException::class, $texto);

    expect(DB::table('transactions')->where('id', $v['tx'])->value('status'))->toBe('final');
})->with([
    'em processamento' => [['status' => 'enviando'], 'em processamento'],
    'contingência' => [['status' => 'contingencia'], 'em processamento'],
    'sem protocolo' => [['metadata' => '{}'], 'protocolo'],
]);

it('UC-VCAN-13 [T0] nota de outra empresa com o mesmo id de venda não entra', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Só um business semeado.');
    }
    Bus::fake();
    $v = ($this->vendaCom)();
    vcanNota($outro, $v['tx'], ['emitido_em' => now()->subDays(30)]);

    app(CancelarVendaCascade::class)->execute(Transaction::findOrFail($v['tx']), ['motivo' => 'Cliente desistiu da compra']);

    expect(DB::table('transactions')->where('id', $v['tx'])->value('status'))->toBe('cancelled');
    expect(DB::table('transaction_documents')->where('transaction_id', $v['tx'])->count())->toBe(0);
    Bus::assertNotDispatched(CancelarNfeJob::class);
});

it('UC-VCAN-14 nota já cancelada não dispara job e continua no banco', function () {
    Bus::fake();
    $v = ($this->vendaCom)();
    $nota = vcanNota($this->biz, $v['tx'], ['status' => 'cancelada']);

    app(CancelarVendaCascade::class)->execute(Transaction::findOrFail($v['tx']), ['motivo' => 'Cliente desistiu da compra']);

    Bus::assertNotDispatched(CancelarNfeJob::class);
    expect(DB::table('nfe_emissoes')->where('id', $nota)->value('status'))->toBe('cancelada');
});

it('UC-VCAN-15 motivo curto chega à SEFAZ com o tamanho que ela exige', function () {
    Bus::fake();
    $v = ($this->vendaCom)();
    vcanNota($this->biz, $v['tx']);

    app(CancelarVendaCascade::class)->execute(Transaction::findOrFail($v['tx']), ['motivo' => 'erro']);

    Bus::assertDispatched(CancelarNfeJob::class, fn (CancelarNfeJob $j) => $j->motivo === 'Cancelamento da venda: erro');
});

it('UC-VCAN-16 [T0] a nota que não cancelou vira alerta só da empresa no cockpit', function () {
    if (! Schema::hasTable('mcp_alertas_eventos')) {
        $this->markTestSkipped('mcp_alertas_eventos ausente.');
    }
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Só um business semeado.');
    }
    $v = ($this->vendaCom)();
    $doc = DB::table('transaction_documents')->insertGetId([
        'business_id' => $this->biz, 'transaction_id' => $v['tx'], 'doc_type' => 'nfe55', 'doc_class' => NfeEmissao::class,
        'doc_id' => vcanNota($this->biz, $v['tx']), 'value_total' => 60, 'status' => 'authorized',
    ]);

    $job = new CancelarNfeJob($this->biz, (int) $doc, 'Cliente desistiu da compra');
    $job->failed(new RuntimeException('SEFAZ rejeitou'));
    $job->failed(new RuntimeException('SEFAZ rejeitou de novo'));

    $alertas = DB::table('mcp_alertas_eventos')
        ->where('chave_idempotencia', sprintf('%s:%d:%d', CancelarNfeJob::TIPO_ALERTA, $this->biz, $doc))->get();
    expect($alertas)->toHaveCount(1);
    expect($alertas[0]->descricao)->toContain("Venda #{$v['tx']}");
    expect($alertas[0]->descricao)->toContain('de novo');

    $cockpit = (new ReflectionClass(CockpitController::class))->newInstanceWithoutConstructor();
    $m = new ReflectionMethod(CockpitController::class, 'ultimaFalhaCancelamentoNfe');
    expect($m->invoke($cockpit, $this->biz)?->descricao)->toContain('de novo');
    expect($m->invoke($cockpit, $outro))->toBeNull();
});
