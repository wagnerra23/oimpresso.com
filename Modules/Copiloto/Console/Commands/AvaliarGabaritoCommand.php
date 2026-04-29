<?php

namespace Modules\Copiloto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Services\Metricas\GabaritoEvaluator;

/**
 * MEM-EVAL-1 (ADR 0049+0050) — Roda gabarito e calcula Recall@K, Precision@K, MRR.
 *
 * Uso:
 *   php artisan copiloto:eval                            # global, sem resposta LLM
 *   php artisan copiloto:eval --business=4               # só Larissa (ROTA LIVRE)
 *   php artisan copiloto:eval --business=4 --resposta    # + chama LLM e checa pattern
 *   php artisan copiloto:eval --json                     # output JSON pra dashboard/CI
 *   php artisan copiloto:eval --persist                  # grava em copiloto_memoria_metricas
 *
 * Custo / esforço:
 *   - Sem --resposta: 50 perguntas × ~50ms recall = ~2.5s, R$ 0,00
 *   - Com --resposta: 50 perguntas × ~3s LLM = 2.5min, ~R$ 0,11/run
 *
 * Threshold de aceitação (ADR 0050):
 *   Recall@3   ≥ 0,80
 *   Precision@3 ≥ 0,60
 *   MRR        ≥ 0,70
 */
class AvaliarGabaritoCommand extends Command
{
    protected $signature = 'copiloto:eval
                            {--business=all : ID do business, "all" pra todos com gabarito}
                            {--resposta : Chama AiAdapter::responderChat e checa resposta_esperada_pattern}
                            {--top-k=10 : Top-K snippets pra calcular recall@K}
                            {--json : Output em JSON (default: tabela legível)}
                            {--persist : Grava agregados em copiloto_memoria_metricas}';

    protected $description = 'Roda 50 perguntas Larissa-style do gabarito e calcula Recall@3, Precision@3, MRR';

    public function handle(GabaritoEvaluator $evaluator): int
    {
        $businessOpt = (string) $this->option('business');
        $opts = [
            'include_resposta' => (bool) $this->option('resposta'),
            'top_k' => (int) $this->option('top-k'),
        ];

        $alvos = $this->resolverAlvos($businessOpt);
        $resultadosTodos = [];

        foreach ($alvos as $bizId) {
            $this->info(sprintf('Rodando gabarito — business=%s, top_k=%d, resposta=%s',
                $bizId === null ? 'NULL' : $bizId,
                $opts['top_k'],
                $opts['include_resposta'] ? 'sim' : 'não',
            ));

            $resultado = $evaluator->rodar($bizId, $opts);

            if (! isset($resultado['agregado'])) {
                $this->warn('  Sem perguntas no gabarito pra esse business — pulando.');
                continue;
            }

            $resultadosTodos[] = $resultado;
            $this->renderResultado($resultado);

            if ($this->option('persist')) {
                $this->persistir($resultado);
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($resultadosTodos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }

    protected function renderResultado(array $r): void
    {
        $a = $r['agregado'];
        $bizLabel = $r['business_id'] === null ? 'plataforma' : "biz {$r['business_id']}";

        $this->line('');
        $this->info("=== Resultado: {$bizLabel} ({$r['total_perguntas']} perguntas) ===");

        $recall3 = $a['recall@3_avg'];
        $precision3 = $a['precision@3_avg'];
        $mrr = $a['mrr_avg'];

        // Gates ADR 0050
        $gateRecall = $recall3 >= 0.80 ? '✓' : '✗';
        $gatePrec = $precision3 >= 0.60 ? '✓' : '✗';
        $gateMrr = $mrr >= 0.70 ? '✓' : '✗';

        $this->table(
            ['Métrica', 'Valor', 'Gate', 'OK?'],
            [
                ['Recall@3 (avg)',     number_format($recall3, 3),   '≥ 0.80', $gateRecall],
                ['Recall@10 (avg)',    number_format($a['recall@10_avg'], 3), '—', '—'],
                ['Precision@3 (avg)',  number_format($precision3, 3), '≥ 0.60', $gatePrec],
                ['MRR (avg)',          number_format($mrr, 3),        '≥ 0.70', $gateMrr],
                ['Latência avg',       $a['duration_ms_avg'] . ' ms', '—', '—'],
                ['Latência p95',       $a['duration_ms_p95'] . ' ms', '< 2000ms', $a['duration_ms_p95'] < 2000 ? '✓' : '✗'],
                ['Resposta match %',   $a['resposta_match_pct'] !== null ? $a['resposta_match_pct'] . '%' : 'n/a (use --resposta)', '≥ 80%', '—'],
            ]
        );

        $this->info('=== Por categoria ===');
        $rows = [];
        foreach ($r['por_categoria'] as $cat => $stats) {
            $rows[] = [
                $cat,
                $stats['count'],
                number_format($stats['recall@3_avg'], 3),
                number_format($stats['recall@10_avg'], 3),
                number_format($stats['precision@3_avg'], 3),
                number_format($stats['mrr_avg'], 3),
            ];
        }
        $this->table(['Categoria', 'N', 'R@3', 'R@10', 'P@3', 'MRR'], $rows);
    }

    protected function persistir(array $r): void
    {
        if (! isset($r['agregado'])) return;
        $a = $r['agregado'];

        DB::table('copiloto_memoria_metricas')->updateOrInsert(
            [
                'apurado_em' => now()->toDateString(),
                'business_id' => $r['business_id'],
            ],
            [
                'recall_at_3'        => $a['recall@3_avg'],
                'precision_at_3'     => $a['precision@3_avg'],
                'mrr'                => $a['mrr_avg'],
                'latencia_p95_ms'    => $a['duration_ms_p95'],
                'detalhes'           => json_encode([
                    'gabarito_run' => true,
                    'recall@10_avg' => $a['recall@10_avg'],
                    'duration_ms_avg' => $a['duration_ms_avg'],
                    'resposta_match_pct' => $a['resposta_match_pct'],
                    'por_categoria' => $r['por_categoria'],
                    'rodado_em' => $r['rodado_em'],
                ]),
                'updated_at'         => now(),
                'created_at'         => now(),
            ]
        );

        $this->info('  ✓ Gravado em copiloto_memoria_metricas');
    }

    protected function resolverAlvos(string $opt): array
    {
        if ($opt === 'plataforma') return [null];
        if (ctype_digit($opt)) return [(int) $opt];

        if ($opt === 'all') {
            // Pega businesses que têm pelo menos 1 pergunta no gabarito
            $bizComGab = DB::table('copiloto_memoria_gabarito')
                ->whereNotNull('business_id')
                ->distinct()
                ->pluck('business_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            return array_merge([null], $bizComGab); // null = perguntas universais
        }

        return [null];
    }
}
