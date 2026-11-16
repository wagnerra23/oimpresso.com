<?php

namespace App\Scopes;

use App\Tenancy\ContextoEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope — tenancy híbrida (adr/arq/0001).
 *
 * Padrão:
 * - Usuário comum vê apenas rows com business_id = session('user.business_id').
 * - Superadmin (permissão jana.superadmin) vê do próprio business
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
        if (ContextoEmpresa::modo() === ContextoEmpresa::ABERTO) {
            // Legado (padrão até o rollout da ADR 0453): sem sessão web → sem filtro.
            if (! auth()->check()) {
                return;
            }

            $businessId = session('user.business_id');

            if (! $businessId) {
                return;
            }
        } else {
            // ADR 0453: empresa vem do contexto (explícito → sessão → usuário do token).
            $contexto = app(ContextoEmpresa::class);
            if ($contexto->emModoPlataforma()) {
                return;
            }

            $businessId = $contexto->atual();

            if (! $businessId) {
                $contexto->semEmpresa($model::class); // log ou exceção, conforme o modo

                return;
            }
        }

        $user = auth()->user();

        if ($user && method_exists($user, 'can') && $user->can('jana.superadmin')) {
            $builder->where(function (Builder $q) use ($businessId) {
                $q->where("{$q->getModel()->getTable()}.business_id", $businessId)
                  ->orWhereNull("{$q->getModel()->getTable()}.business_id");
            });
            return;
        }

        $builder->where("{$builder->getModel()->getTable()}.business_id", $businessId);
    }
}
