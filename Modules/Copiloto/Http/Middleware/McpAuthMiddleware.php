<?php

namespace Modules\Copiloto\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Entities\Mcp\McpAuditLog;
use Modules\Copiloto\Entities\Mcp\McpToken;
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
        // `copiloto.mcp.use` mesmo com token válido. Sem isso → 403 + audit.
        // Granularidade fina (decisions.read, governanca.financeiro, etc.)
        // fica nos Tools individuais (cada Tool checa o scope via $user->can).
        if (method_exists($user, 'can') && ! $user->can('copiloto.mcp.use')) {
            return $this->denied(
                $request,
                $startedAt,
                'no_permission',
                "User não tem permission `copiloto.mcp.use`. Atribua via Spatie role/permission."
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

        // Continua a request
        $response = $next($request);

        // Audit log de sucesso (best-effort, não quebra request se falhar)
        try {
            McpAuditLog::registrar([
                'user_id'             => $user->id,
                'business_id'         => method_exists($user, 'business_id') ? $user->business_id : (data_get($user, 'business_id')),
                'endpoint'            => $this->detectarEndpoint($request),
                'tool_or_resource'    => $this->extrairToolOrResource($request),
                'status'              => $response->isSuccessful() ? 'ok' : 'error',
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
