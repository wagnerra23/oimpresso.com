<?php

namespace Modules\TeamMcp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\User;
use App\Util\OtelHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Jana\Entities\Mcp\McpQuota;
use Modules\Jana\Entities\Mcp\McpToken;
use Modules\TeamMcp\Http\Requests\IssueActorTokenRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MEM-TEAM-1 (ADR 0055) — Self-host equivalent ao Anthropic Team plan admin console.
 *
 * Tela `/team-mcp/team` lista todos devs do business com:
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

        // Wave 11 D6.a — Inertia::defer pra props caras (team rows N×6 queries cada,
        // stats_globais 4 queries DB). Frontend Inertia carrega tela vazia primeiro
        // + skeleton, depois resolve closures em background (~50ms vs 300-800ms
        // hard load). Pricing config inline (1ms).
        return Inertia::render('team-mcp/Team/Index', [
            'team' => Inertia::defer(fn () => $this->buildTeamRowsPayload($businessId)),
            'stats_globais' => Inertia::defer(fn () => $this->buildStatsGlobaisPayload()),
            'pricing_config' => [
                'modelo_default' => config('copiloto.openai.model_chat', 'gpt-4o-mini'),
                'cambio_brl_usd' => (float) config('copiloto.ai.cambio_brl_usd', 5.5),
            ],
        ]);
    }

    /**
     * Builder: lista users do business + montarRow per user (Wave 11 D6.a defer).
     *
     * Cada `montarRow` executa ~6 queries em `mcp_audit_log` + `mcp_tokens` + `mcp_quotas`.
     * Pra business com 5 devs = ~30 queries — defer evita bloqueio do first paint.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildTeamRowsPayload(int $businessId): array
    {
        $users = User::where('business_id', $businessId)
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'username', 'email']);

        return $users->map(fn ($u) => $this->montarRow($u))->values()->toArray();
    }

    /**
     * Builder: stats globais audit log (Wave 11 D6.a defer).
     *
     * 4 queries agregadas em `mcp_audit_log` (sum custo dia/mês + distinct count + count).
     *
     * @return array<string, mixed>
     */
    protected function buildStatsGlobaisPayload(): array
    {
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

        return [
            'custo_hoje_brl' => $totalCustoHoje,
            'custo_mes_brl'  => $totalCustoMes,
            'usuarios_ativos_hoje' => $usuariosAtivosHoje,
            'calls_hoje' => $callsHoje,
        ];
    }

    /**
     * Gera token MCP novo pra um user.
     */
    public function gerarToken(IssueActorTokenRequest $request, int $userId)
    {
        // Permission gate `copiloto.mcp.usage.all` ja aplicada no construtor.
        // IssueActorTokenRequest valida 'note' (nullable|string|max:120) + trim.
        // Tier 0 segredo (ADR 0081): token raw devolvido APENAS no response, 1x,
        // jamais logado nem persistido em raw.
        $user = User::findOrFail($userId);

        // Wave 11 D9.a — OTel span pra token lifecycle (governança crítica MCP server).
        // Atributos NÃO incluem `raw` por design (Tier 0 segredo — ADR 0081).
        return OtelHelper::spanBiz('teammcp.token.issue', function () use ($request, $user) {
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
        }, ['module' => 'TeamMcp', 'target_user_id' => $user->id]);
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

        // Wrapper Node — spawna `npx mcp-remote` com shell:false em Windows (evita
        // bug do cmd parsing quando Node está em "C:\Program Files\nodejs\" com espaço).
        // Em Windows, command='npx.cmd' (Node spawn resolve .cmd via CreateProcess).
        // Em POSIX, command='npx' direto. shell:false em ambos.
        // Lê URL/token de env vars definidas no manifest.json.
        // Bridge nativo Node 18+ — fetch HTTP direto, sem mcp-remote/npx/cmd.exe.
        // Funciona em Windows/macOS/Linux out-of-box. Tamanho < 4KB.
        // Lê JSON-RPC linha-a-linha do stdin, POST com Bearer pro endpoint, SSE+JSON response.
        // Validado: handshake initialize + tools/list (7 tools) sem deps externas.
        $serverStub = <<<'JS'
#!/usr/bin/env node
// Oimpresso MCP DXT — bridge stdio↔HTTP nativo (Node 18+ fetch).
const fs = require('fs');
const path = require('path');
const os = require('os');

const LOG = path.join(os.tmpdir(), 'oimpresso-mcp-debug.log');
function log(msg) { try { fs.appendFileSync(LOG, `[${new Date().toISOString()}] ${msg}\n`); } catch {} }

const url  = process.env.MCP_URL;
const auth = process.env.MCP_AUTHORIZATION;

log('========== START ==========');
log(`platform=${process.platform} node=${process.version}`);
log(`url=${url || '<MISSING>'} auth=${auth ? '<SET>' : '<MISSING>'}`);

if (!url || !auth) { log('FATAL env'); process.exit(1); }
if (typeof fetch !== 'function') { log('FATAL: fetch ausente — Node < 18'); process.exit(1); }

let sessionId = null;
let buffer = '';

async function postOne(line) {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json, text/event-stream',
    'Authorization': auth,
  };
  if (sessionId) headers['Mcp-Session-Id'] = sessionId;

  let res;
  try {
    res = await fetch(url, { method: 'POST', headers, body: line });
  } catch (e) {
    log(`fetch error: ${e.message}`);
    try {
      const msg = JSON.parse(line);
      if (msg.id !== undefined) {
        process.stdout.write(JSON.stringify({
          jsonrpc: '2.0', id: msg.id,
          error: { code: -32603, message: 'Bridge fetch error: ' + e.message },
        }) + '\n');
      }
    } catch {}
    return;
  }

  const newSession = res.headers.get('mcp-session-id');
  if (newSession && newSession !== sessionId) {
    sessionId = newSession;
    log(`session=${sessionId}`);
  }

  if (res.status === 202 || res.status === 204) { log(`-> ${res.status} (no body)`); return; }

  const ct = (res.headers.get('content-type') || '').toLowerCase();

  if (ct.includes('text/event-stream') && res.body) {
    const reader = res.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let sseBuf = '';
    try {
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        sseBuf += decoder.decode(value, { stream: true });
        let evEnd;
        while ((evEnd = sseBuf.indexOf('\n\n')) >= 0) {
          const ev = sseBuf.slice(0, evEnd);
          sseBuf = sseBuf.slice(evEnd + 2);
          for (const evLine of ev.split('\n')) {
            if (evLine.startsWith('data:')) {
              const data = evLine.slice(5).trim();
              if (data) process.stdout.write(data + '\n');
            }
          }
        }
      }
    } catch (e) { log(`SSE read error: ${e.message}`); }
  } else {
    const text = await res.text();
    if (text) process.stdout.write(text.endsWith('\n') ? text : text + '\n');
  }
}

process.stdin.setEncoding('utf-8');
process.stdin.on('data', (chunk) => {
  buffer += chunk;
  let nl;
  while ((nl = buffer.indexOf('\n')) >= 0) {
    const line = buffer.slice(0, nl).replace(/\r$/, '').trim();
    buffer = buffer.slice(nl + 1);
    if (!line) continue;
    postOne(line).catch((e) => log(`postOne uncaught: ${e.message}`));
  }
});

process.stdin.on('end', () => { log('stdin ended'); process.exit(0); });
process.stdin.on('error', (e) => { log(`stdin error: ${e.message}`); process.exit(1); });

log('bridge listening');
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
     * Revoga token (soft-delete) — endpoint legacy (sem scope user).
     */
    public function revogarToken(int $tokenId)
    {
        // Wave 11 D9.a — OTel span pra revoke (audit crítico: invalidação imediata
        // de credencial MCP, equivale a operação Tier 0 governança).
        return OtelHelper::spanBiz('teammcp.token.revoke', function () use ($tokenId) {
            $token = McpToken::findOrFail($tokenId);
            $token->update(['expires_at' => now()]);
            $token->delete();

            return response()->json(['ok' => true]);
        }, ['module' => 'TeamMcp', 'token_id' => $tokenId]);
    }

    /**
     * G-DESIGN-01 — Lista tokens individuais de 1 user (drill-down do contador
     * "N ativos" da tabela team principal). FICHA CAPTERRA 2026-05-25 §6 + ADR
     * 0057 §6 (drill-down esperado por governança Tier 0).
     *
     * Multi-tenant Tier 0 (ADR 0093): scope explícito por business_id do user
     * autenticado — tokens de user com business_id != session business_id
     * resultam em 404 (defesa em profundidade). Permission gate
     * `copiloto.mcp.usage.all` já aplicada no construtor.
     *
     * Reveal-once invariante (ADR 0057 §2): NUNCA expõe `sha256_token` nem raw —
     * apenas metadados. Hidden no Model bloqueia serialização acidental, mas
     * explicitamos os campos retornados aqui pra contrato de API estável.
     */
    public function listTokens(Request $request, int $userId)
    {
        return OtelHelper::spanBiz('teammcp.tokens.list', function () use ($request, $userId) {
            $sessionBusinessId = (int) $request->session()->get('user.business_id');

            // Multi-tenant Tier 0 (ADR 0093): user só visível se mesmo business
            $user = User::where('id', $userId)
                ->where('business_id', $sessionBusinessId)
                ->firstOrFail();

            $tokens = McpToken::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->get([
                    'id', 'name', 'created_at', 'expires_at', 'revoked_at',
                    'last_used_at', 'last_used_ip', 'user_agent',
                ]);

            return response()->json([
                'ok' => true,
                'user' => [
                    'id' => $user->id,
                    'nome' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                        ?: ($user->username ?? "#{$user->id}"),
                ],
                'tokens' => $tokens->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'created_at' => $t->created_at?->toIso8601String(),
                    'expires_at' => $t->expires_at?->toIso8601String(),
                    'revoked_at' => $t->revoked_at?->toIso8601String(),
                    'last_used_at' => $t->last_used_at?->toIso8601String(),
                    'last_used_ip' => $t->last_used_ip,
                ])->values()->toArray(),
            ]);
        }, ['module' => 'TeamMcp', 'target_user_id' => $userId]);
    }

    /**
     * G-DESIGN-02 — Revoga UM token específico de UM user específico (drill-down
     * action). FICHA CAPTERRA 2026-05-25. Difere do revogarToken legacy: força
     * scope explícito por user + business_id (multi-tenant Tier 0 ADR 0093).
     *
     * Audit (ADR 0057 §10): McpToken usa LogsActivity (Spatie) — revoked_at +
     * revoked_by gravados via $token->revogar() pra preservar audit trail LGPD.
     */
    public function revokeToken(Request $request, int $userId, int $tokenId)
    {
        return OtelHelper::spanBiz('teammcp.token.revoke', function () use ($request, $userId, $tokenId) {
            $sessionBusinessId = (int) $request->session()->get('user.business_id');
            $actor = $request->user();

            // Multi-tenant Tier 0: confirma user pertence ao mesmo business
            $user = User::where('id', $userId)
                ->where('business_id', $sessionBusinessId)
                ->firstOrFail();

            // Token deve pertencer a esse user (defesa adicional contra tokenId
            // cross-tenant via URL manipulation).
            $token = McpToken::where('id', $tokenId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Já revogado? idempotente.
            if ($token->revoked_at === null) {
                $token->revogar($actor?->id ?? 0);
            }

            return response()->json(['ok' => true, 'token_id' => $token->id]);
        }, ['module' => 'TeamMcp', 'target_user_id' => $userId, 'token_id' => $tokenId]);
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
