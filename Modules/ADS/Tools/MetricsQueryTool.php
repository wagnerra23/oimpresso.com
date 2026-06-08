<?php

namespace Modules\ADS\Tools;

use Illuminate\Support\Facades\DB;
use Modules\ADS\Contracts\Tool;

/**
 * Tool: query agregada em mcp_dual_brain_decisions.
 * Read-only. Permite agentes responderem "quantas decisões falharam essa semana?"
 */
class MetricsQueryTool implements Tool
{
    public function name(): string { return 'metrics_query'; }
    public function category(): string { return 'análise'; }
    public function isReadOnly(): bool { return true; }

    public function description(): string
    {
        return 'Consulta agregada em mcp_dual_brain_decisions: contagem por destination/'
             . 'outcome/domain/event_type, com filtro temporal. '
             . 'Use para responder perguntas analíticas sobre o sistema ADS.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group_by'   => ['type' => 'string', 'enum' => ['destination', 'outcome', 'domain', 'event_type'], 'default' => 'destination'],
                'since'      => ['type' => 'string', 'description' => 'Inicio (ex: -7 days, -30 days)', 'default' => '-7 days'],
                'business_id' => ['type' => 'integer', 'description' => 'Filtra por business; null = todos'],
                'limit'      => ['type' => 'integer', 'default' => 20],
            ],
            'required' => [],
        ];
    }

    public function execute(array $input): array
    {
        $allowed = ['destination', 'outcome', 'domain', 'event_type'];
        $groupBy = in_array($input['group_by'] ?? '', $allowed, true) ? $input['group_by'] : 'destination';
        $since   = $input['since'] ?? '-7 days';
        $bizId   = $input['business_id'] ?? null;
        $limit   = min(100, max(1, (int) ($input['limit'] ?? 20)));

        $query = DB::table('mcp_dual_brain_decisions')
            ->select($groupBy, DB::raw('count(*) as n'))
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime($since)))
            ->groupBy($groupBy)
            ->orderByDesc('n')
            ->limit($limit);

        if ($bizId) {
            $query->where('business_id', (int) $bizId);
        }

        $rows = $query->get()->map(fn ($r) => [
            $groupBy => $r->$groupBy,
            'count'  => (int) $r->n,
        ])->all();

        $total = (int) array_sum(array_column($rows, 'count'));

        return [
            'ok' => true,
            'output' => [
                'group_by' => $groupBy,
                'since'    => $since,
                'total'    => $total,
                'rows'     => $rows,
            ],
            'error' => null,
        ];
    }
}
