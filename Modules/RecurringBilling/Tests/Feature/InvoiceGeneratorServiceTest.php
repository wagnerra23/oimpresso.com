<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Models\SubscriptionEvent;
use Modules\RecurringBilling\Services\InvoiceGeneratorService;

uses(Tests\TestCase::class);

/**
 * US-RB-003 — Pest cobrindo InvoiceGeneratorService.
 *
 * SQLite in-memory pattern (espelha Wave6PlanCrudTest + AssinaturaCobrancaServiceTest)
 * — migrations legadas UltimatePOS usam sintaxe MySQL-only (ALTER TABLE MODIFY ENUM).
 *
 * Multi-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
 * + biz=1 ([ADR 0101](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)):
 * NUNCA biz=4 (ROTA LIVRE PROD).
 *
 * Cenários (≥7 PASSED):
 *  1. Cria invoice quando next_due_date <= hoje
 *  2. Idempotência: 2× run() não duplica invoice
 *  3. Avança next_due_date += 1 mês (monthly) preservando anchor
 *  4. Skipa subscription paused/canceled
 *  5. dry-run não escreve mas conta
 *  6. lead-days antecipa (venc hoje+3 entra com lead=3)
 *  7. Cross-tenant Tier 0: biz=99 NÃO vaza dados de biz=1
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite'
        || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Smoke test rodado apenas em SQLite in-memory.');
    }

    // rb_plans (espelha migrations 2026_05_06 + 2026_05_16 v975)
    Schema::dropIfExists('rb_plans');
    Schema::create('rb_plans', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('name', 150);
        $t->string('slug', 80);
        $t->text('description')->nullable();
        $t->string('descricao_curta', 200)->nullable();
        $t->decimal('valor', 15, 2);
        $t->string('ciclo', 20);
        $t->unsignedSmallInteger('ciclo_dias')->nullable();
        $t->unsignedSmallInteger('trial_days')->default(0);
        $t->boolean('ativo')->default(true)->index();
        $t->string('fiscal_type', 10)->default('none');
        $t->string('fiscal_cfop', 8)->nullable();
        $t->string('fiscal_servico', 8)->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::dropIfExists('rb_subscriptions');
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
        $t->unsignedInteger('conta_bancaria_id')->nullable();
        $t->string('payment_method', 20)->nullable();
        $t->unsignedBigInteger('last_jobsheet_id')->nullable();
        $t->unsignedSmallInteger('total_paid_cached')->default(0);
        $t->unsignedSmallInteger('failed_count_cached')->default(0);
        $t->decimal('total_revenue_cached', 14, 2)->default(0);
        $t->date('paused_until')->nullable();
        $t->string('churn_reason', 64)->nullable();
        $t->string('contact_phone_cached', 32)->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::dropIfExists('rb_invoices');
    Schema::create('rb_invoices', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('subscription_id')->nullable();
        $t->unsignedInteger('contact_id');
        $t->string('numero_documento', 50);
        $t->decimal('valor', 15, 2);
        $t->string('status', 20)->default('open');
        $t->date('vencimento');
        $t->dateTime('pago_em')->nullable();
        $t->string('gateway', 30)->nullable();
        $t->string('gateway_ref', 100)->nullable();
        $t->unsignedInteger('conta_bancaria_id')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::dropIfExists('rb_subscription_events');
    Schema::create('rb_subscription_events', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('subscription_id');
        $t->string('kind', 30);
        $t->string('by_actor', 80)->nullable();
        $t->text('body')->nullable();
        $t->dateTime('occurred_at');
        $t->timestamps();
    });

    Schema::dropIfExists('activity_log');
    Schema::create('activity_log', function ($t) {
        $t->id();
        $t->string('log_name')->nullable();
        $t->text('description')->nullable();
        $t->nullableMorphs('subject');
        $t->string('event')->nullable();
        $t->nullableMorphs('causer');
        $t->json('properties')->nullable();
        $t->uuid('batch_uuid')->nullable();
        $t->timestamps();
    });

    // Multi-tenant Tier 0 — biz=1 (ADR 0101)
    session(['user.business_id' => 1]);
});

afterEach(function () {
    // rb_* são reais-migradas; o afterEach roda mesmo em teste pulado (PHPUnit 12:
    // tearDown gated só por hasMetRequirements), então dropá-las no MySQL persistente
    // corromperia testes irmãos do módulo. DDL só em sqlite :memory:.
    if (config('database.default') === 'sqlite'
        && str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        Schema::dropIfExists('rb_subscription_events');
        Schema::dropIfExists('rb_invoices');
        Schema::dropIfExists('rb_subscriptions');
        Schema::dropIfExists('rb_plans');
    }
    session()->flush();
});

function makePlan(int $bizId, float $valor = 100.0, string $ciclo = 'monthly'): Plan
{
    return Plan::create([
        'business_id' => $bizId,
        'name'        => "Plano teste {$ciclo} R$ {$valor}",
        'slug'        => "plano-test-{$bizId}-{$ciclo}-".uniqid(),
        'valor'       => $valor,
        'ciclo'       => $ciclo,
        'ativo'       => true,
        'fiscal_type' => 'none',
    ]);
}

function makeSubscription(int $bizId, int $planId, string $nextDueDate, string $status = 'active', ?int $contaBancariaId = null): Subscription
{
    return Subscription::create([
        'business_id'         => $bizId,
        'plan_id'             => $planId,
        'contact_id'          => 999,
        'status'              => $status,
        'start_date'          => $nextDueDate,
        'next_due_date'       => $nextDueDate,
        'billing_anchor_date' => $nextDueDate,
        'conta_bancaria_id'   => $contaBancariaId,
        'payment_method'      => 'boleto',
    ]);
}

test('1. Cria invoice quando next_due_date <= hoje', function () {
    $plan = makePlan(1, 250.50, 'monthly');
    $sub  = makeSubscription(1, $plan->id, '2026-07-10', 'active', 12);

    $service = new InvoiceGeneratorService();
    $stats = $service->run(businessId: 1, date: '2026-07-10');

    expect($stats['generated'])->toBe(1);
    expect($stats['skipped'])->toBe(0);
    expect($stats['errors'])->toBe(0);
    expect($stats['advanced'])->toBe(1);

    $invoice = Invoice::where('subscription_id', $sub->id)->first();
    expect($invoice)->not->toBeNull();
    expect((float) $invoice->valor)->toBe(250.50);
    expect($invoice->status)->toBe('open');
    expect($invoice->vencimento->toDateString())->toBe('2026-07-10');
    expect($invoice->conta_bancaria_id)->toBe(12);
    expect($invoice->numero_documento)->toBe("RB-{$sub->id}-2026-07");
});

test('2. Idempotência: 2x run() nao duplica invoice', function () {
    $plan = makePlan(1, 100.0, 'monthly');
    $sub  = makeSubscription(1, $plan->id, '2026-07-15');

    $service = new InvoiceGeneratorService();
    $service->run(1, '2026-07-15');
    // Re-aponta next_due_date pra mesma data simulando re-execução do job
    $sub->update(['next_due_date' => '2026-07-15']);
    $stats2 = $service->run(1, '2026-07-15');

    expect($stats2['generated'])->toBe(0);
    expect($stats2['skipped'])->toBe(1);
    expect(Invoice::where('subscription_id', $sub->id)->count())->toBe(1);
});

test('3. Avanca next_due_date += 1 mes (monthly) preservando anchor', function () {
    $plan = makePlan(1, 100.0, 'monthly');
    $sub  = makeSubscription(1, $plan->id, '2026-07-10');

    (new InvoiceGeneratorService())->run(1, '2026-07-10');

    $sub->refresh();
    expect($sub->next_due_date->toDateString())->toBe('2026-08-10');
});

test('4. Skipa subscription paused/canceled', function () {
    $plan = makePlan(1, 100.0, 'monthly');
    makeSubscription(1, $plan->id, '2026-07-10', 'paused');
    makeSubscription(1, $plan->id, '2026-07-10', 'canceled');
    makeSubscription(1, $plan->id, '2026-07-10', 'active');

    $stats = (new InvoiceGeneratorService())->run(1, '2026-07-10');

    expect($stats['generated'])->toBe(1);
    expect(Invoice::count())->toBe(1);
});

test('5. dry-run nao escreve mas conta', function () {
    $plan = makePlan(1, 100.0, 'monthly');
    $sub  = makeSubscription(1, $plan->id, '2026-07-10');

    $stats = (new InvoiceGeneratorService())->run(1, '2026-07-10', dryRun: true);

    expect($stats['generated'])->toBe(1);
    expect(Invoice::count())->toBe(0);
    expect(SubscriptionEvent::count())->toBe(0);
    $sub->refresh();
    expect($sub->next_due_date->toDateString())->toBe('2026-07-10'); // não avançou
});

test('6. lead-days antecipa: venc hoje+3 entra com lead=3', function () {
    $plan = makePlan(1, 100.0, 'monthly');
    makeSubscription(1, $plan->id, '2026-07-13'); // venc 3 dias depois

    // Sem lead-days
    $sem = (new InvoiceGeneratorService())->run(1, '2026-07-10', dryRun: true, leadDays: 0);
    expect($sem['generated'])->toBe(0);

    // Com lead-days=3 → entra
    $com = (new InvoiceGeneratorService())->run(1, '2026-07-10', dryRun: true, leadDays: 3);
    expect($com['generated'])->toBe(1);
});

test('7. Cross-tenant Tier 0: biz=99 NAO vaza biz=1', function () {
    // Subscription real em biz=1
    $plan1 = makePlan(1, 100.0, 'monthly');
    makeSubscription(1, $plan1->id, '2026-07-10');

    // Plan e subscription paralela em biz=99
    $plan99 = makePlan(99, 200.0, 'monthly');
    makeSubscription(99, $plan99->id, '2026-07-10');

    // Roda só pra biz=99
    $stats = (new InvoiceGeneratorService())->run(99, '2026-07-10');

    expect($stats['generated'])->toBe(1);
    expect(Invoice::where('business_id', 1)->count())->toBe(0); // biz=1 intacto
    expect(Invoice::where('business_id', 99)->count())->toBe(1);
    expect((float) Invoice::where('business_id', 99)->first()->valor)->toBe(200.0);
});

test('8. Logga SubscriptionEvent kind=event-charge', function () {
    $plan = makePlan(1, 100.0, 'monthly');
    $sub  = makeSubscription(1, $plan->id, '2026-07-10');

    (new InvoiceGeneratorService())->run(1, '2026-07-10');

    $event = SubscriptionEvent::where('subscription_id', $sub->id)->first();
    expect($event)->not->toBeNull();
    expect($event->kind)->toBe(SubscriptionEvent::KIND_CHARGE);
    expect($event->by_actor)->toBe('system:rb:generate-invoices');
    expect($event->body)->toContain('Fatura RB-');
    expect($event->body)->toContain('R$ 100,00');
});
