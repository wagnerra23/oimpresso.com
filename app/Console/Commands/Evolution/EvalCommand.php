<?php

declare(strict_types=1);

namespace App\Console\Commands\Evolution;

use App\Models\Evolution\EvalRun;
use App\Models\Evolution\Evaluation;
use App\Services\Evolution\Eval\GoldenSetRunner;
use Illuminate\Console\Command;

/**
 * evolution:eval — roda golden set + LLM-as-judge (Opus 4.5) e devolve score.
 *
 * Sem ANTHROPIC_API_KEY: cai pra heurística offline (presença dos termos da rubrica).
 *
 * @see memory/requisitos/EvolutionAgent/SPEC.md US-EVOL-004
 * @see memory/requisitos/EvolutionAgent/adr/tech/0002-eval-llm-as-judge-em-ci.md
 */
class EvalCommand extends Command
{
    protected $signature = 'evolution:eval
                            {--baseline=memory/evolution/baseline.json : Caminho do baseline (anti-regressão)}
                            {--update-baseline : Sobrescreve baseline com o resultado deste run}
                            {--json : Saída JSON}';

    protected $description = 'Roda golden set + LLM-as-judge e devolve score (gates regressão >5%)';

    public function handle(): int
    {
        $json = (bool) $this->option('json');

        $runner = new GoldenSetRunner;
        $report = $runner->run();

        if (isset($report['error'])) {
            $this->error($report['error']);

            return self::FAILURE;
        }

        $this->persistRun($report);

        if ($json) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Score médio: %.2f/5 (judge: %s · %d casos)',
            $report['score_avg'],
            $report['judge_model'] ?? '?',
            $report['count']
        ));
        $this->newLine();

        foreach ($report['results'] as $r) {
            $this->line(sprintf(
                '%s · %.2f · %s',
                $r['id'],
                $r['score'],
                mb_substr((string) $r['pergunta'], 0, 60)
            ));
        }

        $baselinePath = base_path((string) $this->option('baseline'));

        if ((bool) $this->option('update-baseline')) {
            @mkdir(dirname($baselinePath), 0775, true);
            file_put_contents(
                $baselinePath,
                json_encode(['score_avg' => $report['score_avg']], JSON_PRETTY_PRINT)
            );
            $this->info('Baseline atualizado: '.$baselinePath);

            return self::SUCCESS;
        }

        if (is_file($baselinePath)) {
            $baseline = json_decode((string) file_get_contents($baselinePath), true);
            $baselineScore = (float) ($baseline['score_avg'] ?? 0);
            $delta = $report['score_avg'] - $baselineScore;

            $this->newLine();
            $this->line(sprintf('Baseline: %.2f · Δ = %+0.2f', $baselineScore, $delta));

            if ($delta < -0.25) {
                $this->error('Regressão >0.25 vs baseline — falhando CI.');

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function persistRun(array $report): void
    {
        try {
            $evaluation = Evaluation::query()->firstOrCreate(
                ['name' => 'golden_set_v1'],
                [
                    'golden_set_json' => $report['results'] ?? [],
                    'judge_model' => $report['judge_model'] ?? 'unknown',
                ]
            );

            EvalRun::query()->create([
                'evaluation_id' => $evaluation->id,
                'agent_version' => 'phase-1b',
                'score_avg' => $report['score_avg'],
                'results_json' => $report['results'] ?? [],
            ]);
        } catch (\Throwable $e) {
            // Persistência é opcional (DB pode estar offline).
        }
    }
}
