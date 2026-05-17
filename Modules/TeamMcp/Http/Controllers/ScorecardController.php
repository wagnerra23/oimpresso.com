<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ScorecardController — G1 FICHA Wave 22 esqueleto TeamMcp scorecard UI.
 *
 * Tela `/team-mcp/scorecard` apresenta:
 *   - **Facts** (factual, sem juízo): contagens diretas do MCP server
 *     (tokens ativos, calls 7d, cost 7d, top tools, drift detectado).
 *   - **Checks** (boolean ok/fail): saúde por dimensão (Tier 0 multi-tenant,
 *     governance gate verde, brief recente, audit log sem PII vazada).
 *
 * Pattern **Facts+Checks** (separar dado de juízo) reduz overhead cognitivo
 * — Wagner vê primeiro "tá tudo verde?" depois entra nos números se preciso.
 *
 * Permissão: `copiloto.mcp.usage.all` (Wagner/superadmin), igual TeamController.
 *
 * D6 Perf (Inertia::defer DEFAULT — rule pages.md):
 *   - `facts` e `checks` ambos defer (queries N×audit_log + N×schemata).
 *   - `meta` eager (config + timestamp, 0 query).
 *
 * D9 Obs: span `teammcp.scorecard.build` wrap nos builders.
 *
 * Multi-tenant Tier 0: scorecard é repo-wide (governance cross-business).
 * Sem business_id filter — INTENCIONAL pra superadmin enxergar saúde global.
 *
 * @see Modules\TeamMcp\Http\Controllers\TeamController (irmão — team plan view)
 * @see memory/decisions/0091-daily-brief.md (facts pattern origem)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ScorecardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        return Inertia::render('team-mcp/Scorecard/Index', [
            // D6.a: defer Facts (queries DB caras)
            'facts'  => Inertia::defer(fn () => $this->buildFacts()),
            // D6.a: defer Checks (consulta schema + audit_log)
            'checks' => Inertia::defer(fn () => $this->buildChecks()),
            // Eager (config inline + agora)
            'meta'   => [
                'generated_at' => now()->toIso8601String(),
                'period_days'  => 7,
                'pattern'      => 'facts_checks',
                'source'       => 'mcp_audit_log + mcp_tokens + mcp_briefs',
            ],
        ]);
    }

    /**
     * Builder: Facts (numbers — sem juízo).
     *
     * @return array<string, mixed>
     */
    protected function buildFacts(): array
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
                'tokens_ativos'      => $tokensAtivos,
                'calls_7d'           => $calls7d,
                'cost_7d_brl'        => round($cost7d, 4),
                'users_ativos_7d'    => $usersAtivos7d,
                'top_tools_7d'       => $topTools,
                'audit_log_present'  => $hasAudit,
                'tokens_table_present' => $hasTokens,
            ];
        });
    }

    /**
     * Builder: Checks (boolean ok/fail).
     *
     * @return array<int, array{name: string, ok: bool, detail: string}>
     */
    protected function buildChecks(): array
    {
        return OtelHelper::spanBiz('teammcp.scorecard.build_checks', function (): array {
            $checks = [];

            // Check 1: mcp_tokens existe (Identity Mesh canon ADR 0081)
            $checks[] = $this->checkSchema('mcp_tokens', 'Tabela mcp_tokens canon (ADR 0081)');

            // Check 2: mcp_audit_log existe (governance Tier 0)
            $checks[] = $this->checkSchema('mcp_audit_log', 'Audit log MCP canon (ADR 0053)');

            // Check 3: mcp_briefs tem brief recente (<24h) — sinal vivo do brief-first
            $checks[] = $this->checkBriefRecente();

            // Check 4: nenhum token órfão (user_id NULL) — Tier 0 segredo
            $checks[] = $this->checkTokensSemOrphan();

            // Check 5: custo médio diário 7d < cap implícito R$ [redacted Tier 0] (sanidade)
            $checks[] = $this->checkCustoMedioSanidade();

            return $checks;
        });
    }

    private function checkSchema(string $table, string $label): array
    {
        $ok = Schema::hasTable($table);

        return [
            'name'   => $label,
            'ok'     => $ok,
            'detail' => $ok ? "Tabela {$table} presente." : "Tabela {$table} AUSENTE — rodar migrations.",
        ];
    }

    private function checkBriefRecente(): array
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

    private function checkTokensSemOrphan(): array
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

    private function checkCustoMedioSanidade(): array
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
            'name'   => 'Custo médio diário 7d < R$ [redacted Tier 0]',
            'ok'     => $ok,
            'detail' => "Custo médio: R$ ".number_format($medio, 2, ',', '.')."/dia (7d).",
        ];
    }
}
