<?php

namespace Modules\Financeiro\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Implementação concreta do scope global por business_id.
 * Separada do trait pra permitir withoutGlobalScope(BusinessScopeImpl::class).
 */
class BusinessScopeImpl implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Sem sessão (CLI/job): não aplica scope. Caller responsável por filtrar.
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
