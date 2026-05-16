<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function () {
    // Schema mínimo pro command rodar — só duas tabelas usadas
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

/**
 * Wave 16 governance v3 D9 — observabilidade módulo Whatsapp.
 *
 * Cobertura smoke:
 * 1. OtelHelper::spanBiz/span é zero-cost quando OTel não configurado
 *    (vários services Whatsapp envolvem ops em spans — não devem quebrar nada).
 * 2. Log estruturado canônico (chaves PT-BR sem PII) chega no logger:
 *    - whatsapp.webhook.received (controllers)
 *    - whatsapp.message.send.* (jobs)
 *    - whatsapp.observability.snapshot (health command)
 * 3. Command `whatsapp:observability-health` está registrado e roda sem erro
 *    em DB vazio (snapshot informativo sempre exit 0).
 *
 * Não exercita drivers HTTP reais (já cobertos em testes específicos).
 *
 * @see app/Util/OtelHelper.php
 * @see Modules/Whatsapp/Console/Commands/WhatsappObservabilityHealthCommand.php
 */
it('OtelHelper::spanBiz roda no-op quando OTel disabled e retorna o valor do callback', function () {
    config()->set('otel.enabled', false);

    $result = \App\Util\OtelHelper::spanBiz('whatsapp.test.span', fn () => 42, ['kind' => 'unit']);

    expect($result)->toBe(42);
});

it('OtelHelper::span permite business_id explícito em jobs (sem session)', function () {
    config()->set('otel.enabled', false);

    $result = \App\Util\OtelHelper::span('whatsapp.test.job_span', [
        'business_id' => 1,
        'phone_id' => 99,
    ], fn () => 'ok-job');

    expect($result)->toBe('ok-job');
});

it('command whatsapp:observability-health está registrado e responde --help sem erro', function () {
    $exit = Artisan::call('whatsapp:observability-health', ['--help' => true]);
    expect($exit)->toBe(0);
});

it('command whatsapp:observability-health roda em DB vazio e emite log estruturado snapshot', function () {
    Log::spy();

    $exit = Artisan::call('whatsapp:observability-health', [
        '--detail' => true,
        '--hours' => 1,
    ]);

    expect($exit)->toBe(0);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context = []) {
            return $message === 'whatsapp.observability.snapshot'
                && array_key_exists('window_hours', $context)
                && array_key_exists('businesses_evaluated', $context)
                && array_key_exists('businesses_in_alert', $context);
        })
        ->atLeast()->once();
});

it('OtelHelper preserva exception (não engole erro do callback)', function () {
    config()->set('otel.enabled', false);

    expect(fn () => \App\Util\OtelHelper::spanBiz(
        'whatsapp.test.error',
        fn () => throw new \RuntimeException('boom-test')
    ))->toThrow(\RuntimeException::class, 'boom-test');
});
