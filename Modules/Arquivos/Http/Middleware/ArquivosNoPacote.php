<?php

namespace Modules\Arquivos\Http\Middleware;

use App\Utils\ModuleUtil;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A tela /arquivos só abre para empresa com `arquivos_module` no pacote — a mesma regra do
 * menu (DataController::modifyAdminMenu). Sem isto, quem não tinha o módulo no pacote
 * chegava à tela pela URL direta.
 *
 * Caminho canônico (Camada 1, feedback-habilitar-modulo-por-business): ModuleUtil::
 * hasThePermissionInSubscription, que já libera o superadmin. Nunca `if ($business_id === N)`.
 *
 * Fica só nas rotas da tela. O download (signed URL) atende anexos de outros módulos
 * (NFe, OficinaAuto, Fisio...) e não depende do pacote do Arquivos.
 */
class ArquivosNoPacote
{
    public function handle(Request $request, Closure $next): Response
    {
        $businessId = (int) $request->session()->get('user.business_id');

        $noPacote = (bool) app(ModuleUtil::class)->hasThePermissionInSubscription(
            $businessId,
            'arquivos_module',
            'superadmin_package'
        );

        if (! $noPacote) {
            abort(403, 'O módulo Arquivos não está no pacote desta empresa.');
        }

        return $next($request);
    }
}
