<?php

namespace App\Http\Middleware;

use App\Services\Support\SupportAccessService;
use App\Services\Support\SupportAuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Modo Suporte (ADR 0305) — guarda das rotas de suporte (service-direct, NÃO via Gate).
 *
 * Roda DEPOIS de auth + SetSessionData. Exige capability de suporte ativa; quando a rota tem
 * o parâmetro {business}, exige canAccessBusiness (que já exclui a operadora) e AUDITA cada
 * acesso (entrou) e cada negação (negado) em support_access_logs (RF3, append-only).
 *
 * NÃO usa Gate de propósito: o Gate::before (App\Providers\AuthServiceProvider) devolve `true`
 * a QUALQUER Admin#<business> para abilities não-superadmin — decidir acesso por Gate vazaria
 * a operadora. A autoridade fica no SupportAccessService.
 *
 * @see App\Services\Support\SupportAccessService
 * @see memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md
 */
class EnsureSupportAccess
{
    public function __construct(
        private SupportAccessService $access,
        private SupportAuditService $audit,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user === null || ! $this->access->isSupportAgent($user)) {
            abort(403, 'Acesso restrito a agentes do Modo Suporte.');
        }

        $businessId = (int) $request->route('business');

        if ($businessId > 0) {
            $route = $request->path();
            $ip = $request->ip();
            $userAgent = mb_substr((string) $request->userAgent(), 0, 512);

            if (! $this->access->canAccessBusiness($user, $businessId)) {
                $this->audit->recordDenied($user, $businessId, $route, $ip, $userAgent);
                abort(403, 'Empresa fora do alcance do Modo Suporte.');
            }

            $this->audit->recordAccess($user, $businessId, $route, $ip, $userAgent);
        }

        return $next($request);
    }
}
