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
            'note' => 'nullable|string|max:120',
        ]);
        $user = User::findOrFail($userId);

        // Usa helper canônico que computa sha256_token corretamente.
        // Schema tem `name` (não `note`) — 'note' é alias UI-side.
        $name = $request->input('note') ?: 'Gerado por admin em ' . now()->toDateString();
        [$token, $raw] = McpToken::gerar($user->id, $name);

        return response()->json([
            'ok' => true,
            'token_id' => $token->id,
            'token_raw' => $raw,
            'aviso' => 'COPIE AGORA — não será mostrado de novo. Hash gravado, raw descartado.',
        ]);
    }

    /**
     * Gera token MCP novo + empacota arquivo .dxt (Desktop Extension)
     * pronto pro dev arrastar no Claude Desktop. Token embutido no manifest.
     *
     * Spec: https://github.com/anthropics/dxt
     */
    public function gerarDxt(Request $request, int $userId)
    {
        $user = User::findOrFail($userId);

        $nomeCurto = trim($user->first_name ?? $user->username ?? 'dev') ?: 'dev';
        $tokenName = 'DXT — ' . $nomeCurto . ' (gerado ' . now()->format('d/m/Y H:i') . ')';
        [$token, $raw] = McpToken::gerar($user->id, $tokenName);

        $slug = Str::slug($nomeCurto . '-' . $user->id);

        // DXT spec atual NÃO suporta server.type=http — apenas {python, node, binary}.
        // Pra MCP HTTP remoto, empacotamos como type=node com mcp-remote como bridge stdio↔HTTP.
        // Requisito no host: Node.js + npx (Claude Desktop tipicamente já tem).
        $manifest = [
            'dxt_version'  => '0.1',
            'name'         => 'oimpresso-mcp-' . $slug,
            'display_name' => 'Oimpresso MCP — ' . $nomeCurto,
            'version'      => '1.0.0',
            'description'  => 'Acesso MCP ao Oimpresso ERP — memória, ADRs, sessões, decisões. Token pessoal de ' . $nomeCurto . ' embutido. Bridge via mcp-remote.',
            'author' => [
                'name'  => 'Oimpresso ERP',
                'email' => 'wagner@oimpresso.com',
                'url'   => 'https://oimpresso.com',
            ],
            'server' => [
                'type'        => 'node',
                'entry_point' => 'server/index.js',
                'mcp_config'  => [
                    'command' => 'node',
                    'args'    => ['${__dirname}/server/index.js'],
                    'env'     => [
                        'MCP_URL'           => 'https://mcp.oimpresso.com/api/mcp',
                        'MCP_AUTHORIZATION' => 'Bearer ' . $raw,
                    ],
                ],
            ],
        ];

        // Wrapper Node — spawna `npx mcp-remote` com shell:true (resolve npx.cmd no Windows).
        // Lê URL/token de env vars definidas no manifest.json.
        // stdio:inherit garante que STDIO MCP passa transparente entre Claude e mcp-remote.
        $serverStub = <<<'JS'
#!/usr/bin/env node
// Oimpresso MCP DXT — bridge stdio↔HTTP via mcp-remote.
// Spawna `npx mcp-remote` com shell:true pra funcionar em Windows (.cmd) e POSIX.
const { spawn } = require('child_process');

const url   = process.env.MCP_URL;
const auth  = process.env.MCP_AUTHORIZATION;

if (!url || !auth) {
  console.error('[oimpresso-mcp] MCP_URL ou MCP_AUTHORIZATION ausente no env do manifest');
  process.exit(1);
}

const args = ['-y', 'mcp-remote@latest', url, '--header', `Authorization: ${auth}`];

const child = spawn('npx', args, {
  stdio: 'inherit',
  shell: true,           // resolve npx.cmd em Windows
  env: process.env,
});

child.on('error', (err) => {
  console.error('[oimpresso-mcp] Erro ao spawnar npx mcp-remote:', err.message);
  console.error('[oimpresso-mcp] Verifique se Node.js + npx estão instalados e no PATH.');
  process.exit(1);
});

child.on('exit', (code, signal) => {
  if (signal) console.error(`[oimpresso-mcp] mcp-remote encerrado por sinal ${signal}`);
  process.exit(code ?? 0);
});

// Encaminha sinais (Ctrl+C, etc) pro processo filho
['SIGINT', 'SIGTERM', 'SIGHUP'].forEach((sig) => {
  process.on(sig, () => child.kill(sig));
});
JS;

        // Empacota ZIP (.dxt) em arquivo temporário
        $tmpFile = tempnam(sys_get_temp_dir(), 'dxt_');
        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString(
            'manifest.json',
            json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        $zip->addFromString('server/index.js', $serverStub);
        $zip->close();

        $contents = file_get_contents($tmpFile);
        @unlink($tmpFile);

        $filename = 'oimpresso-mcp-' . $slug . '.dxt';

        return response($contents, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($contents),
            'X-Token-Id'          => (string) $token->id,
            'Cache-Control'       => 'no-store',
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
