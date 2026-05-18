<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Models\Invoice;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Repositories\InvoiceRepository;

uses(Tests\TestCase::class);

/**
 * Wave 7 v9,75 — cobertura mínima do InvoiceRepository::paginatedForIndex
 * + kpisForIndex que alimentam Pages/RecurringBilling/Faturas/Index.tsx.
 *
 * Padrão SQLite-stub (mesmo Wave4PresenterIndexTest + RecurringV975SchemaTest)
 * — UltimatePOS legacy migrations quebram em sqlite, então criamos schema
 * minimo manual. Multi-tenant Tier 0 (ADR 0093) + biz=1 (ADR 0101).
 *
 * Cenários:
 *  1. paginatedForIndex retorna invoices biz=1 com eager loads contact + subscription.plan
 *  2. Filtros aplicam: status=paid + gateway=inter + periodo=mes_atual + busca=cliente
 *  3. Cross-tenant: invoice biz=1 NÃO aparece quando session biz=99
 *  4. Paginated meta correto (current_page/last_page/per_page/total)
 *  5. KPIs agregam pago_mes/pendente/atrasado/count_overdue/total_faturas
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite' || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Smoke test rodado apenas em SQLite in-memory.');
    }

    foreach (['rb_invoices', 'rb_subscriptions', 'rb_plans', 'contacts'] as $t) {
        Schema::dropIfExists($t);
    }

    // activity_log table (Spatie LogsActivity ATIVA em Plan, Subscription, Invoice — Wave 14 D7 LGPD)
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

    Schema::create('contacts', function ($t) {
        $t->increments('id');
        $t->unsignedInteger('business_id')->index();
        $t->string('name', 200)->nullable();
        $t->string('tax_number', 50)->nullable();
        $t->timestamps();
        $t->softDeletes(); // App\Contact usa SoftDeletes
    });

    Schema::create('rb_plans', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('name', 150);
        $t->string('slug', 80);
        $t->decimal('valor', 15, 2)->default(0);
        $t->string('ciclo', 20)->default('monthly');
        $t->boolean('ativo')->default(true);
        $t->string('fiscal_type', 10)->default('none');
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

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
        $t->string('payment_method', 10)->nullable();
        // v9,75 cached cols (Subscription Model preenche via observer)
        $t->unsignedSmallInteger('total_paid_cached')->default(0);
        $t->unsignedSmallInteger('failed_count_cached')->default(0);
        $t->decimal('total_revenue_cached', 14, 2)->default(0);
        $t->date('paused_until')->nullable();
        $t->string('churn_reason', 64)->nullable();
        $t->string('contact_phone_cached', 32)->nullable();
        $t->unsignedBigInteger('last_jobsheet_id')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('rb_invoices', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('subscription_id')->nullable();
        $t->unsignedInteger('contact_id')->index();
        $t->string('numero_documento', 50);
        $t->decimal('valor', 15, 2)->default(0);
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

    // Bootstrap session biz=1 (canon ADR 0101) — necessário pro HasBusinessScope.
    session(['business' => ['id' => 1], 'user.business_id' => 1]);
});

afterEach(function () {
    Carbon::setTestNow();
    foreach (['rb_invoices', 'rb_subscriptions', 'rb_plans', 'contacts'] as $t) {
        Schema::dropIfExists($t);
    }
});

/**
 * Helpers — criam fixtures consistentes.
 */
function wave7MakeContact(int $bizId, string $name, ?string $cnpj = null): int
{
    \DB::table('contacts')->insert([
        'business_id' => $bizId,
        'name'        => $name,
        'tax_number'  => $cnpj,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
    return (int) \DB::getPdo()->lastInsertId();
}

function wave7MakePlan(int $bizId, string $name = 'Plano Mensal'): Plan
{
    return Plan::create([
        'business_id' => $bizId,
        'name'        => $name,
        'slug'        => 'p-'.uniqid(),
        'valor'       => 480.00,
        'ciclo'       => 'monthly',
        'ativo'       => true,
        'fiscal_type' => 'none',
    ]);
}

function wave7MakeSubscription(int $bizId, int $planId, int $contactId): Subscription
{
    return Subscription::create([
        'business_id'         => $bizId,
        'plan_id'             => $planId,
        'contact_id'          => $contactId,
        'status'              => 'active',
        'start_date'          => '2026-01-01',
        'next_due_date'       => '2026-06-01',
        'billing_anchor_date' => '2026-01-01',
        'payment_method'      => 'pix',
    ]);
}

function wave7MakeInvoice(int $bizId, int $contactId, ?int $subId, array $extra = []): Invoice
{
    return Invoice::create(array_merge([
        'business_id'      => $bizId,
        'subscription_id'  => $subId,
        'contact_id'       => $contactId,
        'numero_documento' => 'INV-'.uniqid(),
        'valor'            => 480.00,
        'status'           => 'open',
        'vencimento'       => '2026-06-10',
        'gateway'          => 'inter',
    ], $extra));
}

it('R-RB-WAVE7-1 — paginatedForIndex retorna invoices biz=1 com eager loads', function () {
    $contactId = wave7MakeContact(1, 'Cliente Alpha', '12.345.678/0001-99');
    $plan = wave7MakePlan(1, 'Plano Premium');
    $sub = wave7MakeSubscription(1, $plan->id, $contactId);
    wave7MakeInvoice(1, $contactId, $sub->id, [
        'numero_documento' => 'INV-2026-0001',
        'valor'            => 750.00,
        'status'           => 'open',
        'vencimento'       => '2026-07-01',
        'gateway'          => 'inter',
    ]);

    $repo = new InvoiceRepository();
    $paginator = $repo->paginatedForIndex(1, [], 50);

    expect($paginator->total())->toBe(1);
    $first = $paginator->items()[0];
    expect($first->numero_documento)->toBe('INV-2026-0001')
        ->and((float) $first->valor)->toBe(750.0)
        ->and($first->gateway)->toBe('inter')
        ->and($first->relationLoaded('contact'))->toBeTrue()
        ->and($first->contact->name)->toBe('Cliente Alpha')
        ->and($first->relationLoaded('subscription'))->toBeTrue()
        ->and($first->subscription->relationLoaded('plan'))->toBeTrue()
        ->and($first->subscription->plan->name)->toBe('Plano Premium');
});

it('R-RB-WAVE7-2 — aplicarFiltrosIndex filtra por status + gateway + busca', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $contactAlpha = wave7MakeContact(1, 'Alpha Vestuário LTDA');
    $contactBeta = wave7MakeContact(1, 'Beta Comércio EIRELI');

    // 4 invoices: 2 paid via inter no mês atual, 2 open via asaas no próximo mês
    wave7MakeInvoice(1, $contactAlpha, null, [
        'numero_documento' => 'INV-001',
        'status'           => 'paid',
        'gateway'          => 'inter',
        'vencimento'       => '2026-06-10',
        'pago_em'          => '2026-06-09 14:30:00',
    ]);
    wave7MakeInvoice(1, $contactBeta, null, [
        'numero_documento' => 'INV-002',
        'status'           => 'paid',
        'gateway'          => 'inter',
        'vencimento'       => '2026-06-20',
        'pago_em'          => '2026-06-19 09:15:00',
    ]);
    wave7MakeInvoice(1, $contactAlpha, null, [
        'numero_documento' => 'INV-003',
        'status'           => 'open',
        'gateway'          => 'asaas',
        'vencimento'       => '2026-07-15',
    ]);
    wave7MakeInvoice(1, $contactBeta, null, [
        'numero_documento' => 'INV-004',
        'status'           => 'open',
        'gateway'          => 'asaas',
        'vencimento'       => '2026-07-20',
    ]);

    $repo = new InvoiceRepository();

    // Filtro status=paid retorna 2
    $paid = $repo->paginatedForIndex(1, ['status' => 'paid']);
    expect($paid->total())->toBe(2);

    // Filtro gateway=inter retorna 2 (mesmas paid)
    $inter = $repo->paginatedForIndex(1, ['gateway' => 'inter']);
    expect($inter->total())->toBe(2);

    // Filtro periodo=mes_atual (vencimento entre 2026-06-01 e 2026-06-30) retorna 2 (paid)
    $mesAtual = $repo->paginatedForIndex(1, ['periodo' => 'mes_atual']);
    expect($mesAtual->total())->toBe(2);

    // Filtro periodo=proximo_mes (vencimento entre 2026-07-01 e 2026-07-31) retorna 2 (open)
    $proximoMes = $repo->paginatedForIndex(1, ['periodo' => 'proximo_mes']);
    expect($proximoMes->total())->toBe(2);

    // Busca por nome cliente "Alpha" retorna 2 (Alpha tem 2 invoices)
    $busca = $repo->paginatedForIndex(1, ['busca' => 'Alpha']);
    expect($busca->total())->toBe(2);

    // Busca por numero documento "INV-003" retorna 1
    $buscaNumero = $repo->paginatedForIndex(1, ['busca' => 'INV-003']);
    expect($buscaNumero->total())->toBe(1)
        ->and($buscaNumero->items()[0]->numero_documento)->toBe('INV-003');
});

it('R-RB-WAVE7-3 — cross-tenant: biz=99 NÃO enxerga invoices de biz=1', function () {
    $contactBiz1 = wave7MakeContact(1, 'Cliente biz=1');
    $contactBiz99 = wave7MakeContact(99, 'Cliente biz=99');

    wave7MakeInvoice(1, $contactBiz1, null, ['numero_documento' => 'INV-B1-001']);
    wave7MakeInvoice(1, $contactBiz1, null, ['numero_documento' => 'INV-B1-002']);
    wave7MakeInvoice(99, $contactBiz99, null, ['numero_documento' => 'INV-B99-001']);

    $repo = new InvoiceRepository();

    // Sob session biz=1, vê apenas 2 invoices de biz=1
    session(['business' => ['id' => 1], 'user.business_id' => 1]);
    $bizUm = $repo->paginatedForIndex(1, []);
    expect($bizUm->total())->toBe(2);

    // Sob biz=99, vê apenas 1 invoice de biz=99
    session(['business' => ['id' => 99], 'user.business_id' => 99]);
    $bizNoventa = $repo->paginatedForIndex(99, []);
    expect($bizNoventa->total())->toBe(1)
        ->and($bizNoventa->items()[0]->numero_documento)->toBe('INV-B99-001');

    // restaura
    session(['business' => ['id' => 1], 'user.business_id' => 1]);
});

it('R-RB-WAVE7-4 — paginated meta retorna current_page/last_page/per_page/total corretos', function () {
    $contactId = wave7MakeContact(1, 'Cliente Pagination');

    // 12 invoices, per_page=5 → 3 páginas
    for ($i = 1; $i <= 12; $i++) {
        wave7MakeInvoice(1, $contactId, null, [
            'numero_documento' => 'INV-PAG-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'vencimento'       => '2026-06-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT),
        ]);
    }

    $repo = new InvoiceRepository();
    $paginator = $repo->paginatedForIndex(1, [], 5);

    expect($paginator->total())->toBe(12)
        ->and($paginator->lastPage())->toBe(3)
        ->and($paginator->perPage())->toBe(5)
        ->and($paginator->currentPage())->toBe(1)
        ->and(count($paginator->items()))->toBe(5);
});

it('R-RB-WAVE7-5 — kpisForIndex agrega pago_mes + pendente + atrasado + count_overdue + total_faturas', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    $contactId = wave7MakeContact(1, 'Cliente KPI');

    // 1 fatura paga no mês atual: 500
    wave7MakeInvoice(1, $contactId, null, [
        'numero_documento' => 'INV-K-PAID',
        'valor'            => 500.00,
        'status'           => 'paid',
        'vencimento'       => '2026-06-10',
        'pago_em'          => '2026-06-09 14:30:00',
    ]);

    // 2 faturas pendentes futuras: 200 + 300 = 500
    wave7MakeInvoice(1, $contactId, null, [
        'numero_documento' => 'INV-K-OPEN-1',
        'valor'            => 200.00,
        'status'           => 'open',
        'vencimento'       => '2026-07-10',
    ]);
    wave7MakeInvoice(1, $contactId, null, [
        'numero_documento' => 'INV-K-OPEN-2',
        'valor'            => 300.00,
        'status'           => 'open',
        'vencimento'       => '2026-08-15',
    ]);

    // 1 fatura status=overdue: 150
    wave7MakeInvoice(1, $contactId, null, [
        'numero_documento' => 'INV-K-OVERDUE',
        'valor'            => 150.00,
        'status'           => 'overdue',
        'vencimento'       => '2026-05-10',
    ]);

    // 1 fatura open com vencimento passado (deve contar como atrasada): 100
    wave7MakeInvoice(1, $contactId, null, [
        'numero_documento' => 'INV-K-OPEN-PAST',
        'valor'            => 100.00,
        'status'           => 'open',
        'vencimento'       => '2026-05-20',
    ]);

    // 1 fatura cancelada (NÃO conta em pendente nem atrasado, mas conta no total): 999
    wave7MakeInvoice(1, $contactId, null, [
        'numero_documento' => 'INV-K-CANCEL',
        'valor'            => 999.00,
        'status'           => 'canceled',
        'vencimento'       => '2026-06-25',
    ]);

    $repo = new InvoiceRepository();
    $kpis = $repo->kpisForIndex(1);

    expect($kpis['total_pago_mes'])->toBe(500.00)
        ->and($kpis['total_pendente'])->toBe(500.00) // 200 + 300 (futuras)
        ->and($kpis['total_atrasado'])->toBe(250.00) // 150 overdue + 100 open-past
        ->and($kpis['count_overdue'])->toBe(2)
        ->and($kpis['total_faturas'])->toBe(6);
});
