<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ScorecardBuilderService — Wave 25 D4 SATURATION (2026-05-16).
 *
 * Extrai lógica de Facts+Checks antes embutida em
 * `Modules\TeamMcp\Http\Controllers\ScorecardController`. Controller fica
 * thin (auth + render Inertia + chamar Service).
 *
 * Pattern **Facts+Checks** ({@see ADR 0091 origem}):
 *   - Facts (numbers — sem juízo): tokens ativos, calls 7d, cost 7d, top tools
 *   - Checks (boolean ok/fail): saúde por dimensão (schema, brief, orphan, custo)
 *
 * **Multi-tenant Tier 0** ({@see ADR 0093}): scorecard é repo-wide intencional
 * (governance cross-business pra superadmin). Sem business_id filter.
 *
 * **OTel spans** ({@see ADR 0155}): cada builder envolvido em `OtelHelper::spanBiz`
 * separa latência facts vs checks (debug perf hot-path admin scorecard).
 *
 * Testes Pest unit-level: cada método público retorna estrutura canônica que
 * o frontend espera (Wave23ScorecardRotateTest cobre).
 *
 * @see Modules\TeamMcp\Http\Controllers\ScorecardController (caller)
 * @see memory/decisions/0091-daily-brief.md (facts pattern origem)
 * @see memory/decisions/0081-identity-mesh-actor-trust-mcp.md (token Tier 0)
 */
class ScorecardBuilderService
{
    /**
     * Builder Facts (numbers — sem juízo).
     *
     * @return array<string, mixed>
     */
    public function buildFacts(): array
    {
        return OtelHelper::spanBiz('teammcp.scorecard.build_facts', function (): array {
            $hoje = Carbon::today();
            $sete = $hoje->copy()->subDays(7);

            $hasAudit = Schema::hasTable('mcp_audit_log');
            $hasTokens = Schema::hasTable('mcp_tokens');

            $tokensAtivos = $hasTokens
                ? (int) DB::table('mcp_tokens')
                    ->whereNull('deleted_at')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })->count()
                : 0;

            $calls7d = $hasAudit
                ? (int) DB::table('mcp_audit_log')->whereBetween('ts', [$sete, $hoje->copy()->endOfDay()])->count()
                : 0;

            $cost7d = $hasAudit
                ? (float) DB::table('mcp_audit_log')->whereBetween('ts', [$sete, $hoje->copy()->endOfDay()])->sum('custo_brl')
                : 0.0;

            $usersAtivos7d = $hasAudit
                ? (int) DB::table('mcp_audit_log')->whereBetween('ts', [$sete, $hoje->copy()->endOfDay()])
                    ->distinct('user_id')->count('user_id')
                : 0;

            $topTools = $hasAudit
                ? DB::table('mcp_audit_log')
                    ->whereBetween('ts', [$sete, $hoje->copy()->endOfDay()])
                    ->whereNotNull('tool_or_resource')
                    ->selectRaw('tool_or_resource, COUNT(*) as c')
                    ->groupBy('tool_or_resource')
                    ->orderByDesc('c')
                    ->limit(5)
                    ->get()
                    ->map(fn ($r) => ['tool' => $r->tool_or_resource, 'count' => (int) $r->c])
                    ->toArray()
                : [];

            return [
                'tokens_ativos'        => $tokensAtivos,
                'calls_7d'             => $calls7d,
                'cost_7d_brl'          => round($cost7d, 4),
                'users_ativos_7d'      => $usersAtivos7d,
                'top_tools_7d'         => $topTools,
                'audit_log_present'    => $hasAudit,
                'tokens_table_present' => $hasTokens,
            ];
        });
    }

    /**
     * Builder Checks (boolean ok/fail).
     *
     * @return array<int, array{name: string, ok: bool, detail: string}>
     */
    public function buildChecks(): array
    {
        return OtelHelper::spanBiz('teammcp.scorecard.build_checks', function (): array {
            return [
                $this->checkSchema('mcp_tokens', 'Tabela mcp_tokens canon (ADR 0081)'),
                $this->checkSchema('mcp_audit_log', 'Audit log MCP canon (ADR 0053)'),
                $this->checkBriefRecente(),
                $this->checkTokensSemOrphan(),
                $this->checkCustoMedioSanidade(),
            ];
        });
    }

    /**
     * Check genérico — `Schema::hasTable($table)`.
     *
     * @return array{name: string, ok: bool, detail: string}
     */
    public function checkSchema(string $table, string $label): array
    {
        $ok = Schema::hasTable($table);

        return [
            'name'   => $label,
            'ok'     => $ok,
            'detail' => $ok ? "Tabela {$table} presente." : "Tabela {$table} AUSENTE — rodar migrations.",
        ];
    }

    /**
     * Check brief recente (<24h) — sinal vivo do brief-first.
     *
     * @return array{name: string, ok: bool, detail: string}
     */
    public function checkBriefRecente(): array
    {
        if (! Schema::hasTable('mcp_briefs')) {
            return [
                'name'   => 'Brief recente (<24h)',
                'ok'     => false,
                'detail' => 'Tabela mcp_briefs ausente.',
            ];
        }

        $ultimoTs = DB::table('mcp_briefs')->where('valid', 1)->max('generated_at');

        if ($ultimoTs === null) {
            return [
                'name'   => 'Brief recente (<24h)',
                'ok'     => false,
                'detail' => 'Nenhum brief válido encontrado.',
            ];
        }

        $diff = now()->diffInHours(Carbon::parse($ultimoTs));
        $ok = $diff < 24;

        return [
            'name'   => 'Brief recente (<24h)',
            'ok'     => $ok,
            'detail' => $ok
                ? "Último brief válido há {$diff}h."
                : "Brief desatualizado — última geração há {$diff}h.",
        ];
    }

    /**
     * Check tokens sem orphan (user_id NULL é Tier 0 violação).
     *
     * @return array{name: string, ok: bool, detail: string}
     */
    public function checkTokensSemOrphan(): array
    {
        if (! Schema::hasTable('mcp_tokens')) {
            return [
                'name'   => 'Tokens sem orphan',
                'ok'     => false,
                'detail' => 'Tabela mcp_tokens ausente.',
            ];
        }

        $orphan = (int) DB::table('mcp_tokens')->whereNull('user_id')->count();
        $ok = $orphan === 0;

        return [
            'name'   => 'Tokens sem orphan',
            'ok'     => $ok,
            'detail' => $ok
                ? 'Todos tokens vinculados a user.'
                : "{$orphan} token(s) órfão(s) — Tier 0 violação (ADR 0081).",
        ];
    }

    /**
     * Check custo médio diário 7d < cap R$10 (sanidade).
     *
     * @return array{name: string, ok: bool, detail: string}
     */
    public function checkCustoMedioSanidade(): array
    {
        if (! Schema::hasTable('mcp_audit_log')) {
            return [
                'name'   => 'Custo médio diário 7d sanity',
                'ok'     => true,
                'detail' => 'Audit log ausente — check skipped.',
            ];
        }

        $sete = now()->subDays(7);
        $total = (float) DB::table('mcp_audit_log')->where('ts', '>=', $sete)->sum('custo_brl');
        $medio = $total / 7.0;
        $ok = $medio < 10.0;

        return [
            'name'   => 'Custo médio diário 7d < R$10',
            'ok'     => $ok,
            'detail' => "Custo médio: R$ ".number_format($medio, 2, ',', '.')."/dia (7d).",
        ];
    }
}
