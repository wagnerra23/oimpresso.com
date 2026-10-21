<?php

namespace Modules\Fisio\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Fisio\Services\FisioAcesso;

/**
 * Barra toda rota autenticada do /fisio quando a empresa não tem o pacote
 * `fisio_module` ou o usuário não tem `fisio.access` (403). Checa a cada request,
 * então desligar o pacote corta o acesso no request seguinte, sem depender de cache
 * de menu (UC-FPI-04).
 */
class FisioLiberado
{
    public function handle(Request $request, Closure $next)
    {
        $liberado = app(FisioAcesso::class)->liberado(
            $request->user(),
            $request->session()->get('user.business_id')
        );

        abort_unless($liberado, 403, __('fisio::fisio.sem_acesso'));

        return $next($request);
    }
}