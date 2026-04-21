<?php

namespace Modules\PontoWr2\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

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

        return $next($request);
    }
}
