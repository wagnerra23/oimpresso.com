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
        $drift = $this->renderCycleDriftAlert();

        return Response::text($brief['content'].$drift."\n\n".$meta);
    }

    /**
     * Cycle drift detector — aprendizado retro CYCLE-01 (sessão 2026-05-07).
     *
     * CYCLE-01 ficou órfão por 5 dias depois do pivot Constituição V2 porque
     * trabalho real (S3/Capterra/MWART/NfeBrasil) não casava com o cycle planejado
     * (memória Copiloto). Falta de alerta = ninguém percebeu.
     *
     * Heurística simples: cruza últimos 7 dias de mcp_git_links com tasks do
     * cycle ativo. Se >50% dos links recentes não tocam tasks do cycle → drift.
     */
    private function renderCycleDriftAlert(): string
    {
        try {
            $row = DB::selectOne(<<<'SQL'
                SELECT
                  c.id AS cycle_id,
                  c.`key` AS cycle_key,
                  c.name AS cycle_name,
                  c.start_date,
                  c.end_date,
                  GREATEST(0, LEAST(100, (DATEDIFF(CURRENT_DATE, c.start_date) / NULLIF(DATEDIFF(c.end_date, c.start_date), 0)) * 100)) AS progress_pct
                FROM mcp_cycles c
                INNER JOIN mcp_jira_projects p ON p.id = c.project_id
                WHERE c.status = 'active' AND p.`key` = 'COPI'
                LIMIT 1
            SQL);

            if (! $row) {
                return '';
            }

            // Cycle recém-ativado (<20% decorrido) ainda não tem trabalho
            // suficiente registrado pra avaliar drift confiavelmente.
            if ((float) $row->progress_pct < 20.0) {
                return '';
            }

            // 3 categorias mutuamente exclusivas (partição exata de `total`):
            //  on_cycle    — commit em task DESTE cycle (alinhado)
            //  other_cycle — commit em task de OUTRO cycle (pivot real)
            //  unlinked    — commit sem task de cycle (sem `Refs: US-XXX`) → rastreio
            //                faltando, NÃO é pivot. Antes era lumpado em "off-cycle"
            //                e inflava o alarme (causa do falso "0% → pivot?").
            $stats = DB::selectOne(<<<'SQL'
                SELECT
                  SUM(CASE WHEN t.cycle_id = ? THEN 1 ELSE 0 END) AS on_cycle,
                  SUM(CASE WHEN t.cycle_id IS NOT NULL AND t.cycle_id <> ? THEN 1 ELSE 0 END) AS other_cycle,
                  SUM(CASE WHEN t.cycle_id IS NULL THEN 1 ELSE 0 END) AS unlinked,
                  COUNT(*) AS total
                FROM mcp_git_links gl
                LEFT JOIN mcp_tasks t ON t.task_id = gl.task_id
                WHERE gl.occurred_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            SQL, [$row->cycle_id, $row->cycle_id]);

            $total = (int) ($stats->total ?? 0);
            if ($total === 0) {
                return '';
            }

            $onCycle = (int) ($stats->on_cycle ?? 0);
            $aligned = (int) round($onCycle / $total * 100);

            if ($aligned >= 50) {
                return '';
            }

            return self::formatCycleDriftAlert(
                $onCycle,
                (int) ($stats->other_cycle ?? 0),
                (int) ($stats->unlinked ?? 0),
                $total,
                $aligned,
                (string) $row->cycle_key,
            );
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Formata o alerta de drift distinguindo as 3 causas (pura — testável sem DB).
     *
     * A sugestão se adapta à causa DOMINANTE: se a maior parte é trabalho em outro
     * cycle → pivot real (rollover); se é commit sem task → rastreio faltando
     * (linkar `Refs: US-XXX`), não pivot. Antes o alarme assumia sempre pivot,
     * mesmo quando o problema era só falta de linkagem.
     */
    public static function formatCycleDriftAlert(
        int $onCycle,
        int $otherCycle,
        int $unlinked,
        int $total,
        int $aligned,
        string $cycleKey,
    ): string {
        $lines = [];
        if ($unlinked > 0) {
            $lines[] = sprintf('  ↳ %d sem task de cycle linkada (commits sem `Refs: US-XXX`) — rastreio faltando, não pivot', $unlinked);
        }
        if ($otherCycle > 0) {
            $lines[] = sprintf('  ↳ %d em tasks de OUTRO cycle — pivot real?', $otherCycle);
        }

        $suggestion = $otherCycle > $unlinked
            ? 'Trabalho migrou pra outro cycle — considere `cycles-close --rollover` + `cycles-create`.'
            : 'A maior parte é commit sem task — linke com `Refs: US-XXX` ou registre a task antes de assumir pivot.';

        return sprintf(
            "\n\n⚠️ **Cycle drift detectado:** só %d/%d commits/PRs (7d) tocam o cycle ativo `%s` (%d%% alinhados).\n%s\n%s ".
            "_Aprendizado retro CYCLE-01 (sessão 2026-05-07)._",
            $onCycle,
            $total,
            $cycleKey,
            $aligned,
            implode("\n", $lines),
            $suggestion,
        );
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
