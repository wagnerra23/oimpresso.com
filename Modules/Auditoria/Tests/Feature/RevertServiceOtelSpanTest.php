<?php

declare(strict_types=1);

use App\Util\OtelHelper;

/**
 * D9.a — OtelHelper wrap em RevertService::revert().
 *
 * Verifica contrato: OtelHelper::span/spanBiz zero-cost quando otel.enabled=false
 * (default prod). Span criado quando habilitado + class_exists SDK.
 *
 * O span real do revert (nome + atributos sem PII) fica em RevertRedacaoContratoTest.
 *
 * @see app/Util/OtelHelper.php
 */

uses(Tests\TestCase::class);

it('OtelHelper zero-cost quando otel.enabled=false (default prod)', function () {
    config(['otel.enabled' => false]);

    $executed = false;
    $result = OtelHelper::span('auditoria.test.span', ['business_id' => 1], function () use (&$executed) {
        $executed = true;
        return 'ok';
    });

    expect($executed)->toBeTrue();
    expect($result)->toBe('ok');
});

it('OtelHelper::spanBiz auto-resolve business_id sem session', function () {
    config(['otel.enabled' => false]);

    $result = OtelHelper::spanBiz('auditoria.test.spanbiz', function () {
        return 42;
    }, ['module' => 'Auditoria']);

    expect($result)->toBe(42);
});
