<?php

use App\Transaction;
use App\TransactionPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\CaixaMovimento;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * Integration test do TransactionPaymentObserver — fix BUG-1 da Onda 2.
 *
 * Cenários cobertos:
 *   - created cria TituloBaixa + CaixaMovimento + atualiza Titulo
 *   - pagamentos múltiplos somam corretamente o valor_aberto
 *   - valor_aberto = 0 marca status='quitado'
 *   - updated com mudança de amount estorna anterior + cria nova
 *   - deleted estorna baixa via row negativa (append-only)
 *   - idempotência: 2 created do mesmo TP cria 1 só baixa
 *   - multi-tenant: BusinessScope respeitado
 *
 * Roda contra DB real (DatabaseTransactions) — alinhado a regra Wagner.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->business = \App\Business::first();
    if (! $this->business) {
        $this->markTestSkipped('Sem business em DB.');
    }

    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    $this->location = DB::table('business_locations')
        ->where('business_id', $this->business->id)
        ->first();
    if (! $this->location) {
        $this->markTestSkipped('Sem business_location.');
    }

    $this->contact = DB::table('contacts')
        ->where('business_id', $this->business->id)
        ->where('type', '!=', 'lead')
        ->first();
    if (! $this->contact) {
        $this->markTestSkipped('Sem contact.');
    }

    $this->conta = ContaBancaria::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('business_id', $this->business->id)
        ->first();
    if (! $this->conta) {
        $this->markTestSkipped('Sem fin_contas_bancarias — rode FinanceiroDatabaseSeeder.');
    }

    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id]);
});

function fin_makeSellForBaixa(int $businessId, int $locationId, int $contactId, int $userId, float $total): Transaction
{
    return Transaction::create([
        'business_id' => $businessId,
        'location_id' => $locationId,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'due',
        'contact_id' => $contactId,
        'transaction_date' => Carbon::now()->toDateTimeString(),
        'final_total' => $total,
        'total_remaining_amount' => $total,
        'created_by' => $userId,
        'invoice_no' => 'FIN-TPO-' . uniqid(),
    ]);
}

it('created cria TituloBaixa + atualiza Titulo + cria CaixaMovimento', function () {
    $tx = fin_makeSellForBaixa($this->business->id, $this->location->id, $this->contact->id, $this->user->id, 100.00);

    $tp = TransactionPayment::create([
        'transaction_id' => $tx->id,
        'business_id' => $tx->business_id,
        'amount' => 30.00,
        'method' => 'cash',
        'paid_on' => Carbon::now()->toDateTimeString(),
        'created_by' => $this->user->id,
        'payment_for' => $tx->contact_id,
    ]);

    $titulo = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx->id)
        ->first();

    expect($titulo)->not->toBeNull();
    expect($titulo->status)->toBe('parcial');
    expect((float) $titulo->valor_aberto)->toBe(70.00);

    $baixa = TituloBaixa::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('transaction_payment_id', $tp->id)
        ->first();
    expect($baixa)->not->toBeNull();
    expect((float) $baixa->valor_baixa)->toBe(30.00);

    $mov = CaixaMovimento::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_tipo', 'titulo_baixa')
        ->where('origem_id', $baixa->id)
        ->first();
    expect($mov)->not->toBeNull();
    expect($mov->tipo)->toBe('entrada');
});

it('pagamentos multiplos somam corretamente o valor_aberto', function () {
    $tx = fin_makeSellForBaixa($this->business->id, $this->location->id, $this->contact->id, $this->user->id, 500.00);

    TransactionPayment::create([
        'transaction_id' => $tx->id,
        'business_id' => $tx->business_id,
        'amount' => 100.00,
        'method' => 'cash',
        'paid_on' => Carbon::now()->toDateTimeString(),
        'created_by' => $this->user->id,
        'payment_for' => $tx->contact_id,
    ]);

    TransactionPayment::create([
        'transaction_id' => $tx->id,
        'business_id' => $tx->business_id,
        'amount' => 200.00,
        'method' => 'bank_transfer',
        'paid_on' => Carbon::now()->toDateTimeString(),
        'created_by' => $this->user->id,
        'payment_for' => $tx->contact_id,
    ]);

    $titulo = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx->id)
        ->first();

    expect($titulo)->not->toBeNull();
    expect($titulo->status)->toBe('parcial');
    expect((float) $titulo->valor_aberto)->toBe(200.00, '500 - 100 - 200 = 200 em aberto');

    $totalBaixas = TituloBaixa::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('titulo_id', $titulo->id)
        ->count();
    expect($totalBaixas)->toBe(2);
});

it('valor_aberto chega a 0 marca Titulo status=quitado', function () {
    $tx = fin_makeSellForBaixa($this->business->id, $this->location->id, $this->contact->id, $this->user->id, 75.00);

    TransactionPayment::create([
        'transaction_id' => $tx->id,
        'business_id' => $tx->business_id,
        'amount' => 75.00,
        'method' => 'cash',
        'paid_on' => Carbon::now()->toDateTimeString(),
        'created_by' => $this->user->id,
        'payment_for' => $tx->contact_id,
    ]);

    $titulo = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx->id)
        ->first();
    expect($titulo->status)->toBe('quitado');
    expect((float) $titulo->valor_aberto)->toBe(0.0);
});

it('updated com mudanca de amount estorna anterior e cria baixa nova', function () {
    $tx = fin_makeSellForBaixa($this->business->id, $this->location->id, $this->contact->id, $this->user->id, 200.00);

    $tp = TransactionPayment::create([
        'transaction_id' => $tx->id,
        'business_id' => $tx->business_id,
        'amount' => 50.00,
        'method' => 'cash',
        'paid_on' => Carbon::now()->toDateTimeString(),
        'created_by' => $this->user->id,
        'payment_for' => $tx->contact_id,
    ]);

    $titulo = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx->id)
        ->first();
    expect((float) $titulo->valor_aberto)->toBe(150.00);

    // Atualiza pra 80
    $tp->amount = 80.00;
    $tp->save();

    $titulo->refresh();
    expect((float) $titulo->valor_aberto)->toBe(120.00, '200 - 80 = 120 (anterior estornado, novo aplicado)');

    // Deve haver: baixa original + estorno + nova = 3 rows
    $todasBaixas = TituloBaixa::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('titulo_id', $titulo->id)
        ->count();
    expect($todasBaixas)->toBeGreaterThanOrEqual(3, 'Deve ter original + estorno + nova');

    // Soma líquida = 80
    $somaLiquida = (float) TituloBaixa::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('titulo_id', $titulo->id)
        ->sum('valor_baixa');
    expect($somaLiquida)->toBe(80.00);
});

it('deleted estorna baixa via row negativa (append-only)', function () {
    $tx = fin_makeSellForBaixa($this->business->id, $this->location->id, $this->contact->id, $this->user->id, 100.00);

    $tp = TransactionPayment::create([
        'transaction_id' => $tx->id,
        'business_id' => $tx->business_id,
        'amount' => 100.00,
        'method' => 'cash',
        'paid_on' => Carbon::now()->toDateTimeString(),
        'created_by' => $this->user->id,
        'payment_for' => $tx->contact_id,
    ]);

    $titulo = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx->id)
        ->first();
    expect($titulo->status)->toBe('quitado');

    $baixaOriginalId = TituloBaixa::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('transaction_payment_id', $tp->id)
        ->whereNull('estorno_de_id')
        ->value('id');
    expect($baixaOriginalId)->not->toBeNull();

    $tpId = $tp->id;
    $tp->delete();

    $estorno = TituloBaixa::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('estorno_de_id', $baixaOriginalId)
        ->first();
    expect($estorno)->not->toBeNull('Deve ter row de estorno após delete');
    expect((float) $estorno->valor_baixa)->toBe(-100.00);

    $titulo->refresh();
    expect($titulo->status)->toBe('aberto', 'Após estorno volta pra aberto');
    expect((float) $titulo->valor_aberto)->toBe(100.00);

    // Movimento de estorno deve existir
    $movEstorno = CaixaMovimento::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_tipo', 'titulo_baixa')
        ->where('origem_id', $estorno->id)
        ->first();
    expect($movEstorno)->not->toBeNull();
    expect($movEstorno->tipo)->toBe('ajuste');
});

it('idempotencia: 2 created do mesmo TransactionPayment cria 1 so baixa', function () {
    $tx = fin_makeSellForBaixa($this->business->id, $this->location->id, $this->contact->id, $this->user->id, 100.00);

    $tp = TransactionPayment::create([
        'transaction_id' => $tx->id,
        'business_id' => $tx->business_id,
        'amount' => 40.00,
        'method' => 'cash',
        'paid_on' => Carbon::now()->toDateTimeString(),
        'created_by' => $this->user->id,
        'payment_for' => $tx->contact_id,
    ]);

    // Re-fire manual do Observer
    $observer = new \Modules\Financeiro\Observers\TransactionPaymentObserver(
        app(\Modules\Financeiro\Services\TituloAutoService::class)
    );
    $observer->created($tp);
    $observer->created($tp);

    $count = TituloBaixa::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('transaction_payment_id', $tp->id)
        ->whereNull('estorno_de_id')
        ->count();
    expect($count)->toBe(1, 'Idempotency_key tp_<id> deve garantir unicidade');

    $titulo = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx->id)
        ->first();
    expect((float) $titulo->valor_aberto)->toBe(60.00, 'valor_aberto consistente mesmo com re-fires');
});

it('multi-tenant: TransactionPaymentObserver respeita BusinessScope', function () {
    // Prefere business com fin_contas_bancarias já configurada.
    $otherBizIds = ContaBancaria::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('business_id', '!=', $this->business->id)
        ->pluck('business_id')
        ->unique()
        ->values()
        ->toArray();

    $other = null;
    foreach ($otherBizIds as $bid) {
        $cand = \App\Business::find($bid);
        if (! $cand) continue;
        $hasLoc = DB::table('business_locations')->where('business_id', $bid)->exists();
        $hasContact = DB::table('contacts')->where('business_id', $bid)->where('type', '!=', 'lead')->exists();
        $hasUser = \App\User::where('business_id', $bid)->exists();
        if ($hasLoc && $hasContact && $hasUser) {
            $other = $cand;
            break;
        }
    }

    if (! $other) {
        $this->markTestSkipped('Sem segundo business com deps completas + fin_contas_bancarias.');
    }

    $otherLoc = DB::table('business_locations')->where('business_id', $other->id)->first();
    $otherContact = DB::table('contacts')->where('business_id', $other->id)->where('type', '!=', 'lead')->first();
    $otherUser = \App\User::where('business_id', $other->id)->first();
    $otherConta = ContaBancaria::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('business_id', $other->id)
        ->first();

    $tx1 = fin_makeSellForBaixa($this->business->id, $this->location->id, $this->contact->id, $this->user->id, 100.00);
    $tx2 = fin_makeSellForBaixa($other->id, $otherLoc->id, $otherContact->id, $otherUser->id, 200.00);

    $tp1 = TransactionPayment::create([
        'transaction_id' => $tx1->id,
        'business_id' => $tx1->business_id,
        'amount' => 100.00,
        'method' => 'cash',
        'paid_on' => Carbon::now()->toDateTimeString(),
        'created_by' => $this->user->id,
        'payment_for' => $tx1->contact_id,
    ]);

    $tp2 = TransactionPayment::create([
        'transaction_id' => $tx2->id,
        'business_id' => $tx2->business_id,
        'amount' => 200.00,
        'method' => 'cash',
        'paid_on' => Carbon::now()->toDateTimeString(),
        'created_by' => $otherUser->id,
        'payment_for' => $tx2->contact_id,
    ]);

    $baixa1 = TituloBaixa::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('transaction_payment_id', $tp1->id)
        ->first();
    $baixa2 = TituloBaixa::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('transaction_payment_id', $tp2->id)
        ->first();

    expect($baixa1)->not->toBeNull();
    expect($baixa2)->not->toBeNull();
    expect($baixa1->business_id)->toBe($this->business->id);
    expect($baixa2->business_id)->toBe($other->id);
    expect($baixa1->id)->not->toBe($baixa2->id);
});
