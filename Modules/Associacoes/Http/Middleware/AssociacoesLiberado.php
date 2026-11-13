<?php

namespace Modules\Associacoes\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Associacoes\Services\AssociacoesAcesso;

/**
 * Barra toda rota autenticada do /associacoes quando a empresa não tem o pacote
 * `associacoes_module` ou o usuário não tem `associacoes.access` (403). Checa a cada request:
 * desligar o pacote corta o acesso no request seguinte.
 */
class AssociacoesLiberado
{
    public function handle(Request $request, Closure $next)
    {
        $liberado = app(AssociacoesAcesso::class)->liberado(
            $request->user(),
            $request->session()->get('user.business_id')
        );

        abort_unless($liberado, 403, __('associacoes::associacoes.sem_acesso'));

        return $next($request);
    }
}
