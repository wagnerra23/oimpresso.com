<?php

namespace Modules\Captacao\Services;

use App\Utils\ModuleUtil;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Regra única de acesso do módulo (ADR 0452 D1), usada pelo middleware e, a partir da onda 2,
 * pelo menu — para que menu e rota nunca discordem.
 *
 *  1. pacote `captacao_module` da empresa (superadmin dispensa só a permissão, nunca o pacote);
 *  2. permissão `captacao.access`.
 *
 * O ModuleUtil vem do container (app()), nunca `new`: os testes trocam o pacote por empresa.
 */
class CaptacaoAcesso
{
    public const PACOTE = 'captacao_module';

    public const PERMISSAO = 'captacao.access';

    public const PERMISSAO_EDITAR = 'captacao.editar';

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
