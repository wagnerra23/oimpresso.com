<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Jobs\ProcessInterWebhookJob;

uses(Tests\TestCase::class);

/**
 * US-RB-047 · InterWebhookController — recebe PIX recebido do Banco Inter.
 *
 * Mesmo pattern de `AsaasWebhookIdempotencyTest`: schema mínimo criado
 * manualmente (migrations UltimatePOS legadas não rodam em SQLite).
 *
 * Cobertura:
 *   - 401 sem secret / com secret errado (multi-tenant Tier 0)
 *   - 404 quando business não tem credencial Inter
 *   - 200 + dispatch job + grava `pg_webhook_events` quando válido
 *   - Idempotência: 2× mesmo `endToEndId` → 1 row, 1 dispatch
 *   - Múltiplos PIX em 1 request → 1 dispatch por endToEndId
 *   - PIX sem `endToEndId` é skipado
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
    // pg_webhook_events/rb_boleto_credentials são reais-migradas; o afterEach roda mesmo
    // em teste pulado (PHPUnit 12: tearDown gated só por hasMetRequirements), então dropá-las
    // no MySQL persistente corromperia testes irmãos do módulo. DDL só em sqlite.
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('pg_webhook_events');
        Schema::dropIfExists('rb_boleto_credentials');
    }
});

function seedInterCredential(int $businessId, string $secret = 'sek-abc'): void
{
    DB::table('rb_boleto_credentials')->insert([
        'business_id' => $businessId,
        'banco'       => 'inter',
        'ambiente'    => 'production',
        'ativo'       => true,
        'config_json' => json_encode([
            'webhook_secret' => $secret,
            'client_id'      => 'cid',
        ]),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
}

function pixPayload(string $endToEndId = 'E182361202026050709abc', ?string $txid = 'tx-001'): array
{
    return [
        'pix' => [[
            'endToEndId' => $endToEndId,
            'txid'       => $txid,
            'valor'      => '150.00',
            'horario'    => '2026-05-07T21:00:00Z',
        ]],
    ];
}

it('rejeita 404 quando business não tem credencial Inter ativa', function () {
    Queue::fake();

    $response = $this->postJson('/webhooks/inter/pix/1', pixPayload());

    $response->assertStatus(404)->assertJsonPath('reason', 'credential_not_found');
    Queue::assertNothingPushed();
});

it('rejeita 401 sem header X-Inter-Webhook-Secret', function () {
    Queue::fake();
    seedInterCredential(businessId: 1);

    $response = $this->postJson('/webhooks/inter/pix/1', pixPayload());

    $response->assertStatus(401)->assertJsonPath('reason', 'secret_mismatch');
    Queue::assertNothingPushed();
});

it('rejeita 401 com secret errado', function () {
    Queue::fake();
    seedInterCredential(businessId: 1, secret: 'right-secret');

    $response = $this->withHeaders(['X-Inter-Webhook-Secret' => 'wrong-secret'])
        ->postJson('/webhooks/inter/pix/1', pixPayload());

    $response->assertStatus(401)->assertJsonPath('reason', 'secret_mismatch');
    Queue::assertNothingPushed();
});

it('aceita PIX com secret válido, grava em pg_webhook_events e dispatcha job', function () {
    Queue::fake();
    seedInterCredential(businessId: 1, secret: 'sek-ok');

    $response = $this->withHeaders(['X-Inter-Webhook-Secret' => 'sek-ok'])
        ->postJson('/webhooks/inter/pix/1', pixPayload('E18-001'));

    $response->assertStatus(200)
        ->assertJsonPath('ok', true)
        ->assertJsonPath('accepted', 1);

    expect(DB::table('pg_webhook_events')
        ->where('provider', 'inter')
        ->where('event_id', 'E18-001')
        ->count())->toBe(1);

    Queue::assertPushed(ProcessInterWebhookJob::class, 1);
});

it('idempotência: 2× mesmo endToEndId → 1 row, 1 dispatch (segunda skipa)', function () {
    Queue::fake();
    seedInterCredential(businessId: 1, secret: 'sek-ok');

    $headers = ['X-Inter-Webhook-Secret' => 'sek-ok'];
    $r1 = $this->withHeaders($headers)->postJson('/webhooks/inter/pix/1', pixPayload('E18-dup'));
    $r2 = $this->withHeaders($headers)->postJson('/webhooks/inter/pix/1', pixPayload('E18-dup'));

    $r1->assertJsonPath('accepted', 1);
    $r2->assertJsonPath('accepted', 0)->assertJsonPath('skipped', 1);

    expect(DB::table('pg_webhook_events')->where('event_id', 'E18-dup')->count())->toBe(1);
    Queue::assertPushed(ProcessInterWebhookJob::class, 1);
});

it('múltiplos PIX no mesmo request → 1 dispatch por endToEndId', function () {
    Queue::fake();
    seedInterCredential(businessId: 1, secret: 'sek-ok');

    $payload = ['pix' => [
        ['endToEndId' => 'E18-A', 'txid' => 'tx1', 'valor' => '10', 'horario' => '2026-05-07T20:00:00Z'],
        ['endToEndId' => 'E18-B', 'txid' => 'tx2', 'valor' => '20', 'horario' => '2026-05-07T20:01:00Z'],
        ['endToEndId' => 'E18-C', 'txid' => 'tx3', 'valor' => '30', 'horario' => '2026-05-07T20:02:00Z'],
    ]];

    $response = $this->withHeaders(['X-Inter-Webhook-Secret' => 'sek-ok'])
        ->postJson('/webhooks/inter/pix/1', $payload);

    $response->assertStatus(200)->assertJsonPath('accepted', 3);
    Queue::assertPushed(ProcessInterWebhookJob::class, 3);
});

it('PIX sem endToEndId é skipado (sem dispatch)', function () {
    Queue::fake();
    seedInterCredential(businessId: 1, secret: 'sek-ok');

    $payload = ['pix' => [
        ['txid' => 'tx-no-id', 'valor' => '10', 'horario' => '2026-05-07T20:00:00Z'],
    ]];

    $response = $this->withHeaders(['X-Inter-Webhook-Secret' => 'sek-ok'])
        ->postJson('/webhooks/inter/pix/1', $payload);

    $response->assertJsonPath('accepted', 0)->assertJsonPath('skipped', 1);
    Queue::assertNothingPushed();
});

it('multi-tenant Tier 0: secret de business 1 não funciona pra business 2', function () {
    Queue::fake();
    seedInterCredential(businessId: 1, secret: 'sek-biz-1');
    seedInterCredential(businessId: 2, secret: 'sek-biz-2');

    // Atacante manda secret do biz 1 pro endpoint do biz 2 → 401
    $response = $this->withHeaders(['X-Inter-Webhook-Secret' => 'sek-biz-1'])
        ->postJson('/webhooks/inter/pix/2', pixPayload('E18-cross'));

    $response->assertStatus(401);
    Queue::assertNothingPushed();
});

it('dispatcha job na fila correta (rb_webhooks)', function () {
    Queue::fake();
    seedInterCredential(businessId: 1, secret: 'sek-ok');

    $this->withHeaders(['X-Inter-Webhook-Secret' => 'sek-ok'])
        ->postJson('/webhooks/inter/pix/1', pixPayload('E18-queue'));

    Queue::assertPushedOn('rb_webhooks', ProcessInterWebhookJob::class);
});

it('credencial inativa também rejeita 404', function () {
    Queue::fake();
    DB::table('rb_boleto_credentials')->insert([
        'business_id' => 1,
        'banco'       => 'inter',
        'ativo'       => false,
        'config_json' => json_encode(['webhook_secret' => 'sek']),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $response = $this->withHeaders(['X-Inter-Webhook-Secret' => 'sek'])
        ->postJson('/webhooks/inter/pix/1', pixPayload());

    $response->assertStatus(404);
});
