<?php

namespace Modules\Jana\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpAuditLog;
use Modules\Jana\Entities\Mcp\McpToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * MEM-MCP-1.b (ADR 0053) — Auth middleware do MCP server.
 *
 * Valida header `Authorization: Bearer mcp_<hex>` contra mcp_tokens.
 * Em sucesso, atribui o user à request e registra no audit log.
 * Em falha, retorna 401 + audit linha de denied.
 *
 * Bind: applied via routes.php no group api/mcp/* exceto /health (público).
 */
class McpAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $authHeader = $request->header('Authorization', '');

        if (! str_starts_with($authHeader, 'Bearer mcp_')) {
            return $this->denied($request, $startedAt, 'missing_bearer', 'Header Authorization ausente ou inválido');
        }

        $raw = trim(substr($authHeader, 7)); // remove "Bearer "
        $token = McpToken::encontrarPorRaw($raw);

        if ($token === null) {
            return $this->denied($request, $startedAt, 'invalid_token', 'Token inválido, expirado ou revogado');
        }

        // Carrega user
        try {
            $userClass = config('auth.providers.users.model', \App\User::class);
            $user = $userClass::find($token->user_id);
        } catch (\Throwable $e) {
            $user = null;
        }

        if ($user === null) {
            return $this->denied($request, $startedAt, 'user_not_found', "User #{$token->user_id} não encontrado");
        }

        // RBAC gate — MEM-MCP-1.d (ADR 0053): user precisa da permission
        // `jana.mcp.use` mesmo com token válido. Sem isso → 403 + audit.
        // Este é o gate GROSSO (acesso ao server). A granularidade fina por
        // scope (jana.mcp.tasks.write, jana.mcp.cycles.manage, jana.mcp.memory.manage,
        // etc.) é enforced nas tools que MUTAM estado via o trait
        // AuthorizesMcpMutation (SDD Leva 2 · A4) — chamado como primeiro
        // statement do handle() de cada tool mutadora. Tools de leitura só
        // exigem este gate básico (e filtram resultado por scope quando aplicável,
        // ex: CcSearchTool com jana.cc.read.all).
        if (method_exists($user, 'can') && ! $user->can('jana.mcp.use')) {
            return $this->denied(
                $request,
                $startedAt,
                'no_permission',
                "User não tem permission `jana.mcp.use`. Atribua via Spatie role/permission."
            );
        }

        // MEM-TEAM-1 Fase 4 (ADR 0055) — quota enforcement.
        // Verifica spend cap antes de processar; bloqueia 429 se excedeu
        // E `block_on_exceed=true`. Alertas 50/80/100% disparados idempotentemente.
        try {
            $quotaCheck = app(\Modules\Jana\Services\Mcp\QuotaEnforcer::class)->checar((int) $user->id);
            if (! $quotaCheck['ok']) {
                return $this->quotaExceeded($request, $startedAt, $quotaCheck);
            }
        } catch (\Throwable $e) {
            // Degradação silenciosa — quota check falhar não pode bloquear chat
            \Illuminate\Support\Facades\Log::channel('copiloto-ai')->warning(
                'QuotaEnforcer: falha (degradação): ' . $e->getMessage()
            );
        }

        // Registra uso do token (last_used_at, last_used_ip)
        $token->registrarUso(
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        // Disponibiliza pra controllers
        $request->attributes->set('mcp_token', $token);
        $request->attributes->set('mcp_user', $user);
        $request->setUserResolver(fn () => $user);

        // PR-7c FIX (ADR 0283/0081): laravel/mcp `Request::user()` resolve via o auth
        // MANAGER (`auth()->userResolver()` → guard()->user()), NÃO via o resolver do
        // request HTTP acima. Sem isto, `$request->user()` dentro das Tools é null e
        // TODA mutação scopeada (handoff-ack/submit/lever, tasks-claim, lgpd-esquecer)
        // cai em "autenticação ausente" no HTTP real — bug mascarado pelos testes, que
        // stubam justamente o manager (`app('auth')->resolveUsersUsing`). setUser é
        // guard-scoped (Octane faz flush entre requests) — sem vazamento cross-request.
        app('auth')->setUser($user);

        // Continua a request
        $response = $next($request);

        // Estimativa de custo (MEM-TEAM-1 Fase 4):
        //   - tokens_in:  Content-Length do request body / 4 (chars/token)
        //   - tokens_out: Content-Length do response body / 4
        //   - custo_brl:  (in × input/1k + out × output/1k) × cambio
        // Heurística é aproximada — superestima ~30% do custo real, mas
        // suficiente pra enforcement de quota kind=brl (kind=calls/tokens não
        // dependem deste valor). Calls que tocam LLM (decisions-search com
        // FULLTEXT) ficam mais caras (override em tool).
        $reqBytes = (int) ($request->server('CONTENT_LENGTH') ?: strlen($request->getContent() ?? ''));
        $respContent = $response->getContent() ?? '';
        $respBytes = strlen($respContent);
        $tokensIn = (int) ceil($reqBytes / 4);
        $tokensOut = (int) ceil($respBytes / 4);
        $custoBrl = self::estimarCustoBrl($tokensIn, $tokensOut);

        // Audit log de sucesso (best-effort, não quebra request se falhar)
        try {
            McpAuditLog::registrar([
                'user_id'             => $user->id,
                'business_id'         => method_exists($user, 'business_id') ? $user->business_id : (data_get($user, 'business_id')),
                'endpoint'            => $this->detectarEndpoint($request),
                'tool_or_resource'    => $this->extrairToolOrResource($request),
                'status'              => $response->isSuccessful() ? 'ok' : 'error',
                'tokens_in'           => $tokensIn,
                'tokens_out'          => $tokensOut,
                'custo_brl'           => $custoBrl,
                'ip'                  => $request->ip(),
                'user_agent'          => $request->userAgent(),
                'mcp_token_id'        => $token->id,
                'duration_ms'         => (int) round((microtime(true) - $startedAt) * 1000),
                'claude_code_session' => $request->header('X-Claude-Code-Session'),
            ]);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('McpAuth audit log falhou: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Estima custo_brl de uma chamada MCP a partir de tokens in/out.
     *
     * BUGFIX (SDD D-COST-FIX · ADR 0278): a versão anterior lia
     * `config('copiloto.openai.pricing.*')` — chave INEXISTENTE — e por isso
     * caía sempre no fallback hardcoded por-token, ignorando a config real.
     * Pior: o pricing canônico (`copiloto.ai.pricing`) é cotado em USD POR 1k
     * TOKENS, então multiplicar `tokens × input` direto inflava o custo em
     * ~1000× (fator-de-mil). Aqui apontamos pra config certa E dividimos por
     * 1000 pra casar a unidade. Resultado: custo_brl numericamente correto
     * (≈ infra de uma query de DB, não de uma LLM-call).
     *
     * @param int $tokensIn  tokens estimados de entrada
     * @param int $tokensOut tokens estimados de saída
     * @return float custo em BRL (6 casas)
     */
    public static function estimarCustoBrl(int $tokensIn, int $tokensOut): float
    {
        $modelo = config('copiloto.ai.pricing_default_model', 'gpt-4o-mini');
        // Pricing canônico vive em copiloto.ai.pricing (USD por 1k tokens).
        $pricing = config(
            "copiloto.ai.pricing.{$modelo}",
            config('copiloto.ai.pricing.gpt-4o-mini', ['input' => 0.00015, 'output' => 0.0006])
        );

        $inputPer1k  = (float) ($pricing['input'] ?? 0);
        $outputPer1k = (float) ($pricing['output'] ?? 0);
        $cambio      = (float) config('copiloto.ai.cambio_brl_usd', 5.5);

        // Unidade: pricing é POR 1k tokens → divide a contagem de tokens por 1000.
        $custoUsd = (($tokensIn / 1000) * $inputPer1k)
            + (($tokensOut / 1000) * $outputPer1k);

        return round($custoUsd * $cambio, 6);
    }

    /**
     * Retorna 429 + grava audit log de quota exceeded.
     */
    protected function quotaExceeded(Request $request, float $startedAt, array $check): Response
    {
        $user = $request->user();
        $message = 'Quota excedida — chamadas bloqueadas. Detalhes:';
        foreach ($check['quotas'] ?? [] as $key => $r) {
            if ($r['excedido'] && $r['block_on_exceed']) {
                $unidade = match ($r['kind'] ?? 'brl') {
                    'calls'  => 'calls',
                    'tokens' => 'tokens',
                    default  => 'R$',
                };
                $message .= sprintf(' [%s_%s: %s %.4f de %s %.4f (%s%%)]',
                    $r['kind'] ?? '?', $r['period'] ?? '?',
                    $unidade, $r['uso_atual'],
                    $unidade, $r['limit'],
                    $r['pct_atingido']);
            }
        }

        try {
            McpAuditLog::registrar([
                'user_id' => $user?->id ?? 0,
                'business_id' => method_exists($user, 'business_id')
                    ? $user->business_id
                    : (data_get($user, 'business_id')),
                'endpoint' => $this->detectarEndpoint($request),
                'tool_or_resource' => $this->extrairToolOrResource($request),
                'status' => 'quota_exceeded',
                'error_code' => 'quota_exceeded',
                'error_message' => $message,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (\Throwable $e) {
            // best-effort
        }

        return response()->json([
            'error' => 'Quota Exceeded',
            'message' => $message,
            'quotas' => $check['quotas'] ?? [],
        ], 429);
    }

    /**
     * Retorna 401 + grava audit log de denied.
     */
    protected function denied(Request $request, float $startedAt, string $errorCode, string $errorMessage): Response
    {
        try {
            McpAuditLog::registrar([
                'user_id'        => 0, // placeholder pra denied sem user
                'endpoint'       => $this->detectarEndpoint($request),
                'status'         => 'denied',
                'error_code'     => $errorCode,
                'error_message'  => $errorMessage,
                'ip'             => $request->ip(),
                'user_agent'     => $request->userAgent(),
                'duration_ms'    => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (\Throwable $e) {
            // best-effort audit
        }

        return response()->json([
            'error'   => 'Unauthorized',
            'message' => $errorMessage,
        ], 401);
    }

    /**
     * Extrai o nome da tool/resource do payload MCP JSON-RPC.
     *
     * MCP JSON-RPC body: {jsonrpc, id, method, params: {name|uri, arguments}}
     * Para tools/call → params.name (ex: "tasks-current")
     * Para resources/read → params.uri (ex: "oimpresso://memory/handoff")
     * Para tools/list, resources/list, prompts/list → null (sem target específico)
     */
    protected function extrairToolOrResource(Request $request): ?string
    {
        // Tenta JSON-RPC params primeiro (formato canônico MCP)
        $name = $request->input('params.name');
        if (! empty($name)) {
            return (string) $name;
        }

        $uri = $request->input('params.uri');
        if (! empty($uri)) {
            return (string) $uri;
        }

        // Fallback: forma antiga (root-level), backwards-compat
        return $request->input('name') ?? $request->input('uri');
    }

    /**
     * Mapeia para endpoint MCP enum.
     *
     * Prioridade:
     *   1. JSON-RPC body field "method" (formato canônico MCP — todas as
     *      chamadas vêm via POST /api/mcp com method={tools/list|tools/call|...})
     *   2. URL path (fallback pra rotas antigas /api/mcp/tools/call, etc.)
     */
    protected function detectarEndpoint(Request $request): string
    {
        // 1. Body method (canônico MCP)
        $method = $request->input('method');
        $valid = ['tools/list', 'tools/call', 'resources/list', 'resources/read',
                  'prompts/list', 'prompts/get', 'initialize'];
        if (is_string($method) && in_array($method, $valid, true)) {
            return $method;
        }

        // 2. Fallback URL path
        $path = $request->path();
        return match (true) {
            str_contains($path, '/tools/list')      => 'tools/list',
            str_contains($path, '/tools/call')      => 'tools/call',
            str_contains($path, '/resources/list')  => 'resources/list',
            str_contains($path, '/resources/read')  => 'resources/read',
            str_contains($path, '/prompts/list')    => 'prompts/list',
            str_contains($path, '/prompts/get')     => 'prompts/get',
            default                                 => 'initialize',
        };
    }
}
