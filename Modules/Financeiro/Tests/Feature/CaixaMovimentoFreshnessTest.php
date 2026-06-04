<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// US-FIN-053 Batch 2: era RefreshDatabase (migrate:fresh) — incompatível com o
// schema baseline (dropa FK + limpa o biz=1 seedado, envenenando a lane). Agora
// DatabaseTransactions (rollback por teste, sem re-migrate) + skip-guard quando o
// schema/biz não estão presentes (lanes SQLite sem migrate).
uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Regressao do check caixa_movimento_freshness (FinanceiroHealthCommand Wave 25 D9 #10).
 *
 * Carbon 3: diffInDays() e signed por padrao. $lastDate (max created_at) e sempre no
 * passado, entao now()->diffInDays($lastDate) sem flag volta NEGATIVO -> WARN >7 nunca
 * dispara. Fix: (int) now()->diffInDays($lastDate, true). Familia do audit Metodo 9.75
 * (sibling do aging-bucket B6 / Titulo #2050).
 *
 * @see Modules/Financeiro/Console/Commands/FinanceiroHealthCommand.php (checkCaixaMovimentoFreshness)
 */
beforeEach(function () {
    if (! Schema::hasTable('fin_caixa_movimentos') || ! DB::table('business')->where('id', 1)->exists()) {
        test()->markTestSkipped('Precisa schema MySQL + biz=1 seedado (lane Financeiro · MySQL).');
    }
    Carbon::setTestNow(Carbon::create(2026, 5, 31, 12, 0, 0));
});
afterEach(fn () => Carbon::setTestNow());

function finCaixaFreshnessSeed(int $daysAgo): void
{
    $ts = now()->copy()->subDays($daysAgo);

    DB::table('fin_caixa_movimentos')->insert([
        'business_id'       => 1,
        'conta_bancaria_id' => null,
        'tipo'              => 'entrada',
        'valor'             => 100.0,
        'saldo_apos'        => 100.0,
        'data'              => $ts->toDateString(),
        'descricao'         => "Lancamento {$daysAgo}d",
        'created_by'        => 1,
        'created_at'        => $ts->toDateTimeString(),
    ]);
}

function finCaixaFreshnessCheck(): array
{
    $exit = Artisan::call('financeiro:health', ['--business' => 1, '--json' => true]);
    expect($exit)->toBe(0);

    $decoded = json_decode(Artisan::output(), true);
    expect($decoded)->toBeArray();

    $check = collect($decoded['checks'])->firstWhere('name', 'caixa_movimento_freshness');
    expect($check)->not->toBeNull();

    return $check;
}

it('3 dias: daysSince positivo (=3) e OK', function () {
    finCaixaFreshnessSeed(3);
    $c = finCaixaFreshnessCheck();
    expect($c['value'])->toBe(3);
    expect($c['status'])->toBe('OK');
});

it('7 dias: OK no limite (nao dispara)', function () {
    finCaixaFreshnessSeed(7);
    $c = finCaixaFreshnessCheck();
    expect($c['value'])->toBe(7);
    expect($c['status'])->toBe('OK');
});

it('10 dias: WARN (o bug silenciava)', function () {
    finCaixaFreshnessSeed(10);
    $c = finCaixaFreshnessCheck();
    expect($c['value'])->toBe(10);
    expect($c['status'])->toBe('WARN');
    expect($c['details'])->toContain('fluxo parado');
});

it('30 dias: WARN', function () {
    finCaixaFreshnessSeed(30);
    $c = finCaixaFreshnessCheck();
    expect($c['value'])->toBe(30);
    expect($c['status'])->toBe('WARN');
});

it('usa max(created_at) com varios lancamentos', function () {
    finCaixaFreshnessSeed(30);
    finCaixaFreshnessSeed(10);
    finCaixaFreshnessSeed(2);
    $c = finCaixaFreshnessCheck();
    expect($c['value'])->toBe(2);
    expect($c['status'])->toBe('OK');
});
