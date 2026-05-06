<?php

declare(strict_types=1);

namespace Modules\Brief\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Brief\Services\BriefGeneratorService;
use Throwable;

/**
 * Tool MCP brief-fetch — camada L7 da Constituição V2 (ADR 0091).
 *
 * Devolve o Daily Brief mais recente — markdown ≤3.5k tokens com estado
 * consolidado do projeto (cycle ativo, HITL pending, decisões 24h, skills 7d,
 * flags). CHAME ANTES DE QUALQUER OUTRA TOOL no início de toda sessão (skill
 * brief-first Tier A force essa ordem).
 *
 * Cache 5min: 10 agents na mesma janela hits cache.
 * force_refresh: regenera AGORA (só Wagner, cap 8/dia).
 *
 * Pattern segue Modules/Copiloto/Mcp/Tools/CyclesActiveTool.php — registro
 * via Modules/Brief/Providers/BriefServiceProvider boot().
 */
class BriefFetchTool extends Tool
{
    protected string $name = 'brief-fetch';

    protected string $title = 'Daily Brief — estado consolidado do projeto (camada L7)';

    protected string $description = 'Devolve o Daily Brief mais recente — markdown ~3k tokens com cycle ativo, HITL pending, decisões 24h, skills uso 7d, flags de risco. CHAME ANTES DE QUALQUER OUTRA TOOL no início de toda sessão. Cache 5min, custo trivial. Substitui exploração inicial via cycles-active + sessions-recent + tasks-active + decisions-search.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'force_refresh' => $schema->boolean()
                ->description('Regenera o brief AGORA antes de retornar. Restrito a agents do Wagner (X-MCP-Agent-Id contém "wagner"). Respeita cap diário de 8 gerações. Default: false (retorna do cache).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $forceRefresh = (bool) $request->get('force_refresh', false);
        $agentId = $this->resolveAgentId($request);

        if ($forceRefresh) {
            $guard = $this->guardForceRefresh($agentId);
            if ($guard !== null) {
                return Response::text($guard);
            }

            try {
                app(BriefGeneratorService::class)->generateNow();
                Cache::forget('brief.current');
            } catch (Throwable $e) {
                return Response::text(
                    "❌ force_refresh falhou: {$e->getMessage()}\n\n_Brief anterior continua disponível em chamada sem force_refresh._"
                );
            }
        }

        $brief = Cache::remember(
            'brief.current',
            now()->addMinutes(5),
            fn () => $this->fetchCurrent(),
        );

        if ($brief === null) {
            return Response::text(
                "⚠️ Nenhum brief válido disponível em `mcp_briefs`.\n\n"
                ."Aguarde próximo cron (07/11/14/17/20/23h BRT) ou peça pra Wagner rodar `php artisan brief:generate`.\n\n"
                ."_Fallback temporário: chame `cycles-active`, `my-work`, `decisions-search`._"
            );
        }

        $this->logAudit($agentId, $brief, $forceRefresh);
        $this->logSkillTelemetry($agentId);

        $meta = $this->renderMetaFooter($brief);

        return Response::text($brief['content']."\n\n".$meta);
    }

    private function fetchCurrent(): ?array
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
            'id' => (int) $row->id,
            'content' => $row->content,
            'token_count' => (int) $row->token_count,
            'generated_at' => $row->generated_at,
            'staleness_minutes' => (int) $row->staleness_minutes,
        ];
    }

    private function guardForceRefresh(string $agentId): ?string
    {
        if (! str_contains(strtolower($agentId), 'wagner')) {
            return '🚫 `force_refresh: true` é restrito a agents do Wagner (`X-MCP-Agent-Id` contendo "wagner"). Sua chamada usou `'.$agentId.'`.';
        }

        $todayCount = DB::table('mcp_briefs')
            ->whereDate('generated_at', today())
            ->count();

        if ($todayCount >= 8) {
            return '🚫 Cap diário de 8 gerações atingido (hoje: '.$todayCount.'). Aguarde próximo dia ou cron 6x/dia.';
        }

        return null;
    }

    private function resolveAgentId(Request $request): string
    {
        // laravel/mcp ^0.7 expõe headers via Request — fallback pra unknown
        try {
            return (string) ($request->header('X-MCP-Agent-Id') ?? 'unknown');
        } catch (Throwable) {
            return 'unknown';
        }
    }

    private function logAudit(string $agentId, array $brief, bool $forceRefresh): void
    {
        try {
            DB::table('mcp_audit_log')->insert([
                'request_id' => (string) Str::uuid(),
                'tool_or_resource' => 'brief-fetch',
                'agent_id' => $agentId,
                'user_id' => auth()->id(),
                'status' => 'ok',
                'tokens_out' => $brief['token_count'],
                'cache_read' => $forceRefresh ? 0 : 1,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Audit best-effort — não trava resposta
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
        } catch (Throwable) {
            // Telemetria best-effort
        }
    }

    private function renderMetaFooter(array $brief): string
    {
        $next = $this->minutesToNextCron();

        return sprintf(
            '_Brief #%d · %d tokens · gerado há %d min · próximo cron em %d min_',
            $brief['id'],
            $brief['token_count'],
            $brief['staleness_minutes'],
            $next,
        );
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
}
