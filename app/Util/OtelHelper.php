<?php

declare(strict_types=1);

namespace App\Util;

/**
 * OtelHelper — facade zero-cost pra instrumentation OTel.
 *
 * Wave 26 (2026-05-17) upgrade canon:
 *   - PII guard duplo: helper descarta attrs sensíveis (cpf/cnpj/email/phone/...)
 *   - Auto-detect `oimpresso.module` via debug_backtrace (Modules/<X>/)
 *   - SpanKind explícito (INTERNAL default) + recordException + STATUS_ERROR
 *   - spanBiz adiciona `oimpresso.tenant_id` (convencão ResourceAttributes canon)
 *   - Back-compat: enabled=false continua zero-cost (overhead < 1µs)
 *   - Back-compat: SDK ausente (class_exists Globals::class false) → pass-through
 *
 * Quando OTel SDK não está configurado (enabled=false), span é no-op.
 * Quando configurado (CT 100 OTel collector), exporta spans reais via SDK.
 *
 * Multi-tenant Tier 0: sempre inclua `business_id` nos attributes via `spanBiz()`.
 *
 * @see ADR 0155 module-grade-v3 D9.a
 * @see ADR 0162 (proposta) OTel collector CT 100 Wave 26
 * @see config/otel.php (enabled, sdk_disabled, sample_rate)
 */
class OtelHelper
{
    /**
     * Keys consideradas sensíveis — descartadas SILENCIOSAMENTE do span (Tier 0).
     *
     * Match case-insensitive por substring. Ex: 'user_email' bate em 'email',
     * 'cliente_cpf' bate em 'cpf', 'http.authorization' bate em 'authorization'.
     */
    private const PII_SENSITIVE_KEYS = [
        'cpf',
        'cnpj',
        'email',
        'phone',
        'telefone',
        'celular',
        'password',
        'senha',
        'token',
        'authorization',
        'api_key',
        'apikey',
        'secret',
    ];

    /**
     * Run callback dentro de OTel span. Zero-cost se OTel ausente.
     *
     * @template T
     *
     * @param  string  $name  Nome do span (ex 'sells.fsm.execute_action')
     * @param  array<string, mixed>  $attributes  Atributos (PII filtrado automaticamente)
     * @param  callable(): T  $callback
     * @return T
     */
    public static function span(string $name, array $attributes, callable $callback)
    {
        // Zero-cost path quando OTel desabilitado.
        if (! config('otel.enabled', false)) {
            return $callback();
        }

        // Kill-switch emergencial (Wave 26): desliga SDK sem mexer flag principal.
        if (config('otel.sdk_disabled', false)) {
            return $callback();
        }

        // SDK ausente (composer install ainda não rodou ou ambiente leve) → pass-through.
        if (! class_exists(\OpenTelemetry\API\Globals::class)) {
            return $callback();
        }

        // Compat com config existente US-WA-083 (`otel.service.name`) + Wave 26 (`otel.service_name`).
        $serviceName = (string) (config('otel.service_name') ?? config('otel.service.name', 'oimpresso'));
        $tracer = \OpenTelemetry\API\Globals::tracerProvider()->getTracer($serviceName);

        $spanBuilder = $tracer->spanBuilder($name)
            ->setSpanKind(\OpenTelemetry\API\Trace\SpanKind::KIND_INTERNAL);
        $span = $spanBuilder->startSpan();

        // Auto-detect oimpresso.module via caller stack (Modules/<X>/...)
        $module = self::detectModuleFromCaller();
        if ($module !== null) {
            $span->setAttribute('oimpresso.module', $module);
        }

        // Aplica attrs com PII guard.
        foreach ($attributes as $k => $v) {
            if (! is_string($k) || self::isPiiSensitive($k)) {
                continue;
            }
            $span->setAttribute($k, $v);
        }

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
     * Helper rápido: span com `business_id` auto-resolvido + `oimpresso.tenant_id` (Tier 0).
     *
     * @template T
     *
     * @param  string  $name  Nome do span (ex 'sells.fsm.execute_action')
     * @param  callable(): T  $callback
     * @param  array<string, mixed>  $extras  Atributos extras além de tenant_id
     * @return T
     */
    public static function spanBiz(string $name, callable $callback, array $extras = [])
    {
        // session() não existe em CLI, queue workers e Unit tests sem TestCase.
        // try/catch evita "Target class [session] does not exist" nesses contextos.
        try {
            $bizId = session()->get('user.business_id');
        } catch (\Throwable) {
            $bizId = null;
        }
        $bizId ??= optional(auth()->user())->business_id ?? 0;

        // Convenção ResourceAttributes canon (config/otel.php): `oimpresso.tenant_id`.
        // Mantém também `business_id` legacy pra compat com call-sites antigos US-WA-083.
        $bizAttrs = [
            'oimpresso.tenant_id' => (string) $bizId,
            'business_id'         => $bizId,
        ];

        return self::span($name, $bizAttrs + $extras, $callback);
    }

    /**
     * Detecta o módulo nWidart do caller via stack trace.
     *
     * Procura por classes que matchem `Modules\<X>\...` nos últimos 5 frames.
     * Retorna o nome do módulo (ex: 'Sells', 'Governance') ou null se não-modular.
     *
     * Pure (sem side-effect, não-cacheável por design). Custo ~5-10µs por chamada
     * (debug_backtrace IGNORE_ARGS limita allocation).
     */
    public static function detectModuleFromCaller(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';
            if (! is_string($class) || $class === '') {
                continue;
            }
            if (preg_match('/^Modules\\\\([^\\\\]+)\\\\/', $class, $m) === 1) {
                return $m[1];
            }
        }
        return null;
    }

    /**
     * Verifica se uma attribute key bate em alguma keyword PII sensível (case-insensitive).
     *
     * Match por substring (não regex word-boundary) — defensivo. Falsos positivos
     * são aceitáveis (descarte excessivo não vaza dados). Falsos negativos são caros
     * (vazamento de PII em telemetry). Ver Tier 0 em CLAUDE.md.
     */
    public static function isPiiSensitive(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::PII_SENSITIVE_KEYS as $sensitive) {
            if (str_contains($lower, $sensitive)) {
                return true;
            }
        }
        return false;
    }
}
