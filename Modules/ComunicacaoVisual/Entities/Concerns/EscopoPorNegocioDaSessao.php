<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Entities\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Escopo multi-tenant dos Models da Comunicação Visual (Tier 0, ADR 0093).
 *
 * É o mesmo filtro que os Models do módulo já aplicavam numa closure dentro de `booted()`,
 * movido para trait para o MultiTenantScopeArchitectureTest reconhecê-lo. O comportamento não
 * muda, e por isso NÃO usa o App\Scopes\ScopeByBusiness, que difere em três pontos: exige
 * `auth()`, deixa o superadmin ver linhas sem empresa e ignora o fallback `business.id`.
 *
 * - Filtra `<tabela>.business_id` pela empresa da sessão (`user.business_id`, senão `business.id`).
 * - Sem empresa na sessão (CLI, fila, app por token), não filtra: quem chama passa o business_id
 *   explícito (ver OrcamentoGravador).
 * - O escopo continua com o nome `business_id`, então `withoutGlobalScope('business_id')` segue
 *   funcionando onde já era usado.
 */
trait EscopoPorNegocioDaSessao
{
    protected static function bootEscopoPorNegocioDaSessao(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where($query->getModel()->getTable().'.business_id', $businessId);
            }
        });
    }
}
