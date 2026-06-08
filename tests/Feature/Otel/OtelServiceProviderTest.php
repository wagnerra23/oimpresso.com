<?php

declare(strict_types=1);

use App\Providers\OtelServiceProvider;

// tests/Feature/ já recebe Tests\TestCase via tests/Pest.php (não duplicar uses()).

/**
 * Regressão do bug T1.b (descoberto 2026-06-01): o OTel NUNCA exportou em prod.
 *
 * O OtelServiceProvider foi escrito contra uma API antiga do SDK e quebrava no
 * boot — caindo silenciosamente pro NoopTracerProvider (try/catch → Log::warning):
 *   1. `ResourceAttributes::DEPLOYMENT_ENVIRONMENT` foi REMOVIDA no sem-conv >=1.27
 *   2. `BatchSpanProcessor::__construct` passou a exigir um clock (2º arg)
 *
 * Como NENHUM teste exercia o caminho com `otel.enabled=true`, o CI ficava verde
 * e o drift passou despercebido. Este teste fecha o gap: prova que o provider
 * constrói um TracerProvider SDK real e que um span chega no exporter.
 *
 * O SDK OTel vive em `require-dev` → presente no CI/dev. Em prod `--no-dev` o SDK
 * some e o provider faz no-op (Gate 3 `class_exists`), daí o skip defensivo.
 */

test('buildTracerProvider monta o pipeline SDK e exporta o span (não cai em Noop)', function () {
    config([
        'otel.enabled' => true,
        'otel.sdk_disabled' => false,
        'otel.sample_rate' => 1.0, // 100% — determinístico (5% poderia não amostrar 1 span)
        'otel.service_name' => 'oimpresso-test',
    ]);

    $exporter = new \OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter();

    $tracerProvider = (new OtelServiceProvider(app()))->buildTracerProvider($exporter);

    // Antes do fix isto era NoopTracerProvider (boot falhava).
    expect($tracerProvider)->not->toBeInstanceOf(\OpenTelemetry\API\Trace\NoopTracerProvider::class);

    $span = $tracerProvider->getTracer('test')->spanBuilder('test.t1b.span')->startSpan();
    $span->end();
    $tracerProvider->forceFlush();

    $spans = $exporter->getSpans();
    expect($spans)->toHaveCount(1);
    expect($spans[0]->getName())->toBe('test.t1b.span');
})->skip(
    ! class_exists(\OpenTelemetry\API\Globals::class),
    'OTel SDK ausente (prod --no-dev) — provider faz no-op'
);
