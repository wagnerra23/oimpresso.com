<?php

declare(strict_types=1);

namespace App\Util;

/**
 * OtelHelper — facade zero-cost pra instrumentation OTel.
 *
 * Quando OTel SDK não está configurado (driver=null), span é no-op (overhead < 1µs).
 * Quando configurado (CT 100 OTel collector), exporta spans real.
 *
 * Multi-tenant Tier 0: sempre inclua `business_id` nos attributes via `spanBiz()`.
 *
 * @see ADR 0155 module-grade-v3 D9.a
 */
class OtelHelper
{
    /**
     * Run callback dentro de OTel span. Zero-cost se OTel ausente.
     *
     * @template T
     *
     * @param  string  $name  Nome do span (ex 'sells.fsm.execute_action')
     * @param  array<string, mixed>  $attributes  Atributos (sempre business_id Tier 0)
     * @param  callable(): T  $callback
     * @return T
     */
    public static function span(string $name, array $attributes, callable $callback)
    {
        // Zero-cost path quando OTel não configurado (config check).
        if (! config('otel.enabled', false)) {
            return $callback();
        }

        // Path real OTel só ativa se SDK presente (composer require open-telemetry/api).
        if (! class_exists(\OpenTelemetry\API\Globals::class)) {
            return $callback();
        }

        // Compat com config existente US-WA-083 (`otel.service.name`) + fallback novo (`otel.service_name`).
        $serviceName = (string) (config('otel.service.name') ?? config('otel.service_name', 'oimpresso'));
        $tracer = \OpenTelemetry\API\Globals::tracerProvider()->getTracer($serviceName);
        $span = $tracer->spanBuilder($name)->startSpan();
        $span->setAttributes($attributes);
        $scope = $span->activate();

        try {
            $result = $callback();
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_OK);

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    /**
     * Helper rápido: span simples com `business_id` auto-resolvido (Tier 0 multi-tenant).
     *
     * @template T
     *
     * @param  string  $name  Nome do span (ex 'sells.fsm.execute_action')
     * @param  callable(): T  $callback
     * @param  array<string, mixed>  $extras  Atributos extras além de business_id
     * @return T
     */
    public static function spanBiz(string $name, callable $callback, array $extras = [])
    {
        $bizId = session()->get('user.business_id')
            ?? optional(auth()->user())->business_id
            ?? 0;

        return self::span($name, ['business_id' => $bizId] + $extras, $callback);
    }
}
