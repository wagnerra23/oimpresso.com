<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Wave 23 D9.a — ADS OTel instrumentation contract test.
 *
 * Garante que Services críticos do ADS (Brain B autonomous) usam OtelHelper canônico.
 * 4 Services já instrumentados — esta suite trava regressão:
 *   - RiskEngine
 *   - DecisionRouter
 *   - PolicyEngine
 *   - ConfidenceEngine
 *
 * Zero-cost OTel: spans são no-op se `otel.enabled=false` (default).
 *
 * @see App/Util/OtelHelper.php
 * @see Modules/ADS/Services/*.php
 * @see memory/decisions/0155-module-grade-v3.md D9.a
 */

dataset('ads_services_instrumentados', [
    'RiskEngine'       => ['Modules/ADS/Services/RiskEngine.php'],
    'DecisionRouter'   => ['Modules/ADS/Services/DecisionRouter.php'],
    'PolicyEngine'     => ['Modules/ADS/Services/PolicyEngine.php'],
    'ConfidenceEngine' => ['Modules/ADS/Services/ConfidenceEngine.php'],
]);

it('Service ADS usa OtelHelper canônico (App\Util\OtelHelper)', function (string $path) {
    $source = file_get_contents(base_path($path));
    expect($source)->toContain('OtelHelper');
    expect($source)->not->toContain('OpenTelemetry\\API\\Trace\\TracerProviderInterface');
})->with('ads_services_instrumentados');

it('Service ADS chama spanBiz pelo menos 1 vez', function (string $path) {
    $source = file_get_contents(base_path($path));
    expect($source)->toContain('OtelHelper::spanBiz(');
})->with('ads_services_instrumentados');
