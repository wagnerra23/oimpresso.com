<?php

namespace Modules\Brief\Http\Controllers;

use App\Util\OtelHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Brief\Http\Requests\BriefFetchToolRequest;
use Modules\Brief\Services\BriefGeneratorService;
use Throwable;

/**
 * Handler da tool MCP brief-fetch (camada L1).
 *
 * Endpoint: POST /api/mcp/tools/brief-fetch
 * Middleware: mcp.auth + throttle:60,1
 *
 * Comportamento (ADR 0091):
 *  - Cache 5min em 'brief.current'
 *  - force_refresh=true: gera AGORA (só Wagner, cap 8/dia)
 *  - Audit log em mcp_audit_log
 *  - Telemetria de skill em mcp_skill_telemetry
 */
final class BriefFetchController
{
    public function __construct(
        private readonly BriefGeneratorService $generator,
    ) {}

    public function __invoke(BriefFetchToolRequest $request): JsonResponse
    {
        // BriefFetchToolRequest valida force_refresh (boolean coerce + nullable).
        // Header X-MCP-Agent-Id e auth Bearer ficam upstream (middleware mcp.auth).
        $agentId = (string) $request->header('X-MCP-Agent-Id', 'unknown');
        $forceRefresh = (bool) ($request->validated()['force_refresh'] ?? false);

        if ($forceRefresh) {
            $this->guardForceRefresh($agentId);
            try {
                $this->generator->generateNow();
            } catch (Throwable $e) {
                return response()->json([
                    'error' => 'force_refresh_failed',
                    'detail' => $e->getMessage(),
                ], 500);
            }
            Cache::forget('brief.current');
        }

        $brief = Cache::remember(
            'brief.current',
            now()->addMinutes(5),
            fn () => $this->fetchCurrent(),
        );

        if ($brief === null) {
            return response()->json([
                'error' => 'no_brief_available',
                'hint' => 'Brief ainda não foi gerado. Aguarde próximo cron ou force_refresh=true (Wagner).',
            ], 503);
        }

        $this->logAudit($agentId, $brief, $forceRefresh);
        $this->logSkillTelemetry($agentId);

        return response()->json([
            'content' => $brief['content'],
            'meta' => [
                'generated_at' => $brief['generated_at'],
                'token_count' => $brief['token_count'],
                'staleness_minutes' => $brief['staleness_minutes'],
                'next_refresh_in_min' => $this->minutesToNextCron(),
            ],
        ]);
    }

    private function fetchCurrent(): ?array
    {
        return OtelHelper::spanBiz('brief.fetch_current', fn () => $this->doFetchCurrent());
    }

    private function doFetchCurrent(): ?array
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT
                b.id,
                b.generated_at,
                b.content,
                b.token_count,
                TIMESTAMPDIFF(MINUTE, b.generated_at, NOW()) AS staleness_minutes
            FROM mcp_briefs b
            WHERE b.valid = 1
            ORDER BY b.generated_at DESC
            LIMIT 1
        SQL);

        if ($row === null) {
            return null;
        }

        return [
            'content' => $row->content,
            'token_count' => (int) $row->token_count,
            'generated_at' => $row->generated_at,
            'staleness_minutes' => (int) $row->staleness_minutes,
        ];
    }

    private function guardForceRefresh(string $agentId): void
    {
        if (! str_contains($agentId, 'wagner')) {
            abort(403, 'force_refresh restrito a agents do Wagner');
        }

        $todayCount = DB::table('mcp_briefs')
            ->whereDate('generated_at', today())
            ->count();

        if ($todayCount >= 8) {
            abort(429, 'Cap diário de 8 gerações atingido');
        }
    }

    private function minutesToNextCron(): int
    {
        $hours = [7, 11, 14, 17, 20, 23];
        $now = now();

        foreach ($hours as $h) {
            $candidate = $now->copy()->setTime($h, 0, 0);
            if ($candidate->gt($now)) {
                return (int) $now->diffInMinutes($candidate);
            }
        }

        return (int) $now->diffInMinutes($now->copy()->addDay()->setTime(7, 0, 0));
    }

    private function logAudit(string $agentId, array $brief, bool $forceRefresh): void
    {
        try {
            DB::table('mcp_audit_log')->insert([
                'request_id' => (string) \Str::uuid(),
                'tool_or_resource' => 'brief-fetch',
                'agent_id' => $agentId,
                'user_id' => auth()->id(),
                'status' => 'ok',
                'tokens_out' => $brief['token_count'],
                'cache_read' => ! $forceRefresh,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            \Log::warning('[brief-fetch] audit log falhou: '.$e->getMessage());
        }
    }

    private function logSkillTelemetry(string $agentId): void
    {
        try {
            DB::table('mcp_skill_telemetry')->insert([
                'skill_name' => 'brief-first',
                'agent_id' => $agentId,
                'triggered_at' => now(),
                'success' => 1,
                'tokens_saved_estimate' => 15000,
            ]);
        } catch (Throwable $e) {
            \Log::warning('[brief-fetch] skill telemetry falhou: '.$e->getMessage());
        }
    }
}
