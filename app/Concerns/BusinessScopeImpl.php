<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Implementacao concreta do scope global por business_id (ADR 0093).
 * Separada do trait pra permitir withoutGlobalScope(BusinessScopeImpl::class).
 *
 * Espelha Modules\Financeiro\Models\Concerns\BusinessScopeImpl.
 */
class BusinessScopeImpl implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Sem sessao (CLI/migration/job): nao aplica scope. Caller responsavel
        // por filtrar business_id explicitamente (Garantia 3 ADR 0093).
        if (! session()->has('user.business_id')) {
            return;
        }

        // Superadmin: cross-tenant permitido (uso administrativo).
        if (auth()->check() && auth()->user()->can('superadmin')) {
            return;
        }

        $businessId = (int) session('user.business_id');
        $builder->where($model->getTable() . '.business_id', $businessId);
    }
}
