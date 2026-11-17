<?php

namespace Modules\Vestuario\Http\Middleware;

use App\Utils\ModuleUtil;
use Closure;
use Illuminate\Http\Request;

/**
 * 403 nas rotas do Vestuario quando a empresa não tem o pacote `vestuario_module`
 * (Camada 1, Superadmin > Pacotes). Contrato: SDD CU-VEST-08, "não há entry de sidebar nem acesso".
 *
 * Antes só o menu conferia o pacote (DataController::modifyAdminMenu), e a URL abria as telas.
 * Superadmin passa dentro do próprio `hasThePermissionInSubscription`. O ModuleUtil vem do container
 * para os testes trocarem o pacote (mesmo padrão do ComprasLiberado).
 */
class VestuarioLiberado
{
    public const PACOTE = 'vestuario_module';

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $businessId = (int) ($user?->business_id ?? 0);

        abort_if($user === null || $businessId <= 0, 403, 'Vestuário não liberado para esta empresa.');

        abort_unless(
            app(ModuleUtil::class)->hasThePermissionInSubscription($businessId, self::PACOTE, 'superadmin_package'),
            403,
            'O pacote da empresa não inclui o módulo Vestuário.'
        );

        return $next($request);
    }
}
