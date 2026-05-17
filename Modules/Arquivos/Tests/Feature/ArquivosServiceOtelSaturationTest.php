<?php

declare(strict_types=1);

use Modules\Arquivos\Services\ArquivosService;

uses(Tests\TestCase::class);

/**
 * Wave 23 D9.a SATURATION — ArquivosService OTel instrumentation.
 *
 * Trava regressão dos 5 métodos públicos do ArquivosService (DMS backbone):
 *   - attach
 *   - classify
 *   - signedUrl
 *   - softDelete
 *   - restore
 *
 * Cada método deve estar instrumentado com `OtelHelper::spanBiz`. Zero-cost se
 * `otel.enabled=false` (default).
 *
 * @see Modules/Arquivos/Services/ArquivosService.php
 * @see memory/decisions/0155-module-grade-v3.md D9.a
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */

it('ArquivosService usa OtelHelper canônico', function () {
    $source = file_get_contents(base_path('Modules/Arquivos/Services/ArquivosService.php'));
    expect($source)->toContain('use App\Util\OtelHelper;');
});

it('ArquivosService instrumenta os 5 métodos públicos críticos com spanBiz()', function () {
    $source = file_get_contents(base_path('Modules/Arquivos/Services/ArquivosService.php'));

    $spansEsperados = [
        'arquivos.attach',
        'arquivos.classify',
        'arquivos.signed_url',
        'arquivos.soft_delete',
        'arquivos.restore',
    ];

    foreach ($spansEsperados as $span) {
        expect($source)->toContain("OtelHelper::spanBiz('{$span}'");
    }
});

it('ArquivosService::attach existe + é público', function () {
    $reflection = new ReflectionClass(ArquivosService::class);
    expect($reflection->hasMethod('attach'))->toBeTrue();
    expect($reflection->getMethod('attach')->isPublic())->toBeTrue();
});

it('ArquivosService::signedUrl assina TTL configurável', function () {
    $reflection = new ReflectionMethod(ArquivosService::class, 'signedUrl');
    $params = collect($reflection->getParameters())->keyBy(fn ($p) => $p->getName());
    expect($params->has('expiresMinutes'))->toBeTrue();
    // Default 60min (URL temporária 1h conforme ADR 0123)
    expect($params['expiresMinutes']->getDefaultValue())->toBe(60);
});

it('ArquivosService::dedupe scopa por business_id (sem leak cross-tenant)', function () {
    $reflection = new ReflectionMethod(ArquivosService::class, 'dedupe');
    $params = collect($reflection->getParameters())->keyBy(fn ($p) => $p->getName());
    expect($params->has('businessId'))->toBeTrue('dedupe sem businessId = leak cross-tenant (ADR 0093)');
    expect($params['businessId']->getType()?->getName())->toBe('int');
});
