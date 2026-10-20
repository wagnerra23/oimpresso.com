<?php

declare(strict_types=1);

use App\Transaction;
use App\TransactionPayment;
use App\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;

uses(Tests\TestCase::class);

/**
 * US-FIN-073 — o valor em aberto do título de venda/compra PARCIAL sobrevive à re-sincronização.
 *
 * O bug: `TituloAutoService::sincronizarDeTransacao` lia `transactions.total_remaining_amount`,
 * coluna que NÃO existe (BridgeExpenseToTitulosCommand:30). O `?? final_total` sempre ganhava.
 * Como o core grava o `payment_status` com save() DEPOIS de cada pagamento
 * (TransactionUtil::updatePaymentStatus), o TransactionObserver re-sincronizava e devolvia o
 * aberto ao bruto — o título de uma venda de 1.000,00 com 400,00 pago voltava a 1.000,00 "aberto".
 *
 * Regra mestre de valor: cada valor é conferido por DOIS caminhos independentes — (a) a soma das
 * baixas do título no banco e (b) final_total − Σ transaction_payments (a conta do core) — além
 * do literal esperado. Tenant 98 (ADR 0358); tudo dentro de transação desfeita no fim
 * (`fin_titulos` não aceita delete).
 *
 * Casos: UC-CRAB-01 (venda) · UC-CRAB-02 (compra) · UC-CRAB-03 (relatório só leitura)
 *        · UC-CRAB-04 (relatório não mostra outro business).
 *
 * @covers-us US-FIN-073
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Financeiro usa schema MySQL.');
    }

    $biz = $this->seededTenant();
    $this->bizId = (int) $biz->id;
    $this->userId = (int) (User::where('business_id', $this->bizId)->value('id') ?? 0);
    if ($this->userId === 0) {
        $this->markTestSkipped('Sem usuário no tenant de teste.');
    }
    $this->locationId = DB::table('business_locations')->where('business_id', $this->bizId)->value('id');

    DB::beginTransaction();
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function abertoParcialTx(int $biz, int $userId, ?int $locationId, string $type, float $total): Transaction
{
    return Transaction::create([
        'business_id' => $biz,
        'location_id' => $locationId,
        'type' => $type,
        'status' => $type === 'purchase' ? 'received' : 'final',
        'payment_status' => 'due',
        'contact_id' => null,
        'invoice_no' => 'ZZ-FIN073-'.Str::random(8),
        'transaction_date' => '2031-02-10 10:00:00',
        'total_before_tax' => $total,
        'final_total' => $total,
        'created_by' => $userId,
    ]);
}

function abertoParcialPagar(Transaction $tx, int $userId, float $valor): TransactionPayment
{
    return TransactionPayment::create([
        'transaction_id' => $tx->id,
        'business_id' => $tx->business_id,
        'amount' => $valor,
        'method' => 'cash',
        'paid_on' => '2031-02-11 10:00:00',
        'created_by' => $userId,
    ]);
}

function abertoParcialTitulo(Transaction $tx): Titulo
{
    return Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('business_id', $tx->business_id)
        ->where('origem', $tx->type === 'purchase' ? 'compra' : 'venda')
        ->where('origem_id', $tx->id)
        ->whereNull('parcela_numero')
        ->firstOrFail();
}

/** Caminho (a): bruto − Σ baixas gravadas no título. */
function abertoParcialPorBaixas(Titulo $t): float
{
    $soma = (float) DB::table('fin_titulo_baixas')->where('titulo_id', $t->id)->sum('valor_baixa');

    return round((float) $t->valor_total - $soma, 2);
}

/** Caminho (b): a conta do core — final_total − Σ pagamentos líquidos de troco. */
function abertoParcialPeloCore(Transaction $tx): float
{
    $pago = (float) DB::table('transaction_payments')
        ->where('transaction_id', $tx->id)
        ->selectRaw('COALESCE(SUM(IF(is_return = 0, amount, -1 * amount)), 0) as p')
        ->value('p');

    return round((float) $tx->final_total - $pago, 2);
}

it('UC-CRAB-01 venda de 1.000,00 com 400,00 pago fica com 600,00 em aberto depois que o core grava "partial"', function () {
    $tx = abertoParcialTx($this->bizId, $this->userId, $this->locationId, 'sell', 1000.00);
    abertoParcialPagar($tx, $this->userId, 400.00);

    // O que o core faz depois de cada pagamento (TransactionUtil::updatePaymentStatus): save().
    $tx->refresh();
    $tx->payment_status = 'partial';
    $tx->save();

    $titulo = abertoParcialTitulo($tx);

    expect((float) $titulo->valor_total)->toBe(1000.0);
    expect((float) $titulo->valor_aberto)->toBe(600.0);
    expect(abertoParcialPorBaixas($titulo))->toBe(600.0);
    expect(abertoParcialPeloCore($tx))->toBe(600.0);
    expect($titulo->status)->toBe('parcial');

    // Re-sincronizar de novo (ex.: edição do vencimento) não mexe no aberto.
    $tx->due_date = '2031-03-15';
    $tx->save();
    expect((float) abertoParcialTitulo($tx)->valor_aberto)->toBe(600.0);
});

it('UC-CRAB-02 compra de 500,00 com 200,00 pago fica com 300,00 em aberto', function () {
    $tx = abertoParcialTx($this->bizId, $this->userId, $this->locationId, 'purchase', 500.00);
    abertoParcialPagar($tx, $this->userId, 200.00);

    $tx->refresh();
    $tx->payment_status = 'partial';
    $tx->save();

    $titulo = abertoParcialTitulo($tx);

    expect((float) $titulo->valor_aberto)->toBe(300.0);
    expect(abertoParcialPorBaixas($titulo))->toBe(300.0);
    expect(abertoParcialPeloCore($tx))->toBe(300.0);
    expect($titulo->status)->toBe('parcial');
    expect($titulo->tipo)->toBe('pagar');
});

it('UC-CRAB-03 o relatório --aberto lista aberto atual → correto e não grava nada', function () {
    $tx = abertoParcialTx($this->bizId, $this->userId, $this->locationId, 'sell', 1000.00);
    abertoParcialPagar($tx, $this->userId, 400.00);
    $tx->refresh();
    $tx->payment_status = 'partial';
    $tx->save();

    // Retrato de um título gravado ANTES do fix: aberto de volta no bruto.
    $titulo = abertoParcialTitulo($tx);
    DB::table('fin_titulos')->where('id', $titulo->id)->update(['valor_aberto' => 1000.00, 'status' => 'aberto']);

    $rc = Artisan::call('financeiro:resync-from-core', ['--business' => $this->bizId, '--aberto' => true]);
    $saida = Artisan::output();

    expect($rc)->toBe(0);
    expect($saida)->toContain($titulo->numero);
    expect($saida)->toContain('1.000,00');
    expect($saida)->toContain('600,00');
    expect($saida)->toContain('aberto → parcial');
    expect($saida)->toContain('Nada foi gravado');

    // Só leitura: o título segue como estava.
    $depois = DB::table('fin_titulos')->where('id', $titulo->id)->first();
    expect((float) $depois->valor_aberto)->toBe(1000.0);
    expect($depois->status)->toBe('aberto');

    // E não existe --apply neste modo: corrigir os existentes é decisão [W].
    $rcApply = Artisan::call('financeiro:resync-from-core', ['--business' => $this->bizId, '--aberto' => true, '--apply' => true]);
    expect($rcApply)->toBe(1);
    expect((float) DB::table('fin_titulos')->where('id', $titulo->id)->value('valor_aberto'))->toBe(1000.0);
});

it('UC-CRAB-04 o relatório de um business não mostra título de outro', function () {
    $outro = (int) (DB::table('business')->where('id', '!=', $this->bizId)->orderBy('id')->value('id') ?? 0);
    if ($outro === 0) {
        $this->markTestSkipped('Só existe um business no banco de teste.');
    }

    $tx = abertoParcialTx($outro, $this->userId, null, 'sell', 777.00);
    abertoParcialPagar($tx, $this->userId, 77.00);
    $tx->refresh();
    $tx->payment_status = 'partial';
    $tx->save();
    $titulo = abertoParcialTitulo($tx);
    // Número único: a numeração R000001 é por business e pode se repetir entre eles.
    $marca = 'ZZ-OUTRO-'.Str::random(8);
    DB::table('fin_titulos')->where('id', $titulo->id)->update(['valor_aberto' => 777.00, 'numero' => $marca]);

    Artisan::call('financeiro:resync-from-core', ['--business' => $this->bizId, '--aberto' => true]);
    expect(Artisan::output())->not->toContain($marca);

    // Controle positivo: no business dono, o mesmo título aparece.
    Artisan::call('financeiro:resync-from-core', ['--business' => $outro, '--aberto' => true]);
    expect(Artisan::output())->toContain($marca);
});
