<?php

namespace Modules\Copiloto\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;

/**
 * MEM-MCP-1.b (ADR 0053) — Endpoint de health do MCP server.
 *
 * 2 variantes:
 *   GET /api/mcp/health           — público, retorna status básico
 *   GET /api/mcp/health/auth      — exige Bearer mcp_*, retorna user info
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
            'ts' => now()->toIso8601String(),
        ]);
    }
}
