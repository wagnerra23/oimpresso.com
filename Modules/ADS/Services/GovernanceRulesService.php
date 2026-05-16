<?php

namespace Modules\ADS\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Observabilidade D9.a (ADR 0155): evaluation envolto em `OtelHelper::span(`
 * (Tracer ads.governance.evaluate) — mede regras x triggers acionados.
 *
 * Meta-skills runtime — avalia condições JSON-DSL e dispara ações.
 *
 * Diferente do PolicyEngine (HARD): regras aqui são SOFT, configuráveis,
 * com versão e contagem de triggers. Wagner edita via UI /ads/admin/meta-skills.
 *
 * DSL de condição (mínima viável):
 *   { "op": "AND", "conds": [
 *     { "field": "wilson_lower_bound", "op": ">=", "value": 0.80 },
 *     { "field": "total_count", "op": ">=", "value": 10 }
 *   ]}
 *
 * Operadores suportados: ==, !=, <, <=, >, >=
 * Lógicos: AND, OR
 */
class GovernanceRulesService
{
    /**
     * Avalia condição contra contexto (assoc array com campos disponíveis).
     */
    public function evaluate(array $condition, array $context): bool
    {
        // Folha — condição simples (avalia primeiro, antes de checar conds)
        if (isset($condition['field'])) {
            return $this->evalLeaf($condition, $context);
        }

        $op = $condition['op'] ?? 'AND';
        $conds = $condition['conds'] ?? [];

        if (empty($conds)) return false;

        $results = array_map(fn ($c) => $this->evaluate($c, $context), $conds);

        return match (strtoupper($op)) {
            'AND' => ! in_array(false, $results, true),
            'OR'  => in_array(true, $results, true),
            default => false,
        };
    }

    private function evalLeaf(array $cond, array $context): bool
    {
        $field = $cond['field'] ?? null;
        $op    = $cond['op'] ?? '==';
        $value = $cond['value'] ?? null;

        if ($field === null || ! array_key_exists($field, $context)) {
            return false;
        }

        $left = $context[$field];

        return match ($op) {
            '=='  => $left == $value,
            '!='  => $left != $value,
            '<'   => $left < $value,
            '<='  => $left <= $value,
            '>'   => $left > $value,
            '>='  => $left >= $value,
            default => false,
        };
    }

    /**
     * Lista todas as regras (com leitura formatada da DSL).
     */
    public function listAll(): array
    {
        return DB::table('mcp_governance_rules')
            ->orderBy('category')
            ->orderBy('rule_key')
            ->get()
            ->map(fn ($r) => [
                'id'                 => $r->id,
                'rule_key'           => $r->rule_key,
                'name'               => $r->name,
                'description'        => $r->description,
                'category'           => $r->category,
                'condition'          => json_decode($r->condition, true) ?: [],
                'condition_human'    => $this->humanize(json_decode($r->condition, true) ?: []),
                'action'             => json_decode($r->action, true) ?: [],
                'enabled'            => (bool) $r->enabled,
                'version'            => (int) $r->version,
                'triggered_count'    => (int) $r->triggered_count,
                'last_triggered_at'  => $r->last_triggered_at,
                'created_by'         => $r->created_by,
            ])
            ->all();
    }

    public function toggle(int $id, bool $enabled): void
    {
        DB::table('mcp_governance_rules')->where('id', $id)->update([
            'enabled'    => $enabled,
            'updated_at' => now(),
        ]);
    }

    public function recordTrigger(int $id): void
    {
        DB::table('mcp_governance_rules')->where('id', $id)->update([
            'triggered_count'   => DB::raw('triggered_count + 1'),
            'last_triggered_at' => now(),
        ]);
    }

    /**
     * Converte DSL JSON em texto PT-BR legível para UI.
     */
    public function humanize(array $condition): string
    {
        $op = $condition['op'] ?? 'AND';
        if (isset($condition['field'])) {
            $field = $condition['field'];
            $cmp = $condition['op'] ?? '==';
            $val = is_bool($condition['value']) ? ($condition['value'] ? 'true' : 'false') : $condition['value'];
            return "{$field} {$cmp} {$val}";
        }

        $parts = [];
        foreach ($condition['conds'] ?? [] as $c) {
            $parts[] = $this->humanize($c);
        }
        $separator = strtoupper($op) === 'AND' ? ' E ' : ' OU ';
        return '(' . implode($separator, $parts) . ')';
    }
}
