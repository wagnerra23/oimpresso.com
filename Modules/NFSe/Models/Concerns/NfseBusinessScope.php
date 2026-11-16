<?php

namespace Modules\NFSe\Models\Concerns;

use App\Tenancy\ContextoEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

trait NfseBusinessScope
{
    public static function bootNfseBusinessScope(): void
    {
        static::addGlobalScope(new class implements Scope {
            public function apply(Builder $builder, Model $model): void
            {
                // Empresa vem do ContextoEmpresa (ADR 0453): no modo `aberto` (padrão) é só a
                // sessão, como sempre foi; em `log`/`fechado` também job/API.
                $businessId = app(ContextoEmpresa::class)->empresaDoEscopo($model::class);
                if ($businessId === null) {
                    return;
                }
                // SEM excecao de superadmin — DECISAO [W] 2026-08-26: "so da empresa
                // selecionada". Mesma correcao do
                // Modules/Financeiro/Models/Concerns/BusinessScopeImpl.php, que carrega a
                // medicao de exposicao. Cross-tenant legitimo continua por
                // `withoutGlobalScopes()` explicito.
                $builder->where($model->getTable() . '.business_id', $businessId);
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->business_id) && session()->has('user.business_id')) {
                $model->business_id = session('user.business_id');
            }
        });
    }
}
