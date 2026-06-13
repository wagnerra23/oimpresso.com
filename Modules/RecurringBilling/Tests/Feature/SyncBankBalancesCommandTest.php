<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Jobs\SyncBankBalancesJob;

uses(Tests\TestCase::class);

/**
 * US-RB-045 · SyncBankBalancesCommand — wrapper artisan que dispara
 * SyncBankBalancesJob (cobertura própria do Job em SyncBankBalancesJobTest).
 *
 * Foco aqui: comando aceita opções, dispatcha Job (não roda lógica de cert/HTTP).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: dropa/recria fin_contas_bancarias (real-migrada) à mão — incompatível com MySQL persistente; quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    if (! Schema::hasTable('fin_contas_bancarias')) {
        Schema::create('fin_contas_bancarias', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('rb_gateway_credential_id')->nullable();
            $t->decimal('saldo_cached', 22, 4)->nullable();
            $t->timestamp('saldo_atualizado_em')->nullable();
            $t->string('tipo_conta', 50)->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
    }
});

afterEach(function () {
    // fin_contas_bancarias é real-migrada; o afterEach roda mesmo em teste pulado (PHPUnit
    // 12: tearDown gated só por hasMetRequirements), então dropá-la no MySQL persistente
    // corromperia testes irmãos do módulo. DDL só em sqlite.
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('fin_contas_bancarias');
    }
});

it('rb:sync-bank-balances aborta com FAILURE se fin_contas_bancarias ausente', function () {
    Schema::dropIfExists('fin_contas_bancarias');

    $exitCode = $this->artisan('rb:sync-bank-balances')->run();

    expect($exitCode)->toBe(1);
});

it('rb:sync-bank-balances --sync sem --conta sincroniza todas (chama Job sync sem dispatch)', function () {
    Bus::fake();

    $this->artisan('rb:sync-bank-balances --sync')
        ->expectsOutputToContain('Todas contas')
        ->assertSuccessful();

    // --sync chama handle() direto, NÃO dispatch
    Bus::assertNotDispatched(SyncBankBalancesJob::class);
});

it('rb:sync-bank-balances sem --sync dispatcha Job na fila', function () {
    Bus::fake();

    $this->artisan('rb:sync-bank-balances')
        ->expectsOutputToContain('dispatched')
        ->assertSuccessful();

    Bus::assertDispatched(SyncBankBalancesJob::class, function (SyncBankBalancesJob $job) {
        return true; // construtor sem args = sincroniza todas
    });
});

it('rb:sync-bank-balances --conta=42 dispatcha Job pra conta específica', function () {
    Bus::fake();

    $this->artisan('rb:sync-bank-balances --conta=42')
        ->expectsOutputToContain('Conta #42 dispatched')
        ->assertSuccessful();

    Bus::assertDispatched(SyncBankBalancesJob::class);
});
