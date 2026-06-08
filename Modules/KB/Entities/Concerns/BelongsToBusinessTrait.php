<?php

declare(strict_types=1);

namespace Modules\KB\Entities\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait BelongsToBusinessTrait — multi-tenant Tier 0 (ADR 0093).
 *
 * Aplica global scope `business_id` em toda query que use o Model.
 *
 * Resolução do business_id (em ordem):
 *   1. session('user.business_id')  ← canônico UltimatePOS
 *   2. session('business.id')        ← fallback legado
 *
 * Se nenhum dos dois está populado (CLI, queue, tests com biz seedado
 * manualmente), o scope NÃO aplica filtro — quem invoca é responsável
 * por passar business_id explicitamente OU usar `withoutGlobalScopes`
 * com comentário `// SUPERADMIN: <razão>`.
 *
 * Jobs assíncronos NUNCA dependem deste trait pra resolver business_id —
 * recebem $businessId no constructor (session() não funciona em fila).
 *
 * Auto-fill: ao criar uma nova row, business_id é preenchido a partir
 * da sessão se ainda for null.
 *
 * **Tier 0 IRREVOGÁVEL** ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
 *
 * Por que módulo-local em vez de app/Traits ou Modules/Jana/Scopes:
 *   - Repo-wide não tinha trait canônico (Grep negativo em 2026-05-15)
 *   - Modules/ComunicacaoVisual e Modules/OficinaAuto inline o boilerplate
 *     na booted() de cada Model — funciona mas duplica código
 *   - Trait local em KB é DRY e isolado; outros módulos podem mover pra
 *     app/Traits se quiserem reusar (refactor sem ADR — sem mudar contrato)
 *
 * TODO[CL]: avaliar promover trait pra app/Traits se outro módulo precisar
 * antes da ONDA 6 (KB referenciando OS/cliente/NFe).
 */
trait BelongsToBusinessTrait
{
    public static function bootBelongsToBusinessTrait(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = self::resolveBusinessId();
            if ($businessId !== null) {
                $table = $query->getModel()->getTable();
                $query->where("{$table}.business_id", $businessId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->business_id)) {
                $resolved = self::resolveBusinessId();
                if ($resolved !== null) {
                    $model->business_id = $resolved;
                }
            }
        });
    }

    /**
     * Resolução canônica do business_id ativo na request/sessão.
     *
     * Retorna null em CLI, queue, ou test sem sessão setada — comportamento
     * intencional pra não vazar entre tenants e forçar passagem explícita.
     */
    protected static function resolveBusinessId(): ?int
    {
        $fromSession = session('user.business_id') ?? session('business.id');
        if ($fromSession !== null) {
            return (int) $fromSession;
        }

        return null;
    }
}
