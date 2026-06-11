<?php

declare(strict_types=1);

use App\Transaction;
use App\TransactionPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Financeiro\Models\CaixaMovimento;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * CU-3→CU-5 — o ENCADEAMENTO de retenção (worklist TRAVA-SEGUNDA Martinho).
 *
 * "O fluxo de retenção é o encadeamento CU-3→CU-4→CU-5 (vende→fatura→recebe)."
 * Este E2E prova o CORAÇÃO da Kamila (financeiro + venda) ponta-a-ponta contra
 * DB real (canon Wagner não-mocka-DB): a venda gera título a receber +30d e o
 * recebimento baixa o título — o "vende→fatura→recebe" inteiro num teste só.
 *
 * Falha alto se o Observer/título/baixa quebrar antes do balcão.
 *
 * Fiscal (CU-4): a EMISSÃO NF-e/NFS-e com stub SEFAZ já é coberta pelas suítes
 * dos módulos (NfeBrasil/NFSe Tests). Aqui só asseguramos que os ENDPOINTS que a
 * tela de venda dispara (wire-up Onda 2) EXISTEM — não reduplicamos SEFAZ.
 *
 * @see tests/Feature/Modules/Financeiro/TransactionObserverIntegrationTest.php
 * @see Modules/NfeBrasil/Http/Controllers/NfeEmissaoController.php (emitir)
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }

    $this->business = \App\Business::query()->orderBy('id')->first();
    if (! $this->business) {
        $this->markTestSkipped('Sem business em DB — rode o seeder UltimatePOS.');
    }
    $this->user = \App\User::where('business_id', $this->business->id)->first();
    $this->location = DB::table('business_locations')->where('business_id', $this->business->id)->first();
    $this->contact = DB::table('contacts')
        ->where('business_id', $this->business->id)
        ->where('type', '!=', 'lead')
        ->first();
    if (! $this->user || ! $this->location || ! $this->contact) {
        $this->markTestSkipped('business sem user/location/contact — rode o seed base.');
    }

    session([
        'user.business_id' => $this->business->id,
        'user.id'          => $this->user->id,
        'business.id'      => $this->business->id,
    ]);
});

function tsMakeSell(int $businessId, int $locationId, int $contactId, int $userId, array $overrides = []): Transaction
{
    $defaults = [
        'business_id'      => $businessId,
        'location_id'      => $locationId,
        'type'             => 'sell',
        'status'           => 'final',
        'payment_status'   => 'due',
        'contact_id'       => $contactId,
        'transaction_date' => Carbon::now()->toDateTimeString(),
        'final_total'      => 100.0000,
        'created_by'       => $userId,
        'invoice_no'       => 'TS-E2E-'.uniqid(),
    ];

    return Transaction::create(array_merge($defaults, $overrides));
}

it('UC-F01 · CU-3→CU-5: venda a prazo gera título a receber com vencimento +30d (valor correto)', function () {
    $hoje = Carbon::now();
    $tx = tsMakeSell(
        $this->business->id,
        $this->location->id,
        $this->contact->id,
        $this->user->id,
        [
            'final_total'     => 1250.00,
            'transaction_date'=> $hoje->toDateTimeString(),
            'pay_term_number' => 30,        // a prazo 30 dias (determinístico — CU-5)
            'pay_term_type'   => 'days',
        ],
    );

    $titulo = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem', 'venda')
        ->where('origem_id', $tx->id)
        ->first();

    expect($titulo)->not->toBeNull('a venda deveria gerar título a receber (CU-5)');
    expect($titulo->tipo)->toBe('receber');
    expect($titulo->status)->toBe('aberto');
    expect((float) $titulo->valor_total)->toBe(1250.00);
    expect((float) $titulo->valor_aberto)->toBe(1250.00);

    // Vencimento a prazo 30 dias — compara data pura (robusto a tz/meia-noite).
    $diffDias = (int) abs(Carbon::parse($titulo->vencimento)->startOfDay()
        ->diffInDays(Carbon::parse($tx->transaction_date)->startOfDay()));
    expect($diffDias)->toBe(30, 'título vence 30 dias após a venda (CU-5)');
});

it('UC-F02 · CU-5: recebimento baixa o título (vende→fatura→recebe completo)', function () {
    $conta = \Modules\Financeiro\Models\ContaBancaria::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('business_id', $this->business->id)
        ->first();
    // ADR 0175: conta bancária é opcional — mas a baixa via TransactionPayment
    // depende do TransactionPaymentObserver, que já roda no DB real.

    $tx = tsMakeSell(
        $this->business->id,
        $this->location->id,
        $this->contact->id,
        $this->user->id,
        ['final_total' => 800.00, 'total_remaining_amount' => 800.00],
    );

    // Recebe o total → título quita.
    $tp = TransactionPayment::create([
        'transaction_id' => $tx->id,
        'business_id'    => $tx->business_id,
        'amount'         => 800.00,
        'method'         => 'bank_transfer',
        'paid_on'        => Carbon::now()->toDateTimeString(),
        'created_by'     => $this->user->id,
        'payment_for'    => $tx->contact_id,
    ]);

    $titulo = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx->id)
        ->where('origem', 'venda')
        ->first();

    expect($titulo)->not->toBeNull();
    expect($titulo->status)->toBe('quitado', 'recebimento total quita o título');
    expect((float) $titulo->valor_aberto)->toBe(0.0);

    $baixa = TituloBaixa::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('transaction_payment_id', $tp->id)
        ->first();
    expect($baixa)->not->toBeNull('o recebimento deveria gerar baixa do título');

    $mov = CaixaMovimento::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_tipo', 'titulo_baixa')
        ->where('origem_id', $baixa->id)
        ->first();
    expect($mov)->not->toBeNull('a baixa deveria registrar entrada no caixa');
    expect($mov->tipo)->toBe('entrada');
});

it('UC-F03 · CU-4: os endpoints fiscais que a tela de venda dispara existem (wire-up Onda 2)', function () {
    // O botão "Emitir NF-e" (VdNfeEmitModal) chama POST /nfe-brasil/transactions/{tx}/emitir.
    expect(Route::has('nfe-brasil.transactions.emitir') || collect(Route::getRoutes())->contains(
        fn ($r) => $r->uri() === 'nfe-brasil/transactions/{tx}/emitir' && in_array('POST', $r->methods(), true),
    ))->toBeTrue('endpoint de emissão NF-e a partir da venda deve existir');

    // O botão "Emitir NFS-e" (VdNfseEmitModal) chama POST /nfse/transactions/{tx}/emitir (endpoint Onda 2).
    expect(collect(Route::getRoutes())->contains(
        fn ($r) => $r->uri() === 'nfse/transactions/{tx}/emitir' && in_array('POST', $r->methods(), true),
    ))->toBeTrue('endpoint JSON de emissão NFS-e a partir da venda deve existir (adicionado nesta Onda)');
});
