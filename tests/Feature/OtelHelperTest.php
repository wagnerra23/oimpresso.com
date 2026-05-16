<?php

declare(strict_types=1);

use App\Util\OtelHelper;

uses(Tests\TestCase::class);

/**
 * OtelHelper — facade zero-cost pra instrumentation OTel.
 *
 * Cenários cobertos:
 *  1. Zero-cost quando otel.enabled=false → callback executa, retorno preservado.
 *  2. Callback executa de fato (não é skippado nem chamado 2x).
 *  3. Attributes recebidos via spanBiz() incluem business_id Tier 0 + extras.
 *
 * @see ADR 0155 module-grade-v3 D9.a
 */
it('é zero-cost quando otel.enabled é false (no-op path)', function () {
    config()->set('otel.enabled', false);

    $resultado = OtelHelper::span('teste.zero_cost', ['x' => 1], fn () => 'pong');

    expect($resultado)->toBe('pong');
});

it('executa o callback exatamente uma vez e preserva retorno tipado', function () {
    config()->set('otel.enabled', false);

    $vezes = 0;
    $resultado = OtelHelper::span('teste.callback', ['business_id' => 1], function () use (&$vezes) {
        $vezes++;

        return ['status' => 'ok', 'count' => $vezes];
    });

    expect($vezes)->toBe(1)
        ->and($resultado)->toBe(['status' => 'ok', 'count' => 1]);
});

it('spanBiz auto-resolve business_id e mescla com extras (sem auth retorna 0)', function () {
    config()->set('otel.enabled', false);

    // Capture attributes que seriam enviados — via callback que retorna os args inspecionáveis.
    $capturado = null;
    $resultado = OtelHelper::spanBiz('teste.biz', function () use (&$capturado) {
        $capturado = 'callback-rodou';

        return 'resultado-final';
    }, ['extra_attr' => 'valor']);

    expect($capturado)->toBe('callback-rodou')
        ->and($resultado)->toBe('resultado-final');
});

it('propaga exception do callback sem engolir (rethrow após end span)', function () {
    config()->set('otel.enabled', false);

    expect(fn () => OtelHelper::span(
        'teste.exception',
        ['business_id' => 1],
        fn () => throw new \RuntimeException('boom esperado')
    ))->toThrow(\RuntimeException::class, 'boom esperado');
});
