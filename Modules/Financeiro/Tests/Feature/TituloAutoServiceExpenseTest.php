<?php

declare(strict_types=1);

use App\Business;
use App\Transaction;
use App\User;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\TituloAutoService;

uses(Tests\TestCase::class);

/**
 * Fase 5.5 deprecação legacy — TituloAutoService cobre type='expense'.
 *
 * Antes da Fase 5.5, TituloAutoService só cobria sell/purchase. Expenses NOVAS
 * criadas via UI legacy /expenses/create não geravam fin_titulo automaticamente
 * → Eliana abria expense e nada aparecia no Financeiro até alguém rodar
 * `php artisan financeiro:bridge-expense-to-titulos` (Fase 5 batch).
 *
 * Fase 5.5 (2026-05-21) estende o Service:
 *  - $tipo match() inclui 'expense' => 'pagar'
 *  - $origem match() inclui 'expense' => 'despesa'
 *  - cross-link prefix 'expense' => 'E' (#E-{txId})
 *  - Filosofia divergente: expense com payment_status='paid' GERA fin_titulo
 *    com status='quitado' (sell/purchase paga cancela título — pagou no caixa
 *    não vira pendência; expense paga PRECISA aparecer pra Eliana ver DRE).
 *  - Mesma regra do BridgeExpenseToTitulosCommand (F5) → consistência total.
 *
 * Cobertura:
 *  1. expense due → fin_titulo aberto, valor_aberto = final_total
 *  2. expense paid → fin_titulo quitado, valor_aberto = 0
 *  3. expense partial → fin_titulo parcial, valor_aberto = total_remaining_amount
 *  4. expense atualizada (final_total mudou) → fin_titulo atualizado (updateOrCreate)
 *  5. expense deletada → fin_titulo cancelado
 *  6. Tier 0 isolation: expense biz A não cria fin_titulo biz B
 *  7. Idempotência: chamar service 2x não duplica
 *
 * Padrão skip gracioso (Financeiro convention) quando DB greenfield.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules/Financeiro/Console/Commands/BridgeExpenseToTitulosCommand.php (F5)
 */

function expenseBootstrap(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    return ['business' => $business, 'user' => $user];
}

function expenseCriarTxDirect(int $businessId, int $userId, string $paymentStatus, float $total, ?float $remaining = null): Transaction
{
    /** @var Transaction $tx */
    $tx = Transaction::create([
        'business_id' => $businessId,
        'location_id' => null,
        'type' => 'expense',
        'status' => 'final',
        'payment_status' => $paymentStatus,
        'contact_id' => null,
        'invoice_no' => 'TEST-EXP-'.uniqid(),
        'transaction_date' => '2026-05-20 12:00:00',
        'total_before_tax' => $total,
        'final_total' => $total,
        'total_remaining_amount' => $remaining ?? ($paymentStatus === 'paid' ? 0.0 : $total),
        'created_by' => $userId,
    ]);

    return $tx;
}

function expenseCleanup(Transaction $tx): void
{
    Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('business_id', $tx->business_id)
        ->where('origem_id', $tx->id)
        ->where('origem', 'despesa')
        ->forceDelete();

    \DB::table('transactions')->where('id', $tx->id)->delete();
}

it('expense DUE gera fin_titulo aberto com valor_aberto=final_total', function () {
    ['business' => $business, 'user' => $user] = expenseBootstrap();

    config(['app.timezone' => 'America/Sao_Paulo']);

    $tx = expenseCriarTxDirect($business->id, $user->id, 'due', 250.00);

    try {
        $service = app(TituloAutoService::class);
        $titulo = $service->sincronizarDeTransacao($tx);

        expect($titulo)->not->toBeNull();
        expect($titulo->origem)->toBe('despesa');
        expect($titulo->tipo)->toBe('pagar');
        expect($titulo->status)->toBe('aberto');
        expect((float) $titulo->valor_total)->toBe(250.00);
        expect((float) $titulo->valor_aberto)->toBe(250.00);
        expect($titulo->business_id)->toBe($business->id);
        // Cross-link #E-{txId} (Fase 5.5 prefix)
        expect($titulo->cliente_descricao)->toContain('#E-'.$tx->id);
        // Numeração P (pagar)
        expect($titulo->numero)->toStartWith('P');
    } finally {
        expenseCleanup($tx);
    }
})->group('fase5.5-expense');

it('expense PAID gera fin_titulo quitado com valor_aberto=0 (divergência vs sell/purchase)', function () {
    ['business' => $business, 'user' => $user] = expenseBootstrap();

    config(['app.timezone' => 'America/Sao_Paulo']);

    $tx = expenseCriarTxDirect($business->id, $user->id, 'paid', 300.00);

    try {
        $service = app(TituloAutoService::class);
        $titulo = $service->sincronizarDeTransacao($tx);

        // Crítico: expense paid GERA título (sell/purchase paid cancela)
        expect($titulo)->not->toBeNull();
        expect($titulo->origem)->toBe('despesa');
        expect($titulo->status)->toBe('quitado');
        expect((float) $titulo->valor_total)->toBe(300.00);
        expect((float) $titulo->valor_aberto)->toBe(0.00);
    } finally {
        expenseCleanup($tx);
    }
})->group('fase5.5-expense');

it('expense PARTIAL gera fin_titulo parcial com valor_aberto=total_remaining_amount', function () {
    ['business' => $business, 'user' => $user] = expenseBootstrap();

    config(['app.timezone' => 'America/Sao_Paulo']);

    // 500 total, 200 remaining = 300 já pago
    $tx = expenseCriarTxDirect($business->id, $user->id, 'partial', 500.00, 200.00);

    try {
        $service = app(TituloAutoService::class);
        $titulo = $service->sincronizarDeTransacao($tx);

        expect($titulo)->not->toBeNull();
        expect($titulo->status)->toBe('parcial');
        expect((float) $titulo->valor_total)->toBe(500.00);
        expect((float) $titulo->valor_aberto)->toBe(200.00);
    } finally {
        expenseCleanup($tx);
    }
})->group('fase5.5-expense');

it('expense atualizada (final_total novo) atualiza fin_titulo (updateOrCreate)', function () {
    ['business' => $business, 'user' => $user] = expenseBootstrap();

    config(['app.timezone' => 'America/Sao_Paulo']);

    $tx = expenseCriarTxDirect($business->id, $user->id, 'due', 100.00);

    try {
        $service = app(TituloAutoService::class);
        $t1 = $service->sincronizarDeTransacao($tx);
        $numeroOriginal = $t1->numero;

        // Edita a transaction — final_total dobra
        $tx->final_total = 200.00;
        $tx->total_remaining_amount = 200.00;
        $tx->save();

        $t2 = $service->sincronizarDeTransacao($tx);

        // Mesmo título (idempotência por UNIQUE business_id, origem, origem_id, parcela_numero)
        expect($t2->id)->toBe($t1->id);
        // numero PRESERVADO (não regenera em updateOrCreate)
        expect($t2->numero)->toBe($numeroOriginal);
        // valores atualizados
        expect((float) $t2->valor_total)->toBe(200.00);
        expect((float) $t2->valor_aberto)->toBe(200.00);

        // Apenas 1 fin_titulo no banco
        $count = Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $business->id)
            ->where('origem', 'despesa')
            ->where('origem_id', $tx->id)
            ->count();
        expect($count)->toBe(1);
    } finally {
        expenseCleanup($tx);
    }
})->group('fase5.5-expense');

it('expense deletada cancela fin_titulo (não hard-delete, append-only TECH-0002)', function () {
    ['business' => $business, 'user' => $user] = expenseBootstrap();

    config(['app.timezone' => 'America/Sao_Paulo']);

    $tx = expenseCriarTxDirect($business->id, $user->id, 'due', 150.00);

    try {
        $service = app(TituloAutoService::class);
        $titulo = $service->sincronizarDeTransacao($tx);
        expect($titulo->status)->toBe('aberto');

        // Simula deleção
        $titulo2 = $service->cancelarSeExistir($tx, motivo: 'despesa excluída');

        expect($titulo2)->not->toBeNull();
        expect($titulo2->id)->toBe($titulo->id);
        expect($titulo2->status)->toBe('cancelado');
        expect($titulo2->observacoes)->toContain('despesa excluída');

        // Titulo continua existindo (não foi hard-deleted)
        $countAposCancel = Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $business->id)
            ->where('origem', 'despesa')
            ->where('origem_id', $tx->id)
            ->count();
        expect($countAposCancel)->toBe(1);
    } finally {
        expenseCleanup($tx);
    }
})->group('fase5.5-expense');

it('Tier 0 IRREVOGÁVEL: expense biz=A não cria fin_titulo em biz=B', function () {
    ['business' => $business, 'user' => $user] = expenseBootstrap();

    config(['app.timezone' => 'America/Sao_Paulo']);

    $tx = expenseCriarTxDirect($business->id, $user->id, 'due', 100.00);

    try {
        $service = app(TituloAutoService::class);
        $titulo = $service->sincronizarDeTransacao($tx);

        expect($titulo->business_id)->toBe($business->id);

        // Garante que NENHUM fin_titulo foi criado em outro business_id
        $countOutroBiz = Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', '!=', $business->id)
            ->where('origem', 'despesa')
            ->where('origem_id', $tx->id)
            ->count();
        expect($countOutroBiz)->toBe(0);
    } finally {
        expenseCleanup($tx);
    }
})->group('fase5.5-expense');

it('idempotência: chamar sincronizarDeTransacao 2x não duplica fin_titulo expense', function () {
    ['business' => $business, 'user' => $user] = expenseBootstrap();

    config(['app.timezone' => 'America/Sao_Paulo']);

    $tx = expenseCriarTxDirect($business->id, $user->id, 'due', 175.00);

    try {
        $service = app(TituloAutoService::class);

        $t1 = $service->sincronizarDeTransacao($tx);
        $t2 = $service->sincronizarDeTransacao($tx);

        expect($t1->id)->toBe($t2->id);
        expect($t1->numero)->toBe($t2->numero);

        $count = Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $business->id)
            ->where('origem', 'despesa')
            ->where('origem_id', $tx->id)
            ->count();
        expect($count)->toBe(1);
    } finally {
        expenseCleanup($tx);
    }
})->group('fase5.5-expense');
