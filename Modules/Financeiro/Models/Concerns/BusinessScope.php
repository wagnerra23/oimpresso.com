<?php

namespace Modules\Financeiro\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait que aplica scope global por business_id em queries do módulo Financeiro.
 *
 * Uso:
 *   class Titulo extends Model {
 *       use BusinessScope;
 *   }
 *
 * Bypass intencional (admin / auditor cross-tenant):
 *   Titulo::withoutGlobalScope(BusinessScopeImpl::class)->where(...)->get();
 *
 * Scope NÃO é aplicado quando:
 *   - Não há sessão (CLI artisan, jobs sem session pré-bootada)
 *   - Usuário é superadmin (definido por can('superadmin'))
 */
trait BusinessScope
{
    public static function bootBusinessScope(): void
    {
        static::addGlobalScope(new BusinessScopeImpl());

        // Auto-preenche business_id ao criar (DRY).
        static::creating(function (Model $model) {
            // getAttribute/setAttribute: o tipo do parâmetro é Model, que não declara
            // business_id; com a propriedade mágica o PHPStan acusa em cada model novo.
            if (empty($model->getAttribute('business_id')) && session()->has('user.business_id')) {
                $model->setAttribute('business_id', session('user.business_id'));
            }
        });
    }
}
