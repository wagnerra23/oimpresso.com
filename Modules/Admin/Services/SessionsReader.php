<?php

namespace Modules\Admin\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SessionsReader — Widget W8 (Sessões Claude Code cross-dev).
 *
 * Lê tabela `mcp_cc_sessions` populada por skill `oimpresso-cc-watcher-setup`
 * em cada PC dev (Wagner/Maiara/Felipe/Luiz/Eliana[E]).
 *
 * Schema (migration 2026_04_29_300001_create_mcp_cc_sessions_table.php):
 *   id, session_uuid, user_id, project_path, started_at, ended_at,
 *   total_messages, total_tokens, total_cost_usd, total_cost_brl,
 *   status, summary_auto, metadata
 *
 * Sprint 2 W8: top 10 últimas + agregado por dev (7 dias).
 * Graceful fallback se tabela ausente (watcher não configurado).
 */
class SessionsReader
{
    public function fetch(): array
    {
        // D9.a OTel (Wave 17): span envolve top 10 + GROUP BY by_dev.
        // Zero-cost se otel.enabled=false.
        return OtelHelper::spanBiz('admin.sessions.fetch', function () {
        try {
            if (! Schema::hasTable('mcp_cc_sessions')) {
                return $this->stub('mcp_cc_sessions_missing');
            }

            // Top 10 últimas sessões globais
            $latest = DB::table('mcp_cc_sessions')
                ->select(
                    'id', 'session_uuid', 'user_id', 'project_path',
                    'started_at', 'total_tokens', 'total_cost_brl', 'status',
                )
                ->where('status', '!=', 'archived')
                ->orderByDesc('started_at')
                ->limit(10)
                ->get();

            // Agregado por dev (últimos 7 dias)
            $byDev = DB::table('mcp_cc_sessions as s')
                ->leftJoin('users as u', 's.user_id', '=', 'u.id')
                ->select(
                    DB::raw('COALESCE(u.username, CONCAT("user#", s.user_id)) as dev'),
                    DB::raw('COUNT(s.id) as sessions'),
                    DB::raw('SUM(s.total_tokens) as tokens'),
                    DB::raw('SUM(s.total_cost_brl) as cost_brl'),
                    DB::raw('MAX(s.started_at) as last_at'),
                )
                ->where('s.started_at', '>=', now()->subDays(7))
                ->groupBy('s.user_id', 'u.username')
                ->orderByDesc('cost_brl')
                ->get();

            return [
                'available' => true,
                'latest'    => $latest,
                'by_dev'    => $byDev,
            ];
        } catch (\Throwable $e) {
            Log::warning('admin.widget.sessions.error', ['error' => $e->getMessage()]);
            return $this->stub('exception:' . substr($e->getMessage(), 0, 120));
        }
        }, ['component' => 'admin.widget.w8']);
    }

    private function stub(string $reason): array
    {
        return [
            'available'    => false,
            'reason'       => $reason,
            'latest'       => [],
            'by_dev'       => [],
            'instructions' => 'Watcher não configurado. Rode skill `oimpresso-cc-watcher-setup` em cada PC dev (1×).',
        ];
    }
}
