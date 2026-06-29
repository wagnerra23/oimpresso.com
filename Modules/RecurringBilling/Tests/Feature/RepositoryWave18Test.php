<?php

declare(strict_types=1);

use Modules\RecurringBilling\Http\Requests\CancelInvoiceRequest;
use Modules\RecurringBilling\Repositories\InvoiceRepository;
use Modules\RecurringBilling\Repositories\SubscriptionRepository;

uses(Tests\TestCase::class);

/**
 * Wave 18 saturação RecurringBilling (69→95).
 *
 * Cobre:
 *  D2/D4 — SubscriptionRepository + InvoiceRepository instanciáveis com type hints
 *  D1   — cross-tenant biz=99 retorna zero (defesa em profundidade)
 *  D8   — CancelInvoiceRequest tipado
 *  D9.a — Repositories wrap em OtelHelper::spanBiz nos métodos hot
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 (ADR 0101) — NUNCA biz=4 ROTA LIVRE.
 *
 * Pattern auditável via reflection — não exige seed completo, robusto pra CI.
 *
 * @see Modules\RecurringBilling\Repositories\SubscriptionRepository
 * @see Modules\RecurringBilling\Repositories\InvoiceRepository
 * @see Modules\RecurringBilling\Http\Requests\CancelInvoiceRequest
 */

beforeEach(function () {
    config()->set('otel.enabled', false);

    // Pattern AssinaturaCobrancaServiceTest — cria tabelas minimas in-memory pra
    // testes que tocam Repository com queries reais.
    if (config('database.default') === 'sqlite' || str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        \Illuminate\Support\Facades\Schema::dropIfExists('rb_invoices');
        \Illuminate\Support\Facades\Schema::create('rb_invoices', function ($t) {
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

        \Illuminate\Support\Facades\Schema::dropIfExists('rb_subscriptions');
        \Illuminate\Support\Facades\Schema::create('rb_subscriptions', function ($t) {
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
    }
});

afterEach(function () {
    if (config('database.default') === 'sqlite' || str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        \Illuminate\Support\Facades\Schema::dropIfExists('rb_invoices');
        \Illuminate\Support\Facades\Schema::dropIfExists('rb_subscriptions');
    }
});

it('D4 — SubscriptionRepository existe + métodos canônicos com type hints corretos', function () {
    $repo = new SubscriptionRepository();
    $reflection = new ReflectionClass($repo);

    $metodos = ['listarPaginado', 'contarAtivas', 'mrrBaselineCached', 'vencendoNoIntervalo', 'acharPorId'];
    foreach ($metodos as $m) {
        expect($reflection->hasMethod($m))->toBeTrue("SubscriptionRepository deve ter {$m}");
        $method = $reflection->getMethod($m);
        expect($method->getReturnType())->not->toBeNull("método {$m} deve ter return type");

        $params = $method->getParameters();
        expect($params[0]->getName())->toBe('businessId', "1º param deve ser businessId (Tier 0)");
        expect((string) $params[0]->getType())->toBe('int');
    }
});

it('D4 — InvoiceRepository existe + métodos canônicos', function () {
    $repo = new InvoiceRepository();
    $reflection = new ReflectionClass($repo);

    $metodos = ['listarPaginado', 'totaisPorStatus', 'atrasadasAntigas', 'acharPorGatewayRef', 'acharPorId'];
    foreach ($metodos as $m) {
        expect($reflection->hasMethod($m))->toBeTrue();
        expect($reflection->getMethod($m)->getReturnType())->not->toBeNull();
    }
});

it('D1 — SubscriptionRepository biz=99 retorna zero (isolamento Tier 0)', function () {
    $repo = new SubscriptionRepository();

    expect($repo->contarAtivas(99))->toBe(0);
    expect($repo->mrrBaselineCached(99))->toBe(0.0);
    expect($repo->vencendoNoIntervalo(99, 7)->count())->toBe(0);
    expect($repo->acharPorId(99, 9999999))->toBeNull();
});

it('D1 — InvoiceRepository biz=99 retorna zero (cross-tenant blindado)', function () {
    $repo = new InvoiceRepository();

    $totais = $repo->totaisPorStatus(99, 'open');
    expect($totais['count'])->toBe(0);
    expect($totais['total'])->toBe(0.0);

    expect($repo->atrasadasAntigas(99, 7)->count())->toBe(0);
    expect($repo->acharPorGatewayRef(99, 'inter', 'fake-ref-zzz'))->toBeNull();
});

it('D8 — CancelInvoiceRequest com rules() canônicas + helpers tipados', function () {
    $req = new CancelInvoiceRequest();
    $rules = $req->rules();

    expect($rules)->toBeArray()->not->toBeEmpty();
    expect($rules)->toHaveKeys(['motivo', 'observacao', 'notificar_cliente']);

    // Helper tipado default
    expect($req->motivo())->toBe('ACERTOS');
});

it('D9.a — SubscriptionRepository wrap em OtelHelper::spanBiz', function () {
    $source = file_get_contents(__DIR__ . '/../../Repositories/SubscriptionRepository.php');

    expect($source)->toContain('use App\Util\OtelHelper');
    expect($source)->toContain("OtelHelper::spanBiz('rb.subscription.repo.listar'");
    expect($source)->toContain("OtelHelper::spanBiz('rb.subscription.repo.mrr'");
});

it('D9.a — InvoiceRepository wrap em OtelHelper::spanBiz', function () {
    $source = file_get_contents(__DIR__ . '/../../Repositories/InvoiceRepository.php');

    expect($source)->toContain('use App\Util\OtelHelper');
    expect($source)->toContain("OtelHelper::spanBiz('rb.invoice.repo.listar'");
});

it('F3b — paginatedForIndex trata preset custom (intervalo from/to em next_due_date, open-ended)', function () {
    // [W] 2026-06-29: preset "Personalizado" na barra "Próxima cobrança". Filtra
    // next_due_date por intervalo; from/to vazios = sem limite naquele lado.
    $src = file_get_contents(__DIR__ . '/../../Repositories/SubscriptionRepository.php');

    expect($src)->toContain("'custom'");
    expect($src)->toMatch("/whereDate\(['\"]next_due_date['\"],\s*'>=',\s*\\\$from\)/");
    expect($src)->toMatch("/whereDate\(['\"]next_due_date['\"],\s*'<=',\s*\\\$to\)/");
    // open-ended: cada lado só aplica se preenchido (não força intervalo fechado).
    expect($src)->toContain("if (\$from !== '')");
    expect($src)->toContain("if (\$to !== '')");
});

it('D1 — Repositories forçam where(business_id) explícito (defesa em profundidade)', function () {
    $subSource = file_get_contents(__DIR__ . '/../../Repositories/SubscriptionRepository.php');
    $invSource = file_get_contents(__DIR__ . '/../../Repositories/InvoiceRepository.php');

    expect($subSource)->toMatch("/where\(['\"]business_id['\"],\s*\\\$businessId\)/");
    expect($invSource)->toMatch("/where\(['\"]business_id['\"],\s*\\\$businessId\)/");
});
