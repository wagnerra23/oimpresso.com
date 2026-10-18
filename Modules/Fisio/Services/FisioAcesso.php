<?php

namespace Modules\Fisio\Services;

use App\Utils\ModuleUtil;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Regra única de "o Fisio está liberado para este usuário nesta empresa?" (ADR 0437 §3.3).
 *
 * Usada pelo middleware das rotas e pelo item da sidebar, para que menu e rota nunca
 * discordem. Três camadas, como o golden do Arquivos: pacote `fisio_module` da empresa
 * → permissão `fisio.access` do papel (superadmin dispensa só a permissão, nunca o
 * pacote) → acesso.
 *
 * O ModuleUtil vem SEMPRE do container (app()), nunca `new`: os testes trocam o
 * pacote por empresa com um partial mock registrado em app()->instance().
 */
class FisioAcesso
{
    public const PACOTE = 'fisio_module';

    public const PERMISSAO = 'fisio.access';

    public function liberado(?Authenticatable $user, $businessId): bool
    {
        if ($user === null || empty($businessId)) {
            return false;
        }

        if (! $this->pacoteAtivo((int) $businessId)) {
            return false;
        }

        return $user->can(self::PERMISSAO) || $user->can('superadmin');
    }

    public function pacoteAtivo(int $businessId): bool
    {
        return (bool) app(ModuleUtil::class)
            ->hasThePermissionInSubscription($businessId, self::PACOTE, 'superadmin_package');
    }
}