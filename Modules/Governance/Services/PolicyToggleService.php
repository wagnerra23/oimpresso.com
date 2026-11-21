<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use App\User;
use App\Util\OtelHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PolicyToggleService — CRUD inline de policies em `mcp_governance_rules`
 * (Constituição Art. 8).
 *
 * Permite Wagner editar rules pelo painel `/governance/policies` sem
 * tocar em código. Desde 2026-11-19 (lote L0 da auditoria do Governance) cada
 * alternância que muda o estado grava UMA linha em `activity_log` (Spatie, o
 * mecanismo de auditoria que o repo já usa): log `governance`, evento `updated`,
 * causer = quem alternou, properties com `policy_id`, `rule_key`, `old.enabled`
 * e `attributes.enabled`. Este código só insere — nunca altera nem apaga a
 * trilha. Sem subject e sem `business_id`: `mcp_governance_rules` é tabela de
 * plataforma (não tem a coluna), então a linha fica fora da /auditoria de
 * qualquer empresa (mesma regra dos logs de plataforma do
 * `ActivityCauserKindObserver`). A `mcp_governance_rule_history` prevista na
 * "Fase 5+1" não foi criada: o `activity_log` já cobre quem/quando/
 * antes→depois sem tabela nova.
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
     * Alterna estado `enabled` de uma policy e registra a trilha.
     *
     * Retorna o NOVO estado, ou `null` quando a policy não existe (o controller
     * responde 404 — antes respondia sucesso para id inexistente). Quando o
     * estado pedido já é o atual, nada é escrito: não há mudança a registrar.
     *
     * @param  int       $id        PK em `mcp_governance_rules.id`
     * @param  bool      $enabled   Novo estado desejado
     * @param  int|null  $causerId  `users.id` de quem alternou
     */
    public function togglePolicy(int $id, bool $enabled, ?int $causerId = null): ?bool
    {
        // D9.a OTel: wrap UPDATE de policy (rastreabilidade de mudanças de governança).
        return OtelHelper::spanBiz('governance.policy_toggle.toggle_policy', function () use ($id, $enabled, $causerId): ?bool {
            return DB::transaction(function () use ($id, $enabled, $causerId): ?bool {
                $atual = DB::table('mcp_governance_rules')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first(['id', 'rule_key', 'enabled']);

                if ($atual === null) {
                    return null;
                }

                $antes = (bool) $atual->enabled;
                if ($antes === $enabled) {
                    return $enabled;
                }

                DB::table('mcp_governance_rules')
                    ->where('id', $id)
                    ->update([
                        'enabled'    => $enabled ? 1 : 0,
                        'updated_at' => now(),
                    ]);

                $log = activity('governance')
                    ->event('updated')
                    ->withProperties([
                        'policy_id'  => (int) $atual->id,
                        'rule_key'   => (string) $atual->rule_key,
                        'old'        => ['enabled' => $antes],
                        'attributes' => ['enabled' => $enabled],
                    ]);
                $causer = $causerId !== null ? User::find($causerId) : null;
                if ($causer !== null) {
                    $log->causedBy($causer);
                }
                $log->log('governance.policy.toggle');

                return $enabled;
            });
        }, [
            'module'     => 'Governance',
            'policy_id'  => $id,
            'new_state'  => $enabled ? 'enabled' : 'disabled',
        ]);
    }
}
