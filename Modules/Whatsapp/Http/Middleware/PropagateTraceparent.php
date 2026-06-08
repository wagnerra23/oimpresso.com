<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * US-WA-083 — OTel tracing distribuído daemon ↔ Hostinger (lightweight bridge).
 *
 * Extrai `traceparent` W3C Trace Context header injetado pelo daemon Node
 * (`Modules/Whatsapp/daemon-node/src/webhook/WebhookDispatcher.ts`) e
 * propaga via:
 *   1. Log context (`Log::withContext`) — todos os logs do request carregam
 *      trace_id + span_id, permitindo correlação manual no Grafana/Loki
 *      até instalarmos SDK OTel completo (PECL extension `opentelemetry`).
 *   2. Request attributes — controllers downstream podem buscar trace_id
 *      via `$request->attributes->get('otel.trace_id')`.
 *
 * **Decisão arquitetural (custo composer):** NÃO instalamos `open-telemetry/sdk`
 * + extensão PECL no Hostinger shared hosting nesta fase. Lightweight bridge
 * cobre 80% do valor (correlação cross-system via logs estruturados) com 0%
 * do custo composer. Evolução futura: container CT 101 com PECL → SDK full.
 *
 * **Formato W3C traceparent (RFC ietf-trace-context):**
 *   `00-{trace-id 32hex}-{parent-id 16hex}-{trace-flags 2hex}`
 *
 * @see Modules/Whatsapp/daemon-node/src/webhook/WebhookDispatcher.ts § US-WA-083
 * @see https://www.w3.org/TR/trace-context/
 * @see config/otel.php
 */
class PropagateTraceparent
{
    /**
     * Regex W3C — trace-id 32 hex, parent-id 16 hex, flags 2 hex.
     * Aceita só versão "00" (current). Rejeita malformados sem 500.
     */
    private const TRACEPARENT_REGEX = '/^00-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/';

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('otel.enabled', true)) {
            return $next($request);
        }

        $traceparent = (string) $request->header('traceparent', '');

        if ($traceparent !== '' && preg_match(self::TRACEPARENT_REGEX, $traceparent, $matches) === 1) {
            [, $traceId, $parentSpanId, $flags] = $matches;

            // Sampled = trace-flags bit 0 set (RFC § 3.2.2.5)
            $sampled = (hexdec($flags) & 0x01) === 0x01;

            $request->attributes->set('otel.trace_id', $traceId);
            $request->attributes->set('otel.parent_span_id', $parentSpanId);
            $request->attributes->set('otel.sampled', $sampled);

            // Log context — todos os logs subsequentes carregam trace_id
            Log::withContext([
                'trace_id' => $traceId,
                'parent_span_id' => $parentSpanId,
                'sampled' => $sampled,
            ]);
        }

        $response = $next($request);

        // Inject traceparent na response também (opcional — daemon não lê,
        // mas cliente HTTP intermediário pode loggar). Mantém span_id local
        // = parent_span_id do daemon (sem SDK não criamos span próprio).
        if (isset($traceId, $parentSpanId, $flags) && $request->attributes->has('otel.trace_id')) {
            $response->headers->set('traceparent', "00-{$traceId}-{$parentSpanId}-{$flags}");
        }

        return $response;
    }
}
