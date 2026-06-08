<?php

namespace Modules\Jana\Services\MemoriaAutonoma;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\AnonymousAgent;
use Modules\Jana\Ai\Agents\WeeklyDigestAgent;
use RuntimeException;

/**
 * WeeklyDigestService — Reflect-style weekly review (AUDITORIA G8 P2).
 *
 * Coleta dados ricos da semana (não só git):
 *  - Git commits (filtrado por --no-merges)
 *  - PRs mergeados via gh CLI (se disponível)
 *  - US closed/created via tabela mcp_tasks
 *  - ADRs criadas (memory/decisions/ + diff filter A)
 *  - Handoffs criados (memory/handoffs/*.md filename date prefix)
 *  - Cycle goals progress delta (mcp_cycle_goals.achieved_value)
 *  - Decisões HITL escaladas (mcp_audit_log se existir)
 *
 * Chama gpt-4o-mini (laravel/ai AnonymousAgent) com instruções do
 * WeeklyDigestAgent pra gerar markdown 5-seções.
 *
 * Salva em:
 *  - File: `memory/sessions/WEEKLY-DIGEST-YYYY-Www.md`
 *  - DB:   `mcp_weekly_digests` (unique by week)
 *
 * Idempotente: re-rodar com --force re-gera; sem --force aborta se já existe.
 */
class WeeklyDigestService
{
    public const PATH_OUTPUT = 'memory/sessions';
    public const FILENAME_PREFIX = 'WEEKLY-DIGEST-';

    /**
     * Gera digest semanal.
     *
     * @param  string  $semana    Identificador ISO YYYY-Www
     * @param  bool    $dryRun    Se true, NÃO chama LLM e NÃO salva
     * @param  bool    $force     Se true, sobrescreve existente
     * @return array{path:?string, contexto:string, digest:?string, metrics:array, custo_estimado:array}
     */
    public function gerar(string $semana, bool $dryRun = false, bool $force = false): array
    {
        // D9.a (Wave 18 SATURATION) — span weekly digest cross-tenant (admin op).
        return OtelHelper::span('jana.weekly_digest.gerar', [
            'semana' => $semana,
            'dry_run' => $dryRun,
            'force' => $force,
        ], fn () => $this->gerarInternal($semana, $dryRun, $force));
    }

    private function gerarInternal(string $semana, bool $dryRun = false, bool $force = false): array
    {
        [$inicio, $fim] = $this->resolverRangeIso($semana);

        $arquivoDestino = base_path(self::PATH_OUTPUT . '/' . self::FILENAME_PREFIX . "{$semana}.md");

        if (file_exists($arquivoDestino) && ! $force && ! $dryRun) {
            throw new RuntimeException(
                "Digest já existe: {$arquivoDestino}. Use --force pra sobrescrever."
            );
        }

        [$contexto, $metrics] = $this->coletarContextoEMetricas($inicio, $fim);

        if ($dryRun) {
            return [
                'path' => null,
                'contexto' => $contexto,
                'digest' => null,
                'metrics' => $metrics,
                'custo_estimado' => $this->estimarCusto($contexto),
            ];
        }

        $startedAt = microtime(true);

        $digest = $this->chamarLlm($semana, $inicio, $fim, $contexto);
        if ($digest === null) {
            throw new RuntimeException('LLM falhou ao gerar digest weekly');
        }

        $duracaoMs = (int) round((microtime(true) - $startedAt) * 1000);

        $conteudoFinal = $this->montarFrontmatter($semana, $inicio, $fim, $duracaoMs, $metrics) . $digest;

        // Garante diretório existe
        $dir = dirname($arquivoDestino);
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($arquivoDestino, $conteudoFinal);

        $this->persistirDb($semana, $inicio, $fim, $conteudoFinal, $metrics, $contexto);

        Log::channel('copiloto-ai')->info('WeeklyDigest gerado', [
            'semana' => $semana,
            'arquivo' => $arquivoDestino,
            'duracao_ms' => $duracaoMs,
            'metrics' => $metrics,
        ]);

        return [
            'path' => $arquivoDestino,
            'contexto' => $contexto,
            'digest' => $digest,
            'metrics' => $metrics,
            'custo_estimado' => $this->estimarCusto($contexto),
        ];
    }

    /**
     * Resolve YYYY-Www → [Carbon $inicio segunda 00:00, Carbon $fim domingo 23:59].
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolverRangeIso(string $semana): array
    {
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $semana, $m)) {
            throw new RuntimeException(
                "Semana inválida: {$semana}. Formato esperado: YYYY-Www (ex.: 2026-W19)"
            );
        }
        $ano = (int) $m[1];
        $sem = (int) $m[2];
        $inicio = Carbon::now()->setISODate($ano, $sem)->startOfWeek()->startOfDay();
        $fim    = $inicio->copy()->endOfWeek()->endOfDay();

        return [$inicio, $fim];
    }

    /**
     * Coleta contexto bruto + métricas estruturadas.
     *
     * @return array{0: string, 1: array}
     */
    public function coletarContextoEMetricas(Carbon $inicio, Carbon $fim): array
    {
        $since = $inicio->toIso8601String();
        $until = $fim->toIso8601String();

        // 1. Commits (top 50 + count total)
        $commitsRaw = $this->git("log --since={$since} --until={$until} --pretty=format:'%h | %an | %s' --no-merges");
        $commitsLinhas = $this->splitNonEmpty($commitsRaw);
        $commitsCount = count($commitsLinhas);
        $commitsTop = implode("\n", array_slice($commitsLinhas, 0, 50));

        // 2. ADRs novas (memory/decisions/ filter A)
        $adrsNovasRaw = $this->git("log --since={$since} --until={$until} --diff-filter=A --name-only --pretty=format: -- memory/decisions/");
        $adrsNovasLista = $this->dedupNonEmpty(explode("\n", $adrsNovasRaw));
        $adrsCount = count($adrsNovasLista);

        // 3. PRs mergeados via gh CLI (fallback gracioso se gh ausente)
        $prsMerged = $this->ghPrMerged($inicio, $fim);
        $prsCount = is_array($prsMerged) ? count($prsMerged) : 0;
        $prsResumo = is_array($prsMerged) ? $this->renderPrs($prsMerged) : 'gh CLI indisponível ou sem rede';

        // 4. Handoffs criados na semana (filename date prefix)
        $handoffsLista = $this->listHandoffsSemana($inicio, $fim);
        $handoffsCount = count($handoffsLista);

        // 5. US closed / created via mcp_tasks (se tabela existir)
        $tasksClosed = $this->tasksClosedNaSemana($inicio, $fim);
        $tasksCreated = $this->tasksCreatedNaSemana($inicio, $fim);

        // 6. Cycle goals progress (snapshot atual — cycle active)
        $cycleProgress = $this->cycleProgressAtual();

        // 7. HITL/audit log decisões (se tabela existir)
        $auditDecisions = $this->auditDecisionsNaSemana($inicio, $fim);

        $contexto = <<<CTX
        == RANGE ==
        {$inicio->format('Y-m-d')} (segunda) a {$fim->format('Y-m-d')} (domingo)

        == COMMITS ({$commitsCount} total, top 50) ==
        {$commitsTop}

        == ADRs NOVAS ({$adrsCount}) ==
        {$this->renderLista($adrsNovasLista)}

        == PRs MERGEADOS ({$prsCount}) ==
        {$prsResumo}

        == HANDOFFS CRIADOS ({$handoffsCount}) ==
        {$this->renderLista($handoffsLista)}

        == US TASKS CLOSED ({$tasksClosed['count']}) ==
        {$tasksClosed['resumo']}

        == US TASKS CRIADAS ({$tasksCreated['count']}) ==
        {$tasksCreated['resumo']}

        == CYCLE PROGRESS ATUAL ==
        {$cycleProgress}

        == HITL / AUDIT LOG DECISÕES ==
        {$auditDecisions}
        CTX;

        $metrics = [
            'commits' => $commitsCount,
            'prs_merged' => $prsCount,
            'us_closed' => $tasksClosed['count'],
            'us_created' => $tasksCreated['count'],
            'adrs_new' => $adrsCount,
            'handoffs' => $handoffsCount,
            'cycle_progress_pct' => $this->extrairPctDoTexto($cycleProgress),
        ];

        return [$contexto, $metrics];
    }

    /**
     * Lê via gh CLI os PRs mergeados na janela. Retorna array ou null se falhar.
     *
     * @return array<int, array{number:int, title:string, mergedAt:string}>|null
     */
    protected function ghPrMerged(Carbon $inicio, Carbon $fim): ?array
    {
        $sinceDate = $inicio->toDateString();
        $untilDate = $fim->toDateString();
        $cmd = 'gh pr list --state merged --search ' . escapeshellarg("merged:{$sinceDate}..{$untilDate}") .
            ' --limit 100 --json number,title,mergedAt 2>&1';
        $out = shell_exec($cmd);
        if ($out === null || $out === '') {
            return null;
        }
        $out = trim($out);
        // Detecta erro CLI
        if (! str_starts_with($out, '[')) {
            return null;
        }
        $decoded = json_decode($out, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<int, array{number:int, title:string, mergedAt:string}> $prs
     */
    protected function renderPrs(array $prs): string
    {
        if (empty($prs)) {
            return 'Nenhum PR mergeado na janela';
        }
        $linhas = [];
        foreach (array_slice($prs, 0, 50) as $pr) {
            $linhas[] = sprintf('#%d | %s | %s', $pr['number'] ?? 0, $pr['mergedAt'] ?? '', $pr['title'] ?? '');
        }

        return implode("\n", $linhas);
    }

    /**
     * Lista handoffs criados na semana (filename pattern YYYY-MM-DD-HHMM-*.md).
     *
     * @return array<int, string>
     */
    protected function listHandoffsSemana(Carbon $inicio, Carbon $fim): array
    {
        $dir = base_path('memory/handoffs');
        if (! is_dir($dir)) {
            return [];
        }
        $entries = @scandir($dir);
        if ($entries === false) {
            return [];
        }
        $lista = [];
        foreach ($entries as $entry) {
            if (! str_ends_with($entry, '.md') || str_starts_with($entry, '_')) {
                continue;
            }
            if (! preg_match('/^(\d{4}-\d{2}-\d{2})-(\d{4})-.+\.md$/', $entry, $m)) {
                continue;
            }
            try {
                $date = Carbon::parse($m[1]);
            } catch (\Throwable $e) {
                continue;
            }
            if ($date->lt($inicio) || $date->gt($fim)) {
                continue;
            }
            $lista[] = $entry;
        }
        sort($lista);

        return $lista;
    }

    /**
     * @return array{count:int, resumo:string}
     */
    protected function tasksClosedNaSemana(Carbon $inicio, Carbon $fim): array
    {
        if (! Schema::hasTable('mcp_tasks')) {
            return ['count' => 0, 'resumo' => 'tabela mcp_tasks ausente'];
        }
        try {
            $cols = Schema::getColumnListing('mcp_tasks');
            $colsTitle = in_array('title', $cols, true) ? 'title' : (in_array('summary', $cols, true) ? 'summary' : 'task_id');
            $colsTaskId = in_array('task_id', $cols, true) ? 'task_id' : 'id';
            $colsStatus = in_array('status', $cols, true) ? 'status' : null;
            $colsClosedAt = in_array('closed_at', $cols, true) ? 'closed_at'
                : (in_array('done_at', $cols, true) ? 'done_at' : 'updated_at');

            if ($colsStatus === null) {
                return ['count' => 0, 'resumo' => 'coluna status ausente'];
            }

            $rows = DB::table('mcp_tasks')
                ->where($colsStatus, 'done')
                ->whereBetween($colsClosedAt, [$inicio, $fim])
                ->select([$colsTaskId . ' as task_id', $colsTitle . ' as title'])
                ->limit(50)
                ->get();

            $count = $rows->count();
            $resumo = $count === 0
                ? 'Nenhuma US closed na janela'
                : $rows->map(fn ($r) => "{$r->task_id} | {$r->title}")->implode("\n");

            return ['count' => $count, 'resumo' => $resumo];
        } catch (\Throwable $e) {
            return ['count' => 0, 'resumo' => 'erro consultando mcp_tasks: ' . $e->getMessage()];
        }
    }

    /**
     * @return array{count:int, resumo:string}
     */
    protected function tasksCreatedNaSemana(Carbon $inicio, Carbon $fim): array
    {
        if (! Schema::hasTable('mcp_tasks')) {
            return ['count' => 0, 'resumo' => 'tabela mcp_tasks ausente'];
        }
        try {
            $cols = Schema::getColumnListing('mcp_tasks');
            $colsTitle = in_array('title', $cols, true) ? 'title' : (in_array('summary', $cols, true) ? 'summary' : 'task_id');
            $colsTaskId = in_array('task_id', $cols, true) ? 'task_id' : 'id';

            $rows = DB::table('mcp_tasks')
                ->whereBetween('created_at', [$inicio, $fim])
                ->select([$colsTaskId . ' as task_id', $colsTitle . ' as title'])
                ->limit(50)
                ->get();

            $count = $rows->count();
            $resumo = $count === 0
                ? 'Nenhuma US criada na janela'
                : $rows->map(fn ($r) => "{$r->task_id} | {$r->title}")->implode("\n");

            return ['count' => $count, 'resumo' => $resumo];
        } catch (\Throwable $e) {
            return ['count' => 0, 'resumo' => 'erro consultando mcp_tasks: ' . $e->getMessage()];
        }
    }

    /**
     * Snapshot do cycle ativo + progresso de goals.
     */
    protected function cycleProgressAtual(): string
    {
        if (! Schema::hasTable('mcp_cycles')) {
            return 'tabela mcp_cycles ausente';
        }
        try {
            $cycle = DB::table('mcp_cycles')
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first();

            if (! $cycle) {
                return 'Sem cycle ativo';
            }

            $cycleId = $cycle->id ?? null;
            $cycleName = $cycle->name ?? $cycle->slug ?? "CYCLE-{$cycleId}";

            if ($cycleId === null || ! Schema::hasTable('mcp_cycle_goals')) {
                return "Cycle ativo: {$cycleName} (sem goals trackados)";
            }

            $goals = DB::table('mcp_cycle_goals')->where('cycle_id', $cycleId)->get();
            $total = $goals->count();
            if ($total === 0) {
                return "Cycle ativo: {$cycleName} — 0 goals cadastrados";
            }

            $achieved = 0;
            $inProgress = 0;
            $blocked = 0;
            foreach ($goals as $g) {
                $status = $g->status ?? null;
                if ($status === 'achieved' || $status === 'done') {
                    $achieved++;
                } elseif ($status === 'blocked') {
                    $blocked++;
                } else {
                    $inProgress++;
                }
            }
            $pct = (int) round(($achieved / $total) * 100);

            return "Cycle ativo: {$cycleName} — {$pct}% de {$total} goals ({$achieved} achieved, {$inProgress} in_progress, {$blocked} blocked)";
        } catch (\Throwable $e) {
            return 'erro consultando cycle: ' . $e->getMessage();
        }
    }

    /**
     * Lê decisões/escalations do mcp_audit_log na semana.
     */
    protected function auditDecisionsNaSemana(Carbon $inicio, Carbon $fim): string
    {
        if (! Schema::hasTable('mcp_audit_log')) {
            return '—';
        }
        try {
            $cols = Schema::getColumnListing('mcp_audit_log');
            $actionCol = in_array('action', $cols, true) ? 'action'
                : (in_array('event_type', $cols, true) ? 'event_type' : null);
            if ($actionCol === null) {
                return '—';
            }

            $rows = DB::table('mcp_audit_log')
                ->whereBetween('created_at', [$inicio, $fim])
                ->where($actionCol, 'like', '%hitl%')
                ->orWhere($actionCol, 'like', '%escalat%')
                ->limit(20)
                ->get();

            if ($rows->isEmpty()) {
                return '—';
            }

            return $rows->map(fn ($r) => ($r->{$actionCol} ?? 'unknown') . ' @ ' . ($r->created_at ?? ''))
                ->implode("\n");
        } catch (\Throwable $e) {
            return '—';
        }
    }

    protected function git(string $args): string
    {
        $cmd = 'git -C ' . escapeshellarg(base_path()) . ' ' . $args . ' 2>&1';
        $out = shell_exec($cmd) ?? '';

        return trim((string) $out);
    }

    /**
     * @return array<int, string>
     */
    protected function splitNonEmpty(string $texto): array
    {
        $linhas = explode("\n", $texto);
        return array_values(array_filter($linhas, fn ($l) => trim($l) !== ''));
    }

    /**
     * @param array<int, string> $linhas
     * @return array<int, string>
     */
    protected function dedupNonEmpty(array $linhas): array
    {
        return array_values(array_unique(array_filter($linhas, fn ($l) => trim($l) !== '')));
    }

    /**
     * @param array<int, string> $lista
     */
    protected function renderLista(array $lista): string
    {
        if (empty($lista)) {
            return '—';
        }

        return implode("\n", $lista);
    }

    protected function extrairPctDoTexto(string $texto): int
    {
        if (preg_match('/(\d{1,3})%/', $texto, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * Chama LLM via laravel/ai AnonymousAgent (gpt-4o-mini default).
     * Wrapper sobre WeeklyDigestAgent pra permitir Ai::fakeAgent em testes.
     */
    protected function chamarLlm(string $semana, Carbon $inicio, Carbon $fim, string $contexto): ?string
    {
        try {
            // Trunca contexto gigante (>25k chars ~6k tokens)
            $ctxTruncado = mb_strlen($contexto) > 25_000
                ? mb_substr($contexto, 0, 25_000) . "\n\n[... truncado por tamanho ...]"
                : $contexto;

            // Uso de AnonymousAgent pra testes Ai::fakeAgent funcionarem
            // (mesma estratégia do HandoffFetchSummarizedTool).
            // Em prod, equivalente ao WeeklyDigestAgent — mesmas instructions.
            $weeklyAgent = new WeeklyDigestAgent(
                semana: $semana,
                rangeInicio: $inicio->toDateString(),
                rangeFim: $fim->toDateString(),
                contextoBruto: $ctxTruncado,
            );

            $agent = new AnonymousAgent(
                instructions: (string) $weeklyAgent->instructions(),
                messages: [],
                tools: [],
            );

            $response = $agent->prompt($weeklyAgent->montarPromptUsuario());
            $texto = trim((string) $response);

            return $texto !== '' ? $texto : null;
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('WeeklyDigest LLM falhou', [
                'semana' => $semana,
                'erro' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function montarFrontmatter(string $semana, Carbon $inicio, Carbon $fim, int $duracaoMs, array $metrics): string
    {
        $geradoEm = Carbon::now()->toIso8601String();
        $metricsJson = json_encode($metrics, JSON_UNESCAPED_UNICODE);

        return <<<FM
        ---
        tipo: weekly-digest
        semana: {$semana}
        range: {$inicio->toDateString()}..{$fim->toDateString()}
        gerado_em: {$geradoEm}
        gerado_por: jana-weekly-digest (gpt-4o-mini)
        duracao_ms: {$duracaoMs}
        metrics: {$metricsJson}
        ---

        # Weekly Digest {$semana}

        > Range: {$inicio->toDateString()} (segunda) a {$fim->toDateString()} (domingo)
        > Gerado por `jana:weekly-digest` segunda 09h BRT — leitura ~90 segundos.


        FM;
    }

    /**
     * Custo gpt-4o-mini: $0.15/M input · $0.60/M output (Anthropic precificação 2026).
     */
    public function estimarCusto(string $contexto): array
    {
        $inputTokens = (int) ceil(mb_strlen($contexto) / 4);
        $outputTokens = 1000; // digest ~1000 tokens
        $custoUsd = ($inputTokens * 0.15 / 1_000_000) + ($outputTokens * 0.60 / 1_000_000);

        return [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'usd' => round($custoUsd, 6),
            'brl_aprox' => round($custoUsd * 5.0, 6),
        ];
    }

    /**
     * Persiste digest em `mcp_weekly_digests` (upsert por week unique).
     */
    protected function persistirDb(string $semana, Carbon $inicio, Carbon $fim, string $conteudoFinal, array $metrics, string $contexto): void
    {
        if (! Schema::hasTable('mcp_weekly_digests')) {
            return;
        }
        try {
            $custo = $this->estimarCusto($contexto);

            $existing = DB::table('mcp_weekly_digests')->where('week', $semana)->first(['id']);

            $payload = [
                'week' => $semana,
                'range_start' => $inicio->toDateString(),
                'range_end' => $fim->toDateString(),
                'digest_markdown' => $conteudoFinal,
                'metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
                'tokens_in' => $custo['input_tokens'],
                'tokens_out' => $custo['output_tokens'],
                'cost_brl' => $custo['brl_aprox'],
                'model' => 'gpt-4o-mini',
                'updated_at' => now(),
            ];

            if ($existing !== null) {
                DB::table('mcp_weekly_digests')->where('id', $existing->id)->update($payload);
            } else {
                $payload['created_at'] = now();
                DB::table('mcp_weekly_digests')->insert($payload);
            }
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('WeeklyDigest DB persist falhou', [
                'semana' => $semana,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
