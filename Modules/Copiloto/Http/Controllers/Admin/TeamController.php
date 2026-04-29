<?php

namespace Modules\Copiloto\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Copiloto\Entities\Mcp\McpQuota;
use Modules\Copiloto\Entities\Mcp\McpToken;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MEM-TEAM-1 (ADR 0055) — Self-host equivalent ao Anthropic Team plan admin console.
 *
 * Tela `/copiloto/admin/team` lista todos devs do business com:
 *   - Tokens MCP ativos
 *   - Custo hoje + mês + % do limite
 *   - Quotas configuradas (daily/monthly em BRL)
 *   - Top tools usadas
 *   - Último uso MCP
 *
 * Actions:
 *   - Gerar token novo pra dev
 *   - Revogar token
 *   - Editar quota daily/monthly
 *   - Export CSV usage
 *
 * Permissão: `copiloto.mcp.usage.all` (Wagner/superadmin).
 */
class TeamController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $businessId = (int) $request->session()->get('user.business_id');

        // Lista users do business — todos (admin pode ver tudo)
        $users = User::where('business_id', $businessId)
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'username', 'email']);

        $rows = $users->map(fn ($u) => $this->montarRow($u));

        // Stats globais
        $hoje = Carbon::today();
        $totalCustoHoje = (float) DB::table('mcp_audit_log')
            ->whereDate('ts', $hoje)
            ->sum('custo_brl');
        $totalCustoMes = (float) DB::table('mcp_audit_log')
            ->whereBetween('ts', [$hoje->copy()->startOfMonth(), $hoje->copy()->endOfMonth()])
            ->sum('custo_brl');
        $usuariosAtivosHoje = (int) DB::table('mcp_audit_log')
            ->whereDate('ts', $hoje)
            ->distinct('user_id')->count('user_id');
        $callsHoje = (int) DB::table('mcp_audit_log')
            ->whereDate('ts', $hoje)
            ->count();

        return Inertia::render('Copiloto/Admin/Team/Index', [
            'team' => $rows->values(),
            'stats_globais' => [
                'custo_hoje_brl' => $totalCustoHoje,
                'custo_mes_brl'  => $totalCustoMes,
                'usuarios_ativos_hoje' => $usuariosAtivosHoje,
                'calls_hoje' => $callsHoje,
            ],
            'pricing_config' => [
                'modelo_default' => config('copiloto.openai.model_chat', 'gpt-4o-mini'),
                'cambio_brl_usd' => (float) config('copiloto.ai.cambio_brl_usd', 5.5),
            ],
        ]);
    }

    /**
     * Gera token MCP novo pra um user.
     */
    public function gerarToken(Request $request, int $userId)
    {
        $request->validate([
            'note' => 'nullable|string|max:255',
        ]);
        $user = User::findOrFail($userId);

        // Gera token raw + persist hash
        $raw = 'mcp_' . bin2hex(random_bytes(32));
        $token = McpToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $raw),
            'note' => $request->input('note', "Gerado por admin em " . now()->toDateString()),
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'token_id' => $token->id,
            'token_raw' => $raw,
            'aviso' => 'COPIE AGORA — não será mostrado de novo. Hash gravado, raw descartado.',
        ]);
    }

    /**
     * Revoga token (soft-delete).
     */
    public function revogarToken(int $tokenId)
    {
        $token = McpToken::findOrFail($tokenId);
        $token->update(['expires_at' => now()]);
        $token->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Atualiza quota de um user (daily/monthly em BRL).
     */
    public function atualizarQuota(Request $request, int $userId)
    {
        $request->validate([
            'period' => 'required|in:daily,monthly',
            'limit_brl' => 'required|numeric|min:0|max:9999.99',
            'block_on_exceed' => 'nullable|boolean',
        ]);

        $period = $request->input('period');
        $limit = (float) $request->input('limit_brl');
        $block = (bool) $request->input('block_on_exceed', true);
        $resetAt = match ($period) {
            'daily'   => now()->copy()->endOfDay(),
            'monthly' => now()->copy()->endOfMonth(),
            default   => now()->copy()->endOfMonth(),
        };

        McpQuota::updateOrCreate(
            [
                'user_id' => $userId,
                'period'  => $period,
                'kind'    => 'brl',
            ],
            [
                'limit' => $limit,
                'block_on_exceed' => $block,
                'ativo' => true,
                'reset_at' => $resetAt,
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Export CSV de audit log filtrado.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $de = $request->input('de', now()->subMonth()->toDateString());
        $ate = $request->input('ate', now()->toDateString());

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="oimpresso-team-usage-' . now()->format('Ymd') . '.csv"',
        ];

        return new StreamedResponse(function () use ($de, $ate) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['ts', 'user_id', 'user_email', 'endpoint', 'tool', 'status', 'tokens_total', 'custo_brl', 'duration_ms']);

            DB::table('mcp_audit_log as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->whereBetween('a.ts', [$de . ' 00:00:00', $ate . ' 23:59:59'])
                ->orderBy('a.ts')
                ->select(
                    'a.ts', 'a.user_id', 'u.email', 'a.endpoint',
                    'a.tool_or_resource', 'a.status', 'a.tokens_in', 'a.tokens_out',
                    'a.custo_brl', 'a.duration_ms',
                )
                ->cursor()
                ->each(function ($r) use ($h) {
                    fputcsv($h, [
                        $r->ts,
                        $r->user_id,
                        $r->email ?? '-',
                        $r->endpoint,
                        $r->tool_or_resource ?? '',
                        $r->status,
                        ((int) ($r->tokens_in ?? 0)) + ((int) ($r->tokens_out ?? 0)),
                        number_format((float) ($r->custo_brl ?? 0), 6, '.', ''),
                        $r->duration_ms ?? '',
                    ]);
                });

            fclose($h);
        }, 200, $headers);
    }

    /**
     * Monta 1 row da tabela team com tudo agregado.
     */
    protected function montarRow(User $u): array
    {
        $hoje = Carbon::today();
        $iniMes = $hoje->copy()->startOfMonth();
        $fimMes = $hoje->copy()->endOfMonth();

        // Tokens ativos (não expirados)
        $tokensAtivos = McpToken::where('user_id', $u->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        // Custo hoje + mês
        $custoHoje = (float) DB::table('mcp_audit_log')
            ->where('user_id', $u->id)
            ->whereDate('ts', $hoje)
            ->sum('custo_brl');
        $custoMes = (float) DB::table('mcp_audit_log')
            ->where('user_id', $u->id)
            ->whereBetween('ts', [$iniMes, $fimMes])
            ->sum('custo_brl');

        // Calls hoje + mês
        $callsHoje = (int) DB::table('mcp_audit_log')
            ->where('user_id', $u->id)
            ->whereDate('ts', $hoje)
            ->count();
        $callsMes = (int) DB::table('mcp_audit_log')
            ->where('user_id', $u->id)
            ->whereBetween('ts', [$iniMes, $fimMes])
            ->count();

        // Quotas
        $quotaDaily = McpQuota::where('user_id', $u->id)
            ->where('period', 'daily')->where('kind', 'brl')->where('ativo', true)
            ->first();
        $quotaMonthly = McpQuota::where('user_id', $u->id)
            ->where('period', 'monthly')->where('kind', 'brl')->where('ativo', true)
            ->first();

        // Último login MCP (último audit log do user)
        $ultimoMcp = DB::table('mcp_audit_log')
            ->where('user_id', $u->id)
            ->orderByDesc('ts')
            ->value('ts');

        // Top 3 tools deste user (mês corrente)
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
            'id' => $u->id,
            'nome' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->username ?? "#{$u->id}"),
            'email' => $u->email ?? '-',
            'tokens_ativos' => $tokensAtivos,
            'custo_hoje_brl' => $custoHoje,
            'custo_mes_brl' => $custoMes,
            'calls_hoje' => $callsHoje,
            'calls_mes' => $callsMes,
            'quota_diaria' => $quotaDaily ? [
                'id' => $quotaDaily->id,
                'limit' => (float) $quotaDaily->limit,
                'block' => (bool) $quotaDaily->block_on_exceed,
                'pct_atingido' => $quotaDaily->limit > 0
                    ? round(($custoHoje / (float) $quotaDaily->limit) * 100, 1)
                    : 0,
            ] : null,
            'quota_mensal' => $quotaMonthly ? [
                'id' => $quotaMonthly->id,
                'limit' => (float) $quotaMonthly->limit,
                'block' => (bool) $quotaMonthly->block_on_exceed,
                'pct_atingido' => $quotaMonthly->limit > 0
                    ? round(($custoMes / (float) $quotaMonthly->limit) * 100, 1)
                    : 0,
            ] : null,
            'top_tools' => $topTools->map(fn ($t) => ['tool' => $t->tool_or_resource, 'count' => (int) $t->c])->toArray(),
            'ultimo_uso_mcp' => $ultimoMcp,
        ];
    }
}
