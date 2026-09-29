<?php

namespace Modules\Ponto\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Ponto\Services\AbasContadoresService;

/**
 * Verifica se o usuário tem acesso ao módulo Ponto WR2 e ao business_id ativo.
 * Integra com spatie/laravel-permission e o business-scope do UltimatePOS.
 */
class CheckPontoAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user, 403);

        // 1) Business-scope do UltimatePOS
        $businessId = session('business.id') ?? $user->business_id;
        abort_unless($businessId, 403, 'Nenhuma empresa ativa na sessão.');

        // 2) Permissão do módulo (spatie/laravel-permission)
        abort_unless(
            $user->can('ponto.access') || $user->hasRole(['admin', 'rh', 'gestor']),
            403,
            'Você não tem permissão para acessar o módulo Ponto.'
        );

        // 3) Contagens das abas do header de módulo (W9 · ADR 0418). Aqui, e não em cada um
        //    dos ~12 controllers, porque TODA rota /ponto passa por este middleware — e só
        //    DEPOIS das duas travas acima: quem não pode ver o Ponto não recebe os números.
        //    `defer`: quatro contagens + a apuração da competência não entram no 1º paint de
        //    tela nenhuma; chegam no request diferido que a página já faz. Partial reload
        //    que não pede `ponto_abas` não recalcula nada (o closure nem roda).
        $bizId = (int) $businessId;
        Inertia::share('ponto_abas', Inertia::defer(
            fn () => app(AbasContadoresService::class)->contar($bizId)
        ));
        // Linha de contexto do header (competência + "N colaboradores no ponto"). Mesmo
        // request diferido das contagens — os dois estão no grupo `default`.
        Inertia::share('ponto_contexto', Inertia::defer(
            fn () => app(AbasContadoresService::class)->contexto($bizId)
        ));

        return $next($request);
    }
}
