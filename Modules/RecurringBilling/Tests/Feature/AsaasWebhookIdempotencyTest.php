<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Jobs\ProcessAsaasWebhookJob;

uses(Tests\TestCase::class);

/**
 * US-RB-041 · Idempotência do webhook Asaas.
 *
 * ADR tech/0001-idempotencia-charge-attempts-e-webhooks define o contrato:
 * webhook duplicado pelo Asaas (ACONTECE em produção) não pode causar
 * processamento dobrado → cobrança duplicada → incidente.
 *
 * Estratégia testada:
 *   1. Tabela pg_webhook_events com UNIQUE(provider, event_id)
 *   2. Controller verifica `WHERE event_id` antes de inserir/dispatch
 *   3. Segunda chamada com mesmo event_id retorna 200 + skipped:duplicate
 *      sem dispatchar job
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    // Cria tabela manualmente (sem RefreshDatabase — migrations UltimatePOS
    // legadas não rodam em SQLite — ver BoletoServiceTest pra contexto)
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

    // Credencial Asaas exigida pela validação Tier 0 do webhook (asaas-access-token).
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

function seedAsaasCredential(int $businessId, string $secret = 'asaas-tok-ok'): void
{
    DB::table('rb_boleto_credentials')->insert([
        'business_id' => $businessId,
        'banco'       => 'asaas',
        'ambiente'    => 'production',
        'ativo'       => true,
        'config_json' => json_encode(['webhook_secret' => $secret]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
}

/** Header de autenticação do webhook Asaas com token válido. */
function asaasAuth(string $secret = 'asaas-tok-ok'): array
{
    return ['asaas-access-token' => $secret];
}

it('aceita webhook novo, registra em pg_webhook_events e dispatcha job', function () {
    Queue::fake();
    seedAsaasCredential(businessId: 1);

    $payload = [
        'id'    => 'evt_abc123',
        'event' => 'PAYMENT_RECEIVED',
        'payment' => [
            'id' => 'pay_xyz', 'value' => 150.00,
            'externalReference' => 'INV-001',
            'paymentDate' => '2026-05-06',
        ],
    ];

    $response = $this->withHeaders(asaasAuth())->postJson('/api/webhooks/asaas/1', $payload);

    $response->assertStatus(200);
    $response->assertJsonPath('ok', true);

    expect(DB::table('pg_webhook_events')->where('event_id', 'evt_abc123')->count())
        ->toBe(1);

    Queue::assertPushed(ProcessAsaasWebhookJob::class, 1);
});

it('rejeita 2ª chamada com mesmo event_id sem dispatchar job (idempotência)', function () {
    Queue::fake();
    seedAsaasCredential(businessId: 1);

    $payload = [
        'id'    => 'evt_dup',
        'event' => 'PAYMENT_RECEIVED',
        'payment' => ['id' => 'pay_x', 'value' => 50, 'externalReference' => 'INV-2'],
    ];

    $r1 = $this->withHeaders(asaasAuth())->postJson('/api/webhooks/asaas/1', $payload);
    $r2 = $this->withHeaders(asaasAuth())->postJson('/api/webhooks/asaas/1', $payload);

    $r1->assertStatus(200)->assertJsonPath('ok', true);
    $r2->assertStatus(200)->assertJsonPath('skipped', 'duplicate');

    expect(DB::table('pg_webhook_events')->where('event_id', 'evt_dup')->count())
        ->toBe(1);

    Queue::assertPushed(ProcessAsaasWebhookJob::class, 1);
});

it('gera event_id determinístico via md5(event+payment.id) quando Asaas não envia id', function () {
    Queue::fake();
    seedAsaasCredential(businessId: 1);

    $payload = [
        'event' => 'PAYMENT_CONFIRMED',
        'payment' => ['id' => 'pay_no_evt', 'value' => 100, 'externalReference' => 'INV-3'],
    ];

    $r1 = $this->withHeaders(asaasAuth())->postJson('/api/webhooks/asaas/1', $payload);
    $r2 = $this->withHeaders(asaasAuth())->postJson('/api/webhooks/asaas/1', $payload);

    $r1->assertStatus(200);
    $r2->assertJsonPath('skipped', 'duplicate');

    $expectedId = md5('PAYMENT_CONFIRMED' . 'pay_no_evt');
    expect(DB::table('pg_webhook_events')->where('event_id', $expectedId)->count())
        ->toBe(1);
});

it('UNIQUE constraint pg_webhook_events(provider, event_id) é enforced no DB', function () {
    DB::table('pg_webhook_events')->insert([
        'provider' => 'asaas', 'event_id' => 'evt_unique',
        'event_type' => 'PAYMENT_RECEIVED', 'payload' => '{}',
        'business_id' => 1, 'processed' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => DB::table('pg_webhook_events')->insert([
        'provider' => 'asaas', 'event_id' => 'evt_unique',
        'event_type' => 'PAYMENT_RECEIVED', 'payload' => '{}',
        'business_id' => 1, 'processed' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('eventos de providers diferentes podem ter mesmo event_id (cross-provider OK)', function () {
    DB::table('pg_webhook_events')->insert([
        'provider' => 'asaas', 'event_id' => 'shared_id',
        'event_type' => 'X', 'payload' => '{}',
        'business_id' => 1, 'processed' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Mesmo event_id mas provider diferente → não viola UNIQUE
    DB::table('pg_webhook_events')->insert([
        'provider' => 'inter', 'event_id' => 'shared_id',
        'event_type' => 'Y', 'payload' => '{}',
        'business_id' => 1, 'processed' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(DB::table('pg_webhook_events')->where('event_id', 'shared_id')->count())
        ->toBe(2);
});

it('dispatcha job na fila correta (rb_webhooks)', function () {
    Queue::fake();
    seedAsaasCredential(businessId: 1);

    $payload = [
        'id'    => 'evt_queue_check',
        'event' => 'PAYMENT_RECEIVED',
        'payment' => ['id' => 'p', 'value' => 1, 'externalReference' => 'X'],
    ];

    $this->withHeaders(asaasAuth())->postJson('/api/webhooks/asaas/1', $payload);

    Queue::assertPushedOn('rb_webhooks', ProcessAsaasWebhookJob::class);
});

it('persiste event_type, business_id e payload completo em pg_webhook_events', function () {
    Queue::fake();
    seedAsaasCredential(businessId: 77);

    $payload = [
        'id'    => 'evt_full_payload',
        'event' => 'PAYMENT_OVERDUE',
        'payment' => ['id' => 'pay_overdue', 'externalReference' => 'INV-OVD'],
        'extraData' => 'vai pro JSON',
    ];

    $this->withHeaders(asaasAuth())->postJson('/api/webhooks/asaas/77', $payload);

    $row = DB::table('pg_webhook_events')->where('event_id', 'evt_full_payload')->first();

    expect($row)->not()->toBeNull()
        ->and($row->event_type)->toBe('PAYMENT_OVERDUE')
        ->and($row->business_id)->toBe(77)
        ->and((bool) $row->processed)->toBeFalse() // SQLite retorna int 0; bool cast funciona em SQLite e MySQL
        ->and(json_decode($row->payload, true))->toMatchArray([
            'id' => 'evt_full_payload',
            'event' => 'PAYMENT_OVERDUE',
            'extraData' => 'vai pro JSON',
        ]);
});
