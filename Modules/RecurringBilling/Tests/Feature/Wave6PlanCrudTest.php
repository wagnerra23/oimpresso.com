<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Http\Controllers\PlanController;
use Modules\RecurringBilling\Http\Requests\StorePlanRequest;
use Modules\RecurringBilling\Http\Requests\UpdatePlanRequest;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;

uses(Tests\TestCase::class);

/**
 * Wave 6 — Pest cobrindo PlanController CRUD.
 *
 * SQLite in-memory pattern (espelha AtualizarCobrancaAssinaturaTest.php +
 * AssinaturaCobrancaServiceTest.php) — migrations legadas UltimatePOS usam
 * sintaxe MySQL-only (ALTER TABLE MODIFY ENUM).
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 (ADR 0101): NUNCA biz=4 (ROTA LIVRE PROD).
 *
 * Estratégia: invocamos métodos do Controller diretamente em vez de roteamento HTTP
 * full — UltimatePOS auth middleware exige schema MySQL pesado (users + roles + business)
 * incompatível com SQLite minimal. Cobertura: store/update/destroy + guard + cross-tenant.
 *
 * Cenários (≥5 PASSED):
 *  1. store() cria plano biz=1 com slug auto-gerado + flash success
 *  2. update() altera valor + ciclo preservando outros campos
 *  3. destroy() soft-deleta plano sem assinatura ativa
 *  4. destroy() retorna 422 (status code) quando plano tem Subscription ativa
 *  5. Cross-tenant: edit() biz=99 acessando plano biz=1 dispara 404 (findOrFail)
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite'
        || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Smoke test rodado apenas em SQLite in-memory.');
    }

    // Schema rb_plans (espelha migrations 2026_05_06 + 2026_05_16 v975)
    Schema::dropIfExists('rb_plans');
    Schema::create('rb_plans', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->string('name', 150);
        $table->string('slug', 80);
        $table->text('description')->nullable();
        $table->string('descricao_curta', 200)->nullable();
        $table->decimal('valor', 15, 2);
        $table->string('ciclo', 20);
        $table->unsignedSmallInteger('ciclo_dias')->nullable();
        $table->unsignedSmallInteger('trial_days')->default(0);
        $table->boolean('ativo')->default(true)->index();
        $table->string('fiscal_type', 10)->default('none');
        $table->string('fiscal_cfop', 8)->nullable();
        $table->string('fiscal_servico', 8)->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['business_id', 'slug'], 'rb_plans_biz_slug_unique');
    });

    Schema::dropIfExists('contacts');
    Schema::create('contacts', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->nullable();
        $t->string('name')->nullable();
        $t->string('mobile', 50)->nullable();
        $t->string('landline', 50)->nullable();
        $t->string('email')->nullable();
        $t->string('tax_number', 50)->nullable();
        $t->string('contact_type', 20)->default('customer');
        $t->string('type', 20)->default('customer');
        $t->timestamps();
        $t->softDeletes();
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

    Schema::dropIfExists('rb_subscriptions');
    Schema::create('rb_subscriptions', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedInteger('contact_id')->nullable();
        $table->string('status', 20)->default('active');
        $table->date('start_date')->nullable();
        $table->date('next_due_date')->nullable();
        $table->date('billing_anchor_date')->nullable();
        $table->dateTime('canceled_at')->nullable();
        $table->dateTime('paused_at')->nullable();
        $table->unsignedInteger('conta_bancaria_id')->nullable();
        $table->string('payment_method', 20)->nullable();
        $table->unsignedBigInteger('last_jobsheet_id')->nullable();
        $table->unsignedSmallInteger('total_paid_cached')->default(0);
        $table->unsignedSmallInteger('failed_count_cached')->default(0);
        $table->decimal('total_revenue_cached', 14, 2)->default(0);
        $table->date('paused_until')->nullable();
        $table->string('churn_reason', 64)->nullable();
        $table->string('contact_phone_cached', 32)->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    // Sessão simulada — business_id=1 (ADR 0101) + bypass de auth Spatie via mock controller helper.
    session(['user.business_id' => 1]);
});

afterEach(function () {
    Schema::dropIfExists('rb_subscriptions');
    Schema::dropIfExists('rb_plans');
    session()->flush();
});

/**
 * Bypass: instancia controller + override `ensurePermission` via reflection inválida.
 * Em vez disso, criamos subclass anônima.
 */
function makeControllerSkippingAuth(): PlanController
{
    return new class extends PlanController
    {
        public function __construct()
        {
        }

        // Override ensurePermission pra evitar dependência de auth()->user() + Spatie.
        // Multi-tenant scope NÃO testado aqui (HasBusinessScope é noop sem auth()->check());
        // cobertura cross-tenant feita via where('business_id') explícito no Controller.
        protected function ensurePermission(): void
        {
            // no-op em testes
        }
    };
}

it('R-RB-WAVE6-1 — store cria plano biz=1 com slug auto-gerado', function () {
    $request = StorePlanRequest::create('/recurring-billing/planos', 'POST', [
        'name'        => 'Plano Anual Pro',
        'valor'       => 1200.00,
        'ciclo'       => 'yearly',
        'trial_days'  => 7,
        'ativo'       => true,
        'fiscal_type' => 'none',
    ]);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    $controller = makeControllerSkippingAuth();
    $response = $controller->store($request);

    expect($response->getTargetUrl())->toContain('/recurring-billing/planos');

    $plan = Plan::withoutGlobalScopes()
        ->where('business_id', 1)
        ->where('name', 'Plano Anual Pro')
        ->first();

    expect($plan)->not->toBeNull();
    expect($plan->slug)->toBe('plano-anual-pro');
    expect((float) $plan->valor)->toBe(1200.0);
    expect($plan->ciclo)->toBe('yearly');
    expect((int) $plan->trial_days)->toBe(7);
    expect((bool) $plan->ativo)->toBeTrue();
    expect($plan->fiscal_type)->toBe('none');
});

it('R-RB-WAVE6-2 — update altera valor + ciclo preservando outros campos', function () {
    $plan = Plan::withoutGlobalScopes()->create([
        'business_id'     => 1,
        'name'            => 'Plano Mensal Basic',
        'slug'            => 'plano-mensal-basic',
        'descricao_curta' => 'Pra testes',
        'valor'           => 49.90,
        'ciclo'           => 'monthly',
        'trial_days'      => 0,
        'ativo'           => true,
        'fiscal_type'     => 'none',
    ]);

    $request = UpdatePlanRequest::create("/recurring-billing/planos/{$plan->id}", 'PUT', [
        'name'        => 'Plano Mensal Basic',
        'slug'        => 'plano-mensal-basic',
        'valor'       => 79.90,
        'ciclo'       => 'quarterly',
        'trial_days'  => 0,
        'ativo'       => true,
        'fiscal_type' => 'none',
    ]);
    $request->setRouteResolver(fn () => tap(new \Illuminate\Routing\Route('PUT', '/foo/{id}', []), function ($r) use ($plan) {
        $r->bind(\Illuminate\Http\Request::create("/foo/{$plan->id}"));
        $r->setParameter('id', $plan->id);
    }));
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->validateResolved();

    $controller = makeControllerSkippingAuth();
    $response = $controller->update($request, $plan->id);

    expect($response->getTargetUrl())->toContain('/recurring-billing/planos');

    $plan->refresh();
    expect((float) $plan->valor)->toBe(79.9);
    expect($plan->ciclo)->toBe('quarterly');
    // descricao_curta NÃO enviado no update → Laravel preserva valor existente (canônico).
    expect($plan->descricao_curta)->toBe('Pra testes');
});

it('R-RB-WAVE6-3 — destroy soft-deleta plano sem assinatura ativa', function () {
    $plan = Plan::withoutGlobalScopes()->create([
        'business_id' => 1,
        'name'        => 'Plano Pra Apagar',
        'slug'        => 'plano-pra-apagar',
        'valor'       => 100.0,
        'ciclo'       => 'monthly',
        'ativo'       => true,
        'fiscal_type' => 'none',
    ]);

    $controller = makeControllerSkippingAuth();
    $response = $controller->destroy($plan->id);

    // SoftDeletes trait — deleted_at populado, registro permanece com flag.
    $trashed = Plan::withoutGlobalScopes()->withTrashed()->find($plan->id);
    expect($trashed)->not->toBeNull();
    expect($trashed->deleted_at)->not->toBeNull();

    // Status code default redirect (302).
    expect($response->getStatusCode())->toBe(302);
});

it('R-RB-WAVE6-4 — destroy retorna 422 quando plano tem Subscription ativa', function () {
    $plan = Plan::withoutGlobalScopes()->create([
        'business_id' => 1,
        'name'        => 'Plano Em Uso',
        'slug'        => 'plano-em-uso',
        'valor'       => 200.0,
        'ciclo'       => 'monthly',
        'ativo'       => true,
        'fiscal_type' => 'none',
    ]);

    // Assinatura ativa — bloqueia exclusão.
    Subscription::withoutGlobalScopes()->create([
        'business_id'  => 1,
        'plan_id'      => $plan->id,
        'contact_id'   => 99,
        'status'       => 'active',
        'start_date'   => '2026-01-01',
        'next_due_date' => '2026-06-01',
    ]);

    $controller = makeControllerSkippingAuth();
    $response = $controller->destroy($plan->id);

    expect($response->getStatusCode())->toBe(422);

    // Plano NÃO foi deletado (nem soft).
    $stillThere = Plan::withoutGlobalScopes()->find($plan->id);
    expect($stillThere)->not->toBeNull();
    expect($stillThere->deleted_at)->toBeNull();
});

it('R-RB-WAVE6-5 — cross-tenant edit() biz=99 acessando plano biz=1 dispara 404', function () {
    $plan = Plan::withoutGlobalScopes()->create([
        'business_id' => 1, // biz=1
        'name'        => 'Plano Isolado biz=1',
        'slug'        => 'plano-isolado-biz-1',
        'valor'       => 300.0,
        'ciclo'       => 'monthly',
        'ativo'       => true,
        'fiscal_type' => 'none',
    ]);

    // Trocamos sessão pra biz=99 — simulando outro tenant logado.
    session(['user.business_id' => 99]);

    $controller = makeControllerSkippingAuth();

    // edit() chama Plan::where('business_id', 99)->findOrFail($id) — 404 esperado (ModelNotFoundException).
    expect(fn () => $controller->edit($plan->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    // Garante que o plano biz=1 permanece intacto.
    $original = Plan::withoutGlobalScopes()->find($plan->id);
    expect($original)->not->toBeNull();
    expect((int) $original->business_id)->toBe(1);
});

it('R-RB-WAVE6-6 — store rejeita slug duplicado per business (unique scoped)', function () {
    Plan::withoutGlobalScopes()->create([
        'business_id' => 1,
        'name'        => 'Plano Original',
        'slug'        => 'plano-original',
        'valor'       => 50.0,
        'ciclo'       => 'monthly',
        'ativo'       => true,
        'fiscal_type' => 'none',
    ]);

    $request = StorePlanRequest::create('/recurring-billing/planos', 'POST', [
        'name'        => 'Outro plano com mesmo slug',
        'slug'        => 'plano-original',
        'valor'       => 75.0,
        'ciclo'       => 'monthly',
        'ativo'       => true,
        'fiscal_type' => 'none',
    ]);
    $request->setContainer(app())->setRedirector(app('redirect'));

    expect(fn () => $request->validateResolved())
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    // Mas o MESMO slug em OUTRO business é permitido — testa escopo do unique.
    session(['user.business_id' => 99]);
    $requestOther = StorePlanRequest::create('/recurring-billing/planos', 'POST', [
        'name'        => 'Plano biz=99 com mesmo slug',
        'slug'        => 'plano-original',
        'valor'       => 99.0,
        'ciclo'       => 'monthly',
        'ativo'       => true,
        'fiscal_type' => 'none',
    ]);
    $requestOther->setContainer(app())->setRedirector(app('redirect'));
    $requestOther->validateResolved(); // não lança

    expect(true)->toBeTrue(); // chegou aqui = unique scoped per biz OK
});
