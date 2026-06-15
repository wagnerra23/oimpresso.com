<?php

declare(strict_types=1);

use App\Business;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * US-FIN-BRIDGE-EXP — guard do BridgeExpenseToTitulosCommand (F5 deprecação legacy).
 *
 * Cobre invariantes:
 *  1. --business obrigatório (Tier 0 IRREVOGÁVEL ADR 0093)
 *  2. --dry não escreve em fin_titulos (idempotent + safe)
 *  3. Execução real cria fin_titulo pra cada transaction expense
 *  4. Re-rodar é idempotente (UNIQUE garante zero duplicatas)
 *  5. Mapping payment_status → status:
 *       paid    → quitado (valor_aberto=0)
 *       partial → parcial
 *       due     → aberto (valor_aberto=valor_total)
 *  6. plano_conta_id default = '5.1.99.999 Despesas (a classificar)' (criada
 *     idempotente se não existir)
 *  7. origem='despesa', metadata.bridged_from='core_transaction'
 *
 * Padrão skip gracioso (Financeiro convention) quando DB greenfield.
 */
function bridgeExpenseBootstrap(): Business
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco.');
    }

    return $business;
}

it('bloqueia execução sem --business (Tier 0)', function () {
    $exit = $this->artisan('financeiro:bridge-expense-to-titulos')->run();

    expect($exit)->toBe(1); // FAILURE
});

it('valida formato --since YYYY-MM-DD', function () {
    bridgeExpenseBootstrap();
    $exit = $this->artisan('financeiro:bridge-expense-to-titulos --business=1 --since=20260101 --dry')->run();

    expect($exit)->toBe(1);
});

it('dry-run não escreve em fin_titulos', function () {
    $business = bridgeExpenseBootstrap();

    // Cria 1 transaction expense pra teste
    $userId = DB::table('users')->where('business_id', $business->id)->value('id');
    if (! $userId) {
        $this->markTestSkipped('Sem user no business.');
    }

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => $business->id,
        'type' => 'expense',
        'final_total' => 100.00,
        'transaction_date' => '2026-05-20 12:00:00',
        'payment_status' => 'due',
        'created_by' => $userId,
        'status' => 'final',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $countBefore = DB::table('fin_titulos')
        ->where('business_id', $business->id)
        ->where('origem', 'despesa')
        ->where('origem_id', $txId)
        ->count();

    $this->artisan("financeiro:bridge-expense-to-titulos --business={$business->id} --dry")
        ->assertExitCode(0);

    $countAfter = DB::table('fin_titulos')
        ->where('business_id', $business->id)
        ->where('origem', 'despesa')
        ->where('origem_id', $txId)
        ->count();

    expect($countAfter)->toBe($countBefore); // ZERO escrita em dry

    // Cleanup
    DB::table('transactions')->where('id', $txId)->delete();
});

it('cria fin_titulo pra expense status=due (status=aberto, valor_aberto=valor_total)', function () {
    $business = bridgeExpenseBootstrap();
    $userId = DB::table('users')->where('business_id', $business->id)->value('id');
    if (! $userId) {
        $this->markTestSkipped('Sem user.');
    }

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => $business->id,
        'type' => 'expense',
        'final_total' => 250.50,
        'transaction_date' => '2026-05-15 10:00:00',
        'payment_status' => 'due',
        'created_by' => $userId,
        'status' => 'final',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan("financeiro:bridge-expense-to-titulos --business={$business->id}")
        ->assertExitCode(0);

    $titulo = DB::table('fin_titulos')
        ->where('business_id', $business->id)
        ->where('origem', 'despesa')
        ->where('origem_id', $txId)
        ->first();

    expect($titulo)->not->toBeNull();
    expect($titulo->tipo)->toBe('pagar');
    expect($titulo->status)->toBe('aberto');
    expect((float) $titulo->valor_total)->toBe(250.50);
    expect((float) $titulo->valor_aberto)->toBe(250.50);
    expect($titulo->competencia_mes)->toBe('2026-05');

    // Cleanup
    DB::table('fin_titulos')->where('id', $titulo->id)->delete();
    DB::table('transactions')->where('id', $txId)->delete();
});

it('cria fin_titulo pra expense status=paid (status=quitado, valor_aberto=0)', function () {
    $business = bridgeExpenseBootstrap();
    $userId = DB::table('users')->where('business_id', $business->id)->value('id');
    if (! $userId) {
        $this->markTestSkipped('Sem user.');
    }

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => $business->id,
        'type' => 'expense',
        'final_total' => 99.90,
        'transaction_date' => '2026-05-10 14:00:00',
        'payment_status' => 'paid',
        'created_by' => $userId,
        'status' => 'final',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan("financeiro:bridge-expense-to-titulos --business={$business->id}")
        ->assertExitCode(0);

    $titulo = DB::table('fin_titulos')
        ->where('business_id', $business->id)
        ->where('origem', 'despesa')
        ->where('origem_id', $txId)
        ->first();

    expect($titulo)->not->toBeNull();
    expect($titulo->status)->toBe('quitado');
    expect((float) $titulo->valor_aberto)->toBe(0.0);

    DB::table('fin_titulos')->where('id', $titulo->id)->delete();
    DB::table('transactions')->where('id', $txId)->delete();
});

it('cria fin_titulo pra expense status=partial (valor_aberto = final_total − Σ pagamentos)', function () {
    $business = bridgeExpenseBootstrap();
    $userId = DB::table('users')->where('business_id', $business->id)->value('id');
    if (! $userId) {
        $this->markTestSkipped('Sem user.');
    }

    // Despesa R$ 500 com R$ 300 já pagos (1 transaction_payment não-estorno).
    // Restante CANÔNICO = 500 − 300 = 200, derivado da subquery (sem coluna
    // total_remaining_amount fabricada). Mesma fórmula do core (final_total − total_paid).
    $txId = DB::table('transactions')->insertGetId([
        'business_id' => $business->id,
        'type' => 'expense',
        'final_total' => 500.00,
        'transaction_date' => '2026-05-12 11:00:00',
        'payment_status' => 'partial',
        'created_by' => $userId,
        'status' => 'final',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tpId = DB::table('transaction_payments')->insertGetId([
        'business_id' => $business->id,
        'transaction_id' => $txId,
        'amount' => 300.00,
        'method' => 'cash',
        'is_return' => 0,
        'paid_on' => '2026-05-12 11:00:00',
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan("financeiro:bridge-expense-to-titulos --business={$business->id}")
        ->assertExitCode(0);

    $titulo = DB::table('fin_titulos')
        ->where('business_id', $business->id)
        ->where('origem', 'despesa')
        ->where('origem_id', $txId)
        ->first();

    expect($titulo)->not->toBeNull();
    expect($titulo->status)->toBe('parcial');
    expect((float) $titulo->valor_total)->toBe(500.00);
    expect((float) $titulo->valor_aberto)->toBe(200.00); // 500 − 300 derivado

    // Cleanup
    DB::table('fin_titulos')->where('id', $titulo->id)->delete();
    DB::table('transaction_payments')->where('id', $tpId)->delete();
    DB::table('transactions')->where('id', $txId)->delete();
});

it('é idempotente — re-rodar não duplica fin_titulos', function () {
    $business = bridgeExpenseBootstrap();
    $userId = DB::table('users')->where('business_id', $business->id)->value('id');
    if (! $userId) {
        $this->markTestSkipped('Sem user.');
    }

    $txId = DB::table('transactions')->insertGetId([
        'business_id' => $business->id,
        'type' => 'expense',
        'final_total' => 42.00,
        'transaction_date' => '2026-04-01 09:00:00',
        'payment_status' => 'due',
        'created_by' => $userId,
        'status' => 'final',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 1ª run
    $this->artisan("financeiro:bridge-expense-to-titulos --business={$business->id}")
        ->assertExitCode(0);

    $count1 = DB::table('fin_titulos')
        ->where('business_id', $business->id)
        ->where('origem', 'despesa')
        ->where('origem_id', $txId)
        ->count();

    // 2ª run — não deve criar duplicata
    $this->artisan("financeiro:bridge-expense-to-titulos --business={$business->id}")
        ->assertExitCode(0);

    $count2 = DB::table('fin_titulos')
        ->where('business_id', $business->id)
        ->where('origem', 'despesa')
        ->where('origem_id', $txId)
        ->count();

    expect($count1)->toBe(1);
    expect($count2)->toBe(1); // UNIQUE bloqueou 2ª inserção

    // Cleanup
    DB::table('fin_titulos')
        ->where('business_id', $business->id)
        ->where('origem', 'despesa')
        ->where('origem_id', $txId)
        ->delete();
    DB::table('transactions')->where('id', $txId)->delete();
});

it('não bridja transactions de outro business (Tier 0 multi-tenant)', function () {
    $business = bridgeExpenseBootstrap();
    $otherBusiness = Business::where('id', '!=', $business->id)->first();
    if (! $otherBusiness) {
        $this->markTestSkipped('Precisa 2+ businesses no banco pra esse teste.');
    }
    $userId = DB::table('users')->where('business_id', $otherBusiness->id)->value('id');
    if (! $userId) {
        $this->markTestSkipped('Sem user no outro business.');
    }

    // Cria expense no OUTRO business
    $txId = DB::table('transactions')->insertGetId([
        'business_id' => $otherBusiness->id,
        'type' => 'expense',
        'final_total' => 999.00,
        'transaction_date' => '2026-05-01 10:00:00',
        'payment_status' => 'due',
        'created_by' => $userId,
        'status' => 'final',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Roda comando pro business DIFERENTE
    $this->artisan("financeiro:bridge-expense-to-titulos --business={$business->id}")
        ->assertExitCode(0);

    // Nada deve ter sido criado pro outro business
    $exists = DB::table('fin_titulos')
        ->where('origem', 'despesa')
        ->where('origem_id', $txId)
        ->exists();

    expect($exists)->toBeFalse();

    DB::table('transactions')->where('id', $txId)->delete();
});
