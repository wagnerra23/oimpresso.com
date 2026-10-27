<?php

namespace Modules\Marketplaces\Services;

use App\Utils\ModuleUtil;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Regra única de "o Marketplaces está liberado para este usuário nesta empresa?" (ADR 0439).
 * Pacote `marketplaces_module` da empresa → permissão do papel (superadmin dispensa só a
 * permissão, nunca o pacote). O ModuleUtil vem do container para os testes trocarem o pacote.
 */
class MarketplacesAcesso
{
    public const PACOTE = 'marketplaces_module';

    public const PERMISSAO = 'marketplaces.access';

    public const PERMISSAO_CONECTAR = 'marketplaces.account.connect';

    public function liberado(?Authenticatable $user, $businessId, string $permissao = self::PERMISSAO): bool
    {
        if ($user === null || empty($businessId) || ! $this->pacoteAtivo((int) $businessId)) {
            return false;
        }

        return $user->can($permissao) || $user->can('superadmin');
    }

    public function pacoteAtivo(int $businessId): bool
    {
        return (bool) app(ModuleUtil::class)
            ->hasThePermissionInSubscription($businessId, self::PACOTE, 'superadmin_package');
    }
}
