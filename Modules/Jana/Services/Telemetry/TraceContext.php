<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Telemetry;

/**
 * Contexto request-scoped da correlação Jana.
 *
 * Não emite telemetria: é somente o dono único da chave que liga controller,
 * listener Laravel AI e retrieval ao mesmo trace Langfuse.
 */
final class TraceContext
{
    private const REQUEST_ATTRIBUTE = 'jana_trace_id';

    public static function current(): ?string
    {
        try {
            if (! app()->bound('request')) {
                return null;
            }

            $traceId = request()->attributes->get(self::REQUEST_ATTRIBUTE);

            return is_string($traceId) && $traceId !== '' ? $traceId : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Ativa a correlação e devolve o valor anterior para restauração.
     */
    public static function activate(string $traceId): ?string
    {
        $previous = self::current();

        try {
            if (app()->bound('request')) {
                request()->attributes->set(self::REQUEST_ATTRIBUTE, $traceId);
            }
        } catch (\Throwable) {
            // Fora de HTTP: contexto ausente é o fallback esperado.
        }

        return $previous;
    }

    public static function restore(?string $previous): void
    {
        try {
            if (! app()->bound('request')) {
                return;
            }

            if ($previous !== null) {
                request()->attributes->set(self::REQUEST_ATTRIBUTE, $previous);

                return;
            }

            request()->attributes->remove(self::REQUEST_ATTRIBUTE);
        } catch (\Throwable) {
            // Fail-open: telemetria nunca derruba o fluxo principal.
        }
    }
}
