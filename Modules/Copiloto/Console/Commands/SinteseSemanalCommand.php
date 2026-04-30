<?php

namespace Modules\Copiloto\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Copiloto\Services\MemoriaAutonoma\SinteseSemanalService;

/**
 * SinteseSemanalCommand — Fase 1 MemoriaAutonoma.
 *
 * Gera síntese automática da semana passada (default) ou semana específica.
 * Roda via cron sex 18h (definido em app/Console/Kernel.php).
 *
 * Output: memory/sessions/SEMANA-YYYY-Www-resumo.md
 *
 * Uso:
 *   php artisan copiloto:sintese-semanal                    # semana ANTERIOR
 *   php artisan copiloto:sintese-semanal --week=2026-W18    # semana específica
 *   php artisan copiloto:sintese-semanal --dry-run          # mostra contexto sem chamar LLM
 *   php artisan copiloto:sintese-semanal --force            # sobrescreve arquivo existente
 *
 * Ver ADR `MemoriaAutonoma/adr/arq/0001-fase-1-sintese-semanal.md`.
 */
class SinteseSemanalCommand extends Command
{
    protected $signature = 'copiloto:sintese-semanal
                            {--week=          : Semana ISO YYYY-Www (default: anterior)}
                            {--dry-run        : Coleta contexto sem chamar LLM nem salvar arquivo}
                            {--force          : Sobrescreve arquivo existente}';

    protected $description = 'Gera síntese semanal automática (Fase 1 MemoriaAutonoma) — ADR MemoriaAutonoma/0001';

    public function handle(SinteseSemanalService $service): int
    {
        $semana = (string) ($this->option('week') ?: $this->semanaAnterior());
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');

        $this->info("Síntese semanal: {$semana}" . ($dryRun ? ' (dry-run)' : ''));

        try {
            $resultado = $service->gerar($semana, $dryRun, $force);
        } catch (\Throwable $e) {
            $this->error("Falhou: {$e->getMessage()}");
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->line('');
            $this->line('=== CONTEXTO COLETADO (truncado a 2000 chars) ===');
            $this->line(mb_substr($resultado['contexto'], 0, 2000));
            $this->line('');
            $custo = $resultado['custo_estimado'];
            $this->line("=== CUSTO ESTIMADO ===");
            $this->line("Input tokens : ~{$custo['input_tokens']}");
            $this->line("Output tokens: ~{$custo['output_tokens']}");
            $this->line("USD          : ~\${$custo['usd']}");
            $this->line("BRL aprox    : ~R\${$custo['brl_aprox']}");
            return self::SUCCESS;
        }

        $this->info("✓ Síntese gerada: {$resultado['path']}");
        $custo = $resultado['custo_estimado'];
        $this->line("  Custo: ~\${$custo['usd']} (~R\${$custo['brl_aprox']})");

        return self::SUCCESS;
    }

    /**
     * Retorna ID ISO YYYY-Www da semana ANTERIOR (segunda passada → domingo passado).
     * Sex 18h roda → semana fechada essa que acabou de passar.
     */
    protected function semanaAnterior(): string
    {
        $hoje = Carbon::now();
        // Se hoje é sexta após 18h, a semana atual é a que tá fechando hoje.
        // Mas pra ser seguro e idempotente, sempre pega semana ANTERIOR à atual.
        $umaSemanaAtras = $hoje->copy()->subWeek();
        $ano = (int) $umaSemanaAtras->isoWeekYear;
        $sem = (int) $umaSemanaAtras->isoWeek;
        return sprintf('%04d-W%02d', $ano, $sem);
    }
}
