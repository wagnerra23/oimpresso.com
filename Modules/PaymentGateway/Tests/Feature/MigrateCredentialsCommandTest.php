<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Onda 2.5 — ADR 0170.
 *
 * Testa o comando `paymentgateway:migrate-credentials` em 3 dimensões:
 *   (1) DRY-RUN não persiste nada
 *   (2) APPLY popula payment_gateway_credentials + vincula fin_contas_bancarias
 *   (3) 2x APPLY idempotente (UNIQUE composta + skip existing)
 *
 * Princípio ADR 0101: business_id=1, nunca cliente real.
 */

beforeEach(function () {
    // Seed legacy table — só se ela existir (RB instalado).
    if (! Schema::hasTable('rb_boleto_credentials')) {
        $this->markTestSkipped('rb_boleto_credentials ausente — RB não instalado neste env.');
    }

    DB::table('rb_boleto_credentials')->insert([
        [
            'business_id'       => 1,
            'banco'             => 'inter',
            'ambiente'          => 'production',
            'ativo'             => true,
            'config_json'       => json_encode(['token' => 'fake-inter']),
            'nome_display'      => 'Inter Prod',
            'conta_bancaria_id' => null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ],
        [
            'business_id'       => 1,
            'banco'             => 'asaas',
            'ambiente'          => 'sandbox',
            'ativo'             => true,
            'config_json'       => json_encode(['api_key' => 'fake-asaas']),
            'nome_display'      => 'Asaas SB',
            'conta_bancaria_id' => null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ],
    ]);

    session(['business.id' => 1]);
});

it('DRY-RUN não persiste em payment_gateway_credentials', function () {
    expect(PaymentGatewayCredential::query()->withoutGlobalScopes()->count())->toBe(0);

    $this->artisan('paymentgateway:migrate-credentials')
        ->assertSuccessful();

    expect(PaymentGatewayCredential::query()->withoutGlobalScopes()->count())
        ->toBe(0); // dry-run não cria
});

it('APPLY popula payment_gateway_credentials preservando business_id + mapping banco→gateway_key', function () {
    $this->artisan('paymentgateway:migrate-credentials', ['--apply' => true])
        ->assertSuccessful();

    $rows = PaymentGatewayCredential::query()->withoutGlobalScopes()->get();
    expect($rows->count())->toBe(2);

    $inter = $rows->firstWhere('gateway_key', 'inter');
    expect($inter)->not->toBeNull();
    expect($inter->business_id)->toBe(1);
    expect($inter->ambiente)->toBe('production');
    expect($inter->ativo)->toBeTrue();
    expect($inter->config_json)->toMatchArray(['token' => 'fake-inter']);

    $asaas = $rows->firstWhere('gateway_key', 'asaas');
    expect($asaas)->not->toBeNull();
    expect($asaas->ambiente)->toBe('sandbox');
});

it('2x APPLY é idempotente — não duplica credenciais', function () {
    $this->artisan('paymentgateway:migrate-credentials', ['--apply' => true])
        ->assertSuccessful();
    expect(PaymentGatewayCredential::query()->withoutGlobalScopes()->count())->toBe(2);

    // Segunda chamada deve detectar existência via UNIQUE composta.
    $this->artisan('paymentgateway:migrate-credentials', ['--apply' => true])
        ->assertSuccessful();
    expect(PaymentGatewayCredential::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('--business restringe escopo do backfill', function () {
    // Inserir credencial em biz=99 também
    DB::table('rb_boleto_credentials')->insert([
        'business_id'  => 99,
        'banco'        => 'c6',
        'ambiente'     => 'production',
        'ativo'        => true,
        'config_json'  => json_encode(['key' => 'biz99']),
        'nome_display' => 'C6 biz99',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    $this->artisan('paymentgateway:migrate-credentials', [
        '--business' => 1,
        '--apply'    => true,
    ])->assertSuccessful();

    // biz=99 NÃO deve ter sido tocado
    $biz99Count = PaymentGatewayCredential::query()
        ->withoutGlobalScopes()
        ->where('business_id', 99)
        ->count();
    expect($biz99Count)->toBe(0);

    // biz=1 tem as 2 credenciais
    $biz1Count = PaymentGatewayCredential::query()
        ->withoutGlobalScopes()
        ->where('business_id', 1)
        ->count();
    expect($biz1Count)->toBe(2);
});

it('vincula fin_contas_bancarias.payment_gateway_credential_id se conta_bancaria_id presente', function () {
    if (! Schema::hasTable('fin_contas_bancarias')) {
        $this->markTestSkipped('fin_contas_bancarias ausente — Financeiro não instalado neste env.');
    }

    // fin_contas_bancarias nunca teve coluna `nome`: a migration de criação guarda só os dados
    // CNAB e aponta 1-1 para accounts (accounts.name é o nome). A fixture segue o schema real —
    // mesma forma de Financeiro/Tests/Feature/BackfillExtratoOfxTest.
    $accountId = DB::table('accounts')->insertGetId([
        'business_id'    => 1,
        'name'           => 'Conta Teste',
        'account_number' => '777',
        'created_by'     => 1,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);
    $contaId = DB::table('fin_contas_bancarias')->insertGetId([
        'business_id'                   => 1,
        'account_id'                    => $accountId,
        'banco_codigo'                  => '077',
        'agencia'                       => '0001',
        'carteira'                      => '112',
        'beneficiario_documento'        => '00.000.000/0000-00', // pii-allowlist — fixture de teste
        'beneficiario_razao_social'     => 'Conta Teste',
        'created_at'                    => now(),
        'updated_at'                    => now(),
        'payment_gateway_credential_id' => null,
    ]);

    // Refazer seed legacy com FK pra essa conta
    DB::table('rb_boleto_credentials')->where('business_id', 1)->delete();
    DB::table('rb_boleto_credentials')->insert([
        'business_id'       => 1,
        'banco'             => 'inter',
        'ambiente'          => 'production',
        'ativo'             => true,
        'config_json'       => json_encode(['token' => 'x']),
        'nome_display'      => 'Inter+Conta',
        'conta_bancaria_id' => $contaId,
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    $this->artisan('paymentgateway:migrate-credentials', ['--apply' => true])
        ->assertSuccessful();

    $contaAfter = DB::table('fin_contas_bancarias')->where('id', $contaId)->first();
    expect($contaAfter->payment_gateway_credential_id)->not->toBeNull();
});
