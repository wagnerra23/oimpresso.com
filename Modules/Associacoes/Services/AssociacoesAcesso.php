<?php

namespace Modules\Associacoes\Services;

use App\Utils\ModuleUtil;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Associacoes\Entities\Representante;

/**
 * Regra única de acesso do módulo (ADR 0451 D4). Usada pelo middleware, pelo menu e por toda
 * consulta das telas, para que menu, rota e lista nunca discordem.
 *
 *  1. pacote `associacoes_module` da empresa (superadmin dispensa só a permissão, nunca o pacote);
 *  2. permissão `associacoes.access`;
 *  3. alcance: com `associacoes.rede` (ou superadmin) vê a rede inteira; sem ela, só as
 *     associações em que é representante (`assoc_representantes`).
 *
 * O ModuleUtil vem do container (app()), nunca `new`: os testes trocam o pacote por empresa.
 */
class AssociacoesAcesso
{
    public const PACOTE = 'associacoes_module';

    public const PERMISSAO = 'associacoes.access';

    public const PERMISSAO_REDE = 'associacoes.rede';

    public const PERMISSAO_EDITAR = 'associacoes.editar';

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

    public function veARede(Authenticatable $user): bool
    {
        return $user->can(self::PERMISSAO_REDE) || $user->can('superadmin');
    }

    /**
     * IDs de associação que o usuário alcança; `null` = a rede inteira. Lista vazia = nenhuma
     * (usuário com acesso ao módulo mas sem vínculo de representante).
     *
     * @return list<int>|null
     */
    public function associacoesVisiveis(Authenticatable $user): ?array
    {
        if ($this->veARede($user)) {
            return null;
        }

        return Representante::query()
            ->where('user_id', (int) $user->getAuthIdentifier())
            ->pluck('associacao_id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Restringe uma consulta ao alcance do usuário. `$coluna` é a coluna com o id da associação
     * (`id` em assoc_associacoes, `associacao_id` nas demais). O escopo de empresa já vem do Model.
     */
    public function restringir(Builder $query, Authenticatable $user, string $coluna = 'associacao_id'): Builder
    {
        $ids = $this->associacoesVisiveis($user);

        return $ids === null ? $query : $query->whereIn($query->qualifyColumn($coluna), $ids);
    }
}
