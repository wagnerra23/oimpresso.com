<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Repositories\SubscriptionRepository;
use Modules\RecurringBilling\Services\AssinaturaService;

uses(Tests\TestCase::class);

/**
 * Wave 18 RETRY D2/D4 — AssinaturaService extraído de Controllers (no-op
 * legacy). Pest cobre criar/pausar/retomar/cancelar + idempotência + cross-tenant.
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 (ADR 0101) — NUNCA biz=4 ROTA LIVRE.
 *
 * Schema in-memory: rb_subscriptions + rb_plans + rb_invoices criadas no
 * beforeEach (UltimatePOS migrations não rodam em SQLite por causa ALTER TABLE
 * MODIFY COLUMN ENUM).
 *
 * @see Modules\RecurringBilling\Services\AssinaturaService
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
    config()->set('activitylog.enabled', false);

    if (config('database.default') !== 'sqlite' && ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Smoke test rodado apenas em SQLite in-memory.');
    }

    Schema::dropIfExists('rb_invoices');
    Schema::dropIfExists('rb_subscriptions');
    Schema::dropIfExists('rb_plans');

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

    // activity_log table (Spatie LogsActivity ATIVA em Plan, Subscription)
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

    // Seed plano biz=1
    \Modules\RecurringBilling\Models\Plan::create([
        'business_id' => 1,
        'name'        => 'Mensal Standard',
        'slug'        => 'mensal-standard',
        'valor'       => 199.00,
        'ciclo'       => 'mensal',
        'ativo'       => true,
    ]);
});

afterEach(function () {
    Schema::dropIfExists('rb_invoices');
    Schema::dropIfExists('rb_subscriptions');
    Schema::dropIfExists('rb_plans');
});

function makeAssinaturaService(): AssinaturaService
{
    return new AssinaturaService(new SubscriptionRepository());
}

it('D4 — criar() falha com plan_id/contact_id ausentes (422)', function () {
    $service = makeAssinaturaService();
    $result = $service->criar(1, []);

    expect($result['ok'])->toBeFalse();
    expect($result['http_status'])->toBe(422);
});

it('D4 — criar() falha quando plan inexistente no business (404)', function () {
    $service = makeAssinaturaService();
    $result = $service->criar(1, ['plan_id' => 9999, 'contact_id' => 1]);

    expect($result['ok'])->toBeFalse();
    expect($result['http_status'])->toBe(404);
});

it('D4 — criar() cria subscription com next_due_date calculado pelo ciclo', function () {
    $service = makeAssinaturaService();

    $plan = \Modules\RecurringBilling\Models\Plan::where('business_id', 1)->first();

    $result = $service->criar(1, [
        'plan_id'    => $plan->id,
        'contact_id' => 1,
        'start_date' => '2026-05-16',
    ]);

    expect($result['ok'])->toBeTrue();
    expect($result['subscription'])->toBeInstanceOf(Subscription::class);
    expect($result['subscription']->business_id)->toBe(1);
    expect($result['subscription']->status)->toBe('active');
    expect($result['subscription']->next_due_date->toDateString())->toBe('2026-06-16');
});

it('D2 — calcularProximoVencimento respeita ciclos canônicos', function () {
    $service = makeAssinaturaService();

    expect($service->calcularProximoVencimento('2026-05-16', 'mensal'))->toBe('2026-06-16');
    expect($service->calcularProximoVencimento('2026-05-16', 'trimestral'))->toBe('2026-08-16');
    expect($service->calcularProximoVencimento('2026-05-16', 'semestral'))->toBe('2026-11-16');
    expect($service->calcularProximoVencimento('2026-05-16', 'anual'))->toBe('2027-05-16');
});

it('D4 — pausar() é idempotente (já pausada → skipped)', function () {
    $service = makeAssinaturaService();

    $sub = Subscription::create([
        'business_id' => 1,
        'contact_id'  => 1,
        'status'      => 'paused',
        'paused_at'   => now(),
    ]);

    $result = $service->pausar(1, $sub->id);

    expect($result['ok'])->toBeTrue();
    expect($result['skipped'] ?? null)->toBe('already_paused');
});

it('D4 — pausar() bloqueia subscription cancelada (422)', function () {
    $service = makeAssinaturaService();

    $sub = Subscription::create([
        'business_id' => 1,
        'contact_id'  => 1,
        'status'      => 'canceled',
        'canceled_at' => now(),
    ]);

    $result = $service->pausar(1, $sub->id);

    expect($result['ok'])->toBeFalse();
    expect($result['http_status'])->toBe(422);
});

it('D4 — retomar() recalcula next_due_date pra hoje + 1 ciclo', function () {
    $service = makeAssinaturaService();

    $sub = Subscription::create([
        'business_id' => 1,
        'contact_id'  => 1,
        'status'      => 'paused',
        'paused_at'   => now()->subDays(30),
        'metadata'    => ['ciclo' => 'mensal'],
    ]);

    $result = $service->retomar(1, $sub->id);

    expect($result['ok'])->toBeTrue();
    expect($result['subscription']->status)->toBe('active');
    expect($result['subscription']->next_due_date->toDateString())
        ->toBe(now()->addMonth()->toDateString());
});

it('D4 — cancelar() é idempotente + grava churn_reason', function () {
    $service = makeAssinaturaService();

    $sub = Subscription::create([
        'business_id' => 1,
        'contact_id'  => 1,
        'status'      => 'active',
    ]);

    $result = $service->cancelar(1, $sub->id, 'preco_alto');

    expect($result['ok'])->toBeTrue();
    expect($result['subscription']->status)->toBe('canceled');
    expect($result['subscription']->churn_reason)->toBe('preco_alto');

    // Re-chamada idempotente
    $result2 = $service->cancelar(1, $sub->id, 'outro');
    expect($result2['ok'])->toBeTrue();
    expect($result2['skipped'] ?? null)->toBe('already_canceled');
});

it('D1 — cancelar() de biz=99 com sub de biz=1 retorna 404 (cross-tenant blindado)', function () {
    $service = makeAssinaturaService();

    $sub = Subscription::create([
        'business_id' => 1,
        'contact_id'  => 1,
        'status'      => 'active',
    ]);

    // biz=99 tenta cancelar sub de biz=1
    $result = $service->cancelar(99, $sub->id);

    expect($result['ok'])->toBeFalse();
    expect($result['http_status'])->toBe(404);

    // Sub de biz=1 NÃO foi alterada
    $sub->refresh();
    expect($sub->status)->toBe('active');
});

it('D9.a — AssinaturaService wrap em OtelHelper::spanBiz nos 4 métodos críticos', function () {
    $source = file_get_contents(__DIR__ . '/../../Services/AssinaturaService.php');

    expect($source)->toContain('use App\Util\OtelHelper');
    expect($source)->toContain("OtelHelper::spanBiz('rb.assinatura.criar'");
    expect($source)->toContain("OtelHelper::spanBiz('rb.assinatura.pausar'");
    expect($source)->toContain("OtelHelper::spanBiz('rb.assinatura.retomar'");
    expect($source)->toContain("OtelHelper::spanBiz('rb.assinatura.cancelar'");
});

it('D4 — AssinaturaService registrado como singleton no Provider', function () {
    $a = app(AssinaturaService::class);
    $b = app(AssinaturaService::class);
    expect($a)->toBe($b);
});
