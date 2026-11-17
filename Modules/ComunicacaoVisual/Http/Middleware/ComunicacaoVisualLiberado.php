<?php

namespace Modules\ComunicacaoVisual\Http\Middleware;

use App\Utils\ModuleUtil;
use Closure;
use Illuminate\Http\Request;

/**
 * 403 nas rotas operacionais de /comunicacao-visual quando a empresa não tem o pacote
 * `comunicacao_visual_module` (Camada 1, Superadmin > Pacotes).
 *
 * Antes dele só o menu (DataController::modifyAdminMenu) e o app das lojas
 * (AppOrcamentoRapidoController) conferiam o pacote. Como `Admin#{biz}` passa em todo `can()`, o
 * admin de empresa sem o pacote usava o módulo digitando a URL (auditoria 2026-11-05, I-04/S8).
 *
 * A permissão de cada ação segue no controller. Ficam de fora: a página pública do aceite (o cliente
 * não tem login) e as rotas de instalação (só superadmin). Superadmin passa pelo pacote dentro do
 * próprio `hasThePermissionInSubscription`. O ModuleUtil vem do container para os testes trocarem
 * o pacote, mesmo padrão do ComprasLiberado.
 */
class ComunicacaoVisualLiberado
{
    public const PACOTE = 'comunicacao_visual_module';

    public function handle(Request $request, Closure $next)
    {
        $businessId = (int) ($request->user()?->business_id ?? 0);

        abort_if($businessId <= 0, 403, 'Comunicação Visual não liberada para esta empresa.');
        abort_unless(self::pacoteAtivo($businessId), 403, 'O pacote da empresa não inclui o módulo Comunicação Visual.');

        return $next($request);
    }

    public static function pacoteAtivo(int $businessId): bool
    {
        return (bool) app(ModuleUtil::class)
            ->hasThePermissionInSubscription($businessId, self::PACOTE, 'superadmin_package');
    }
}
