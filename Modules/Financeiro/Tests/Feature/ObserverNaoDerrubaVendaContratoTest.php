<?php

declare(strict_types=1);

use App\Transaction;
use App\TransactionPayment;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\TituloAutoService;

uses(Tests\TestCase::class);

/**
 * US-FIN-059 — o Financeiro é módulo opcional e não derruba a venda.
 *
 * Contrato (SPEC Financeiro, US-FIN-059, Acceptance 1): os observers de `Transaction` e
 * `TransactionPayment` envolvem o trabalho do `TituloAutoService` em try/catch que chama
 * `report()` e nunca propaga. Antes do conserto, uma exceção no espelho estourava o
 * `Transaction::create()` do core (o BUG-2 que bloqueou pagamentos).
 *
 * Cada caso de falha confere três coisas: a venda (ou o pagamento) foi gravada, o erro foi
 * reportado, e não sobrou título pela metade — o serviço falso grava o título de verdade e
 * só então explode, então um título sobrando seria o savepoint não desfeito.
 *
 * Regra mestre de valor: os casos sem falha provam que o título continua com o mesmo valor
 * por dois caminhos — o `valor_total` do título da venda e a soma do `valor_aberto` a
 * receber do tenant, antes e depois. Tenant 98 (ADR 0358); tudo dentro de transação
 * desfeita no fim (`fin_titulos` não aceita delete).
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

function fin059Tx(int $biz, int $userId, ?int $locationId, string $type, float $total): Transaction
{
    return Transaction::create([
        'business_id' => $biz,
        'location_id' => $locationId,
        'type' => $type,
        'status' => $type === 'purchase' ? 'received' : 'final',
        'payment_status' => 'due',
        'contact_id' => null,
        'invoice_no' => 'ZZ-FIN059-'.Str::random(8),
        'transaction_date' => '2031-05-10 10:00:00',
        'total_before_tax' => $total,
        'final_total' => $total,
        'created_by' => $userId,
    ]);
}

/** Caminho (a): títulos da transação, cancelados incluídos. */
function fin059Titulos(int $biz, int $txId, string $origem = 'venda')
{
    return Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('business_id', $biz)
        ->where('origem', $origem)
        ->where('origem_id', $txId)
        ->get();
}

/** Caminho (b): total em aberto do tipo pedido no business. */
function fin059Aberto(int $biz, string $tipo = 'receber'): float
{
    return round((float) DB::table('fin_titulos')
        ->where('business_id', $biz)
        ->where('tipo', $tipo)
        ->where('status', '!=', 'cancelado')
        ->whereNull('deleted_at')
        ->sum('valor_aberto'), 2);
}

/** Serviço que grava o título de verdade e explode logo depois. */
function fin059ServicoQueFalhaDepoisDeGravar(): void
{
    app()->instance(TituloAutoService::class, new class extends TituloAutoService {
        public function sincronizarDeTransacao(Transaction $tx): ?Titulo
        {
            parent::sincronizarDeTransacao($tx);

            throw new RuntimeException('falha forçada no espelho Financeiro (US-FIN-059)');
        }

        public function registrarPagamento(TransactionPayment $tp): ?\Modules\Financeiro\Models\TituloBaixa
        {
            throw new RuntimeException('falha forçada na baixa (US-FIN-059)');
        }
    });
}

it('US-FIN-059 venda de 1.234,56 cria título com o mesmo valor (sem falha)', function () {
    $antes = fin059Aberto($this->bizId);

    $tx = fin059Tx($this->bizId, $this->userId, $this->locationId, 'sell', 1234.56);

    $titulos = fin059Titulos($this->bizId, $tx->id);
    expect($titulos)->toHaveCount(1);
    expect((float) $titulos->first()->valor_total)->toBe(1234.56);
    expect((float) $titulos->first()->valor_aberto)->toBe(1234.56);
    expect(round(fin059Aberto($this->bizId) - $antes, 2))->toBe(1234.56);
});

it('US-FIN-059 compra de 789,10 cria título a pagar com o mesmo valor (sem falha)', function () {
    $antes = fin059Aberto($this->bizId, 'pagar');

    $tx = fin059Tx($this->bizId, $this->userId, $this->locationId, 'purchase', 789.10);

    $titulos = fin059Titulos($this->bizId, $tx->id, 'compra');
    expect($titulos)->toHaveCount(1);
    expect((float) $titulos->first()->valor_total)->toBe(789.1);
    expect(round(fin059Aberto($this->bizId, 'pagar') - $antes, 2))->toBe(789.1);
});

it('US-FIN-059 erro no Financeiro não derruba a venda, é reportado e não deixa título pela metade', function () {
    Exceptions::fake();
    fin059ServicoQueFalhaDepoisDeGravar();
    $antes = fin059Aberto($this->bizId);

    $tx = fin059Tx($this->bizId, $this->userId, $this->locationId, 'sell', 1234.56);

    expect($tx->exists)->toBeTrue();
    expect(DB::table('transactions')->where('id', $tx->id)->where('business_id', $this->bizId)->exists())->toBeTrue();
    Exceptions::assertReported(fn (RuntimeException $e) => str_contains($e->getMessage(), 'espelho Financeiro'));
    expect(fin059Titulos($this->bizId, $tx->id))->toHaveCount(0);
    expect(round(fin059Aberto($this->bizId) - $antes, 2))->toBe(0.0);
});

it('US-FIN-059 erro no Financeiro ao editar a venda não derruba o save', function () {
    $tx = fin059Tx($this->bizId, $this->userId, $this->locationId, 'sell', 500.00);
    expect(fin059Titulos($this->bizId, $tx->id))->toHaveCount(1);

    Exceptions::fake();
    fin059ServicoQueFalhaDepoisDeGravar();

    $tx->final_total = 650.00;
    $tx->total_before_tax = 650.00;
    $tx->save();

    expect((float) DB::table('transactions')->where('id', $tx->id)->value('final_total'))->toBe(650.0);
    Exceptions::assertReported(RuntimeException::class);
    // O savepoint desfez a re-sincronização: o título segue com o valor anterior.
    expect((float) fin059Titulos($this->bizId, $tx->id)->first()->valor_total)->toBe(500.0);
});

it('US-FIN-059 erro na baixa não derruba o pagamento e é reportado', function () {
    $tx = fin059Tx($this->bizId, $this->userId, $this->locationId, 'sell', 300.00);

    Exceptions::fake();
    fin059ServicoQueFalhaDepoisDeGravar();

    $tp = TransactionPayment::create([
        'transaction_id' => $tx->id,
        'business_id' => $tx->business_id,
        'amount' => 100.00,
        'method' => 'cash',
        'paid_on' => '2031-05-11 10:00:00',
        'created_by' => $this->userId,
    ]);

    expect(DB::table('transaction_payments')->where('id', $tp->id)->exists())->toBeTrue();
    Exceptions::assertReported(fn (RuntimeException $e) => str_contains($e->getMessage(), 'baixa'));
    expect(DB::table('fin_titulo_baixas')->where('transaction_payment_id', $tp->id)->count())->toBe(0);
});
