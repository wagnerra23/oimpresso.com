<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\Jana\Services\Kb\KbAnswerService;
use Modules\Jana\Services\Ragas\JudgeUnavailableException;
use Modules\Jana\Services\Ragas\OllamaRagasJudge;
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
 *   php artisan jana:ragas-real-eval                            # tabela humana (juiz openai)
 *   php artisan jana:ragas-real-eval --json                     # JSON p/ runner/baseline
 *   php artisan jana:ragas-real-eval --sample-size=5 --json     # smoke barato (~$0.01)
 *   php artisan jana:ragas-real-eval --judge=local --sample-size=3  # juiz Ollama CT 100 (zero egress, US-COPI-137)
 *
 * O --judge=local (US-COPI-137) roteia SÓ o julgamento pro Ollama self-host do
 * CT 100 (OllamaRagasJudge, zero egress) — a síntese segue no OpenAI. É o mesmo
 * juiz local do eval online (JudgeTraceOnlineJob), aqui exercitado sobre o
 * pipeline real do gold-set (verificável sem esperar tráfego 5%).
 *
 * PISOS (US-COPI-136): o dono único é `thresholds_regressao` em
 * governance/jana-ragas-real-baseline.json — o comando LÊ de lá (fecha o follow-up
 * registrado na própria ADR 0318 §"v1 usa thresholds absolutos"). As flags de CLI só
 * sobrescrevem quando passadas explicitamente. Antes deste commit os pisos viviam em
 * DOIS lugares (default do signature + flags do Kernel.php) e o `thresholds_regressao`
 * do baseline era decorativo — lido por ninguém (grep: 1 hit, ele mesmo).
 *
 * Exit code:
 *   0 = gate pass (thresholds OK) OU skipped (sem infra — neutro, não bloqueia)
 *   1 = gate fail (faithfulness/relevancy/context_recall real abaixo do piso)
 *
 * O exit 1 aqui é ALERTA de cron (Kernel.php ->onFailure() loga ERROR no canal
 * copiloto-ai), NÃO gate de merge — este comando não roda em CI (o workflow
 * jana-ragas-gate.yml roda o `ci-eval` tautológico, e só o cita em comentário).
 *
 * @see Modules/Jana/Services/Kb/KbAnswerService.php
 * @see Modules/Jana/Services/Ragas/RagasJudgeService.php
 * @see memory/decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md
 * @see memory/decisions/0318-ragas-eval-real-mata-tautologia-ct100-staging.md
 */
class JanaRagasRealEvalCommand extends Command
{
    /** Mesmo gold-set canônico do ci-eval (51 perguntas, 6 buckets, sem PII). */
    protected const GOLD_SET_PATH = 'Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json';

    /** Dono único dos pisos (US-COPI-136) — `thresholds_regressao` mora aqui. */
    protected const BASELINE_PATH = 'governance/jana-ragas-real-baseline.json';

    /**
     * Fallback SÓ pra baseline ausente/ilegível (ex: checkout parcial). Iguais aos
     * valores versionados no baseline — se divergirem, o baseline vence.
     */
    protected const FALLBACK_THRESHOLDS = [
        'faithfulness' => 0.65,
        'answer_relevancy' => 0.75,
        'context_recall' => 0.36,
    ];

    /** Custo aprox por judge call gpt-4o-mini (2026-05). */
    protected const COST_PER_JUDGE_CALL_USD = 0.0004;

    /** Custo aprox por síntese KbAnswerAgent gpt-4o-mini (input ~2k + output ~300). */
    protected const COST_PER_SYNTH_CALL_USD = 0.0005;

    protected $signature = 'jana:ragas-real-eval
                            {--json : Output só JSON estruturado (sem tabela humana)}
                            {--sample-size=0 : Limita N perguntas (0 = gold-set completo)}
                            {--judge=openai : Juiz das métricas — openai (gpt-4o-mini) | local (Ollama CT 100, zero egress · US-COPI-137)}
                            {--threshold-faithfulness= : Piso faithfulness (default: thresholds_regressao do baseline)}
                            {--threshold-relevancy= : Piso answer_relevancy (default: thresholds_regressao do baseline)}
                            {--threshold-context-recall= : Piso context_recall (default: thresholds_regressao do baseline)}
                            {--topk=10 : Docs recuperados por pergunta (retrieval)}
                            {--max-citacoes=5 : Máx citações na síntese}';

    protected $description = 'RAGAS eval REAL (não-tautológico) — mede a saída do pipeline Jana (KbAnswerService) vs threshold';

    /** Procedência dos pisos (vai no report — auditável, não adivinhado). */
    protected string $thresholdsFonte = '';

    /**
     * Veredito do gate — função PURA (testável sem DB, sem LLM, sem corpus).
     *
     * Extraída pelo mesmo motivo do `HealthCheckCommand::allChecksOk` (bite-tests dos
     * sentinelas, 2026-06-20): sem isto nenhum teste PROVA que o piso morde, e um
     * refactor poderia silenciar o exit code com a suíte verde ("a suite mente").
     *
     * Métrica sem medida (null) NÃO é julgada — 0.0 fabricaria regressão falsa.
     *
     * @param  array<string, float|null>  $medidas   metrica => média medida
     * @param  array<string, float|null>  $pisos     metrica => piso (null = não julga)
     */
    public static function gateVerdict(array $medidas, array $pisos): bool
    {
        foreach ($pisos as $metrica => $piso) {
            if ($piso === null) {
                continue;
            }
            $medida = $medidas[$metrica] ?? null;
            if ($medida === null) {
                continue;
            }
            if ($medida < $piso) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve os pisos: baseline honesto (dono único) → CLI sobrescreve se passada.
     *
     * Fecha o follow-up da ADR 0318 §"v1 usa thresholds absolutos ... Follow-up: fazer
     * o comando ler jana-ragas-real-baseline.json". Antes, `thresholds_regressao` era
     * decorativo e os pisos reais viviam duplicados no signature + no Kernel.php.
     *
     * @return array<string, float|null>
     */
    protected function resolveThresholds(): array
    {
        $doBaseline = [];
        $fonte = 'fallback do comando (baseline ausente/ilegível)';

        try {
            $path = base_path(self::BASELINE_PATH);
            if (File::exists($path)) {
                $json = json_decode((string) File::get($path), true);
                $lidos = $json['thresholds_regressao'] ?? null;
                if (is_array($lidos)) {
                    foreach ($lidos as $k => $v) {
                        if (is_numeric($v)) {
                            $doBaseline[$k] = (float) $v;
                        }
                    }
                    $fonte = self::BASELINE_PATH.' (thresholds_regressao)';
                }
            }
        } catch (\Throwable $e) {
            $this->warn('Baseline ilegível — usando fallback do comando: '.$e->getMessage());
        }

        $cli = [
            'faithfulness' => $this->option('threshold-faithfulness'),
            'answer_relevancy' => $this->option('threshold-relevancy'),
            'context_recall' => $this->option('threshold-context-recall'),
        ];

        $pisos = [];
        $sobrescritas = [];
        foreach (self::FALLBACK_THRESHOLDS as $metrica => $fallback) {
            if ($cli[$metrica] !== null && is_numeric($cli[$metrica])) {
                $pisos[$metrica] = (float) $cli[$metrica];
                $sobrescritas[] = $metrica;
            } else {
                $pisos[$metrica] = $doBaseline[$metrica] ?? $fallback;
            }
        }

        $this->thresholdsFonte = $fonte.($sobrescritas ? ' · CLI sobrescreveu: '.implode(', ', $sobrescritas) : '');

        return $pisos;
    }

    public function handle(RagasJudgeService $judge, KbAnswerService $kb): int
    {
        $thresholds = $this->resolveThresholds();
        $sampleSize = (int) $this->option('sample-size');
        $topK = max(1, (int) $this->option('topk'));
        $maxCitacoes = max(1, (int) $this->option('max-citacoes'));

        // Juiz das métricas (US-COPI-137): openai (gpt-4o-mini) ou local (Ollama CT 100,
        // zero egress). A SÍNTESE (KbAnswerService) segue no OpenAI nos dois casos — o
        // --judge só troca QUEM avalia, não quem responde.
        $judgeTarget = (string) $this->option('judge');
        if (! in_array($judgeTarget, ['openai', 'local'], true)) {
            return $this->failHard("--judge inválido: {$judgeTarget} (use openai|local)");
        }
        $activeJudge = $judgeTarget === 'local' ? app(OllamaRagasJudge::class) : $judge;

        // ANTITAUTOLOGIA: sem chave de LLM, um "eval real" não existe (a SÍNTESE precisa
        // dela mesmo com --judge=local). NÃO caímos em mock — SKIP neutro (não mente).
        $apiKey = config('openai.api_key') ?: env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            return $this->emitSkip(
                'OPENAI_API_KEY ausente — a síntese do eval real exige LLM. Rode no CT 100 staging (infra real). NÃO caímos em mock (seria tautologia).',
                $thresholds,
            );
        }

        if ($activeJudge->isMockMode()) {
            // Proteção dura: mock injetado externamente tornaria o eval teatro.
            return $this->emitSkip(
                'RagasJudgeService está em mock — abortado (eval real não pode usar scores fixos).',
                $thresholds,
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
        $judgeFailed = 0;
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
            // Juiz local (Ollama) pode estar indisponível (sem modelo de chat / infra
            // down): PULA a pergunta (honesto) em vez de gravar 0.0 fabricado.
            try {
                $faith = $activeJudge->scoreFaithfulness($question, $answer, $context);
                $rel = $activeJudge->scoreAnswerRelevancy($question, $answer);
                $recall = $activeJudge->scoreContextRecall($question, $context, $groundTruth);
                $judgeCalls += 3;
            } catch (JudgeUnavailableException $e) {
                $judgeFailed++;
                $failures[] = [
                    'idx' => $i,
                    'q' => mb_substr($question, 0, 120),
                    'reason' => 'judge_failed — '.mb_substr($e->getMessage(), 0, 100),
                ];

                continue;
            }

            $faithScores[] = $faith;
            $relScores[] = $rel;
            $recallScores[] = $recall;

            // Diagnóstico POR PERGUNTA fica em faith/rel de propósito: o piso de
            // context_recall (US-COPI-136) é sobre a MÉDIA (o 0.3839 medido é média).
            // Aplicá-lo aqui marcaria a maioria das perguntas como falha e afundaria
            // n_passed/n_failed no trend por mudança de régua, não por regressão.
            if (! ($faith >= $thresholds['faithfulness'] && $rel >= $thresholds['answer_relevancy'])) {
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
                "Nenhuma pergunta avaliável (no_context={$noContext}, synth_failed={$synthFailed}, judge_failed={$judgeFailed}) — corpus/infra/juiz insuficiente. Não inventamos score.",
                $thresholds,
                extra: [
                    'judge' => $judgeTarget,
                    'n_no_context' => $noContext,
                    'n_synth_failed' => $synthFailed,
                    'n_judge_failed' => $judgeFailed,
                ],
            );
        }

        $faithAvg = array_sum($faithScores) / $nEval;
        $relAvg = array_sum($relScores) / $nEval;
        // null (não 0.0) quando não há recall medido: 0.0 fabricaria uma regressão
        // contra o piso. Sem medida → gateVerdict não julga a métrica.
        $recallAvg = $recallScores ? array_sum($recallScores) / count($recallScores) : null;
        $scoreAvg = ($faithAvg + $relAvg) / 2.0;

        $gatePass = self::gateVerdict([
            'faithfulness' => $faithAvg,
            'answer_relevancy' => $relAvg,
            'context_recall' => $recallAvg,
        ], $thresholds);
        // Juiz local (Ollama self-host) = custo ~0; só o openai cobra por judge call.
        // A síntese cobra nos dois casos (OpenAI).
        $judgeCostUsd = $judgeTarget === 'openai' ? $judgeCalls * self::COST_PER_JUDGE_CALL_USD : 0.0;
        $costUsd = round($judgeCostUsd + $synthCalls * self::COST_PER_SYNTH_CALL_USD, 4);

        $report = [
            'eval_kind' => 'real_pipeline',
            'gate_status' => $gatePass ? 'pass' : 'fail',
            'judge' => $judgeTarget,
            'score_avg' => round($scoreAvg, 4),
            'faithfulness_avg' => round($faithAvg, 4),
            'relevancy_avg' => round($relAvg, 4),
            'context_recall_avg' => $recallAvg === null ? null : round($recallAvg, 4),
            'n_questions' => count($questions),
            'n_evaluated' => $nEval,
            'n_no_context' => $noContext,
            'n_synth_failed' => $synthFailed,
            'n_judge_failed' => $judgeFailed,
            'n_passed' => $nEval - count(array_filter($failures, fn ($f) => isset($f['faithfulness']))),
            'n_failed' => count($failures),
            'failures' => array_slice($failures, 0, 10),
            'mode' => 'real',
            'cost_usd' => $costUsd,
            'ran_at' => now()->toIso8601String(),
            'thresholds' => $thresholds,
            'thresholds_fonte' => $this->thresholdsFonte,
        ];

        $this->persistReport($report);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderHumanTable($report);
        }

        return $gatePass ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Persiste o report da run em storage/app/governance/ragas-real-eval-latest.json —
     * contrato do transporte semanal (ct100-ragas-publish.sh lê este arquivo, faz merge
     * no trend e publica na órfã governance/ragas-real-trend; pattern nightly-floor
     * ADR 0279). Persistimos TAMBÉM os SKIPs: semana skipped é run INVÁLIDO no uptime
     * (honestidade — não publicar esconderia o downtime). Falha de escrita não derruba
     * o eval (o report já saiu no stdout/log agendado — fallback do transporte).
     */
    protected function persistReport(array $report): void
    {
        try {
            $dir = storage_path('app/governance');
            File::ensureDirectoryExists($dir);
            File::put($dir.'/ragas-real-eval-latest.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            $this->warn('persistReport falhou (transporte cai no fallback via log): '.$e->getMessage());
        }
    }

    /** Render tabela amigável (modo dev local, sem --json). */
    protected function renderHumanTable(array $report): void
    {
        $icon = $report['gate_status'] === 'pass' ? '[PASS]' : '[FAIL]';
        $this->line("{$icon} Jana RAGAS REAL-eval — pipeline real (custo: \${$report['cost_usd']})");
        $this->newLine();
        $linha = function (string $metrica, string $chaveAvg) use ($report): array {
            $valor = $report[$chaveAvg];
            $piso = $report['thresholds'][$metrica] ?? null;

            if ($valor === null) {
                return [$metrica, '—', $piso === null ? '—' : sprintf('%.2f', $piso), 'NÃO MEDIDO'];
            }

            return [
                $metrica,
                sprintf('%.3f', $valor),
                $piso === null ? '—' : sprintf('%.2f', $piso),
                $piso === null ? '—' : ($valor >= $piso ? 'OK' : 'FAIL'),
            ];
        };

        $this->table(
            ['Métrica', 'Score médio', 'Piso', 'Status'],
            [
                $linha('faithfulness', 'faithfulness_avg'),
                $linha('answer_relevancy', 'relevancy_avg'),
                $linha('context_recall', 'context_recall_avg'),
            ]
        );
        $this->line("Pisos: {$report['thresholds_fonte']}");
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
    protected function emitSkip(string $reason, array $thresholds, array $extra = []): int
    {
        $report = array_merge([
            'eval_kind' => 'real_pipeline',
            'gate_status' => 'skipped',
            'reason' => $reason,
            'score_avg' => null,
            'faithfulness_avg' => null,
            'relevancy_avg' => null,
            'context_recall_avg' => null,
            'mode' => 'real',
            'cost_usd' => 0.0,
            'ran_at' => now()->toIso8601String(),
            'thresholds' => $thresholds,
            'thresholds_fonte' => $this->thresholdsFonte,
        ], $extra);

        $this->persistReport($report);

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
        $report = [
            'eval_kind' => 'real_pipeline',
            'gate_status' => 'fail',
            'error' => $reason,
            'ran_at' => now()->toIso8601String(),
        ];

        $this->persistReport($report);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->error("[FAIL] {$reason}");
        }

        return Command::FAILURE;
    }
}
