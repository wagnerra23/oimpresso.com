<?php

namespace Modules\OficinaAuto\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\OficinaAuto\Services\OficinaAutoAcesso;

/**
 * 403 em toda rota autenticada do /oficina-auto quando a empresa da sessão não tem o
 * pacote `oficina_auto_module`. Checa a cada request: desligar o pacote corta o acesso
 * no request seguinte, sem depender do menu. As permissões por ação (vehicle.view,
 * service_order.update...) continuam nos controllers/policies — isto é a camada do pacote.
 *
 * Antes deste middleware só o menu conferia o pacote, e `Admin#{biz}` passa em toda
 * permissão: o admin de empresa sem o pacote abria /oficina-auto/* digitando a URL.
 */
class OficinaAutoLiberado
{
    public function handle(Request $request, Closure $next)
    {
        $liberado = app(OficinaAutoAcesso::class)->pacoteAtivo(
            $request->session()->get('user.business_id')
        );

        abort_unless($liberado, 403, 'O módulo Oficina Auto não está contratado para esta empresa.');

        return $next($request);
    }
}
