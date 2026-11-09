<?php

namespace Modules\Compras\Http\Middleware;

use App\Utils\ModuleUtil;
use Closure;
use Illuminate\Http\Request;

/**
 * 403 nas rotas operacionais de /compras quando a empresa não tem o pacote `compras_module`
 * (Camada 1, Superadmin > Pacotes) ou o usuário não tem a permissão pedida (Camada 3).
 *
 * Antes deste middleware só o menu conferia o pacote (DataController::modifyAdminMenu): em
 * 2026-11-06, na biz=1, `/compras` respondeu 200 com a Page `Compras/Index` sem o pacote.
 *
 * Superadmin passa pelo pacote dentro do próprio `hasThePermissionInSubscription`. O ModuleUtil
 * vem do container para os testes trocarem o pacote (mesmo padrão do MarketplacesLiberado).
 *
 * Uso: `ComprasLiberado::class` (só o pacote) ou `ComprasLiberado::class.':compras.view'`.
 */
class ComprasLiberado
{
    public const PACOTE = 'compras_module';

    public function handle(Request $request, Closure $next, ?string $permissao = null)
    {
        $user = $request->user();
        $businessId = (int) ($user?->business_id ?? 0);

        abort_if($user === null || $businessId <= 0, 403, 'Compras não liberado para esta empresa.');

        abort_unless(self::pacoteAtivo($businessId), 403, 'O pacote da empresa não inclui o módulo Compras.');

        if ($permissao !== null) {
            abort_unless($user->can($permissao), 403);
        }

        return $next($request);
    }

    /** Regra única do pacote, usada também pelo menu (DataController::modifyAdminMenu). */
    public static function pacoteAtivo(int $businessId): bool
    {
        return (bool) app(ModuleUtil::class)
            ->hasThePermissionInSubscription($businessId, self::PACOTE, 'superadmin_package');
    }
}
