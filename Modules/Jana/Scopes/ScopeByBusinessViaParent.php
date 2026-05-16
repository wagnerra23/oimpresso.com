<?php

declare(strict_types=1);

namespace Modules\Jana\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope multi-tenant Tier 0 via parent (ADR 0093 IRREVOGÁVEL).
 *
 * Aplica filtro de business_id em Models FILHAS que NÃO têm coluna `business_id`
 * direta — herdam via FK chain pra parent (que TEM coluna + ScopeByBusiness).
 *
 * Casos canônicos (Wave 7 audit 2026-05-16):
 *  - Mensagem / Sugestao  → parent = Conversa (jana_conversas.business_id)
 *  - MetaApuracao / MetaFonte / MetaPeriodo  → parent = Meta (jana_metas.business_id)
 *
 * Comportamento (mesma semântica do ScopeByBusiness):
 *  - Sem auth (CLI/jobs) → sem filtro (job DEVE passar $businessId no constructor)
 *  - Sem session('user.business_id') → sem filtro (login flow não ativo)
 *  - Superadmin → próprio business + parents com business_id NULL (plataforma)
 *  - Usuário comum → apenas rows cujo parent.business_id casa com session
 *
 * Defesa em profundidade: query DIRETA `Sugestao::all()` autenticado como biz=1
 * NÃO retorna mais Sugestoes cujo conversa.business_id ≠ 1 (cross-tenant fix).
 *
 * Para sair do escopo deliberadamente:
 *   Sugestao::withoutGlobalScope(ScopeByBusinessViaParent::class)->get();
 *   // SUPERADMIN: <razão> — skill commit-discipline (Tier A) flag se faltar comentário
 *
 * @see Modules\Jana\Scopes\ScopeByBusiness        scope direto (para parents)
 * @see App\Concerns\BelongsToBusinessViaParent    trait que aplica este scope
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ScopeByBusinessViaParent implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // CLI/jobs sem auth → sem filtro (resolve manualmente).
        if (! auth()->check()) {
            return;
        }

        $businessId = session('user.business_id');

        if (! $businessId) {
            return;
        }

        // Property obrigatória na Model que usa este scope.
        $relation = property_exists($model, 'businessParentRelation')
            ? $model->businessParentRelation
            : null;

        if (! $relation || ! method_exists($model, $relation)) {
            // Defensivo: se Model não declarou parent relation, não filtra
            // (fail-open + log via worklog futuro). Não dispara exception
            // pra não quebrar telas em prod — Pest pega config errada.
            return;
        }

        $user = auth()->user();
        $isSuperadmin = $user && method_exists($user, 'can') && $user->can('jana.superadmin');

        $builder->whereHas($relation, function (Builder $q) use ($businessId, $isSuperadmin) {
            $parentTable = $q->getModel()->getTable();

            if ($isSuperadmin) {
                $q->where(function (Builder $inner) use ($parentTable, $businessId) {
                    $inner->where("{$parentTable}.business_id", $businessId)
                        ->orWhereNull("{$parentTable}.business_id");
                });

                return;
            }

            $q->where("{$parentTable}.business_id", $businessId);
        });
    }
}
