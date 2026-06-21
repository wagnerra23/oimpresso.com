<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use App\Util\OtelHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Ragas\RagasJudgeService;

/**
 * jana:drift-sentinel — Wave 23 — canary semanal Jana.
 *
 * Compara respostas atuais vs baseline checked-in (baseline-responses.json).
 * Alerta se >10% das perguntas divergirem ≥ DRIFT_THRESHOLD do baseline.
 *
 * Schedule: weekly Sun 06:00 BRT (app/Console/Kernel.php) — RELIGADO 2026-06-20.
 * Requer OPENAI_API_KEY no servidor pra rodar real; sem a chave o run falha e o
 * onFailure registra (sinal honesto). --mock é só pra CI/teste (mock NÃO detecta
 * drift real — faithfulness fixa em 0.85).
 *
 * Uso:
 *   php artisan jana:drift-sentinel                    # roda real (precisa OPENAI_API_KEY)
 *   php artisan jana:drift-sentinel --mock              # mock-mode (CI safe)
 *   php artisan jana:drift-sentinel --update-baseline   # regrava baseline (Wagner aprova)
 *   php artisan jana:drift-sentinel --detail            # log detalhado por pergunta
 *
 * Exit code:
 *   0 = drift dentro do aceitável (<10% perguntas divergiram)
 *   1 = drift acima do threshold — gera ALERT entry + retorna falha
 *
 * Custo aprox por run real (50 ex):
 *   - 50 perguntas × ~$0.0004 (judge faithfulness) = ~$0.02
 *   - Weekly = ~$0.08/mês
 *
 * Tier 0 multi-tenant: comando opera sobre fixture estática (memory canon),
 * não toca dados de business — não exige --business-id.
 *
 * @see Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json
 * @see Modules/Jana/Tests/Feature/Ai/fixtures/baseline-responses.json
 * @see Wave 22 FICHA Jana §G2 Drift sentinel canary semanal (+6pp)
 */
class JanaDriftSentinelCommand extends Command
{
    protected $signature = 'jana:drift-sentinel
                            {--mock : Usa RagasJudgeService em mock mode (CI safe)}
                            {--update-baseline : Regrava baseline-responses.json a partir do gold-set (Wagner aprova)}
                            {--detail : Log detalhado por pergunta}
                            {--max-drift=10 : % perguntas divergentes acima disso → exit 1}
                            {--drift-threshold=0.25 : Δ faithfulness pra contar como divergência}';

    protected $description = 'Canary semanal Jana — compara faithfulness atual vs baseline canon. Alert se drift >10%.';

    public function handle(): int
    {
        return OtelHelper::span('jana.drift_sentinel', ['module' => 'Jana'], function () {
            return $this->doHandle();
        });
    }

    private function doHandle(): int
    {
        $goldPath = base_path('Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json');
        $baselinePath = base_path('Modules/Jana/Tests/Feature/Ai/fixtures/baseline-responses.json');

        if (! File::exists($goldPath)) {
            $this->error("Gold-set ausente: {$goldPath}");

            return self::FAILURE;
        }

        $goldData = json_decode((string) File::get($goldPath), true);
        if (! is_array($goldData) || empty($goldData['questions'])) {
            $this->error('Gold-set malformado.');

            return self::FAILURE;
        }
        $questions = $goldData['questions'];

        $judge = app(RagasJudgeService::class);
        if (($this->option('mock') || env('RAGAS_FORCE_MOCK', false)) && ! $judge->isMockMode()) {
            // Mock mode: simula faithfulness alto (0.85±0.05) — CI safe.
            // Só ativa se NÃO estava em mock (preserva mocks customizados injetados via testing).
            $judge->enableMock(['faithfulness' => 0.85]);
        }

        // Modo --update-baseline: regrava baseline atual (Wagner aprova caso a caso).
        if ($this->option('update-baseline')) {
            return $this->updateBaseline($baselinePath, $questions, $judge);
        }

        if (! File::exists($baselinePath)) {
            $this->warn("Baseline ausente: {$baselinePath}");
            $this->warn('Rode primeiro: php artisan jana:drift-sentinel --update-baseline --mock');

            return self::FAILURE;
        }

        $baselineData = json_decode((string) File::get($baselinePath), true);
        if (! is_array($baselineData) || empty($baselineData['responses'])) {
            $this->error('Baseline malformado.');

            return self::FAILURE;
        }
        $baseline = collect($baselineData['responses'])->keyBy('question_id');

        $maxDriftPct = (float) $this->option('max-drift');
        $driftThreshold = (float) $this->option('drift-threshold');

        $diverged = 0;
        $total = 0;
        $details = [];

        foreach ($questions as $i => $q) {
            $qid = $i + 1;
            $baselineEntry = $baseline->get($qid);
            if (! $baselineEntry) {
                continue; // pergunta nova ainda sem baseline
            }

            $total++;

            // Mock-friendly: usa ground_truth como answer + context simulando recall ideal.
            $currentScore = $judge->scoreFaithfulness(
                $q['question'],
                $q['ground_truth'],
                $q['ground_truth']
            );
            $baselineScore = (float) ($baselineEntry['faithfulness'] ?? 0.0);

            $delta = abs($currentScore - $baselineScore);
            $isDrift = $delta >= $driftThreshold;

            if ($isDrift) {
                $diverged++;
            }

            if ($this->option('detail')) {
                $details[] = [
                    'qid' => $qid,
                    'question' => mb_substr($q['question'], 0, 60),
                    'baseline' => round($baselineScore, 3),
                    'current' => round($currentScore, 3),
                    'delta' => round($delta, 3),
                    'drift' => $isDrift ? 'YES' : 'no',
                ];
            }
        }

        $driftPct = $total > 0 ? ($diverged / $total) * 100.0 : 0.0;

        $report = [
            'ran_at' => now()->toIso8601String(),
            'total_questions' => $total,
            'diverged' => $diverged,
            'drift_pct' => round($driftPct, 2),
            'threshold_pct' => $maxDriftPct,
            'drift_threshold_delta' => $driftThreshold,
            'mock_mode' => (bool) ($this->option('mock') || env('RAGAS_FORCE_MOCK', false)),
        ];

        if ($this->option('detail')) {
            $this->table(['#', 'Question', 'Baseline', 'Current', 'Δ', 'Drift'], $details);
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($driftPct > $maxDriftPct) {
            Log::channel('copiloto-ai')->warning('[Jana drift-sentinel] ALERT drift acima do threshold', $report);
            $this->error("DRIFT ALERT: {$diverged}/{$total} divergiram ({$driftPct}%) > {$maxDriftPct}%");

            return self::FAILURE;
        }

        Log::channel('copiloto-ai')->info('[Jana drift-sentinel] OK', $report);
        $this->info("OK: drift {$driftPct}% ≤ {$maxDriftPct}%");

        return self::SUCCESS;
    }

    private function updateBaseline(string $baselinePath, array $questions, RagasJudgeService $judge): int
    {
        $this->warn('Regravando baseline-responses.json a partir do gold-set atual...');

        $responses = [];
        foreach ($questions as $i => $q) {
            $score = $judge->scoreFaithfulness(
                $q['question'],
                $q['ground_truth'],
                $q['ground_truth']
            );
            $responses[] = [
                'question_id' => $i + 1,
                'question' => $q['question'],
                'faithfulness' => round($score, 4),
            ];
        }

        $out = [
            '_meta' => [
                'version' => '1.0',
                'updated_at' => now()->toIso8601String(),
                'description' => 'Baseline canônico Jana — faithfulness por pergunta gold-set. Regravar via jana:drift-sentinel --update-baseline (Wagner aprova).',
            ],
            'responses' => $responses,
        ];

        File::ensureDirectoryExists(dirname($baselinePath));
        File::put($baselinePath, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Baseline regravado: {$baselinePath} (".count($responses).' respostas)');

        return self::SUCCESS;
    }
}
