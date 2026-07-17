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
    /** Cache classe → nome da relação parent (evita reflection por query). */
    private static array $relationByClass = [];

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

        // Nome da relação parent declarado no Model. NÃO pode ser lido via
        // `$model->businessParentRelation` — ver resolveRelation() abaixo.
        $relation = $this->resolveRelation($model);

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

    /**
     * Resolve o nome da relação parent declarado no Model.
     *
     * ⚠️ NUNCA ler via `$model->businessParentRelation`: a property é `protected`,
     * então lida de FORA (desta classe Scope) cai no `__get` do Eloquent →
     * getAttribute → **NULL** (não o valor real 'conversa'/'meta'). Isso zerava o
     * scope silenciosamente (`if (! $relation) return` → fail-open → vazamento
     * cross-tenant Tier 0, ADR 0093). Bug latente desde Wave 7 (2026-05-16),
     * mascarado pelo FK-1452 do teste; descoberto e provado no CT100 em 2026-07-17
     * (SQL sem whereHas + LEAKED=YES). Reflection lê o valor real independentemente
     * da visibilidade. Cacheado por classe (valor é default estático do Model).
     */
    private function resolveRelation(Model $model): ?string
    {
        $class = get_class($model);

        if (! array_key_exists($class, self::$relationByClass)) {
            $value = null;
            if (property_exists($model, 'businessParentRelation')) {
                $ref = new \ReflectionProperty($model, 'businessParentRelation');
                $ref->setAccessible(true);
                $value = $ref->getValue($model);
            }
            self::$relationByClass[$class] = (is_string($value) && $value !== '') ? $value : null;
        }

        return self::$relationByClass[$class];
    }
}
