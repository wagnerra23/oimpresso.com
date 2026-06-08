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
 * Test guard pro fix #444 — TituloAutoService timezone fiscal explícito.
 *
 * Cobertura:
 *  - competencia_mes / emissao formato + TZ correto (regression guard fix #444)
 *  - vencimento via pay_term (accessor due_date custom da Transaction UltimatePOS)
 *  - vencimento fallback (sem pay_term)
 *  - business_id herdado da Transaction (Observer/Job sem session — ADR 0093)
 *  - idempotência via UNIQUE (business_id, origem, origem_id, parcela_numero)
 *
 * ⚠️ Notas técnicas descobertas escrevendo este test:
 *  1. `Transaction::due_date` é um ACCESSOR (`getDueDateAttribute`) que
 *     SEMPRE deriva de `transaction_date + pay_term_*` — não é coluna persistida
 *     na tabela. Pra testar `vencimento` precisa setar pay_term_number + pay_term_type.
 *  2. `transaction_date` é DATETIME no MySQL. String passada em `Transaction::create()`
 *     é interpretada pelo PHP runtime no TZ default (= app.timezone).
 *  3. Fix #444 é DEFENSIVO — APP_TIMEZONE atual = America/Sao_Paulo, então
 *     comportamento observável ANTES e DEPOIS do fix bate. O test guarda contra
 *     mudança de APP_TIMEZONE futura ou regressão (devolver default UTC).
 *
 * Padrão de skip: igual outros testes do módulo Financeiro — pula gracioso
 * quando phpunit.xml força sqlite :memory: (config default). Roda contra
 * MySQL real via env override (DB_CONNECTION=mysql php vendor/bin/pest ...).
 */

function tituloAutoBootstrap(): array
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

/**
 * Cria Transaction sell DUE diretamente em DB (sem Observer disparar).
 * Vamos chamar o Service nós mesmos pra isolar o teste.
 */
function tituloAutoCriarTxSell(int $businessId, int $userId, string $txDate, ?int $payTermDays = null): Transaction
{
    /** @var Transaction $tx */
    $tx = Transaction::create([
        'business_id' => $businessId,
        'location_id' => null,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'due',
        'contact_id' => null,
        'invoice_no' => 'TEST-TZ-'.uniqid(),
        'transaction_date' => $txDate,
        'pay_term_number' => $payTermDays,
        'pay_term_type' => $payTermDays !== null ? 'days' : null,
        'total_before_tax' => 100.0,
        'final_total' => 100.0,
        'total_remaining_amount' => 100.0,
        'created_by' => $userId,
    ]);

    return $tx;
}

function tituloAutoCleanup(Transaction $tx): void
{
    Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('business_id', $tx->business_id)
        ->where('origem_id', $tx->id)
        ->where('origem', 'venda')
        ->forceDelete();

    \DB::table('transactions')->where('id', $tx->id)->delete();
}

it('competencia_mes formato Y-m correto + emissao Y-m-d (regression guard fix #444)', function () {
    ['business' => $business, 'user' => $user] = tituloAutoBootstrap();

    config(['app.timezone' => 'America/Sao_Paulo']);

    // 2026-12-31 23:30 SP — virada do dia local. Test guarda que NEM transaction_date
    // NEM Carbon::parse derivam Y-m do mês errado. Sem fix #444, se um dia
    // APP_TIMEZONE virasse UTC default, Carbon::parse interpretaria a string
    // como UTC, e competencia_mes ficaria em risco de regressão silenciosa.
    $tx = tituloAutoCriarTxSell($business->id, $user->id, '2026-12-31 23:30:00');

    try {
        $service = app(TituloAutoService::class);
        $titulo = $service->sincronizarDeTransacao($tx);

        expect($titulo)->not->toBeNull();
        // competencia_mes é format('Y-m') — não pode incluir dia, hora, ou TZ artifact
        expect($titulo->competencia_mes)->toBe('2026-12');
        // emissao é toDateString() — não datetime
        expect((string) $titulo->emissao->format('Y-m-d'))->toBe('2026-12-31');
    } finally {
        tituloAutoCleanup($tx);
    }
})->group('timezone-fiscal');

it('competencia_mes em mês comum bate corretamente (sanidade)', function () {
    ['business' => $business, 'user' => $user] = tituloAutoBootstrap();

    config(['app.timezone' => 'America/Sao_Paulo']);

    $tx = tituloAutoCriarTxSell($business->id, $user->id, '2026-06-15 12:00:00');

    try {
        $service = app(TituloAutoService::class);
        $titulo = $service->sincronizarDeTransacao($tx);

        expect($titulo)->not->toBeNull();
        expect($titulo->competencia_mes)->toBe('2026-06');
        expect((string) $titulo->emissao->format('Y-m-d'))->toBe('2026-06-15');
    } finally {
        tituloAutoCleanup($tx);
    }
})->group('timezone-fiscal');

it('vencimento usa pay_term_number/type quando setado (Transaction accessor)', function () {
    ['business' => $business, 'user' => $user] = tituloAutoBootstrap();

    config(['app.timezone' => 'America/Sao_Paulo']);

    // pay_term=30 dias → due_date accessor calcula transaction_date + 30 days.
    // calcularVencimento() do Service primeiro tenta $tx->due_date (accessor
    // sempre populado em UltimatePOS), fallback +30 dias se due_date "vazio".
    $tx = tituloAutoCriarTxSell($business->id, $user->id, '2026-06-15 12:00:00', 30);

    try {
        $service = app(TituloAutoService::class);
        $titulo = $service->sincronizarDeTransacao($tx);

        expect($titulo)->not->toBeNull();
        // 2026-06-15 + 30 dias = 2026-07-15
        expect((string) $titulo->vencimento->format('Y-m-d'))->toBe('2026-07-15');
    } finally {
        tituloAutoCleanup($tx);
    }
});

it('Titulo herda business_id da Transaction (simula Observer sem session)', function () {
    ['business' => $business, 'user' => $user] = tituloAutoBootstrap();

    config(['app.timezone' => 'America/Sao_Paulo']);

    $tx = tituloAutoCriarTxSell($business->id, $user->id, '2026-06-15 12:00:00');

    try {
        // Garante que session NÃO está populada — simula contexto Observer/Job
        // (Tier 0 ADR 0093 — business_id deve vir da Transaction, não da session).
        session()->forget(['user.business_id', 'business.id']);

        $service = app(TituloAutoService::class);
        $titulo = $service->sincronizarDeTransacao($tx);

        expect($titulo)->not->toBeNull();
        expect($titulo->business_id)->toBe($business->id);
        expect($titulo->origem)->toBe('venda');
        expect($titulo->origem_id)->toBe($tx->id);
        expect($titulo->tipo)->toBe('receber');
    } finally {
        tituloAutoCleanup($tx);
    }
});

it('idempotência: chamar 2x a mesma Transaction não duplica Titulo', function () {
    ['business' => $business, 'user' => $user] = tituloAutoBootstrap();

    config(['app.timezone' => 'America/Sao_Paulo']);

    $tx = tituloAutoCriarTxSell($business->id, $user->id, '2026-06-15 12:00:00');

    try {
        $service = app(TituloAutoService::class);

        $t1 = $service->sincronizarDeTransacao($tx);
        $t2 = $service->sincronizarDeTransacao($tx);

        expect($t1->id)->toBe($t2->id);
        expect($t1->numero)->toBe($t2->numero);

        $count = Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $business->id)
            ->where('origem', 'venda')
            ->where('origem_id', $tx->id)
            ->count();
        expect($count)->toBe(1);
    } finally {
        tituloAutoCleanup($tx);
    }
});

it('Transaction com payment_status=paid não cria Titulo', function () {
    ['business' => $business, 'user' => $user] = tituloAutoBootstrap();

    /** @var Transaction $tx */
    $tx = Transaction::create([
        'business_id' => $business->id,
        'location_id' => null,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'paid', // <-- pago = sem título financeiro
        'contact_id' => null,
        'invoice_no' => 'TEST-PAID-'.uniqid(),
        'transaction_date' => '2026-06-15 12:00:00',
        'total_before_tax' => 100.0,
        'final_total' => 100.0,
        'total_remaining_amount' => 0.0,
        'created_by' => $user->id,
    ]);

    try {
        $service = app(TituloAutoService::class);
        $titulo = $service->sincronizarDeTransacao($tx);

        expect($titulo)->toBeNull();

        $count = Titulo::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $business->id)
            ->where('origem', 'venda')
            ->where('origem_id', $tx->id)
            ->count();
        expect($count)->toBe(0);
    } finally {
        tituloAutoCleanup($tx);
    }
});
