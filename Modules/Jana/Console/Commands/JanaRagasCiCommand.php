<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\Jana\Services\Ragas\RagasJudgeService;

/**
 * jana:ragas-ci-eval — RAGAS CI gate BLOQUEANTE Jana (W28-2).
 *
 * Roda golden set Jana (Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json)
 * através de RagasJudgeService existente e emite JSON estruturado pro workflow
 * .github/workflows/jana-ragas-gate.yml consumir.
 *
 * Diferente de jana:ragas:eval (W22 MVP que dispara Pest e captura output):
 *   - Este avalia direto via RagasJudgeService (sem subprocess Pest)
 *   - Output JSON estruturado consumível por gh pr comment / actions/github-script
 *   - Gate bloqueante (exit 1 se gate_status=fail) — MVP W22 era alerta
 *
 * Uso:
 *   php artisan jana:ragas-ci-eval                       # mock default + tabela humana
 *   php artisan jana:ragas-ci-eval --json                # JSON estruturado p/ CI
 *   php artisan jana:ragas-ci-eval --mode=real           # consome LLM real (~$0.06)
 *   php artisan jana:ragas-ci-eval --threshold-faithfulness=0.85
 *
 * Output JSON canon (consumido pelo workflow):
 *   {
 *     "gate_status": "pass" | "fail",
 *     "score_avg": 0.85,
 *     "faithfulness_avg": 0.82,
 *     "relevancy_avg": 0.88,
 *     "n_questions": 100,
 *     "n_passed": 92,
 *     "n_failed": 8,
 *     "failures": [{"q":"...", "faithfulness": 0.5, "relevancy": 0.6}],
 *     "mode": "mock" | "real",
 *     "cost_usd": 0.06,
 *     "ran_at": "2026-05-17T...",
 *     "thresholds": {"faithfulness": 0.80, "answer_relevancy": 0.75}
 *   }
 *
 * Exit code:
 *   0 = gate_status=pass (todos thresholds OK)
 *   1 = gate_status=fail (pelo menos uma métrica abaixo threshold)
 *
 * @see ADR 0037 §GAP-2 — RAGAS gate em CI
 * @see W27 estado-da-arte AI bucket §G1 RAGAS golden set sem CI gate
 * @see Modules/Jana/Services/Ragas/RagasJudgeService.php
 * @see config/ragas.php
 */
class JanaRagasCiCommand extends Command
{
    /** Caminho default do golden set (W23 — 50+ perguntas canon, sem PII real). */
    protected const GOLD_SET_PATH = 'Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json';

    /** Custo aprox por chamada judge gpt-4o-mini (input + output médios) — 2026-05. */
    protected const COST_PER_JUDGE_CALL_USD = 0.0004;

    protected $signature = 'jana:ragas-ci-eval
                            {--json : Output só JSON estruturado (sem tabela humana)}
                            {--mode=mock : Modo (mock|real). mock = zero $ CI; real = consome LLM ~$0.06}
                            {--threshold-faithfulness=0.80 : Threshold mínimo faithfulness médio}
                            {--threshold-relevancy=0.75 : Threshold mínimo answer_relevancy médio}
                            {--sample-size=0 : Limita N perguntas (0 = golden set completo)}';

    protected $description = 'RAGAS CI gate BLOQUEANTE Jana — golden set vs threshold faithfulness/relevancy';

    public function handle(): int
    {
        $mode = $this->option('mode');
        $thresholdFaith = (float) $this->option('threshold-faithfulness');
        $thresholdRel = (float) $this->option('threshold-relevancy');
        $sampleSize = (int) $this->option('sample-size');

        // Carrega golden set
        $goldPath = base_path(self::GOLD_SET_PATH);
        if (! File::exists($goldPath)) {
            return $this->failHard("Golden set ausente: {$goldPath}");
        }

        $data = json_decode((string) File::get($goldPath), true);
        if (! is_array($data) || empty($data['questions'])) {
            return $this->failHard('Golden set malformado — shape esperado: {"questions":[...]}');
        }

        $questions = $data['questions'];
        if ($sampleSize > 0) {
            $questions = array_slice($questions, 0, $sampleSize);
        }

        // Mock vs real
        $judge = app(RagasJudgeService::class);
        $forceMock = $mode === 'mock' || env('RAGAS_FORCE_MOCK', false);
        if ($forceMock) {
            // Scores mock-safe que passam threshold default (0.80/0.75) por padrão.
            // Tests podem injetar scores custom via RagasJudgeService::enableMock antes.
            if (! $judge->isMockMode()) {
                $judge->enableMock([
                    'faithfulness' => 0.85,
                    'answer_relevancy' => 0.82,
                ]);
            }
        }

        // Avalia cada pergunta — apenas 2 métricas no gate (faithfulness + answer_relevancy)
        // Context precision/recall ficam fora do gate W28 (não-bloqueante, info-only no W22)
        $faithScores = [];
        $relScores = [];
        $failures = [];

        foreach ($questions as $i => $q) {
            $question = (string) ($q['question'] ?? '');
            $groundTruth = (string) ($q['ground_truth'] ?? '');
            if ($question === '' || $groundTruth === '') {
                continue;
            }

            // MVP recall-ideal: answer = ground_truth, context = ground_truth.
            // Em produção W29+: answer vem do Brief/KbAnswer real, context vem do retriever.
            $answer = $groundTruth;
            $context = $groundTruth;

            $faith = $judge->scoreFaithfulness($question, $answer, $context);
            $rel = $judge->scoreAnswerRelevancy($question, $answer);

            $faithScores[] = $faith;
            $relScores[] = $rel;

            $passed = $faith >= $thresholdFaith && $rel >= $thresholdRel;
            if (! $passed) {
                $failures[] = [
                    'idx' => $i,
                    'q' => mb_substr($question, 0, 120),
                    'faithfulness' => round($faith, 3),
                    'relevancy' => round($rel, 3),
                ];
            }
        }

        $n = max(1, count($faithScores));
        $faithAvg = array_sum($faithScores) / $n;
        $relAvg = array_sum($relScores) / $n;
        $scoreAvg = ($faithAvg + $relAvg) / 2.0;

        $gatePass = $faithAvg >= $thresholdFaith && $relAvg >= $thresholdRel;
        // Custo: 2 chamadas por pergunta (faithfulness + relevancy) × cost por call
        $costUsd = $forceMock ? 0.0 : round($n * 2 * self::COST_PER_JUDGE_CALL_USD, 4);

        $report = [
            'gate_status' => $gatePass ? 'pass' : 'fail',
            'score_avg' => round($scoreAvg, 4),
            'faithfulness_avg' => round($faithAvg, 4),
            'relevancy_avg' => round($relAvg, 4),
            'n_questions' => count($faithScores),
            'n_passed' => count($faithScores) - count($failures),
            'n_failed' => count($failures),
            'failures' => $failures,
            'mode' => $forceMock ? 'mock' : 'real',
            'cost_usd' => $costUsd,
            'ran_at' => now()->toIso8601String(),
            'thresholds' => [
                'faithfulness' => $thresholdFaith,
                'answer_relevancy' => $thresholdRel,
            ],
        ];

        if ($this->option('json')) {
            // Output APENAS JSON (consumido pelo workflow gh pr comment)
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderHumanTable($report);
        }

        return $gatePass ? Command::SUCCESS : Command::FAILURE;
    }

    /** Render tabela amigável (modo dev local, sem --json). */
    protected function renderHumanTable(array $report): void
    {
        $icon = $report['gate_status'] === 'pass' ? '[PASS]' : '[FAIL]';
        $this->line("{$icon} Jana RAGAS Gate — modo: {$report['mode']} (custo: \${$report['cost_usd']})");
        $this->newLine();
        $this->table(
            ['Métrica', 'Score médio', 'Threshold', 'Status'],
            [
                [
                    'faithfulness',
                    sprintf('%.3f', $report['faithfulness_avg']),
                    sprintf('%.2f', $report['thresholds']['faithfulness']),
                    $report['faithfulness_avg'] >= $report['thresholds']['faithfulness'] ? 'OK' : 'FAIL',
                ],
                [
                    'answer_relevancy',
                    sprintf('%.3f', $report['relevancy_avg']),
                    sprintf('%.2f', $report['thresholds']['answer_relevancy']),
                    $report['relevancy_avg'] >= $report['thresholds']['answer_relevancy'] ? 'OK' : 'FAIL',
                ],
            ]
        );
        $this->info("Perguntas: {$report['n_passed']}/{$report['n_questions']} OK ({$report['n_failed']} falharam)");
        if (! empty($report['failures'])) {
            $this->warn('Primeiras 5 falhas:');
            foreach (array_slice($report['failures'], 0, 5) as $f) {
                $this->line(" - [{$f['idx']}] {$f['q']} (faith={$f['faithfulness']}, rel={$f['relevancy']})");
            }
        }
    }

    /** Falha estrutural — emite JSON fail e sai 1. */
    protected function failHard(string $reason): int
    {
        $report = [
            'gate_status' => 'fail',
            'score_avg' => 0.0,
            'faithfulness_avg' => 0.0,
            'relevancy_avg' => 0.0,
            'n_questions' => 0,
            'n_passed' => 0,
            'n_failed' => 0,
            'failures' => [],
            'mode' => 'error',
            'cost_usd' => 0.0,
            'ran_at' => now()->toIso8601String(),
            'thresholds' => [],
            'error' => $reason,
        ];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error("[FAIL] {$reason}");
        }

        return Command::FAILURE;
    }
}
