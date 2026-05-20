<?php

namespace Modules\Financeiro\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\AdvisorBusinessAccess;
use Symfony\Component\HttpFoundation\Response;

/**
 * AdvisorViewScope — middleware Onda 31 #57 US-FIN-037.
 *
 * Aplicado em rotas read-only do Financeiro (relatorios, unificado). Só
 * dispara quando query string carrega `?advisor_view=1`. Verifica:
 *
 *   1. Advisor logado via guard `web-advisor` (Auth::guard('web-advisor')).
 *   2. business_id na query existe e há row ativa em advisor_business_access
 *      (advisor_id atual, business_id da query, revoked_at IS NULL).
 *   3. Método HTTP é read-only (GET/HEAD/OPTIONS) — POST/PUT/PATCH/DELETE
 *      retornam 403 hard (mesmo se advisor tem grant — readonly enforce).
 *   4. Override session('user.business_id') pro biz da query — assim o
 *      BusinessScope global do Financeiro filtra automaticamente pros dados
 *      do cliente do contador (e não pro próprio business do advisor, que
 *      nem existe — advisor é entidade GLOBAL).
 *
 * Cross-tenant safety (Pest cobre): se advisor A tem grant pra biz=1 e
 * tenta acessar ?advisor_view=1&business_id=99 → 403.
 *
 * Multi-tenant Tier 0 (ADR 0093): override de session é cirúrgico —
 * `session('user.business_id', $businessIdSolicitado)` só pra esta request.
 */
class AdvisorViewScope
{
    public function handle(Request $request, Closure $next): Response
    {
        // Sem query advisor_view → middleware é no-op (deixa fluxo normal).
        if (! $request->query('advisor_view')) {
            return $next($request);
        }

        // 1) Advisor precisa estar logado no guard isolado.
        $advisor = Auth::guard('web-advisor')->user();
        if (! $advisor) {
            Log::warning('Onda 31: advisor_view sem advisor logado', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            return redirect('/advisor/login')->with('error', 'Sessão expirada. Entre novamente.');
        }

        // 2) business_id solicitado precisa existir.
        $businessIdSolicitado = (int) $request->query('business_id');
        if ($businessIdSolicitado <= 0) {
            return response('business_id obrigatório em advisor_view.', 400);
        }

        // 3) Verifica grant ATIVO no banco (advisor_business_access).
        $grant = AdvisorBusinessAccess::query()
            ->where('advisor_id', $advisor->id)
            ->where('business_id', $businessIdSolicitado)
            ->whereNull('revoked_at')
            ->whereNull('deleted_at')
            ->first();

        if (! $grant) {
            Log::warning('Onda 31: advisor sem grant tentou acessar business', [
                'advisor_id' => $advisor->id,
                'business_id' => $businessIdSolicitado,
                'path' => $request->path(),
            ]);
            return response('Sem permissão para visualizar este cliente.', 403);
        }

        // 4) READONLY ENFORCE: método não-read = 403.
        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            Log::warning('Onda 31: advisor tentou método não-read', [
                'advisor_id' => $advisor->id,
                'business_id' => $businessIdSolicitado,
                'method' => $request->method(),
                'path' => $request->path(),
            ]);
            return response('Portal contador é somente leitura.', 403);
        }

        // 5) Verifica scope JSON do grant — bloqueia se escopo da rota não habilitado.
        // Rotas de relatorios precisam can_view_reports; unificado precisa can_view_unificado.
        $path = $request->path();
        if (str_contains($path, 'relatorios') && ! $grant->canViewReports()) {
            return response('Escopo do grant não inclui relatórios.', 403);
        }
        if (str_contains($path, 'unificado') && ! $grant->canViewUnificado()) {
            return response('Escopo do grant não inclui visão unificada.', 403);
        }

        // 6) LGPD: bloqueia se consent ausente no grant (defesa-em-profundidade).
        if (! $grant->hasConsent()) {
            return response('Grant sem consentimento LGPD registrado.', 403);
        }

        // 7) Override session pra que BusinessScope global filtre pro biz do cliente.
        // Salva original (advisor nunca tem business_id próprio, mas mantém limpo).
        $request->attributes->set('advisor_view_active', true);
        $request->attributes->set('advisor_view_business_id', $businessIdSolicitado);
        session(['user.business_id' => $businessIdSolicitado]);
        session(['business.id' => $businessIdSolicitado]);

        return $next($request);
    }
}
