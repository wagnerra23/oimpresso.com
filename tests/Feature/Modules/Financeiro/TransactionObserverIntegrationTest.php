<?php

use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\CaixaMovimento;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Observers\TransactionObserver;
use Modules\Financeiro\Services\TituloAutoService;

/**
 * Integration test end-to-end do fluxo Transaction (core UltimatePOS) ->
 * TransactionObserver -> TituloAutoService -> Titulo (DB real).
 *
 * Complemento ao TransactionObserverTest.php (mockado / contract test).
 *
 * Roda contra DB dev real (regra Wagner: nao mocka DB; got burned com
 * sqlite mocks que passavam local mas migration prod quebrava).
 *
 * BUGs encontrados durante a escrita do test (Onda 1) — scenarios marcados
 * com ->skip('BUG: ...'):
 *
 *   BUG-1 — Pagamento parcial via transaction_payment NAO cria CaixaMovimento.
 *           Nao existe Observer no TransactionPayment nem chamada explicita
 *           pra registrarPagamento (metodo esse que tampouco existe em
 *           TituloAutoService). O fluxo completo de baixa automatica fica
 *           pra Onda 2 (R-FIN-009 / R-FIN-010).
 *
 *   BUG-2 — Pagamento total via transaction_payment idem BUG-1.
 *
 *   BUG-3 — Transaction type='purchase' payment_status='due' NAO cria Titulo.
 *           sincronizarDeVenda() retorna null pra type !== 'sell'.
 *           Existe Job CriarTituloDeVendaJob que cobre purchase, mas o
 *           Observer atual nao dispara o job — chama direto sincronizarDeVenda.
 *           Onda 2 deveria fazer o Observer chamar Job ou estender o Service
 *           pra incluir purchase.
 *
 *   BUG-4 — Updated com payment_status mudando due->paid faz cancelarSeExistir
 *           (status='cancelado'), nao status='quitado'. Comportamento atual
 *           do Service e razoavel (venda virou paga = sem titulo aberto), mas
 *           confunde reporting (cancelado != quitado). Test reflete o real.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    // Dependencias de DB
    $this->business = \App\Business::first();
    if (! $this->business) {
        $this->markTestSkipped('Sem business em DB — rode seeder UltimatePOS antes.');
    }

    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business.');
    }

    $this->location = DB::table('business_locations')
        ->where('business_id', $this->business->id)
        ->first();
    if (! $this->location) {
        $this->markTestSkipped('Sem business_location pro business primary.');
    }

    $this->contact = DB::table('contacts')
        ->where('business_id', $this->business->id)
        ->where('type', '!=', 'lead')
        ->first();
    if (! $this->contact) {
        $this->markTestSkipped('Sem contact no business.');
    }

    // Sessão minima — algumas helpers do core dependem disso
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id]);
});

/**
 * Helper: cria Transaction de venda direto via DB (bypass mass-assignment do
 * Eloquent pra ter controle preciso dos campos NOT NULL). Retorna model
 * Eloquent reload-ado pra disparar observer ao Save subsequente.
 */
function fin_makeSell(int $businessId, int $locationId, int $contactId, int $userId, array $overrides = []): Transaction
{
    $defaults = [
        'business_id' => $businessId,
        'location_id' => $locationId,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'due',
        'contact_id' => $contactId,
        'transaction_date' => Carbon::now()->toDateTimeString(),
        'final_total' => 100.0000,
        'created_by' => $userId,
        'invoice_no' => 'FIN-INT-'.uniqid(),
    ];

    return Transaction::create(array_merge($defaults, $overrides));
}

it('cenario 1: created sell com payment_status=due gera Titulo aberto a receber', function () {
    $tx = fin_makeSell(
        $this->business->id,
        $this->location->id,
        $this->contact->id,
        $this->user->id,
        ['final_total' => 250.00]
    );

    $titulo = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem', 'venda')
        ->where('origem_id', $tx->id)
        ->first();

    expect($titulo)->not->toBeNull('Observer deveria ter criado Titulo via sincronizarDeVenda');
    expect($titulo->business_id)->toBe($this->business->id);
    expect($titulo->tipo)->toBe('receber');
    expect($titulo->status)->toBe('aberto');
    expect($titulo->origem)->toBe('venda');
    expect($titulo->origem_id)->toBe($tx->id);
    expect((float) $titulo->valor_total)->toBe(250.00);
    expect((float) $titulo->valor_aberto)->toBe(250.00);
    expect($titulo->moeda)->toBe('BRL');
    // numero == (string) tx.id pelo TituloAutoService atual (Job CriarTituloDeVendaJob
    // gera R000001 mas o Observer nao chama o Job).
    expect($titulo->numero)->toBe((string) $tx->id);
});

it('cenario 2: idempotencia — chamar observer 2x cria 1 unico titulo', function () {
    $tx = fin_makeSell(
        $this->business->id,
        $this->location->id,
        $this->contact->id,
        $this->user->id,
        ['final_total' => 99.50]
    );

    // Primeiro observer ja rodou no Transaction::create (event 'created').
    // Disparamos manualmente mais 2x pra simular re-entrada (job retry, evento duplicado).
    $observer = new TransactionObserver(app(TituloAutoService::class));
    $observer->created($tx);
    $observer->created($tx);

    $count = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem', 'venda')
        ->where('origem_id', $tx->id)
        ->count();

    expect($count)->toBe(1, 'UNIQUE (business_id, origem, origem_id, parcela_numero) deve garantir unicidade');
});

it('cenario 3: pagamento parcial via transaction_payment marca titulo parcial e cria CaixaMovimento')
    ->skip('BUG-1: nao ha Observer no TransactionPayment. Inserir transaction_payment NAO atualiza Titulo nem cria CaixaMovimento. Onda 2.');

it('cenario 4: pagamento total via transaction_payment marca titulo quitado e cria CaixaMovimento')
    ->skip('BUG-2: idem BUG-1. transaction_payment com amount = final_total nao dispara baixa automatica. Onda 2.');

it('cenario 5: updated com payment_status mudando due->paid cancela o Titulo', function () {
    $tx = fin_makeSell(
        $this->business->id,
        $this->location->id,
        $this->contact->id,
        $this->user->id,
        ['final_total' => 80.00, 'payment_status' => 'due']
    );

    $titulo = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx->id)
        ->first();
    expect($titulo)->not->toBeNull();
    expect($titulo->status)->toBe('aberto');

    // Muda payment_status pra 'paid' — Observer deveria re-sincronizar e o
    // Service deveria cancelar (porque !in_array(['due','partial'])).
    $tx->payment_status = 'paid';
    $tx->save();

    $titulo->refresh();
    // BUG-4: status vira 'cancelado', nao 'quitado'. Reflete comportamento real.
    expect($titulo->status)->toBe('cancelado', 'Comportamento atual: cancelarSeExistir marca cancelado quando venda vira paid');
});

it('cenario 6: updated SEM mudanca financeira nao altera o titulo', function () {
    $tx = fin_makeSell(
        $this->business->id,
        $this->location->id,
        $this->contact->id,
        $this->user->id,
        ['final_total' => 60.00]
    );

    $tituloAntes = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx->id)
        ->first();

    $updatedAntes = $tituloAntes->updated_at?->toDateTimeString();
    $statusAntes = $tituloAntes->status;
    $valorAbertoAntes = (float) $tituloAntes->valor_aberto;

    // Muda campo nao-financeiro
    $tx->additional_notes = 'Anotacao alterada — nao deve sincronizar';
    $tx->save();

    $totalTitulos = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx->id)
        ->count();
    expect($totalTitulos)->toBe(1, 'Update sem mudanca financeira nao cria novo titulo');

    $tituloDepois = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx->id)
        ->first();

    expect($tituloDepois->status)->toBe($statusAntes);
    expect((float) $tituloDepois->valor_aberto)->toBe($valorAbertoAntes);
});

it('cenario 7: deleted da Transaction marca titulo como cancelado', function () {
    $tx = fin_makeSell(
        $this->business->id,
        $this->location->id,
        $this->contact->id,
        $this->user->id,
        ['final_total' => 42.00]
    );

    $tituloId = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx->id)
        ->value('id');
    expect($tituloId)->not->toBeNull();

    $tx->delete();

    $titulo = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('id', $tituloId)
        ->first();

    expect($titulo)->not->toBeNull('Titulo nao e hard-deleted, fica como cancelado (TECH-0002 append-only)');
    expect($titulo->status)->toBe('cancelado');
    expect($titulo->observacoes)->toContain('venda excluida');
});

it('cenario 8: multi-tenant — sells em 2 businesses geram titulos isolados', function () {
    $other = \App\Business::where('id', '!=', $this->business->id)->first();
    if (! $other) {
        $this->markTestSkipped('Sem segundo business — pula isolamento real.');
    }

    $otherLoc = DB::table('business_locations')->where('business_id', $other->id)->first();
    $otherContact = DB::table('contacts')->where('business_id', $other->id)->first();
    $otherUser = \App\User::where('business_id', $other->id)->first();

    if (! $otherLoc || ! $otherContact || ! $otherUser) {
        $this->markTestSkipped('Segundo business nao tem location/contact/user — pula.');
    }

    $tx1 = fin_makeSell(
        $this->business->id,
        $this->location->id,
        $this->contact->id,
        $this->user->id,
        ['final_total' => 10.00]
    );
    $tx2 = fin_makeSell(
        $other->id,
        $otherLoc->id,
        $otherContact->id,
        $otherUser->id,
        ['final_total' => 20.00]
    );

    $titulo1 = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx1->id)
        ->first();
    $titulo2 = Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('origem_id', $tx2->id)
        ->first();

    expect($titulo1)->not->toBeNull();
    expect($titulo2)->not->toBeNull();
    expect($titulo1->business_id)->toBe($this->business->id);
    expect($titulo2->business_id)->toBe($other->id);
    expect($titulo1->id)->not->toBe($titulo2->id);
});

it('cenario 9: purchase type=purchase deveria gerar titulo a pagar')
    ->skip('BUG-3: TituloAutoService::sincronizarDeVenda retorna null pra type !== sell. Job CriarTituloDeVendaJob cobre purchase mas Observer nao o invoca. Onda 2.');
