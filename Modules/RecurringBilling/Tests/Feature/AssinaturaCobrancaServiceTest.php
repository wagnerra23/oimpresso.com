<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Services\AssinaturaCobrancaService;
use Modules\RecurringBilling\Services\Boleto\BoletoService;

uses(Tests\TestCase::class);

/**
 * US-RB-042 Wave J — Smoke pest do AssinaturaCobrancaService.
 *
 * Sem RefreshDatabase: migrations legadas UltimatePOS usam ALTER TABLE MODIFY
 * COLUMN ENUM (sintaxe MySQL-only) e quebram em SQLite. Criamos só rb_invoices
 * manualmente.
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 (ADR 0101): testes usam business_id=1.
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite' && ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Smoke test rodado apenas em SQLite in-memory.');
    }

    Schema::dropIfExists('rb_invoices');
    Schema::create('rb_invoices', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->unsignedBigInteger('subscription_id')->nullable();
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('numero_documento')->nullable();
        $table->decimal('valor', 12, 2)->default(0);
        $table->string('status', 20)->default('open');
        $table->date('vencimento')->nullable();
        $table->dateTime('pago_em')->nullable();
        $table->string('gateway', 20)->nullable();
        $table->string('gateway_ref')->nullable();
        $table->unsignedBigInteger('conta_bancaria_id')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

afterEach(function () {
    Schema::dropIfExists('rb_invoices');
});

/**
 * Cenário 1 (idempotência): invoice já canceled → ok + skipped, sem chamar gateway.
 */
it('retorna idempotente quando invoice já canceled (skip gateway)', function () {
    $boletos = Mockery::mock(BoletoService::class);
    $boletos->shouldNotReceive('cancelar');

    $service = new AssinaturaCobrancaService($boletos);

    $invoice = Invoice::create([
        'business_id' => 1,
        'numero_documento' => 'INV-2026-0001',
        'valor' => 100.00,
        'status' => 'canceled',
        'gateway' => 'inter',
        'gateway_ref' => 'NOSSO123',
    ]);

    $result = $service->cancelInvoice(1, $invoice->id);

    expect($result['ok'])->toBeTrue();
    expect($result['skipped'] ?? null)->toBe('already_canceled');
    expect($result['gateway_call'])->toBeFalse();
});

/**
 * Cenário 2 (guard pago): invoice paga → 422 (use estorno).
 */
it('bloqueia cancelamento quando invoice paga e sugere estorno', function () {
    $boletos = Mockery::mock(BoletoService::class);
    $boletos->shouldNotReceive('cancelar');

    $service = new AssinaturaCobrancaService($boletos);

    $invoice = Invoice::create([
        'business_id' => 1,
        'numero_documento' => 'INV-2026-0002',
        'valor' => 250.00,
        'status' => 'paid',
        'pago_em' => now(),
        'gateway' => 'asaas',
        'gateway_ref' => 'pay_abc123',
    ]);

    $result = $service->cancelInvoice(1, $invoice->id);

    expect($result['ok'])->toBeFalse();
    expect($result['http_status'])->toBe(422);
    expect($result['error'])->toContain('estorno');
});

/**
 * Cenário 3 (happy path local-only): invoice open sem gateway_ref → cancela só local sem chamar gateway.
 */
it('cancela local quando invoice nunca foi enviada ao gateway', function () {
    $boletos = Mockery::mock(BoletoService::class);
    $boletos->shouldNotReceive('cancelar');

    $service = new AssinaturaCobrancaService($boletos);

    $invoice = Invoice::create([
        'business_id' => 1,
        'numero_documento' => 'INV-2026-0003',
        'valor' => 75.00,
        'status' => 'open',
        'gateway' => null,
        'gateway_ref' => null,
    ]);

    $result = $service->cancelInvoice(1, $invoice->id);

    expect($result['ok'])->toBeTrue();
    expect($result['gateway_call'])->toBeFalse();
    expect($result['invoice']->status)->toBe('canceled');
});
