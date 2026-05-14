<?php

declare(strict_types=1);

use Modules\Whatsapp\Http\Middleware\PropagateTraceparent;

uses(Tests\TestCase::class);

/**
 * Regression test pro middleware PropagateTraceparent (US-WA-083).
 *
 * @see Modules/Whatsapp/Http/Middleware/PropagateTraceparent.php
 */
beforeEach(function () {
    config(['otel.enabled' => true]);
});

it('R-WA-OTEL-001 — sem traceparent header → passa direto sem trace_id', function () {
    $request = \Illuminate\Http\Request::create('/test', 'POST');

    $middleware = new PropagateTraceparent();
    $response = $middleware->handle($request, fn ($r) => response()->json(['ok' => true], 200));

    expect($response->getStatusCode())->toBe(200);
    expect($request->attributes->has('otel.trace_id'))->toBeFalse();
    expect($response->headers->has('traceparent'))->toBeFalse();
});

it('R-WA-OTEL-002 — traceparent válido → extrai trace_id + parent_span_id + sampled', function () {
    $traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

    $request = \Illuminate\Http\Request::create('/test', 'POST');
    $request->headers->set('traceparent', $traceparent);

    $middleware = new PropagateTraceparent();
    $response = $middleware->handle($request, fn ($r) => response()->json(['ok' => true], 200));

    expect($response->getStatusCode())->toBe(200);
    expect($request->attributes->get('otel.trace_id'))->toBe('4bf92f3577b34da6a3ce929d0e0e4736');
    expect($request->attributes->get('otel.parent_span_id'))->toBe('00f067aa0ba902b7');
    expect($request->attributes->get('otel.sampled'))->toBeTrue();

    // Response carrega traceparent injetado
    expect($response->headers->get('traceparent'))->toBe($traceparent);
});

it('R-WA-OTEL-003 — sampled flag 00 (not sampled) → sampled=false', function () {
    $traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-00';

    $request = \Illuminate\Http\Request::create('/test', 'POST');
    $request->headers->set('traceparent', $traceparent);

    $middleware = new PropagateTraceparent();
    $middleware->handle($request, fn ($r) => response()->json(['ok' => true], 200));

    expect($request->attributes->get('otel.sampled'))->toBeFalse();
});

it('R-WA-OTEL-004 — traceparent malformado → ignora sem erro', function () {
    $request = \Illuminate\Http\Request::create('/test', 'POST');
    $request->headers->set('traceparent', 'invalido-formato-completamente');

    $middleware = new PropagateTraceparent();
    $response = $middleware->handle($request, fn ($r) => response()->json(['ok' => true], 200));

    expect($response->getStatusCode())->toBe(200);
    expect($request->attributes->has('otel.trace_id'))->toBeFalse();
});

it('R-WA-OTEL-005 — config otel.enabled=false → no-op', function () {
    config(['otel.enabled' => false]);

    $traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

    $request = \Illuminate\Http\Request::create('/test', 'POST');
    $request->headers->set('traceparent', $traceparent);

    $middleware = new PropagateTraceparent();
    $middleware->handle($request, fn ($r) => response()->json(['ok' => true], 200));

    expect($request->attributes->has('otel.trace_id'))->toBeFalse();
});

it('R-WA-OTEL-006 — versão futura "01" → não-suportada (regex só aceita "00")', function () {
    // RFC § 3.2 — version 00 é o atual; futuras versions devem ser ignoradas
    // até a lib parser ser atualizada.
    $traceparent = '01-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

    $request = \Illuminate\Http\Request::create('/test', 'POST');
    $request->headers->set('traceparent', $traceparent);

    $middleware = new PropagateTraceparent();
    $middleware->handle($request, fn ($r) => response()->json(['ok' => true], 200));

    expect($request->attributes->has('otel.trace_id'))->toBeFalse();
});
