<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Jobs\ProcessAsaasWebhookJob;

uses(Tests\TestCase::class);

/**
 * FIX SEC — AsaasWebhookController exige autenticação antes de creditar dinheiro.
 *
 * Buraco fechado: a rota só tinha `throttle:60,1`; o controller NÃO validava
 * assinatura/secret, então qualquer atacante POST-ava um `PAYMENT_RECEIVED`
 * com valor arbitrário e o sistema dispatchava o job de crédito.
 *
 * Fix replica o pattern do `InterWebhookController`: valida o header
 * `asaas-access-token` via hash_equals contra `config_json.webhook_secret`
 * da credencial Asaas ATIVA do business (escopada por business_id — Tier 0),
 * ANTES de gravar `pg_webhook_events` ou dispatchar o job.
 *
 * Cobertura (prova do exploit fechado):
 *   - 404 quando business não tem credencial Asaas ativa
 *   - 401 sem header asaas-access-token
 *   - 401 com token errado (atacante)
 *   - 200 + dispatch quando token válido (comportamento legítimo preservado)
 *   - multi-tenant Tier 0: token do business A NÃO credita no business B
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    Schema::dropIfExists('pg_webhook_events');
    Schema::create('pg_webhook_events', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->string('provider', 30)->index();
        $table->string('event_id', 100);
        $table->string('event_type', 60);
        $table->json('payload');
        $table->boolean('processed')->default(false)->index();
        $table->timestamps();
        $table->unique(['provider', 'event_id'], 'pg_webhook_idempotency');
    });

    Schema::dropIfExists('rb_boleto_credentials');
    Schema::create('rb_boleto_credentials', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->string('banco', 20);
        $table->string('ambiente', 20)->default('production');
        $table->boolean('ativo')->default(true);
        $table->json('config_json');
        $table->timestamps();
    });
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('pg_webhook_events');
        Schema::dropIfExists('rb_boleto_credentials');
    }
});

function seedAsaasCred(int $businessId, string $secret = 'asaas-tok-ok', bool $ativo = true): void
{
    DB::table('rb_boleto_credentials')->insert([
        'business_id' => $businessId,
        'banco'       => 'asaas',
        'ambiente'    => 'production',
        'ativo'       => $ativo,
        'config_json' => json_encode(['webhook_secret' => $secret]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
}

function paymentReceivedPayload(string $eventId = 'evt_sec', float $value = 999999.00): array
{
    return [
        'id'    => $eventId,
        'event' => 'PAYMENT_RECEIVED',
        'payment' => [
            'id'                => 'pay_sec',
            'value'             => $value, // valor do atacante — não pode ser creditado sem auth
            'externalReference' => 'INV-SEC',
            'paymentDate'       => '2026-06-22',
        ],
    ];
}

it('rejeita 404 quando business não tem credencial Asaas ativa', function () {
    Queue::fake();

    $response = $this->postJson('/api/webhooks/asaas/1', paymentReceivedPayload());

    $response->assertStatus(404)->assertJsonPath('reason', 'credential_not_found');
    Queue::assertNothingPushed();
    expect(DB::table('pg_webhook_events')->count())->toBe(0);
});

it('EXPLOIT: webhook sem header asaas-access-token NÃO credita (401)', function () {
    Queue::fake();
    seedAsaasCred(businessId: 1);

    $response = $this->postJson('/api/webhooks/asaas/1', paymentReceivedPayload());

    $response->assertStatus(401)->assertJsonPath('reason', 'secret_mismatch');
    Queue::assertNothingPushed();
    // Nada gravado → job de crédito nunca roda
    expect(DB::table('pg_webhook_events')->count())->toBe(0);
});

it('EXPLOIT: webhook com token errado (atacante) NÃO credita (401)', function () {
    Queue::fake();
    seedAsaasCred(businessId: 1, secret: 'segredo-real');

    $response = $this->withHeaders(['asaas-access-token' => 'token-do-atacante'])
        ->postJson('/api/webhooks/asaas/1', paymentReceivedPayload());

    $response->assertStatus(401)->assertJsonPath('reason', 'secret_mismatch');
    Queue::assertNothingPushed();
    expect(DB::table('pg_webhook_events')->count())->toBe(0);
});

it('aceita e dispatcha quando token é válido (comportamento legítimo preservado)', function () {
    Queue::fake();
    seedAsaasCred(businessId: 1, secret: 'asaas-ok');

    $response = $this->withHeaders(['asaas-access-token' => 'asaas-ok'])
        ->postJson('/api/webhooks/asaas/1', paymentReceivedPayload('evt_ok'));

    $response->assertStatus(200)->assertJsonPath('ok', true);

    expect(DB::table('pg_webhook_events')
        ->where('provider', 'asaas')
        ->where('event_id', 'evt_ok')
        ->count())->toBe(1);

    Queue::assertPushed(ProcessAsaasWebhookJob::class, 1);
});

it('credencial Asaas inativa rejeita 404 (não autentica contra credencial desligada)', function () {
    Queue::fake();
    seedAsaasCred(businessId: 1, secret: 'asaas-ok', ativo: false);

    $response = $this->withHeaders(['asaas-access-token' => 'asaas-ok'])
        ->postJson('/api/webhooks/asaas/1', paymentReceivedPayload());

    $response->assertStatus(404)->assertJsonPath('reason', 'credential_not_found');
    Queue::assertNothingPushed();
});

it('multi-tenant Tier 0: token do business 1 NÃO credita no business 2', function () {
    Queue::fake();
    seedAsaasCred(businessId: 1, secret: 'tok-biz-1');
    seedAsaasCred(businessId: 2, secret: 'tok-biz-2');

    // Atacante usa o token do biz 1 contra o endpoint do biz 2 → 401, nada dispatchado
    $response = $this->withHeaders(['asaas-access-token' => 'tok-biz-1'])
        ->postJson('/api/webhooks/asaas/2', paymentReceivedPayload('evt_cross'));

    $response->assertStatus(401)->assertJsonPath('reason', 'secret_mismatch');
    Queue::assertNothingPushed();
    expect(DB::table('pg_webhook_events')->where('business_id', 2)->count())->toBe(0);
});
