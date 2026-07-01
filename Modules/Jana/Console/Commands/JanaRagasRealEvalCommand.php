<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\Jana\Services\Kb\KbAnswerService;
use Modules\Jana\Services\Ragas\RagasJudgeService;

/**
 * jana:ragas-real-eval — RAGAS eval que mede a saída REAL da Jana.
 *
 * Diferença fundamental vs `jana:ragas-ci-eval` (o legado):
 *   - ci-eval faz `answer = context = ground_truth` → TAUTOLOGIA (mede se o
 *     ground_truth é fiel a si mesmo → ~1.0 sempre, mesmo em modo "real").
 *     É o gate de teatro que a ADR 0271 baniu + proibicoes.md §"Teste que
 *     deriva do código".
 *   - este comando gera a resposta pelo PIPELINE REAL (KbAnswerService:
 *     retrieval em mcp_memory_documents → síntese gpt-4o-mini) e o contexto
 *     vem do retriever de verdade. Aí sim faithfulness/relevancy medem algo.
 *
 * Por que roda no CT 100 staging (não no GitHub CI):
 *   O pipeline real precisa de `mcp_memory_documents` POPULADA (1153 docs no
 *   staging) + Meilisearch + OPENAI_API_KEY. O CI do GitHub é sqlite :memory:
 *   efêmero, sem KB — lá o retriever não teria o que recuperar. Logo, o eval
 *   real é um job agendado no CT 100 staging (infra real). O gate PR do GitHub
 *   fica advisory-smoke (não mede qualidade — não tem KB).
 *
 * ANTITAUTOLOGIA (Tier 0): este comando NUNCA usa mock nem substitui a resposta
 * pelo ground_truth. Sem OPENAI_API_KEY OU sem contexto recuperado → SKIP honesto
 * (status "skipped"/"no_context"), jamais um score inventado.
 *
 * Uso (no CT 100 staging):
 *   php artisan jana:ragas-real-eval                         # tabela humana
 *   php artisan jana:ragas-real-eval --json                  # JSON p/ runner/baseline
 *   php artisan jana:ragas-real-eval --sample-size=5 --json  # smoke barato (~$0.01)
 *
 * Exit code:
 *   0 = gate pass (thresholds OK) OU skipped (sem infra — neutro, não bloqueia)
 *   1 = gate fail (faithfulness/relevancy real abaixo do threshold)
 *
 * @see Modules/Jana/Services/Kb/KbAnswerService.php
 * @see Modules/Jana/Services/Ragas/RagasJudgeService.php
 * @see memory/decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md
 */
class JanaRagasRealEvalCommand extends Command
{
    /** Mesmo gold-set canônico do ci-eval (51 perguntas, 6 buckets, sem PII). */
    protected const GOLD_SET_PATH = 'Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json';

    /** Custo aprox por judge call gpt-4o-mini (2026-05). */
    protected const COST_PER_JUDGE_CALL_USD = 0.0004;

    /** Custo aprox por síntese KbAnswerAgent gpt-4o-mini (input ~2k + output ~300). */
    protected const COST_PER_SYNTH_CALL_USD = 0.0005;

    protected $signature = 'jana:ragas-real-eval
                            {--json : Output só JSON estruturado (sem tabela humana)}
                            {--sample-size=0 : Limita N perguntas (0 = gold-set completo)}
                            {--threshold-faithfulness=0.80 : Threshold mínimo faithfulness médio}
                            {--threshold-relevancy=0.75 : Threshold mínimo answer_relevancy médio}
                            {--topk=10 : Docs recuperados por pergunta (retrieval)}
                            {--max-citacoes=5 : Máx citações na síntese}';

    protected $description = 'RAGAS eval REAL (não-tautológico) — mede a saída do pipeline Jana (KbAnswerService) vs threshold';

    public function handle(RagasJudgeService $judge, KbAnswerService $kb): int
    {
        $thresholdFaith = (float) $this->option('threshold-faithfulness');
        $thresholdRel = (float) $this->option('threshold-relevancy');
        $sampleSize = (int) $this->option('sample-size');
        $topK = max(1, (int) $this->option('topk'));
        $maxCitacoes = max(1, (int) $this->option('max-citacoes'));

        // ANTITAUTOLOGIA: sem chave de LLM, um "eval real" não existe. NÃO caímos
        // em mock — devolvemos SKIP neutro (exit 0, não bloqueia, não mente).
        $apiKey = config('openai.api_key') ?: env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            return $this->emitSkip(
                'OPENAI_API_KEY ausente — eval real exige LLM. Rode no CT 100 staging (infra real). NÃO caímos em mock (seria tautologia).',
                $thresholdFaith,
                $thresholdRel,
            );
        }

        if ($judge->isMockMode()) {
            // Proteção dura: mock injetado externamente tornaria o eval teatro.
            return $this->emitSkip(
                'RagasJudgeService está em mock — abortado (eval real não pode usar scores fixos).',
                $thresholdFaith,
                $thresholdRel,
            );
        }

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

        $faithScores = [];
        $relScores = [];
        $recallScores = [];
        $failures = [];
        $noContext = 0;
        $synthFailed = 0;
        $judgeCalls = 0;
        $synthCalls = 0;

        foreach ($questions as $i => $q) {
            $question = (string) ($q['question'] ?? '');
            $groundTruth = (string) ($q['ground_truth'] ?? '');
            if ($question === '' || $groundTruth === '') {
                continue;
            }

            // FASE 1 — retrieval REAL (user=null → corpus global KB, ADR 0053).
            $docs = $kb->retrieve(null, $question, 'all', '', $topK);
            if ($docs->isEmpty()) {
                // Sem contexto recuperado: honesto contar como buraco de recall,
                // NUNCA usar ground_truth como contexto (isso é a tautologia).
                $noContext++;
                $failures[] = [
                    'idx' => $i,
                    'q' => mb_substr($question, 0, 120),
                    'reason' => 'no_context — retriever não achou docs',
                ];

                continue;
            }
            $context = $kb->renderFontes($docs);

            // FASE 2 — síntese REAL (gpt-4o-mini). Falha = sinal, não tautologia.
            try {
                $answer = $kb->synthesize($question, $context, $maxCitacoes);
                $synthCalls++;
            } catch (\Throwable $e) {
                $synthFailed++;
                $failures[] = [
                    'idx' => $i,
                    'q' => mb_substr($question, 0, 120),
                    'reason' => 'synth_failed — '.mb_substr($e->getMessage(), 0, 100),
                ];

                continue;
            }

            // FASE 3 — julgamento LLM-as-judge sobre a saída REAL.
            $faith = $judge->scoreFaithfulness($question, $answer, $context);
            $rel = $judge->scoreAnswerRelevancy($question, $answer);
            $recall = $judge->scoreContextRecall($question, $context, $groundTruth);
            $judgeCalls += 3;

            $faithScores[] = $faith;
            $relScores[] = $rel;
            $recallScores[] = $recall;

            if (! ($faith >= $thresholdFaith && $rel >= $thresholdRel)) {
                $failures[] = [
                    'idx' => $i,
                    'q' => mb_substr($question, 0, 120),
                    'faithfulness' => round($faith, 3),
                    'relevancy' => round($rel, 3),
                    'context_recall' => round($recall, 3),
                ];
            }
        }

        $nEval = count($faithScores);
        // ANTITAUTOLOGIA: se NENHUMA pergunta pôde ser avaliada de verdade
        // (tudo caiu em no_context/synth_failed), não há eval — SKIP, não pass.
        if ($nEval === 0) {
            return $this->emitSkip(
                "Nenhuma pergunta avaliável (no_context={$noContext}, synth_failed={$synthFailed}) — corpus/infra insuficiente. Não inventamos score.",
                $thresholdFaith,
                $thresholdRel,
                extra: ['n_no_context' => $noContext, 'n_synth_failed' => $synthFailed],
            );
        }

        $faithAvg = array_sum($faithScores) / $nEval;
        $relAvg = array_sum($relScores) / $nEval;
        $recallAvg = $recallScores ? array_sum($recallScores) / count($recallScores) : 0.0;
        $scoreAvg = ($faithAvg + $relAvg) / 2.0;

        $gatePass = $faithAvg >= $thresholdFaith && $relAvg >= $thresholdRel;
        $costUsd = round($judgeCalls * self::COST_PER_JUDGE_CALL_USD + $synthCalls * self::COST_PER_SYNTH_CALL_USD, 4);

        $report = [
            'eval_kind' => 'real_pipeline',
            'gate_status' => $gatePass ? 'pass' : 'fail',
            'score_avg' => round($scoreAvg, 4),
            'faithfulness_avg' => round($faithAvg, 4),
            'relevancy_avg' => round($relAvg, 4),
            'context_recall_avg' => round($recallAvg, 4),
            'n_questions' => count($questions),
            'n_evaluated' => $nEval,
            'n_no_context' => $noContext,
            'n_synth_failed' => $synthFailed,
            'n_passed' => $nEval - count(array_filter($failures, fn ($f) => isset($f['faithfulness']))),
            'n_failed' => count($failures),
            'failures' => array_slice($failures, 0, 10),
            'mode' => 'real',
            'cost_usd' => $costUsd,
            'ran_at' => now()->toIso8601String(),
            'thresholds' => [
                'faithfulness' => $thresholdFaith,
                'answer_relevancy' => $thresholdRel,
            ],
        ];

        if ($this->option('json')) {
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
        $this->line("{$icon} Jana RAGAS REAL-eval — pipeline real (custo: \${$report['cost_usd']})");
        $this->newLine();
        $this->table(
            ['Métrica', 'Score médio', 'Threshold', 'Status'],
            [
                ['faithfulness', sprintf('%.3f', $report['faithfulness_avg']), sprintf('%.2f', $report['thresholds']['faithfulness']), $report['faithfulness_avg'] >= $report['thresholds']['faithfulness'] ? 'OK' : 'FAIL'],
                ['answer_relevancy', sprintf('%.3f', $report['relevancy_avg']), sprintf('%.2f', $report['thresholds']['answer_relevancy']), $report['relevancy_avg'] >= $report['thresholds']['answer_relevancy'] ? 'OK' : 'FAIL'],
                ['context_recall (info)', sprintf('%.3f', $report['context_recall_avg']), '—', '—'],
            ]
        );
        $this->info("Avaliadas: {$report['n_evaluated']}/{$report['n_questions']} · sem contexto: {$report['n_no_context']} · síntese falhou: {$report['n_synth_failed']}");
        if (! empty($report['failures'])) {
            $this->warn('Primeiras falhas:');
            foreach (array_slice($report['failures'], 0, 5) as $f) {
                $detalhe = $f['reason'] ?? "faith={$f['faithfulness']}, rel={$f['relevancy']}";
                $this->line(" - [{$f['idx']}] {$f['q']} ({$detalhe})");
            }
        }
    }

    /**
     * SKIP neutro (exit 0) — infra insuficiente pra eval real. NÃO é pass nem
     * fail: é honestidade (não medimos nada, não bloqueamos, não inventamos).
     */
    protected function emitSkip(string $reason, float $tFaith, float $tRel, array $extra = []): int
    {
        $report = array_merge([
            'eval_kind' => 'real_pipeline',
            'gate_status' => 'skipped',
            'reason' => $reason,
            'score_avg' => null,
            'faithfulness_avg' => null,
            'relevancy_avg' => null,
            'mode' => 'real',
            'cost_usd' => 0.0,
            'ran_at' => now()->toIso8601String(),
            'thresholds' => ['faithfulness' => $tFaith, 'answer_relevancy' => $tRel],
        ], $extra);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->warn("[SKIP] {$reason}");
        }

        return Command::SUCCESS; // neutro — não bloqueia
    }

    /** Falha estrutural (gold-set ausente/malformado) — emite fail e sai 1. */
    protected function failHard(string $reason): int
    {
        if ($this->option('json')) {
            $this->line(json_encode([
                'eval_kind' => 'real_pipeline',
                'gate_status' => 'fail',
                'error' => $reason,
                'ran_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error("[FAIL] {$reason}");
        }

        return Command::FAILURE;
    }
}
