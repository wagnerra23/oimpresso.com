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
                'tool_or_resource'    => $request->input('name') ?? $request->input('uri'),
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
     * Mapeia URL pra endpoint MCP enum.
     */
    protected function detectarEndpoint(Request $request): string
    {
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
