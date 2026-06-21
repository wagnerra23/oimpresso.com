<?php

namespace Modules\TeamMcp\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;

/**
 * MEM-MCP-1.b (ADR 0053) — Endpoint de health do MCP server.
 *
 * 3 variantes:
 *   GET /api/mcp/health           — público, retorna status básico
 *   GET /api/mcp/health/auth      — exige Bearer mcp_*, retorna user info + commit
 *   GET /api/mcp/version          — Bearer MCP_DRIFT_TOKEN (dedicado, sem user/RBAC),
 *                                   retorna só o commit servido (drift sentinel)
 *
 * Usado por:
 *   - Smoke teste pós-deploy
 *   - Traefik healthcheck (configurado no docker-compose)
 *   - Wagner verificando que o token dele funciona
 */
class HealthController extends Controller
{
    public function publico(Request $request): JsonResponse
    {
        return response()->json([
            'status'   => 'ok',
            'service'  => 'oimpresso-mcp',
            'version'  => '0.1',
            'spec_mcp' => '2025-06-18',
            'ts'       => now()->toIso8601String(),
        ]);
    }

    public function autenticado(Request $request): JsonResponse
    {
        $token = $request->attributes->get('mcp_token');
        $user = $request->attributes->get('mcp_user');

        try {
            $totalDocs = McpMemoryDocument::count();
            $docsAcessiveis = McpMemoryDocument::acessiveisPara($user)->count();
        } catch (\Throwable $e) {
            $totalDocs = null;
            $docsAcessiveis = null;
        }

        return response()->json([
            'status' => 'authenticated',
            'user'   => [
                'id'    => $user->id,
                'name'  => $user->first_name ?? $user->username ?? "user-{$user->id}",
            ],
            'token' => [
                'id'           => $token->id,
                'name'         => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at'   => $token->expires_at?->toIso8601String(),
            ],
            'memory_documents' => [
                'total'                  => $totalDocs,
                'accessible_to_this_user' => $docsAcessiveis,
            ],
            ...$this->deployedCommit(),
            'ts' => now()->toIso8601String(),
        ]);
    }

    /**
     * Drift observability (ADR 0256) — endpoint DEDICADO pra sentinela externa.
     *
     * Auth por token PRÓPRIO (config copiloto.mcp.drift_token / env MCP_DRIFT_TOKEN),
     * NÃO por token mcp_* de usuário: sem user, sem RBAC jana.mcp.use, sem query no DB.
     * Blast radius se o token vazar = revela só o SHA do commit servido. É por isso que
     * a sentinela usa ESTE endpoint, não o /health/auth (que carrega permissão de tool).
     */
    public function versao(Request $request): JsonResponse
    {
        $expected = (string) config('copiloto.mcp.drift_token', '');
        if ($expected === '') {
            return response()->json(['error' => 'Misconfigured'], 500);
        }
        $provided = (string) $request->bearerToken();
        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'service' => 'oimpresso-mcp',
            ...$this->deployedCommit(),
            'ts' => now()->toIso8601String(),
        ]);
    }

    /**
     * Lê o commit servido (escrito pelo entrypoint-octane a cada boot em
     * storage/app/deployed_commit.txt). Retorna chaves prontas pro response JSON.
     * "unknown" (git indisponível no boot) vira null.
     */
    private function deployedCommit(): array
    {
        $commit = null;
        $deployedAt = null;
        $commitPath = storage_path('app/deployed_commit.txt');
        if (is_readable($commitPath)) {
            $commit = trim((string) @file_get_contents($commitPath)) ?: null;
            if ($commit === 'unknown') {
                $commit = null;
            }
            $deployedAt = ($mtime = @filemtime($commitPath)) ? date('c', $mtime) : null;
        }

        return [
            'commit'       => $commit,
            'commit_short' => $commit ? substr($commit, 0, 9) : null,
            'deployed_at'  => $deployedAt,
        ];
    }
}
