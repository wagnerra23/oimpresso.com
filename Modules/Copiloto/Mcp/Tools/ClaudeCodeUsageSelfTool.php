<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Copiloto\Entities\Mcp\McpAuditLog;

/**
 * MEM-MCP-1.c (ADR 0053) — Tool claude-code-usage-self.
 *
 * Retorna uso de IA do user atual (request_id, tokens, custo) nos
 * últimos 7 dias. Útil pro dev saber o quanto está consumindo em
 * tempo real e ajustar (Sonnet vs Opus, /clear, /compact).
 *
 * Sempre permitido (não exige scope) — info do próprio user.
 */
class ClaudeCodeUsageSelfTool extends Tool
{
    protected string $name = 'claude-code-usage-self';

    protected string $title = 'Meu uso Claude Code (7d)';

    protected string $description = 'Mostra resumo do consumo do dev autenticado nos últimos 7 dias: total de calls MCP, tokens acumulados, custo R$ estimado, top tools usadas. Sempre acessível ao user (não exige scope adicional).';

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Auth não identificada — McpAuthMiddleware falhou?');
        }

        $userId = $user->id;
        $cutoff = now()->subDays(7)->startOfDay();

        $stats = McpAuditLog::where('user_id', $userId)
            ->where('ts', '>=', $cutoff)
            ->selectRaw("
                COUNT(*)                              AS total_calls,
                COUNT(CASE WHEN status='ok' THEN 1 END)              AS calls_ok,
                COUNT(CASE WHEN status='denied' THEN 1 END)          AS calls_denied,
                COUNT(CASE WHEN status='quota_exceeded' THEN 1 END)  AS calls_quota,
                COALESCE(SUM(tokens_in), 0)           AS tokens_in,
                COALESCE(SUM(tokens_out), 0)          AS tokens_out,
                COALESCE(SUM(cache_read), 0)          AS cache_read,
                COALESCE(SUM(cache_write), 0)         AS cache_write,
                COALESCE(SUM(custo_brl), 0)           AS custo_brl,
                COALESCE(AVG(duration_ms), 0)         AS avg_duration_ms
            ")
            ->first();

        $topTools = McpAuditLog::where('user_id', $userId)
            ->where('ts', '>=', $cutoff)
            ->whereNotNull('tool_or_resource')
            ->selectRaw('tool_or_resource, COUNT(*) AS cnt')
            ->groupBy('tool_or_resource')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        $output = "## Seu uso Claude Code — últimos 7 dias\n\n";
        $output .= sprintf(
            "**Calls:** %d (ok: %d, denied: %d, quota: %d)\n",
            $stats->total_calls,
            $stats->calls_ok,
            $stats->calls_denied,
            $stats->calls_quota
        );
        $output .= sprintf(
            "**Tokens:** input %s · output %s · cache_read %s · cache_write %s\n",
            number_format($stats->tokens_in, 0, ',', '.'),
            number_format($stats->tokens_out, 0, ',', '.'),
            number_format($stats->cache_read, 0, ',', '.'),
            number_format($stats->cache_write, 0, ',', '.'),
        );
        $output .= sprintf(
            "**Custo BRL:** R$ %s\n",
            number_format($stats->custo_brl, 2, ',', '.')
        );
        $output .= sprintf(
            "**Latência média:** %d ms\n\n",
            (int) $stats->avg_duration_ms
        );

        if ($topTools->isNotEmpty()) {
            $output .= "**Top tools:**\n";
            foreach ($topTools as $t) {
                $output .= "- `{$t->tool_or_resource}`: {$t->cnt} calls\n";
            }
        }

        if ($stats->total_calls === 0) {
            $output .= "_Nenhuma chamada MCP registrada nos últimos 7 dias._";
        }

        return Response::text($output);
    }
}
