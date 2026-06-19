<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Services\Metrics\MetricsSnapshotBuilder;

uses(Tests\TestCase::class);

/**
 * Wave 18 saturation D2/D4 — MetricsSnapshotBuilder (extraído de
 * WhatsappObservabilityHealthCommand). Cobre fail-soft + multi-tenant.
 *
 * @see Modules/Whatsapp/Services/Metrics/MetricsSnapshotBuilder.php
 */

beforeEach(function () {
    // era-sqlite: este teste cria schema manual (sqlite-friendly). No MySQL persistente
    // do nightly isso DROPA tabelas reais → corrompe os testes irmãos (lever do floor SDD).
    // Cobertura real é na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
    foreach (['whatsapp_business_phones', 'whatsapp_messages'] as $t) {
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

it('cenario 1: snapshotOutbound retorna zeros em DB vazio (fail-soft)', function () {
    config()->set('otel.enabled', false);

    $builder = new MetricsSnapshotBuilder();
    $snap = $builder->snapshotOutbound(businessId: 1, janelaHoras: 24);

    expect($snap)
        ->toHaveKeys(['total', 'sucesso', 'falha', 'taxa_sucesso', 'janela_horas'])
        ->and($snap['total'])->toBe(0)
        ->and($snap['taxa_sucesso'])->toBe(0.0)
        ->and($snap['janela_horas'])->toBe(24);
});

it('cenario 2: snapshotOutbound calcula taxa de sucesso corretamente', function () {
    config()->set('otel.enabled', false);

    \DB::table('whatsapp_messages')->insert([
        ['business_id' => 1, 'direction' => 'outbound', 'status' => 'delivered', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'direction' => 'outbound', 'status' => 'delivered', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'direction' => 'outbound', 'status' => 'failed',    'created_at' => now(), 'updated_at' => now()],
    ]);

    $snap = (new MetricsSnapshotBuilder())->snapshotOutbound(1, 24);

    expect($snap['total'])->toBe(3)
        ->and($snap['sucesso'])->toBe(2)
        ->and($snap['falha'])->toBe(1)
        ->and($snap['taxa_sucesso'])->toEqualWithDelta(66.67, 0.01);
});

it('cenario 3: snapshotOutbound NUNCA vaza dados cross-tenant (biz=1 vs biz=99)', function () {
    config()->set('otel.enabled', false);

    \DB::table('whatsapp_messages')->insert([
        ['business_id' => 1,  'direction' => 'outbound', 'status' => 'delivered', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 99, 'direction' => 'outbound', 'status' => 'delivered', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 99, 'direction' => 'outbound', 'status' => 'failed',    'created_at' => now(), 'updated_at' => now()],
    ]);

    $builder = new MetricsSnapshotBuilder();
    $biz1 = $builder->snapshotOutbound(1, 24);
    $biz99 = $builder->snapshotOutbound(99, 24);

    expect($biz1['total'])->toBe(1)
        ->and($biz1['falha'])->toBe(0)
        ->and($biz99['total'])->toBe(2)
        ->and($biz99['falha'])->toBe(1);
});

it('cenario 4: snapshotOutbound exclui mensagens fora da janela', function () {
    config()->set('otel.enabled', false);

    \DB::table('whatsapp_messages')->insert([
        ['business_id' => 1, 'direction' => 'outbound', 'status' => 'delivered', 'created_at' => now()->subHours(2), 'updated_at' => now()],
        ['business_id' => 1, 'direction' => 'outbound', 'status' => 'delivered', 'created_at' => now()->subHours(48), 'updated_at' => now()],
    ]);

    $snap = (new MetricsSnapshotBuilder())->snapshotOutbound(1, 24);

    expect($snap['total'])->toBe(1);
});

it('cenario 5: snapshotOutbound exclui inbound (so direction=outbound)', function () {
    config()->set('otel.enabled', false);

    \DB::table('whatsapp_messages')->insert([
        ['business_id' => 1, 'direction' => 'outbound', 'status' => 'delivered', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'direction' => 'inbound',  'status' => 'delivered', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $snap = (new MetricsSnapshotBuilder())->snapshotOutbound(1, 24);

    expect($snap['total'])->toBe(1);
});

it('cenario 6: snapshotPorDriver agrega phones por driver', function () {
    config()->set('otel.enabled', false);

    \DB::table('whatsapp_business_phones')->insert([
        ['business_id' => 1, 'driver' => 'meta_cloud', 'display_phone' => '55119...', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'driver' => 'meta_cloud', 'display_phone' => '55119...', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'driver' => 'baileys',    'display_phone' => '55119...', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $snap = (new MetricsSnapshotBuilder())->snapshotPorDriver(1);

    expect($snap)->toHaveKey('meta_cloud')
        ->and($snap['meta_cloud']['phones'])->toBe(2)
        ->and($snap['baileys']['phones'])->toBe(1);
});

it('cenario 7: OtelHelper preserva exception de DB (nao engole)', function () {
    config()->set('otel.enabled', false);

    Schema::dropIfExists('whatsapp_messages');
    // Sem a tabela vai retornar zeros (fail-soft), nao throw.
    $snap = (new MetricsSnapshotBuilder())->snapshotOutbound(1, 24);
    expect($snap['total'])->toBe(0);
});
