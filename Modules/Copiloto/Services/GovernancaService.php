<?php

namespace Modules\Copiloto\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MEM-MCP-1.e (ADR 0053) — Service de governança do MCP server.
 *
 * Lê o `mcp_audit_log` (append-only) e `mcp_usage_diaria` (agregações)
 * pra alimentar o dashboard `/copiloto/admin/governanca`.
 *
 * Diferente de CustosService (que mostra IA do CHAT por business), aqui
 * é o consumo do MCP cross-team — cada chamada que CADA dev faz pra MCP
 * server a partir de Claude Code/Desktop fica registrada.
 *
 * Permissão: `copiloto.mcp.usage.all` — só superadmin/Wagner por padrão.
 *
 * KPIs:
 *   - total de calls no período + breakdown por status (ok/denied/error/quota)
 *   - latency p50/p95/p99 (histograma duration_ms)
 *   - top tools/resources por volume
 *   - top users por volume + custo R$
 *   - série diária (gráfico)
 *   - denied breakdown por error_code (pra detectar misconfig RBAC)
 */
class GovernancaService
{
    /**
     * Resolve período a partir de preset ou range custom.
     * Mesmo contrato do CustosService.
     */
    public function resolverPeriodo(string $preset, ?string $de = null, ?string $ate = null): array
    {
        if ($preset === 'custom' && $de !== null && $ate !== null) {
            return [
                'inicio' => Carbon::parse($de)->startOfDay(),
                'fim'    => Carbon::parse($ate)->endOfDay(),
            ];
        }

        return match ($preset) {
            'hoje'         => [
                'inicio' => Carbon::now()->startOfDay(),
                'fim'    => Carbon::now()->endOfDay(),
            ],
            'ontem'        => [
                'inicio' => Carbon::now()->subDay()->startOfDay(),
                'fim'    => Carbon::now()->subDay()->endOfDay(),
            ],
            '7d'           => [
                'inicio' => Carbon::now()->subDays(6)->startOfDay(),
                'fim'    => Carbon::now()->endOfDay(),
            ],
            'mes_anterior' => [
                'inicio' => Carbon::now()->subMonthNoOverflow()->startOfMonth(),
                'fim'    => Carbon::now()->subMonthNoOverflow()->endOfMonth(),
            ],
            default        => [ // 30d
                'inicio' => Carbon::now()->subDays(29)->startOfDay(),
                'fim'    => Carbon::now()->endOfDay(),
            ],
        };
    }

    /**
     * Painel completo: KPIs + breakdowns + séries.
     *
     * @return array{
     *   kpis: array<string, mixed>,
     *   por_status: array<int, array{status: string, calls: int, pct: float}>,
     *   latency: array{p50: int, p95: int, p99: int, max: int},
     *   top_tools: array<int, array{tool: string, calls: int, custo_brl: float}>,
     *   top_users: array<int, array{user_id: int, nome: string, calls: int, custo_brl: float}>,
     *   denied_por_codigo: array<int, array{error_code: string, calls: int}>,
     *   serie_diaria: array<int, array{data: string, calls: int, custo_brl: float, denied: int}>,
     *   periodo: array{inicio: string, fim: string, label: string},
     * }
     */
    public function painel(CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $iniSql = $inicio->copy()->startOfDay()->toDateTimeString();
        $fimSql = $fim->copy()->endOfDay()->toDateTimeString();

        $base = DB::table('mcp_audit_log')->whereBetween('ts', [$iniSql, $fimSql]);

        // ----- KPIs gerais -----
        $totais = (clone $base)
            ->selectRaw('
                COUNT(*) as total_calls,
                COUNT(DISTINCT user_id) as usuarios_ativos,
                COALESCE(SUM(custo_brl), 0) as custo_total,
                COALESCE(SUM(tokens_in + tokens_out + cache_read + cache_write), 0) as tokens_total,
                COALESCE(AVG(duration_ms), 0) as latency_avg
            ')
            ->first();

        $kpis = [
            'total_calls'     => (int) ($totais->total_calls ?? 0),
            'usuarios_ativos' => (int) ($totais->usuarios_ativos ?? 0),
            'custo_total'     => (float) ($totais->custo_total ?? 0),
            'tokens_total'    => (int) ($totais->tokens_total ?? 0),
            'latency_avg_ms'  => (int) round($totais->latency_avg ?? 0),
        ];

        // ----- Breakdown por status -----
        $porStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as calls')
            ->groupBy('status')
            ->orderByDesc('calls')
            ->get();

        $totalCalls = max(1, $kpis['total_calls']);
        $statusFmt = $porStatus->map(fn ($r) => [
            'status' => (string) $r->status,
            'calls'  => (int) $r->calls,
            'pct'    => round(($r->calls / $totalCalls) * 100, 1),
        ])->values()->toArray();

        // ----- Latency percentiles (p50/p95/p99) -----
        // MySQL não tem percentile_cont nativo até 8.4 (PERCENT_RANK em window).
        // Usar approach simples: ORDER BY + OFFSET. Funciona pra <100k linhas/mês.
        $latency = $this->calcularPercentis($iniSql, $fimSql);

        // ----- Top tools/resources (10) -----
        $topTools = (clone $base)
            ->whereNotNull('tool_or_resource')
            ->selectRaw('tool_or_resource as tool, COUNT(*) as calls, COALESCE(SUM(custo_brl), 0) as custo_brl')
            ->groupBy('tool_or_resource')
            ->orderByDesc('calls')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'tool'      => (string) $r->tool,
                'calls'     => (int) $r->calls,
                'custo_brl' => (float) $r->custo_brl,
            ])
            ->toArray();

        // ----- Top users (10) -----
        $topUsers = (clone $base)
            ->leftJoin('users as u', 'u.id', '=', 'mcp_audit_log.user_id')
            ->selectRaw("
                mcp_audit_log.user_id,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username, CONCAT('#', mcp_audit_log.user_id)) as nome,
                COUNT(*) as calls,
                COALESCE(SUM(mcp_audit_log.custo_brl), 0) as custo_brl
            ")
            ->groupBy('mcp_audit_log.user_id', 'u.first_name', 'u.last_name', 'u.username')
            ->orderByDesc('calls')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'user_id'   => (int) $r->user_id,
                'nome'      => (string) $r->nome,
                'calls'     => (int) $r->calls,
                'custo_brl' => (float) $r->custo_brl,
            ])
            ->toArray();

        // ----- Denied breakdown por error_code -----
        $deniedPorCodigo = (clone $base)
            ->where('status', 'denied')
            ->whereNotNull('error_code')
            ->selectRaw('error_code, COUNT(*) as calls')
            ->groupBy('error_code')
            ->orderByDesc('calls')
            ->get()
            ->map(fn ($r) => [
                'error_code' => (string) $r->error_code,
                'calls'      => (int) $r->calls,
            ])
            ->toArray();

        // ----- Série diária -----
        $serieDiaria = (clone $base)
            ->selectRaw("
                DATE(ts) as dia,
                COUNT(*) as calls,
                COALESCE(SUM(custo_brl), 0) as custo_brl,
                SUM(CASE WHEN status = 'denied' THEN 1 ELSE 0 END) as denied
            ")
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        $serieFmt = $this->preencherDiasFaltantes($serieDiaria, $inicio, $fim);

        return [
            'kpis'              => $kpis,
            'por_status'        => $statusFmt,
            'latency'           => $latency,
            'top_tools'         => $topTools,
            'top_users'         => $topUsers,
            'denied_por_codigo' => $deniedPorCodigo,
            'serie_diaria'      => $serieFmt,
            'periodo'           => [
                'inicio' => $inicio->toDateString(),
                'fim'    => $fim->toDateString(),
                'label'  => $this->formatarLabelPeriodo($inicio, $fim),
            ],
        ];
    }

    /**
     * Calcula p50/p95/p99 da latência via OFFSET — funciona em MySQL <8.4.
     * Para volumes acima de ~100k linhas/mês, vale migrar pra PERCENTILE_CONT
     * ou janela materializada (Cycle 02+).
     */
    protected function calcularPercentis(string $iniSql, string $fimSql): array
    {
        $total = (int) DB::table('mcp_audit_log')
            ->whereBetween('ts', [$iniSql, $fimSql])
            ->whereNotNull('duration_ms')
            ->count();

        if ($total === 0) {
            return ['p50' => 0, 'p95' => 0, 'p99' => 0, 'max' => 0];
        }

        $offsetP50 = (int) floor($total * 0.50);
        $offsetP95 = (int) floor($total * 0.95);
        $offsetP99 = (int) floor($total * 0.99);

        $pickAt = function (int $offset) use ($iniSql, $fimSql): int {
            $row = DB::table('mcp_audit_log')
                ->whereBetween('ts', [$iniSql, $fimSql])
                ->whereNotNull('duration_ms')
                ->orderBy('duration_ms')
                ->offset(max(0, $offset))
                ->limit(1)
                ->value('duration_ms');
            return (int) ($row ?? 0);
        };

        $max = (int) DB::table('mcp_audit_log')
            ->whereBetween('ts', [$iniSql, $fimSql])
            ->max('duration_ms') ?? 0;

        return [
            'p50' => $pickAt($offsetP50),
            'p95' => $pickAt($offsetP95),
            'p99' => $pickAt($offsetP99),
            'max' => $max,
        ];
    }

    /**
     * Preenche dias faltantes com calls=0 pra não quebrar o gráfico.
     */
    protected function preencherDiasFaltantes(
        \Illuminate\Support\Collection $rows,
        CarbonInterface $inicio,
        CarbonInterface $fim
    ): array {
        $byDia = [];
        foreach ($rows as $r) {
            $byDia[(string) $r->dia] = $r;
        }

        $resultado = [];
        $cursor = $inicio->copy()->startOfDay();
        $end = $fim->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $r = $byDia[$key] ?? null;
            $resultado[] = [
                'data'      => $key,
                'calls'     => (int) ($r->calls ?? 0),
                'custo_brl' => (float) ($r->custo_brl ?? 0),
                'denied'    => (int) ($r->denied ?? 0),
            ];
            $cursor->addDay();
        }

        return $resultado;
    }

    protected function formatarLabelPeriodo(CarbonInterface $inicio, CarbonInterface $fim): string
    {
        $diasDif = $inicio->diffInDays($fim);

        if ($diasDif === 0) {
            return $inicio->format('d/m/Y');
        }

        return sprintf('%s — %s', $inicio->format('d/m'), $fim->format('d/m/Y'));
    }
}
