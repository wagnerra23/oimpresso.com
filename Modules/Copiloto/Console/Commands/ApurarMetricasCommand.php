<?php

namespace Modules\Copiloto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Services\Metricas\MetricasApurador;

/**
 * MEM-MET-2 (ADR 0050+0051) — Apura as 8 métricas obrigatórias + contadores
 * para 1 dia / 1 business (ou todos), gravando 1 linha em
 * `copiloto_memoria_metricas` via upsert idempotente.
 *
 * Métricas RAGAS (Recall@3/Precision@3/MRR/faithfulness/answer_relevancy/
 * context_precision) ficam NULL até o golden set MEM-P2-1 existir e o
 * comando ser invocado com --golden=path (próxima task).
 *
 * Uso:
 *   php artisan copiloto:metrics:apurar                   # hoje, todos os businesses + plataforma
 *   php artisan copiloto:metrics:apurar --date=2026-04-29 # data específica
 *   php artisan copiloto:metrics:apurar --business=4      # 1 tenant só
 *   php artisan copiloto:metrics:apurar --business=all    # todos os tenants
 *   php artisan copiloto:metrics:apurar --business=plataforma # só linha agregada (NULL)
 */
class ApurarMetricasCommand extends Command
{
    protected $signature = 'copiloto:metrics:apurar
                            {--date= : Data alvo YYYY-MM-DD (default: hoje)}
                            {--business=all : ID do business, "all", ou "plataforma"}';

    protected $description = 'Apura as 8 métricas obrigatórias do Copiloto para 1 dia e grava em copiloto_memoria_metricas';

    public function handle(MetricasApurador $apurador): int
    {
        $date = $this->option('date'); // null = hoje
        $businessOpt = (string) $this->option('business');

        $alvos = $this->resolverAlvos($businessOpt);

        $this->info(sprintf(
            'Apurando métricas — data=%s, alvos=%d',
            $date ?? 'hoje',
            count($alvos),
        ));

        $errors = 0;
        foreach ($alvos as $businessId) {
            try {
                $linha = $apurador->apurar($businessId, $date);
                $this->line(sprintf(
                    '  ✓ business_id=%s → latência_p95=%s ms · tokens_médios=%s · interacoes=%d · memorias=%d · bloat=%s · contradições=%s%%',
                    $businessId === null ? 'NULL (plataforma)' : $businessId,
                    $linha->latencia_p95_ms ?? 'n/a',
                    $linha->tokens_medio_interacao ?? 'n/a',
                    $linha->total_interacoes_dia,
                    $linha->total_memorias_ativas,
                    $linha->memory_bloat_ratio !== null ? number_format($linha->memory_bloat_ratio, 3) : 'n/a',
                    $linha->taxa_contradicoes_pct !== null ? number_format($linha->taxa_contradicoes_pct, 2) : 'n/a',
                ));
            } catch (\Throwable $e) {
                $errors++;
                $this->error(sprintf(
                    '  ✗ business_id=%s falhou: %s',
                    $businessId === null ? 'NULL' : $businessId,
                    $e->getMessage(),
                ));
            }
        }

        $this->info(sprintf(
            'Apuração concluída — %d gravadas, %d erros.',
            count($alvos) - $errors,
            $errors,
        ));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolve a opção --business pra lista de IDs (null = plataforma).
     *
     * @return array<int|null>
     */
    protected function resolverAlvos(string $opt): array
    {
        if ($opt === 'plataforma') {
            return [null];
        }

        if (ctype_digit($opt)) {
            return [(int) $opt];
        }

        if ($opt === 'all') {
            // Plataforma + todos os businesses que têm pelo menos 1 mensagem
            $bizComAtividade = DB::table('copiloto_conversas')
                ->whereNotNull('business_id')
                ->distinct()
                ->pluck('business_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            return array_merge([null], $bizComAtividade);
        }

        $this->warn("Opção --business='{$opt}' não reconhecida; usando 'plataforma'.");
        return [null];
    }
}
