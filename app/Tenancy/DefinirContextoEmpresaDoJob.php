<?php

declare(strict_types=1);

namespace App\Tenancy;

use Closure;
use ReflectionProperty;

/**
 * Pipe do Bus (ADR 0453): job que carrega `$businessId` (Garantia 3 da ADR 0093) roda com essa
 * empresa no ContextoEmpresa; no fim, o contexto anterior volta. Job sem `$businessId` passa
 * intocado — se ele consultar model de empresa, o modo `tenancy.escopo_sem_contexto` decide.
 *
 * Registrado em AppServiceProvider::boot via Bus::pipeThrough. Vale para job na fila
 * (CallQueuedHandler → dispatchNow) e síncrono.
 */
final class DefinirContextoEmpresaDoJob
{
    public function handle(object $command, Closure $next): mixed
    {
        $businessId = self::empresaDo($command);
        if ($businessId === null) {
            return $next($command);
        }

        return app(ContextoEmpresa::class)->comEmpresa($businessId, fn () => $next($command));
    }

    /** Empresa declarada pelo job em `$businessId` (público ou não); null se ausente/vazia. */
    public static function empresaDo(object $command): ?int
    {
        if (! property_exists($command, 'businessId')) {
            return null;
        }

        $prop = new ReflectionProperty($command, 'businessId');
        if (! $prop->isInitialized($command)) {
            return null;
        }

        $valor = $prop->getValue($command);

        return is_numeric($valor) && (int) $valor > 0 ? (int) $valor : null;
    }
}
