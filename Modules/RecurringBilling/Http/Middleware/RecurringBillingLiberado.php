<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Middleware;

use App\Utils\ModuleUtil;
use Closure;
use Illuminate\Http\Request;

/**
 * 403 nas rotas web da Cobrança Recorrente quando a empresa não tem o pacote
 * `recurringbilling_module` (Camada 1, Superadmin > Pacotes).
 *
 * Antes só o menu conferia o pacote (DataController::modifyAdminMenu): a empresa sem o
 * pacote abria /recurring-billing pela URL (auditoria RecurringBilling 2026-11-19, achado a5).
 *
 * Superadmin passa dentro do próprio `hasThePermissionInSubscription`. O ModuleUtil vem do
 * container para os testes trocarem o pacote (mesmo padrão do ComprasLiberado).
 *
 * NÃO vai nos webhooks (Asaas/Inter chegam sem usuário) nem no install do módulo.
 */
class RecurringBillingLiberado
{
    public const PACOTE = 'recurringbilling_module';

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $businessId = (int) ($user?->business_id ?? 0);

        abort_if($user === null || $businessId <= 0, 403, 'Cobrança Recorrente não liberada para esta empresa.');

        abort_unless(self::pacoteAtivo($businessId), 403, 'O pacote da empresa não inclui a Cobrança Recorrente.');

        return $next($request);
    }

    public static function pacoteAtivo(int $businessId): bool
    {
        return (bool) app(ModuleUtil::class)
            ->hasThePermissionInSubscription($businessId, self::PACOTE, 'superadmin_package');
    }
}
