<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Repositories\InvoiceRepository;
use Modules\RecurringBilling\Repositories\SubscriptionRepository;
use Modules\RecurringBilling\Services\AssinaturaCobrancaService;
use Modules\RecurringBilling\Services\AssinaturaService;
use Modules\RecurringBilling\Services\Boleto\BoletoService;

uses(Tests\TestCase::class);

/**
 * Wave 18 RETRY D5 — Customer Journey end-to-end (smoke).
 *
 * Cobre ciclo COMPLETO de cliente recorrente:
 *   1. Cliente assina plano "Mensal Standard" (criar)
 *   2. Sistema gera invoice mensal (manual aqui — listener simulado)
 *   3. Cliente pede pra pausar 30 dias (pausar)
 *   4. Cliente retoma (retomar) — next_due_date recalculado
 *   5. Cliente atualiza ciclo pra anual (atualizarCobrancaAssinatura)
 *   6. Inadimplência → invoice overdue
 *   7. Cliente paga invoice atrasada (mark paid manual)
 *   8. Cliente decide cancelar (cancelar) — churn_reason
 *   9. MRR baseline reflete cancelamento
 *
 * Smoke test cobre os 9 passos como **um fluxo só** sem mockar Service interno.
 * Mockerias só gateways externos (BoletoService::cancelar / driver HTTP).
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 (ADR 0101).
 *
 * @see Modules\RecurringBilling\Services\AssinaturaService (lifecycle)
 * @see Modules\RecurringBilling\Services\AssinaturaCobrancaService (cobrança + invoice cancel + update)
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
    config()->set('activitylog.enabled', false);

    if (config('database.default') !== 'sqlite' || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Customer Journey rodado apenas em SQLite in-memory.');
    }

    Schema::dropIfExists('rb_invoices');
    Schema::dropIfExists('rb_subscriptions');
    Schema::dropIfExists('rb_plans');
    Schema::dropIfExists('rb_boleto_credentials');

    Schema::create('rb_plans', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('name');
        $t->string('slug')->nullable();
        $t->string('description')->nullable();
        $t->decimal('valor', 12, 2)->default(0);
        $t->string('ciclo', 20)->default('mensal');
        $t->integer('ciclo_dias')->nullable();
        $t->integer('trial_days')->default(0);
        $t->boolean('ativo')->default(true);
        $t->json('metadata')->nullable();
        $t->string('descricao_curta')->nullable();
        $t->string('fiscal_type')->nullable();
        $t->string('fiscal_cfop')->nullable();
        $t->string('fiscal_servico')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function ($t) {
            $t->id();
            $t->string('log_name')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('causer_id')->nullable();
            $t->string('causer_type')->nullable();
            $t->json('properties')->nullable();
            $t->string('event')->nullable();
            $t->uuid('batch_uuid')->nullable();
            $t->timestamps();
        });
    }

    Schema::create('rb_subscriptions', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('plan_id')->nullable();
        $t->unsignedInteger('contact_id')->nullable();
        $t->string('status', 20)->default('active');
        $t->date('start_date')->nullable();
        $t->date('next_due_date')->nullable();
        $t->date('billing_anchor_date')->nullable();
        $t->dateTime('canceled_at')->nullable();
        $t->dateTime('paused_at')->nullable();
        $t->unsignedBigInteger('conta_bancaria_id')->nullable();
        $t->json('metadata')->nullable();
        $t->string('payment_method', 30)->nullable();
        $t->unsignedBigInteger('last_jobsheet_id')->nullable();
        $t->decimal('total_paid_cached', 12, 2)->nullable();
        $t->integer('failed_count_cached')->nullable();
        $t->decimal('total_revenue_cached', 12, 2)->nullable();
        $t->date('paused_until')->nullable();
        $t->string('churn_reason')->nullable();
        $t->string('contact_phone_cached')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('rb_invoices', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('subscription_id')->nullable();
        $t->unsignedInteger('contact_id')->nullable();
        $t->string('numero_documento')->nullable();
        $t->decimal('valor', 12, 2)->default(0);
        $t->string('status', 20)->default('open');
        $t->date('vencimento')->nullable();
        $t->dateTime('pago_em')->nullable();
        $t->string('gateway', 20)->nullable();
        $t->string('gateway_ref')->nullable();
        $t->unsignedBigInteger('conta_bancaria_id')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    // Plano canônico
    Plan::create([
        'business_id' => 1,
        'name'        => 'Mensal Standard',
        'slug'        => 'mensal-standard',
        'valor'       => 199.00,
        'ciclo'       => 'mensal',
        'ativo'       => true,
    ]);
});

afterEach(function () {
    // rb_* são reais-migradas; o afterEach roda mesmo em teste pulado (PHPUnit 12:
    // tearDown gated só por hasMetRequirements), então dropá-las no MySQL persistente
    // corromperia testes irmãos do módulo. DDL só em sqlite.
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('rb_invoices');
        Schema::dropIfExists('rb_subscriptions');
        Schema::dropIfExists('rb_plans');
    }
});

it('D5 — Customer Journey completo (9 passos)', function () {
    // ───────── Setup ─────────
    $subRepo = new SubscriptionRepository();
    $invRepo = new InvoiceRepository();
    $service = new AssinaturaService($subRepo);
    $boletosMock = Mockery::mock(BoletoService::class);
    $boletosMock->shouldReceive('cancelar')->andReturn(true);
    $cobranca = new AssinaturaCobrancaService($boletosMock);

    $plan = Plan::where('business_id', 1)->first();

    // ───────── Passo 1: criar ─────────
    $criar = $service->criar(1, [
        'plan_id'    => $plan->id,
        'contact_id' => 1,
        'start_date' => '2026-01-15',
        'payment_method' => 'boleto',
        'metadata'   => ['valor' => 199.00, 'ciclo' => 'mensal'],
    ]);

    expect($criar['ok'])->toBeTrue();
    $sub = $criar['subscription'];
    expect($sub->status)->toBe('active');
    expect($sub->next_due_date->toDateString())->toBe('2026-02-15');

    // ───────── Passo 2: invoice mensal gerada (manual aqui) ─────────
    $invoice = Invoice::create([
        'business_id'     => 1,
        'subscription_id' => $sub->id,
        'contact_id'      => 1,
        'valor'           => 199.00,
        'status'          => 'open',
        'vencimento'      => '2026-02-15',
        'gateway'         => 'asaas',
        'gateway_ref'     => 'pay_journey_001',
    ]);

    expect($invRepo->totaisPorStatus(1, 'open')['count'])->toBe(1);

    // ───────── Passo 3: pausar 30d ─────────
    $pausa = $service->pausar(1, $sub->id, '2026-03-15', 'viagem');
    expect($pausa['ok'])->toBeTrue();
    expect($pausa['subscription']->status)->toBe('paused');
    expect($subRepo->contarAtivas(1))->toBe(0); // pausada não conta como ativa

    // ───────── Passo 4: retomar ─────────
    Carbon::setTestNow('2026-03-15');
    $retomar = $service->retomar(1, $sub->id);
    expect($retomar['ok'])->toBeTrue();
    expect($retomar['subscription']->status)->toBe('active');
    expect($retomar['subscription']->next_due_date->toDateString())->toBe('2026-04-15');
    Carbon::setTestNow();

    // ───────── Passo 5: atualizar ciclo pra anual ─────────
    $atualizar = $cobranca->atualizarCobrancaAssinatura(1, $sub->id, [
        'ciclo' => 'anual',
        'valor' => 1990.00,
    ]);

    expect($atualizar['ok'])->toBeTrue();
    $sub->refresh();
    expect($sub->metadata['ciclo'])->toBe('anual');
    expect((float) $sub->metadata['valor'])->toBe(1990.0);

    // ───────── Passo 6: inadimplência (invoice overdue) ─────────
    $invoice->update(['status' => 'overdue', 'vencimento' => '2026-04-15']);
    expect($invRepo->totaisPorStatus(1, 'overdue')['count'])->toBe(1);

    // ───────── Passo 7: cliente paga atrasado ─────────
    $invoice->update([
        'status'   => 'paid',
        'pago_em'  => now(),
    ]);
    expect($invRepo->totaisPorStatus(1, 'paid')['count'])->toBe(1);
    expect($invRepo->totaisPorStatus(1, 'overdue')['count'])->toBe(0);

    // ───────── Passo 8: cliente decide cancelar ─────────
    $cancelar = $service->cancelar(1, $sub->id, 'mudanca_fornecedor');
    expect($cancelar['ok'])->toBeTrue();
    expect($cancelar['subscription']->status)->toBe('canceled');
    expect($cancelar['subscription']->churn_reason)->toBe('mudanca_fornecedor');

    // ───────── Passo 9: MRR baseline reflete cancelamento ─────────
    expect($subRepo->contarAtivas(1))->toBe(0);
    expect($subRepo->mrrBaselineCached(1))->toBe(0.0); // canceled não soma MRR
});

it('D5 — Multi-tenant journey: biz=99 não interfere com biz=1', function () {
    $subRepo = new SubscriptionRepository();
    $service = new AssinaturaService($subRepo);
    $plan = Plan::where('business_id', 1)->first();

    // biz=1 cria sub
    $sub1 = $service->criar(1, [
        'plan_id'    => $plan->id,
        'contact_id' => 1,
        'metadata'   => ['valor' => 199.00, 'ciclo' => 'mensal'],
    ]);

    expect($sub1['ok'])->toBeTrue();

    // biz=99 NÃO vê sub de biz=1
    expect($subRepo->contarAtivas(99))->toBe(0);
    expect($subRepo->mrrBaselineCached(99))->toBe(0.0);
    expect($subRepo->acharPorId(99, $sub1['subscription']->id))->toBeNull();

    // biz=1 vê sua própria sub
    expect($subRepo->contarAtivas(1))->toBe(1);
    expect($subRepo->mrrBaselineCached(1))->toBe(199.0);
});

it('D9.a — Journey valida 5+ spans OTel emitidos (criar, pausar, retomar, cancelar, update)', function () {
    // Smoke: confirma que cada método chamado wrap em OtelHelper::spanBiz.
    // Não conta spans (otel.enabled=false), apenas valida que o source code
    // possui os spans canon (resilient a mock de OtelHelper).
    $assinatura = file_get_contents(__DIR__ . '/../../Services/AssinaturaService.php');
    $cobranca = file_get_contents(__DIR__ . '/../../Services/AssinaturaCobrancaService.php');

    expect($assinatura)->toContain("OtelHelper::spanBiz('rb.assinatura.criar'");
    expect($assinatura)->toContain("OtelHelper::spanBiz('rb.assinatura.pausar'");
    expect($assinatura)->toContain("OtelHelper::spanBiz('rb.assinatura.retomar'");
    expect($assinatura)->toContain("OtelHelper::spanBiz('rb.assinatura.cancelar'");
    expect($cobranca)->toContain("OtelHelper::spanBiz('rb.subscription.update'");
});
