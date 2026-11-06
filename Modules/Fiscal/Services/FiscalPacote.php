<?php

namespace Modules\Fiscal\Services;

use App\Utils\ModuleUtil;

/**
 * Regra única de "esta empresa tem o pacote fiscal na assinatura?".
 *
 * Usada pelo middleware das rotas (FiscalLiberado). A mesma pergunta já é feita pelo
 * menu (DataController::modifyAdminMenu) e pela API do app
 * (App\Http\Controllers\Api\App\FiscalController::tiposVisiveis) — antes deste
 * serviço a URL direta era a única porta que não perguntava.
 *
 * Superadmin passa: é o comportamento do próprio ModuleUtil::hasThePermissionInSubscription
 * e do menu, que mostra o Fiscal ao superadmin quando o módulo está instalado.
 *
 * O ModuleUtil vem SEMPRE do container (app()), nunca `new`: os testes trocam o pacote
 * por empresa com um partial mock em app()->instance().
 */
class FiscalPacote
{
    public const PACOTE = 'fiscal_module';

    public function ativo($businessId): bool
    {
        if (empty($businessId)) {
            return false;
        }

        return (bool) app(ModuleUtil::class)
            ->hasThePermissionInSubscription((int) $businessId, self::PACOTE, 'superadmin_package');
    }
}
