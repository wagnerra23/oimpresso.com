<?php

declare(strict_types=1);

use App\Transaction;
use App\TransactionPayment;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;

uses(Tests\TestCase::class);

/**
 * P-154 (Sabor Brasil) — venda bonificada não cria título a receber.
 *
 * Contrato entre as partes do pedido: a venda bonificada tem `transactions.sub_type = 'bonificacao'`.
 * O `TransactionObserver` chama `TituloAutoService::sincronizarDeTransacao`; para a bonificação ele
 * não cria `fin_titulos`, e a venda que vira bonificação depois tem o título cancelado (nunca delete).
 * Pagamento lançado numa bonificação não gera baixa nem entrada no caixa.
 *
 * Regra mestre de valor: cada caso confere por dois caminhos — a contagem de `fin_titulos` da venda
 * e a soma do `valor_aberto` a receber do tenant, antes e depois. Tenant 98 (ADR 0358), adversário 99;
 * tudo dentro de transação desfeita no fim (`fin_titulos` não aceita delete).
 *
 * Casos: UC-FBON-01 · UC-FBON-02 · UC-FBON-03 · UC-FBON-04 · UC-FBON-05
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

function bonifTx(int $biz, int $userId, ?int $locationId, string $type, float $total, ?string $subType = null): Transaction
{
    return Transaction::create([
        'business_id' => $biz,
        'location_id' => $locationId,
        'type' => $type,
        'sub_type' => $subType,
        'status' => $type === 'purchase' ? 'received' : 'final',
        'payment_status' => 'due',
        'contact_id' => null,
        'invoice_no' => 'ZZ-P154-'.Str::random(8),
        'transaction_date' => '2031-03-10 10:00:00',
        'total_before_tax' => $total,
        'final_total' => $total,
        'created_by' => $userId,
    ]);
}

/** Caminho (a): títulos da transação, cancelados incluídos. */
function bonifTitulos(Transaction $tx)
{
    return Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('business_id', $tx->business_id)
        ->where('origem', $tx->type === 'purchase' ? 'compra' : 'venda')
        ->where('origem_id', $tx->id)
        ->get();
}

/** Caminho (b): total a receber em aberto do business. */
function bonifAReceber(int $biz): float
{
    return round((float) DB::table('fin_titulos')
        ->where('business_id', $biz)
        ->where('tipo', 'receber')
        ->where('status', '!=', 'cancelado')
        ->whereNull('deleted_at')
        ->sum('valor_aberto'), 2);
}

it('UC-FBON-01 venda bonificada de 1.000,00 não cria título; a venda comum de 1.000,00 cria', function () {
    $antes = bonifAReceber($this->bizId);

    $comum = bonifTx($this->bizId, $this->userId, $this->locationId, 'sell', 1000.00);
    $depoisComum = bonifAReceber($this->bizId);

    $bonif = bonifTx($this->bizId, $this->userId, $this->locationId, 'sell', 1000.00, 'bonificacao');
    $depoisBonif = bonifAReceber($this->bizId);

    $tComum = bonifTitulos($comum);
    expect($tComum)->toHaveCount(1);
    expect((float) $tComum->first()->valor_total)->toBe(1000.0);
    expect($tComum->first()->status)->toBe('aberto');
    expect(round($depoisComum - $antes, 2))->toBe(1000.0);

    expect(bonifTitulos($bonif))->toHaveCount(0);
    expect(round($depoisBonif - $depoisComum, 2))->toBe(0.0);

    // Re-sincronizar (ex.: mudar o vencimento) segue sem título.
    $bonif->due_date = '2031-04-10';
    $bonif->save();
    expect(bonifTitulos($bonif))->toHaveCount(0);
});

it('UC-FBON-02 venda que vira bonificação tem o título cancelado, não apagado', function () {
    $tx = bonifTx($this->bizId, $this->userId, $this->locationId, 'sell', 640.00);
    $titulo = bonifTitulos($tx)->first();
    expect($titulo)->not->toBeNull();
    $antes = bonifAReceber($this->bizId);

    $tx->sub_type = 'bonificacao';
    $tx->save();

    $depois = bonifTitulos($tx);
    expect($depois)->toHaveCount(1);
    expect($depois->first()->id)->toBe($titulo->id);
    expect($depois->first()->status)->toBe('cancelado');
    expect(round($antes - bonifAReceber($this->bizId), 2))->toBe(640.0);
});

it('UC-FBON-03 pagamento numa bonificação não gera baixa nem entrada no caixa', function () {
    $tx = bonifTx($this->bizId, $this->userId, $this->locationId, 'sell', 300.00, 'bonificacao');
    $caixaAntes = (int) DB::table('fin_caixa_movimentos')->where('business_id', $this->bizId)->count();

    $tp = TransactionPayment::create([
        'transaction_id' => $tx->id,
        'business_id' => $tx->business_id,
        'amount' => 300.00,
        'method' => 'cash',
        'paid_on' => '2031-03-11 10:00:00',
        'created_by' => $this->userId,
    ]);

    expect(DB::table('fin_titulo_baixas')->where('transaction_payment_id', $tp->id)->count())->toBe(0);
    expect((int) DB::table('fin_caixa_movimentos')->where('business_id', $this->bizId)->count())->toBe($caixaAntes);
    expect(bonifTitulos($tx))->toHaveCount(0);
});

it('UC-FBON-04 compra e venda de reparo seguem criando título como antes', function () {
    $compra = bonifTx($this->bizId, $this->userId, $this->locationId, 'purchase', 500.00);
    $reparo = bonifTx($this->bizId, $this->userId, $this->locationId, 'sell', 250.00, 'repair');

    $tCompra = bonifTitulos($compra);
    expect($tCompra)->toHaveCount(1);
    expect($tCompra->first()->tipo)->toBe('pagar');
    expect((float) $tCompra->first()->valor_total)->toBe(500.0);

    $tReparo = bonifTitulos($reparo);
    expect($tReparo)->toHaveCount(1);
    expect($tReparo->first()->tipo)->toBe('receber');
    expect((float) $tReparo->first()->valor_aberto)->toBe(250.0);
});

it('UC-FBON-05 bonificação de uma empresa não mexe no título da outra', function () {
    $outro = (int) $this->seededSupportClientTenant()->id;
    expect($outro)->not->toBe($this->bizId);

    $txOutro = bonifTx($outro, $this->userId, null, 'sell', 410.00);
    $tituloOutro = bonifTitulos($txOutro)->first();
    expect($tituloOutro)->not->toBeNull();
    $aReceberOutro = bonifAReceber($outro);

    $txAqui = bonifTx($this->bizId, $this->userId, $this->locationId, 'sell', 410.00);
    $txAqui->sub_type = 'bonificacao';
    $txAqui->save();

    expect(bonifTitulos($txAqui)->first()->status)->toBe('cancelado');
    expect(bonifTitulos($txOutro)->first()->status)->toBe('aberto');
    expect(bonifAReceber($outro))->toBe($aReceberOutro);
});
