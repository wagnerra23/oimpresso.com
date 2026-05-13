<?php

namespace Modules\Jana\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Jana\Services\MemoriaAutonoma\WeeklyDigestService;

/**
 * JanaWeeklyDigestCommand — Reflect-style weekly digest (AUDITORIA G8 P2).
 *
 * Gera digest semanal estruturado em 5 seções (Marco / Trabalho / Cycle progress
 * / Decisões / Próxima semana) consolidando commits + PRs mergeados + US closed
 * + ADRs + handoffs + cycle goals progress.
 *
 * Diferente de `copiloto:sintese-semanal` (narrativa LLM sex 18h):
 *  - SEMANA-YYYY-Www-resumo.md = síntese narrativa Wagner-style (Haiku)
 *  - WEEKLY-DIGEST-YYYY-Www.md = digest report Reflect-style (gpt-4o-mini)
 *
 * Schedule: segunda 09:00 BRT (Wagner abre semana e vê o que mudou).
 *
 * Uso:
 *   php artisan jana:weekly-digest                    # semana anterior
 *   php artisan jana:weekly-digest --week=2026-W19    # específica
 *   php artisan jana:weekly-digest --dry-run          # contexto sem LLM
 *   php artisan jana:weekly-digest --force            # sobrescreve existente
 */
class JanaWeeklyDigestCommand extends Command
{
    protected $signature = 'jana:weekly-digest
                            {--week=          : Semana ISO YYYY-Www (default: anterior)}
                            {--dry-run        : Coleta contexto sem chamar LLM}
                            {--force          : Sobrescreve arquivo/row existente}';

    protected $description = 'Gera weekly digest Reflect-style (AUDITORIA G8 P2) — 5 seções estruturadas pra Wagner abrir segunda 09h';

    public function handle(WeeklyDigestService $service): int
    {
        $semana = (string) ($this->option('week') ?: $this->semanaAnterior());
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');

        $this->info("Weekly digest: {$semana}" . ($dryRun ? ' (dry-run)' : ''));

        try {
            $resultado = $service->gerar($semana, $dryRun, $force);
        } catch (\Throwable $e) {
            $this->error("Falhou: {$e->getMessage()}");

            return self::FAILURE;
        }

        $metrics = $resultado['metrics'];
        $this->line('');
        $this->line('=== MÉTRICAS COLETADAS ===');
        $this->line("Commits     : {$metrics['commits']}");
        $this->line("PRs merged  : {$metrics['prs_merged']}");
        $this->line("US closed   : {$metrics['us_closed']}");
        $this->line("US created  : {$metrics['us_created']}");
        $this->line("ADRs new    : {$metrics['adrs_new']}");
        $this->line("Handoffs    : {$metrics['handoffs']}");
        $this->line("Cycle prog  : {$metrics['cycle_progress_pct']}%");

        if ($dryRun) {
            $this->line('');
            $this->line('=== CONTEXTO (truncado a 2000 chars) ===');
            $this->line(mb_substr($resultado['contexto'], 0, 2000));
            $custo = $resultado['custo_estimado'];
            $this->line('');
            $this->line('=== CUSTO ESTIMADO ===');
            $this->line("Input tokens : ~{$custo['input_tokens']}");
            $this->line("Output tokens: ~{$custo['output_tokens']}");
            $this->line("USD          : ~\${$custo['usd']}");
            $this->line("BRL          : ~R\${$custo['brl_aprox']}");

            return self::SUCCESS;
        }

        $this->info("✓ Digest gerado: {$resultado['path']}");
        $custo = $resultado['custo_estimado'];
        $this->line("  Custo: ~\${$custo['usd']} (~R\${$custo['brl_aprox']})");

        return self::SUCCESS;
    }

    /**
     * Semana ANTERIOR à atual (ID ISO YYYY-Www).
     * Segunda 09h roda → semana fechada que terminou ontem.
     */
    protected function semanaAnterior(): string
    {
        $umaSemanaAtras = Carbon::now()->subWeek();
        $ano = (int) $umaSemanaAtras->isoWeekYear;
        $sem = (int) $umaSemanaAtras->isoWeek;

        return sprintf('%04d-W%02d', $ano, $sem);
    }
}
