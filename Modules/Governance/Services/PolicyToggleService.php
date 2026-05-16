<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PolicyToggleService — CRUD inline de policies em `mcp_governance_rules`
 * (Constituição Art. 8).
 *
 * Permite Wagner editar rules pelo painel `/governance/policies` sem
 * tocar em código. Toda mudança deve futuramente virar INSERT em
 * `mcp_governance_rule_history` (Fase 5+1 — audit append-only).
 *
 * MVP: listagem agrupada por categoria + toggle enabled. Edit inline +
 * create new ficam pra próxima iteração quando frontend ganhar editor JSON.
 *
 * @see Modules\Governance\Http\Controllers\PoliciesController
 */
class PolicyToggleService
{
    /**
     * Lista todas policies ordenadas por enabled desc, categoria, rule_key.
     *
     * @return Collection<int, object>
     */
    public function listPolicies(): Collection
    {
        // D9.a OTel: wrap query de policies (UI Governance list).
        return OtelHelper::spanBiz('governance.policy_toggle.list_policies', function (): Collection {
            return DB::table('mcp_governance_rules')
                ->orderByDesc('enabled')
                ->orderBy('category')
                ->orderBy('rule_key')
                ->get();
        }, [
            'module' => 'Governance',
        ]);
    }

    /**
     * Agrupa coleção de policies por categoria, formatando para a UI Inertia.
     *
     * @param  Collection<int, object>  $rules
     * @return Collection<int, array{category: string, rules: Collection<int, array>}>
     */
    public function groupByCategory(Collection $rules): Collection
    {
        return $rules->groupBy('category')->map(function ($group, $cat) {
            return [
                'category' => $cat ?: 'uncategorized',
                'rules' => $group->map(function ($r) {
                    return [
                        'id'              => $r->id,
                        'rule_key'        => $r->rule_key,
                        'name'            => $r->name,
                        'description'     => $r->description,
                        'enabled'         => (bool) $r->enabled,
                        'version'         => $r->version,
                        'triggered_count' => $r->triggered_count ?? 0,
                        'created_by'      => $r->created_by,
                        'updated_at'      => $r->updated_at,
                    ];
                })->values(),
            ];
        })->values();
    }

    /**
     * KPIs agregados a partir das policies (total/enabled/triggered/categories).
     *
     * @param  Collection<int, object>     $rules
     * @param  Collection<int, mixed>      $byCategory
     * @return array{total: int, enabled: int, triggered: int, categories: int}
     */
    public function kpisFor(Collection $rules, Collection $byCategory): array
    {
        return [
            'total'      => $rules->count(),
            'enabled'    => $rules->where('enabled', 1)->count(),
            'triggered'  => (int) $rules->sum('triggered_count'),
            'categories' => $byCategory->count(),
        ];
    }

    /**
     * Alterna estado `enabled` de uma policy.
     *
     * Retorna o NOVO estado (true=ativada, false=desativada). Se policy não
     * existir, retorna false sem lançar exceção (fail-safe pra UI).
     *
     * @param  int   $id       PK em `mcp_governance_rules.id`
     * @param  bool  $enabled  Novo estado desejado
     */
    public function togglePolicy(int $id, bool $enabled): bool
    {
        // D9.a OTel: wrap UPDATE de policy (rastreabilidade de mudanças de governança).
        return OtelHelper::spanBiz('governance.policy_toggle.toggle_policy', function () use ($id, $enabled): bool {
            DB::table('mcp_governance_rules')
                ->where('id', $id)
                ->update([
                    'enabled'    => $enabled ? 1 : 0,
                    'updated_at' => now(),
                ]);

            return $enabled;
        }, [
            'module'     => 'Governance',
            'policy_id'  => $id,
            'new_state'  => $enabled ? 'enabled' : 'disabled',
        ]);
    }
}
