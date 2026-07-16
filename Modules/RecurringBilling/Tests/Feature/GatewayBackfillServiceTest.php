<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\RecurringBilling\Models\BoletoCredential;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Models\SubscriptionEvent;
use Modules\RecurringBilling\Services\GatewayBackfillService;

uses(Tests\TestCase::class);

/**
 * US-RB-052 — Pest cobrindo GatewayBackfillService (backfill gateway dormentes).
 *
 * SQLite in-memory pattern (espelha InvoiceGeneratorServiceTest) — migrations
 * legadas UltimatePOS usam sintaxe MySQL-only.
 *
 * Multi-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
 * + biz=1 ([ADR 0101](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)):
 * NUNCA biz=4 (ROTA LIVRE PROD).
 *
 * Âncora de contrato (não tautológico): US-RB-052 DoD (SPEC RecurringBilling)
 * + ADR arq/0007 (credencial da conta é a fonte do gateway) + BoletoService
 * drivers suportados {inter, c6, asaas}.
 *
 * Cenários:
 *  1. Atribui via FK reverso credencial→conta (fonte credencial_da_conta)
 *  2. Atribui via FK direto conta.rb_gateway_credential_id
 *  3. Fallback credencial_por_banco (banco_codigo 336 → c6)
 *  4. Cora (403) bloqueada — driver_nao_suportado:cora
 *  5. Sem credencial ativa → bloqueada sem_credencial_ativa
 *  6. Dry-run (default) NÃO escreve nada
 *  7. Idempotência: 2ª execução skipa; 1 SubscriptionEvent só
 *  8. Cross-tenant Tier 0: biz=99 não é tocado; credencial de biz=99 não vaza pra biz=1
 *  9. Sem conta bancária → bloqueada sem_conta_bancaria
 * 10. Cancelada não é tocada
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite'
        || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Smoke test rodado apenas em SQLite in-memory.');
    }

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
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::dropIfExists('fin_contas_bancarias');
    Schema::create('fin_contas_bancarias', function ($t) {
        $t->increments('id');
        $t->unsignedInteger('business_id')->index();
        $t->string('banco_codigo', 5)->nullable();
        $t->boolean('ativo_para_boleto')->default(false);
        $t->unsignedBigInteger('rb_gateway_credential_id')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::dropIfExists('rb_boleto_credentials');
    Schema::create('rb_boleto_credentials', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedInteger('conta_bancaria_id')->nullable();
        $t->string('banco', 20);
        $t->string('ambiente', 20)->default('production');
        $t->boolean('ativo')->default(true);
        $t->json('config_json')->nullable();
        $t->string('nome_display', 100)->nullable();
        $t->timestamps();
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
    // DDL só em sqlite :memory: (PHPUnit 12 roda tearDown mesmo em teste pulado).
    if (config('database.default') === 'sqlite'
        && str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('rb_subscription_events');
        Schema::dropIfExists('rb_boleto_credentials');
        Schema::dropIfExists('fin_contas_bancarias');
        Schema::dropIfExists('rb_subscriptions');
    }
    session()->flush();
});

function makeConta(int $bizId, ?string $bancoCodigo, ?int $credId = null): ContaBancaria
{
    return ContaBancaria::create([
        'business_id'              => $bizId,
        'banco_codigo'             => $bancoCodigo,
        'ativo_para_boleto'        => true,
        'rb_gateway_credential_id' => $credId,
    ]);
}

function makeCredencial(int $bizId, string $banco, ?int $contaBancariaId = null, bool $ativo = true): BoletoCredential
{
    return BoletoCredential::create([
        'business_id'       => $bizId,
        'conta_bancaria_id' => $contaBancariaId,
        'banco'             => $banco,
        'ambiente'          => 'production',
        'ativo'             => $ativo,
        'nome_display'      => "Cred {$banco} teste",
    ]);
}

function makeSubGatewayNull(int $bizId, ?int $contaBancariaId, string $status = 'active', ?array $metadata = null): Subscription
{
    return Subscription::create([
        'business_id'       => $bizId,
        'plan_id'           => 1,
        'contact_id'        => 999,
        'status'            => $status,
        'start_date'        => '2026-07-01',
        'next_due_date'     => '2026-08-10',
        'conta_bancaria_id' => $contaBancariaId,
        'payment_method'    => 'boleto',
        // Espelha a migração WR2: metadata SEM a chave 'gateway'
        'metadata'          => $metadata ?? ['source' => 'wr2-migration-test'],
    ]);
}

test('1. Atribui via FK reverso credencial→conta (credencial_da_conta)', function () {
    $conta = makeConta(1, '077');
    makeCredencial(1, 'inter', $conta->id);
    $sub = makeSubGatewayNull(1, $conta->id);

    $stats = (new GatewayBackfillService())->run(1, execute: true);

    expect($stats['total_null'])->toBe(1);
    expect($stats['atribuidas'])->toBe(1);
    expect($stats['bloqueadas'])->toBe(0);
    expect($stats['por_gateway'])->toBe(['inter' => 1]);

    $sub->refresh();
    expect($sub->metadata['gateway'])->toBe('inter');
    expect($sub->metadata['gateway_backfill']['fonte'])->toBe('credencial_da_conta');
    expect($sub->metadata['source'])->toBe('wr2-migration-test'); // metadata existente preservado

    $event = SubscriptionEvent::where('subscription_id', $sub->id)->first();
    expect($event)->not->toBeNull();
    expect($event->by_actor)->toBe('system:rb:backfill-gateway');
});

test('2. Atribui via FK direto conta.rb_gateway_credential_id', function () {
    $cred = makeCredencial(1, 'inter');
    $conta = makeConta(1, '077', $cred->id);
    $sub = makeSubGatewayNull(1, $conta->id);

    $stats = (new GatewayBackfillService())->run(1, execute: true);

    expect($stats['atribuidas'])->toBe(1);
    $sub->refresh();
    expect($sub->metadata['gateway'])->toBe('inter');
});

test('3. Fallback credencial_por_banco: conta 336 sem FK, credencial c6 do business', function () {
    $conta = makeConta(1, '336');
    makeCredencial(1, 'c6'); // sem vínculo a conta
    $sub = makeSubGatewayNull(1, $conta->id);

    $stats = (new GatewayBackfillService())->run(1, execute: true);

    expect($stats['atribuidas'])->toBe(1);
    $sub->refresh();
    expect($sub->metadata['gateway'])->toBe('c6');
    expect($sub->metadata['gateway_backfill']['fonte'])->toBe('credencial_por_banco');
});

test('4. Cora (403) bloqueada — driver_nao_suportado', function () {
    $conta = makeConta(1, '403');
    $sub = makeSubGatewayNull(1, $conta->id);

    $stats = (new GatewayBackfillService())->run(1, execute: true);

    expect($stats['atribuidas'])->toBe(0);
    expect($stats['bloqueadas'])->toBe(1);
    expect($stats['por_motivo'])->toHaveKey('driver_nao_suportado:cora');

    $sub->refresh();
    expect($sub->metadata['gateway'] ?? null)->toBeNull();
});

test('5. Sem credencial ativa → bloqueada (credencial inativa NÃO conta)', function () {
    $conta = makeConta(1, '077');
    makeCredencial(1, 'inter', $conta->id, ativo: false); // inativa
    $sub = makeSubGatewayNull(1, $conta->id);

    $stats = (new GatewayBackfillService())->run(1, execute: true);

    expect($stats['atribuidas'])->toBe(0);
    expect($stats['por_motivo'])->toHaveKey('sem_credencial_ativa');
    $sub->refresh();
    expect($sub->metadata['gateway'] ?? null)->toBeNull();
});

test('6. Dry-run (default) NÃO escreve nada', function () {
    $conta = makeConta(1, '077');
    makeCredencial(1, 'inter', $conta->id);
    $sub = makeSubGatewayNull(1, $conta->id);

    $stats = (new GatewayBackfillService())->run(1); // execute default false

    expect($stats['atribuidas'])->toBe(1); // simula
    $sub->refresh();
    expect($sub->metadata['gateway'] ?? null)->toBeNull(); // NADA escrito
    expect(SubscriptionEvent::where('subscription_id', $sub->id)->count())->toBe(0);
});

test('7. Idempotência: 2ª execução skipa e não duplica audit', function () {
    $conta = makeConta(1, '077');
    makeCredencial(1, 'inter', $conta->id);
    $sub = makeSubGatewayNull(1, $conta->id);

    $service = new GatewayBackfillService();
    $service->run(1, execute: true);
    $stats2 = $service->run(1, execute: true);

    expect($stats2['total_null'])->toBe(0);
    expect($stats2['atribuidas'])->toBe(0);
    expect($stats2['skipped'])->toBe(1);
    expect(SubscriptionEvent::where('subscription_id', $sub->id)->count())->toBe(1);
});

test('8. Cross-tenant Tier 0: biz=99 intocado; credencial biz=99 não vaza pra biz=1', function () {
    // biz=1: conta inter SEM credencial própria
    $conta1 = makeConta(1, '077');
    $sub1 = makeSubGatewayNull(1, $conta1->id);

    // biz=99: credencial inter ativa + sub própria
    session(['user.business_id' => 99]);
    $conta99 = makeConta(99, '077');
    makeCredencial(99, 'inter', $conta99->id);
    $sub99 = makeSubGatewayNull(99, $conta99->id);
    session(['user.business_id' => 1]);

    $stats = (new GatewayBackfillService())->run(1, execute: true);

    // credencial de biz=99 NÃO pode destravar sub de biz=1
    expect($stats['atribuidas'])->toBe(0);
    expect($stats['por_motivo'])->toHaveKey('sem_credencial_ativa');

    // sub de biz=99 NÃO foi tocada pelo run(1)
    session(['user.business_id' => 99]);
    $sub99->refresh();
    expect($sub99->metadata['gateway'] ?? null)->toBeNull();
    expect(SubscriptionEvent::where('subscription_id', $sub99->id)->count())->toBe(0);
});

test('9. Sem conta bancária → bloqueada sem_conta_bancaria', function () {
    makeCredencial(1, 'inter');
    $sub = makeSubGatewayNull(1, null);

    $stats = (new GatewayBackfillService())->run(1, execute: true);

    expect($stats['bloqueadas'])->toBe(1);
    expect($stats['por_motivo'])->toHaveKey('sem_conta_bancaria');
});

test('10. Cancelada não é tocada', function () {
    $conta = makeConta(1, '077');
    makeCredencial(1, 'inter', $conta->id);
    $sub = makeSubGatewayNull(1, $conta->id, status: 'canceled');

    $stats = (new GatewayBackfillService())->run(1, execute: true);

    expect($stats['total_null'])->toBe(0);
    expect($stats['atribuidas'])->toBe(0);
    $sub->refresh();
    expect($sub->metadata['gateway'] ?? null)->toBeNull();
});
