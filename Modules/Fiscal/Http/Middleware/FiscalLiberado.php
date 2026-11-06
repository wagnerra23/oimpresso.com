<?php

namespace Modules\Fiscal\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Fiscal\Services\FiscalPacote;

/**
 * Barra toda rota operacional do /fiscal quando a empresa não tem o pacote
 * `fiscal_module` na assinatura (403). Checa a cada request: desligar o pacote corta
 * o acesso no request seguinte, sem depender do menu.
 *
 * Só o PACOTE é decidido aqui. A permissão de cada tela (`fiscal.nfe.view`,
 * `fiscal.dfe.manage`…) continua no controller dela — este middleware não substitui
 * nenhuma.
 */
class FiscalLiberado
{
    public function handle(Request $request, Closure $next)
    {
        $businessId = $request->session()->get('user.business_id');

        abort_unless(app(FiscalPacote::class)->ativo($businessId), 403, __('fiscal::fiscal.sem_pacote'));

        return $next($request);
    }
}
