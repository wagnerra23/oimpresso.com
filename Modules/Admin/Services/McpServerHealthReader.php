<?php

namespace Modules\Admin\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * McpServerHealthReader — Widget W6 (MCP server health).
 *
 * Lê:
 * - `mcp_memory_documents`: count + último git_sha sincronizado
 * - `mcp_tokens` (se existir): count tokens emitidos + último uso
 * - HTTP ping em `mcp.oimpresso.com` (CT 100) com timeout 2s
 *
 * Cache 60s (snapshot evita rate-limit em dashboard refresh).
 * Graceful fallback: tabelas ausentes / endpoint timeout → stub
 * com instruções.
 *
 * @see memory/decisions/0053-mcp-server-governanca-como-produto.md
 */
class McpServerHealthReader
{
    private const PING_URL = 'https://mcp.oimpresso.com/health';

    public function fetch(): array
    {
        // D9.a OTel (Wave 17): span envolve Cache::remember + ping HTTP CT 100.
        // Zero-cost se otel.enabled=false.
        return OtelHelper::spanBiz('admin.mcp_health.fetch', function () {
            return Cache::remember('admin.widget.mcp', 60, function () {
            try {
                if (! Schema::hasTable('mcp_memory_documents')) {
                    return $this->stub('mcp_memory_documents_missing');
                }

                $docs = DB::table('mcp_memory_documents')
                    ->select(
                        DB::raw('COUNT(*) as total'),
                        DB::raw('MAX(updated_at) as last_sync'),
                    )
                    ->first();

                $tokens = Schema::hasTable('mcp_tokens')
                    ? DB::table('mcp_tokens')
                        ->select(
                            DB::raw('COUNT(*) as total'),
                            DB::raw('MAX(last_used_at) as last_used'),
                            DB::raw('SUM(CASE WHEN revoked_at IS NULL THEN 1 ELSE 0 END) as active'),
                        )
                        ->first()
                    : null;

                // Ping — soft fallback se timeout / erro DNS / cert
                $ping = $this->pingMcp();

                return [
                    'available'      => true,
                    'docs_count'     => (int) ($docs->total ?? 0),
                    'last_sync'      => $docs->last_sync ?? null,
                    'tokens_total'   => (int) ($tokens->total ?? 0),
                    'tokens_active'  => (int) ($tokens->active ?? 0),
                    'last_token_use' => $tokens->last_used ?? null,
                    'ping'           => $ping,
                ];
            } catch (\Throwable $e) {
                Log::warning('admin.widget.mcp.error', ['error' => $e->getMessage()]);
                return $this->stub('exception:' . substr($e->getMessage(), 0, 120));
            }
            });
        }, ['component' => 'admin.widget.w6']);
    }

    private function pingMcp(): array
    {
        try {
            $start = microtime(true);
            $response = Http::timeout(2)->get(self::PING_URL);
            $latency = (int) ((microtime(true) - $start) * 1000);

            return [
                'reachable'  => $response->successful(),
                'status'     => $response->status(),
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'reachable'  => false,
                'status'     => 0,
                'latency_ms' => null,
                'error'      => substr($e->getMessage(), 0, 100),
            ];
        }
    }

    private function stub(string $reason): array
    {
        return [
            'available'      => false,
            'reason'         => $reason,
            'docs_count'     => 0,
            'tokens_total'   => 0,
            'tokens_active'  => 0,
            'ping'           => ['reachable' => false],
            'instructions'   => 'Verifique se MCP server está rodando em CT 100 e mcp.oimpresso.com resolve.',
        ];
    }
}
