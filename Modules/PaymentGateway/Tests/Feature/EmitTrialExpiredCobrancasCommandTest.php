<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;

uses(Tests\TestCase::class);

/**
 * Pest — paymentgateway:emit-trial-expired (Onda 5.B cron daily).
 *
 * Validações de contrato (sem mock real do BCB driver — apenas pré-condições
 * + skips). Cobertura mais profunda quer chega quando smoke real Wagner
 * (clientes piloto) confirma fluxo end-to-end.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL completo.');
    }
    if (!Schema::hasTable('subscriptions') || !Schema::hasTable('payment_gateway_credentials') || !Schema::hasTable('fin_contas_bancarias')) {
        $this->markTestSkipped('Schema PaymentGateway / Financeiro / Superadmin ausente.');
    }
});

it('falha (exit 1) quando NÃO há credencial BCB ativa em biz=1', function () {
    // Garante limpeza: sem credencial bcb_pix ativa
    DB::table('payment_gateway_credentials')
        ->where('business_id', 1)
        ->where('driver', 'bcb_pix')
        ->update(['ativo' => false]);

    $this->artisan('paymentgateway:emit-trial-expired', ['--dry-run' => true])
        ->expectsOutput('Sem credencial BCB ativa em biz=1. Cadastre em /paymentgateway/credenciais.')
        ->assertExitCode(1);
});

it('dry-run lista candidatas sem emitir', function () {
    // Pré-condição: precisa credencial + conta cadastradas. Skip se não estiver.
    $temCredencial = DB::table('payment_gateway_credentials')
        ->where('business_id', 1)
        ->where('driver', 'bcb_pix')
        ->where('ativo', true)
        ->exists();

    if (!$temCredencial) {
        $this->markTestSkipped('Pré-condição: credencial BCB ativa em biz=1 não cadastrada (Wagner manual em prod).');
    }

    $package = Package::create([
        'name' => 'Pkg Trial Test', 'description' => '...',
        'location_count' => 0, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0,
        'interval' => 'months', 'interval_count' => 1, 'trial_days' => 1,
        'price' => 99.90, 'is_active' => 1, 'sort_order' => 999, 'is_private' => 0, 'is_one_time' => 0,
    ]);

    $sub = Subscription::create([
        'business_id' => 9999, 'package_id' => $package->id,
        'paid_via' => 'paymentgateway_pix_automatico', 'payment_transaction_id' => null,
        'start_date' => null, 'end_date' => null,
        'trial_end_date' => now()->subDays(1), 'status' => 'waiting',
        'package_price' => 99.90, 'package_details' => ['name' => 'Pkg Trial Test'],
        'created_id' => 1,
    ]);

    $this->artisan('paymentgateway:emit-trial-expired', ['--dry-run' => true])
        ->assertExitCode(0);

    // Subscription não foi alterada
    $sub->refresh();
    expect($sub->payment_transaction_id)->toBeNull();

    $sub->forceDelete();
    $package->forceDelete();
});

it('skip Subscription com cobrança ativa neste mês', function () {
    $temCredencial = DB::table('payment_gateway_credentials')
        ->where('business_id', 1)
        ->where('driver', 'bcb_pix')
        ->where('ativo', true)
        ->exists();

    if (!$temCredencial) {
        $this->markTestSkipped('Pré-condição: credencial BCB ativa em biz=1 não cadastrada.');
    }

    $package = Package::create([
        'name' => 'Pkg Trial Skip', 'description' => '...',
        'location_count' => 0, 'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0,
        'interval' => 'months', 'interval_count' => 1, 'trial_days' => 1,
        'price' => 99.90, 'is_active' => 1, 'sort_order' => 999, 'is_private' => 0, 'is_one_time' => 0,
    ]);

    $sub = Subscription::create([
        'business_id' => 9998, 'package_id' => $package->id,
        'paid_via' => 'paymentgateway_pix_automatico', 'payment_transaction_id' => null,
        'start_date' => null, 'end_date' => null,
        'trial_end_date' => now()->subDays(1), 'status' => 'waiting',
        'package_price' => 99.90, 'package_details' => ['name' => 'Pkg Trial Skip'],
        'created_id' => 1,
    ]);

    // Cobrança já existente este mês
    $cobranca = Cobranca::create([
        'business_id' => 1,
        'tipo' => 'pix_recv', 'status' => 'emitida',
        'valor_centavos' => 9990,
        'vencimento' => now()->addDays(7)->toDateString(),
        'descricao' => 'Mensalidade SaaS — already emitted',
        'idempotency_key' => 'test-already-' . uniqid(),
        'origem_type' => 'subscription_license',
        'origem_id' => $sub->id,
    ]);

    $this->artisan('paymentgateway:emit-trial-expired', ['--dry-run' => true])
        ->expectsOutputToContain('já tem cobrança ativa este mês — skip')
        ->assertExitCode(0);

    Cobranca::withoutGlobalScopes()->where('id', $cobranca->id)->forceDelete();
    $sub->forceDelete();
    $package->forceDelete();
});
