<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Escopo da biblioteca de exercícios: `business_id = sessão OR business_id IS NULL`.
 *
 * O `HasBusinessScope` cru filtra só `business_id = sessão` e esconderia a biblioteca global
 * (business_id nulo) de todo usuário comum. Mesma semântica do `App\Scopes\ScopeByBusiness`
 * no resto: sem auth ou sem empresa na sessão (CLI/jobs) não filtra — o job passa o
 * business no construtor e filtra explicitamente (ADR 0093).
 *
 * Global é LEITURA para todo business; editar global é recusado na camada HTTP (US-FISIO-004).
 */
trait EscopoBibliotecaFisio
{
    protected static function bootEscopoBibliotecaFisio(): void
    {
        static::addGlobalScope('fisio_biblioteca', function (Builder $builder): void {
            if (! auth()->check()) {
                return;
            }
            $businessId = session('user.business_id');
            if (! $businessId) {
                return;
            }
            $tabela = $builder->getModel()->getTable();
            $builder->where(function (Builder $q) use ($tabela, $businessId): void {
                $q->where("{$tabela}.business_id", $businessId)->orWhereNull("{$tabela}.business_id");
            });
        });
    }
}