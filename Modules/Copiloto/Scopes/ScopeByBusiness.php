<?php

namespace Modules\Copiloto\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope — tenancy híbrida (adr/arq/0001).
 *
 * Padrão:
 * - Usuário comum vê apenas rows com business_id = session('user.business_id').
 * - Superadmin (permissão copiloto.superadmin) vê do próprio business
 *   MAIS rows com business_id NULL (metas da plataforma).
 *
 * Toda query que deliberadamente queira sair desse escopo precisa chamar
 * ->withoutGlobalScope(ScopeByBusiness::class). Caso contrário, NUNCA há
 * vazamento entre businesses.
 */
class ScopeByBusiness implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Sessão sem user_id → sem filtro (CLI/jobs resolvem manualmente)
        if (! auth()->check()) {
            return;
        }

        $businessId = session('user.business_id');

        if (! $businessId) {
            return;
        }

        $user = auth()->user();

        if ($user && method_exists($user, 'can') && $user->can('copiloto.superadmin')) {
            $builder->where(function (Builder $q) use ($businessId) {
                $q->where("{$q->getModel()->getTable()}.business_id", $businessId)
                  ->orWhereNull("{$q->getModel()->getTable()}.business_id");
            });
            return;
        }

        $builder->where("{$builder->getModel()->getTable()}.business_id", $businessId);
    }
}
