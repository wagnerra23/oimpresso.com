<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use App\User;
use App\Util\OtelHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\Mcp\McpQuota;
use Modules\Jana\Entities\Mcp\McpToken;

/**
 * TeamUsageAggregator — Wave 18 D4 SATURATION (2026-05-16).
 *
 * Extrai lógica de agregação de uso MCP por user/business antes embutida em
 * `TeamController::montarRow()` + `buildTeamRowsPayload()` + `buildStatsGlobaisPayload()`.
 *
 * Service thin: zero side-effect, apenas queries READ em `mcp_audit_log`,
 * `mcp_tokens`, `mcp_quotas`. Cobre 3 responsabilidades:
 *
 *   - `rowsForBusiness(int $bizId)` — lista usuários do business com stats (~6 queries/user)
 *   - `globalStats()` — agregados cross-business (4 queries)
 *   - `montarRow(User $u)` — row individual com tokens/quotas/custos/topTools
 *
 * **Multi-tenant Tier 0** ({@see ADR 0093}): `mcp_audit_log` tem coluna `user_id`
 * que linka pra `users.business_id` — caller injeta $businessId, Service filtra
 * via `User::where('business_id', $bizId)`.
 *
 * **OTel spans** ({@see ADR 0155}) — todas queries críticas envolvidas em
 * `OtelHelper::spanBiz` pra observabilidade hot-path admin console.
 *
 * @see Modules\TeamMcp\Http\Controllers\TeamController
 */
class TeamUsageAggregator
{
    /**
     * Lista usuários do business com stats agregadas pra renderizar a tabela `team-mcp/Team/Index`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsForBusiness(int $businessId): array
    {
        return OtelHelper::spanBiz('teammcp.usage.rows_for_business', function () use ($businessId) {
            $users = User::where('business_id', $businessId)
                ->orderBy('id')
                ->get(['id', 'first_name', 'last_name', 'username', 'email']);

            return $users->map(fn ($u) => $this->montarRow($u))->values()->toArray();
        }, ['module' => 'TeamMcp', 'business_id' => $businessId]);
    }

    /**
     * Stats agregadas globais (audit log) — usado no header da Index.
     *
     * @return array<string, mixed>
     */
    public function globalStats(): array
    {
        return OtelHelper::spanBiz('teammcp.usage.global_stats', function () {
            $hoje = Carbon::today();
            $totalCustoHoje = (float) DB::table('mcp_audit_log')
                ->whereDate('ts', $hoje)
                ->sum('custo_brl');
            $totalCustoMes = (float) DB::table('mcp_audit_log')
                ->whereBetween('ts', [$hoje->copy()->startOfMonth(), $hoje->copy()->endOfMonth()])
                ->sum('custo_brl');
            $usuariosAtivosHoje = (int) DB::table('mcp_audit_log')
                ->whereDate('ts', $hoje)
                ->distinct('user_id')
                ->count('user_id');
            $callsHoje = (int) DB::table('mcp_audit_log')
                ->whereDate('ts', $hoje)
                ->count();

            return [
                'custo_hoje_brl'       => $totalCustoHoje,
                'custo_mes_brl'        => $totalCustoMes,
                'usuarios_ativos_hoje' => $usuariosAtivosHoje,
                'calls_hoje'           => $callsHoje,
            ];
        }, ['module' => 'TeamMcp']);
    }

    /**
     * Monta 1 row da tabela team com tudo agregado pra renderizar `Team/Index.tsx`.
     *
     * @return array<string, mixed>
     */
    public function montarRow(User $u): array
    {
        $hoje = Carbon::today();
        $iniMes = $hoje->copy()->startOfMonth();
        $fimMes = $hoje->copy()->endOfMonth();

        $tokensAtivos = McpToken::where('user_id', $u->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        $custoHoje = (float) DB::table('mcp_audit_log')
            ->where('user_id', $u->id)
            ->whereDate('ts', $hoje)
            ->sum('custo_brl');
        $custoMes = (float) DB::table('mcp_audit_log')
            ->where('user_id', $u->id)
            ->whereBetween('ts', [$iniMes, $fimMes])
            ->sum('custo_brl');

        $callsHoje = (int) DB::table('mcp_audit_log')
            ->where('user_id', $u->id)
            ->whereDate('ts', $hoje)
            ->count();
        $callsMes = (int) DB::table('mcp_audit_log')
            ->where('user_id', $u->id)
            ->whereBetween('ts', [$iniMes, $fimMes])
            ->count();

        $quotaDaily = McpQuota::where('user_id', $u->id)
            ->where('period', 'daily')->where('kind', 'brl')->where('ativo', true)
            ->first();
        $quotaMonthly = McpQuota::where('user_id', $u->id)
            ->where('period', 'monthly')->where('kind', 'brl')->where('ativo', true)
            ->first();

        $ultimoMcp = DB::table('mcp_audit_log')
            ->where('user_id', $u->id)
            ->orderByDesc('ts')
            ->value('ts');

        $topTools = DB::table('mcp_audit_log')
            ->where('user_id', $u->id)
            ->whereBetween('ts', [$iniMes, $fimMes])
            ->whereNotNull('tool_or_resource')
            ->selectRaw('tool_or_resource, COUNT(*) as c')
            ->groupBy('tool_or_resource')
            ->orderByDesc('c')
            ->limit(3)
            ->get();

        return [
            'id'             => $u->id,
            'nome'           => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->username ?? "#{$u->id}"),
            'email'          => $u->email ?? '-',
            'tokens_ativos'  => $tokensAtivos,
            'custo_hoje_brl' => $custoHoje,
            'custo_mes_brl'  => $custoMes,
            'calls_hoje'     => $callsHoje,
            'calls_mes'      => $callsMes,
            'quota_diaria'   => $this->quotaPayload($quotaDaily, $custoHoje),
            'quota_mensal'   => $this->quotaPayload($quotaMonthly, $custoMes),
            'top_tools'      => $topTools->map(fn ($t) => ['tool' => $t->tool_or_resource, 'count' => (int) $t->c])->toArray(),
            'ultimo_uso_mcp' => $ultimoMcp,
        ];
    }

    /**
     * Helper interno: monta payload de quota com pct atingido (zero-safe).
     *
     * @return array<string, mixed>|null
     */
    private function quotaPayload(?McpQuota $quota, float $custoAtual): ?array
    {
        if ($quota === null) {
            return null;
        }

        return [
            'id'            => $quota->id,
            'limit'         => (float) $quota->limit,
            'block'         => (bool) $quota->block_on_exceed,
            'pct_atingido'  => $quota->limit > 0
                ? round(($custoAtual / (float) $quota->limit) * 100, 1)
                : 0,
        ];
    }
}
