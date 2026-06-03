<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait que aplica scope global por business_id (multi-tenant Tier 0 IRREVOGAVEL,
 * ADR 0093 Garantia 2). Versao App-root, espelha
 * Modules/Financeiro/Models/Concerns/BusinessScope (padrao canonico validado).
 *
 * Vive em App\Concerns (e nao import cross-module de Modules\Financeiro) pra
 * nao acoplar a camada App ao modulo Financeiro.
 *
 * Uso:
 *   class ContactAddress extends Model {
 *       use \App\Concerns\BusinessScope;
 *   }
 *
 * Bypass intencional (admin / auditor / job cross-tenant):
 *   ContactAddress::withoutGlobalScope(BusinessScopeImpl::class)->where(...)->get();
 *
 * Scope NAO e aplicado quando:
 *   - Nao ha sessao (CLI artisan, migration, jobs sem session pre-bootada)
 *   - Usuario e superadmin (auth()->user()->can('superadmin'))
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
trait BusinessScope
{
    public static function bootBusinessScope(): void
    {
        static::addGlobalScope(new BusinessScopeImpl());

        // Auto-preenche business_id ao criar (DRY) -- so quando ha sessao.
        static::creating(function (Model $model) {
            if (empty($model->business_id) && session()->has('user.business_id')) {
                $model->business_id = session('user.business_id');
            }
        });
    }
}
