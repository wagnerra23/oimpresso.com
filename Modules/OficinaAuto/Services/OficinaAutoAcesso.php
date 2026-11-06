<?php

namespace Modules\OficinaAuto\Services;

use App\Utils\ModuleUtil;

/**
 * Regra única de "a empresa contratou o Oficina Auto?" — pacote `oficina_auto_module`
 * do superadmin (Camada 1 de memory/reference/feedback-habilitar-modulo-por-business.md).
 *
 * Usada pelo middleware das rotas (OficinaAutoLiberado), pelo item da sidebar
 * (DataController::modifyAdminMenu) e pelos comandos agendados, para que menu, rota e
 * cron nunca discordem. Habilitar por empresa é SÓ pelo pacote; nunca `if ($biz === N)`.
 *
 * O ModuleUtil vem do container (app()), nunca `new`: os testes trocam o pacote por
 * empresa com um partial mock em app()->instance() (mesmo idioma do FisioAcesso).
 */
class OficinaAutoAcesso
{
    public const PACOTE = 'oficina_auto_module';

    public function pacoteAtivo($businessId): bool
    {
        if (empty($businessId)) {
            return false;
        }

        return (bool) app(ModuleUtil::class)
            ->hasThePermissionInSubscription((int) $businessId, self::PACOTE, 'superadmin_package');
    }
}
