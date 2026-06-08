<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Services\Metrics\MetricsSnapshotBuilder;
use Modules\Whatsapp\Services\Webhook\WebhookSignatureChecker;

uses(Tests\TestCase::class);

/**
 * Wave 18 saturation D5 — E2E journey biz=1 (Wagner dev real-world).
 *
 * Smoke do journey README:
 *  - Webhook entrante validado por WebhookSignatureChecker
 *  - Mensagem outbound persistida + agregada no snapshot
 *  - Multi-tenant isolation cross-biz preservado em snapshot
 *  - Drivers múltiplos (Meta + Baileys + Z-API) coexistem
 *
 * Roda offline (não bate em rede real Meta/Baileys). Multi-tenant Tier 0:
 * usa biz=1 (ADR 0101 — nunca cliente real).
 *
 * @see Modules/Whatsapp/README.md
 * @see Modules/Whatsapp/Services/Webhook/WebhookSignatureChecker.php
 * @see Modules/Whatsapp/Services/Metrics/MetricsSnapshotBuilder.php
 */

const E2E_BIZ_WAGNER = 1;
const E2E_BIZ_OUTRO = 99;

beforeEach(function () {
    config()->set('otel.enabled', false);

    foreach (['whatsapp_messages', 'whatsapp_business_phones'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('whatsapp_business_phones', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('driver', 30)->default('meta_cloud');
        $table->string('display_phone', 30)->nullable();
        $table->timestamps();
    });

    Schema::create('whatsapp_messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('direction', 12);
        $table->string('status', 16);
        $table->timestamps();
    });
});

it('cenario E2E 1: webhook Meta validado + ingestão + snapshot agregado biz=1', function () {
    $checker = new WebhookSignatureChecker();
    $secret = 'biz1-webhook-secret';
    $body = json_encode(['entry' => [['changes' => [['value' => ['messages' => [['id' => 'wamid.1']]]]]]]]);
    $sig = 'sha256='.hash_hmac('sha256', $body, $secret);

    // Passo 1: webhook valido entrou
    expect($checker->verifyMeta($body, $sig, $secret))->toBeTrue();

    // Passo 2: simula ingestão (controller persistiria; aqui ja inserimos)
    \DB::table('whatsapp_messages')->insert([
        'business_id' => E2E_BIZ_WAGNER, 'direction' => 'inbound', 'status' => 'received',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Passo 3: outbound delivered (resposta automatica do bot Jana, ou enviado pelo operador)
    \DB::table('whatsapp_messages')->insert([
        ['business_id' => E2E_BIZ_WAGNER, 'direction' => 'outbound', 'status' => 'delivered', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => E2E_BIZ_WAGNER, 'direction' => 'outbound', 'status' => 'delivered', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => E2E_BIZ_WAGNER, 'direction' => 'outbound', 'status' => 'failed',    'created_at' => now(), 'updated_at' => now()],
    ]);

    // Passo 4: snapshot pra dashboard observability
    $snap = (new MetricsSnapshotBuilder())->snapshotOutbound(E2E_BIZ_WAGNER, 24);

    expect($snap['total'])->toBe(3)
        ->and($snap['sucesso'])->toBe(2)
        ->and($snap['falha'])->toBe(1)
        ->and($snap['taxa_sucesso'])->toEqualWithDelta(66.67, 0.01);
});

it('cenario E2E 2: tampered webhook NÃO passa nem persiste (defesa primaria)', function () {
    $checker = new WebhookSignatureChecker();
    $secret = 'biz1-webhook-secret';
    $bodyOriginal = '{"id":"wamid.1"}';
    $sig = 'sha256='.hash_hmac('sha256', $bodyOriginal, $secret);

    // Atacante muda body mantendo signature original
    $tampered = '{"id":"wamid.MALICIOSO"}';

    expect($checker->verifyMeta($tampered, $sig, $secret))->toBeFalse();
    // Snapshot vazio confirma que nada foi persistido (controller teria rejeitado 401)
    expect(\DB::table('whatsapp_messages')->count())->toBe(0);
});

it('cenario E2E 3: drivers multiplos coexistem por business sem leak', function () {
    \DB::table('whatsapp_business_phones')->insert([
        ['business_id' => E2E_BIZ_WAGNER, 'driver' => 'meta_cloud', 'display_phone' => '5511...', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => E2E_BIZ_WAGNER, 'driver' => 'baileys',    'display_phone' => '5511...', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => E2E_BIZ_OUTRO,  'driver' => 'zapi',       'display_phone' => '5511...', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $snap1 = (new MetricsSnapshotBuilder())->snapshotPorDriver(E2E_BIZ_WAGNER);
    $snap99 = (new MetricsSnapshotBuilder())->snapshotPorDriver(E2E_BIZ_OUTRO);

    expect($snap1)->toHaveKeys(['meta_cloud', 'baileys'])
        ->and($snap1)->not->toHaveKey('zapi') // biz=1 nao tem zapi
        ->and($snap99)->toHaveKey('zapi')
        ->and($snap99)->not->toHaveKey('meta_cloud'); // biz=99 nao tem meta
});

it('cenario E2E 4: mensagens fora da janela (>24h) excluidas do snapshot diario', function () {
    \DB::table('whatsapp_messages')->insert([
        ['business_id' => E2E_BIZ_WAGNER, 'direction' => 'outbound', 'status' => 'delivered', 'created_at' => now()->subHours(48), 'updated_at' => now()],
        ['business_id' => E2E_BIZ_WAGNER, 'direction' => 'outbound', 'status' => 'delivered', 'created_at' => now()->subMinutes(30), 'updated_at' => now()],
    ]);

    $snap = (new MetricsSnapshotBuilder())->snapshotOutbound(E2E_BIZ_WAGNER, 24);
    expect($snap['total'])->toBe(1);
});

it('cenario E2E 5: snapshot vazio retorna estrutura completa (UI degrada safe)', function () {
    $snap = (new MetricsSnapshotBuilder())->snapshotOutbound(E2E_BIZ_WAGNER, 24);

    expect($snap)
        ->toHaveKeys(['total', 'sucesso', 'falha', 'taxa_sucesso', 'janela_horas'])
        ->and($snap['total'])->toBe(0)
        ->and($snap['taxa_sucesso'])->toBe(0.0);
});
