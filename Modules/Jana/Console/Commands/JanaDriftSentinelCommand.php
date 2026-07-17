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
 * ⚠️⚠️ TAUTOLÓGICO — NÃO detecta drift REAL da Jana (provado 2026-07-17, US-COPI-143).
 * Este canary chama `scoreFaithfulness(question, ground_truth, ground_truth)` — ou seja,
 * answer = context = ground_truth. O ground_truth é trivialmente fiel a si mesmo, então
 * `Current = 1.0` nas 51 perguntas SEMPRE (medido no CT 100: as 51 deram 1.0). Ele NUNCA
 * roda o pipeline real (`KbAnswerService`: retrieval + síntese) — é a MESMA tautologia que
 * a ADR 0318 matou no `jana:ragas-ci-eval`, e que sobreviveu porque a 0318 só tocou aquele.
 * O que varia run-a-run é só a auto-consistência do juiz LLM em gt-vs-gt, não a Jana.
 *
 * ⛔ NÃO "conserte" via `--update-baseline` (era o chip C3): regravar captura o gt-vs-gt=1.0,
 * setaria baseline=1.0 pra tudo → Δ=0 pra SEMPRE → o alarme ficaria ESTRITAMENTE pior (nunca
 * dispara). Polir um instrumento tautológico é teatro (proibicoes.md §5 2026-07-17).
 *
 * ✅ O sinal de drift REAL da Jana é `jana:ragas-real-eval` (ADR 0318, roda no CT 100 via
 * US-COPI-140) + o piso de context_recall (US-COPI-136). Deprecação formal deste sentinel:
 * US-COPI-143 (decisão [W]). Até lá, a fiação fica (não quebra o dashboard), mas o report
 * carrega `caveat` explícito pra ninguém ler o "ok" como "qualidade da Jana OK".
 *
 * Compara respostas atuais vs baseline checked-in (baseline-responses.json).
 * Alerta se >10% das perguntas divergirem ≥ DRIFT_THRESHOLD do baseline.
 *
 * Schedule: weekly Sun 06:00 BRT (app/Console/Kernel.php) — RELIGADO 2026-06-20.
 * Requer OPENAI_API_KEY no servidor pra rodar real. SEM a chave (e sem --mock) o
 * canary entra em estado DORMANT honesto: exit 0 (não estoura o onFailure do cron
 * toda semana) + status=dormant no JSON, que o agregador governance-audit.mjs
 * mostra como ⊘ dormant (≠ silêncio, ≠ falso "DRIFT 100%"). Skip-guard adicionado
 * 2026-06-20 (auditoria de sentinelas). --mock é só pra CI/teste (faithfulness
 * fixa em 0.85 — NÃO detecta drift real).
 *
 * Uso:
 *   php artisan jana:drift-sentinel                    # roda real (precisa OPENAI_API_KEY)
 *   php artisan jana:drift-sentinel --mock              # mock-mode (CI safe)
 *   php artisan jana:drift-sentinel --json              # só envelope JSON (agregador/Brief)
 *   php artisan jana:drift-sentinel --status --json     # probe armed/dormant (sem custo/rede)
 *   php artisan jana:drift-sentinel --update-baseline   # regrava baseline (Wagner aprova)
 *   php artisan jana:drift-sentinel --detail            # log detalhado por pergunta
 *
 * Exit code:
 *   0 = drift aceitável (<10%) OU dormant (sem OPENAI_API_KEY — não rodou)
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
                            {--json : Emite só o envelope JSON (machine-readable; agregador/Brief)}
                            {--status : Probe leve (sem custo/rede): só reporta armed vs dormant}
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
        // Chave resolvida via config() — cache-safe (ver PEGADINHA no skip-guard abaixo).
        $apiKey = config('openai.api_key') ?: config('services.openai.api_key');
        $apiKey = is_string($apiKey) ? $apiKey : null;

        // --status: probe leve consumido pelo agregador governance-audit.mjs. Responde
        // "esse sentinela está vivo?" (armed=chave presente) SEM disparar o run pago
        // real (que é o cron semanal). Sem custo, sem rede, sem DB.
        if ($this->option('status')) {
            return $this->reportStatus($apiKey);
        }

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

        // Skip-guard HONESTO (auditoria de sentinelas 2026-06-20): o run real precisa
        // de OPENAI_API_KEY (RagasJudgeService chama a OpenAI). Sem mock e sem chave
        // NÃO existe medição possível — em vez de pontuar 0.0 em tudo e gritar
        // "DRIFT 100%" toda semana (falso-positivo) OU estourar o onFailure do cron
        // (ruído), o canary entra em estado DORMANT explícito: exit 0 (não morde o
        // cron) + status=dormant no JSON (agregador mostra ⊘, não silêncio).
        //
        // PEGADINHA: $apiKey acima é lido via config() (config/openai.php +
        // config/services.php, ambos baked de OPENAI_API_KEY no config:cache) — NUNCA
        // env() direto, que devolve null com config:cache ligado em prod e faria o
        // guard pular SEMPRE (vira fantasma de novo). Mesma fonte que
        // RagasJudgeService::callJudge().
        if (self::isDormant($judge->isMockMode(), $apiKey)) {
            return $this->reportDormant();
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

        $isAlert = $driftPct > $maxDriftPct;

        $report = [
            'ran_at' => now()->toIso8601String(),
            'status' => $isAlert ? 'drift' : 'ok',
            'ok' => ! $isAlert,
            // TAUTOLOGIA (US-COPI-143): o 'ok' aqui NÃO significa "Jana OK". Este canary
            // mede faithfulness(gt, gt)≈1.0 (answer=context=ground_truth), não o pipeline
            // real. Sinal de drift REAL = jana:ragas-real-eval (ADR 0318) + piso US-COPI-136.
            'caveat' => 'tautologico: mede gt-vs-gt (~1.0), NAO drift real da Jana — ver US-COPI-143; sinal real=jana:ragas-real-eval',
            'total_questions' => $total,
            'diverged' => $diverged,
            'drift_pct' => round($driftPct, 2),
            'threshold_pct' => $maxDriftPct,
            'drift_threshold_delta' => $driftThreshold,
            'mock_mode' => (bool) ($this->option('mock') || env('RAGAS_FORCE_MOCK', false)),
        ];

        if ($this->option('detail') && ! $this->option('json')) {
            $this->table(['#', 'Question', 'Baseline', 'Current', 'Δ', 'Drift'], $details);
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($isAlert) {
            Log::channel('copiloto-ai')->warning('[Jana drift-sentinel] ALERT drift acima do threshold', $report);
            if (! $this->option('json')) {
                $this->error("DRIFT ALERT: {$diverged}/{$total} divergiram ({$driftPct}%) > {$maxDriftPct}%");
            }

            return self::FAILURE;
        }

        Log::channel('copiloto-ai')->info('[Jana drift-sentinel] OK', $report);
        if (! $this->option('json')) {
            $this->info("OK: drift {$driftPct}% ≤ {$maxDriftPct}%");
        }

        return self::SUCCESS;
    }

    /**
     * Veredito de dormência (testável sem DB): o canary só roda real com
     * OPENAI_API_KEY. Sem mock E sem chave NÃO há medição possível — estado
     * "dormant" honesto (≠ drift, ≠ ok). Espelha o padrão allChecksOk() das
     * sentinelas health-check/system-audit (PR #3098).
     *
     * @param  bool         $mockMode  judge em mock (--mock / RAGAS_FORCE_MOCK / injetado em teste)
     * @param  string|null  $apiKey    chave já resolvida via config() (cache-safe)
     */
    public static function isDormant(bool $mockMode, ?string $apiKey): bool
    {
        return ! $mockMode && ($apiKey === null || $apiKey === '');
    }

    /**
     * --status: probe armed/dormant pro agregador (sem rodar scoring, sem custo/rede).
     * `armed` = OPENAI_API_KEY presente (o cron semanal roda real); `dormant` = ausente.
     */
    private function reportStatus(?string $apiKey): int
    {
        $armed = ! ($apiKey === null || $apiKey === '');

        $report = [
            'ran_at' => now()->toIso8601String(),
            'status' => $armed ? 'armed' : 'dormant',
            'ok' => true,
            'armed' => $armed,
            'probe' => true,
            'reason' => $armed
                ? 'OPENAI_API_KEY presente — canary semanal roda real'
                : 'OPENAI_API_KEY ausente — canary dormant (não roda até a chave existir)',
        ];

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if (! $this->option('json')) {
            if ($armed) {
                $this->info('ARMED: OPENAI_API_KEY presente — canary semanal ativo.');
            } else {
                $this->warn('DORMANT: OPENAI_API_KEY ausente — canary não roda até a chave existir.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Emite o estado DORMANT: exit 0 (não morde o cron) + status=dormant no JSON
     * (agregador/Brief mostram ⊘). Loga INFO (não warning/error) — dormência é
     * estado conhecido e benigno, não falha.
     */
    private function reportDormant(): int
    {
        $report = [
            'ran_at' => now()->toIso8601String(),
            'status' => 'dormant',
            'ok' => true,
            'reason' => 'OPENAI_API_KEY ausente — canary cego, não rodou',
            'mock_mode' => false,
        ];

        Log::channel('copiloto-ai')->info(
            '[Jana drift-sentinel] DORMANT — sem OPENAI_API_KEY (canary não rodou; sem ruído semanal)',
            $report
        );

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if (! $this->option('json')) {
            $this->warn(
                'DORMANT: OPENAI_API_KEY ausente — canary não rodou. Defina a chave no '.
                'ambiente live pra ativar o drift real (ou use --mock pra CI).'
            );
        }

        return self::SUCCESS;
    }

    private function updateBaseline(string $baselinePath, array $questions, RagasJudgeService $judge): int
    {
        // ⛔ GUARD (US-COPI-143): regravar aqui captura faithfulness(gt, gt)≈1.0 — a mesma
        // tautologia do resto do comando. Setaria baseline=1.0 pra tudo → Δ=0 pra sempre →
        // alarme NUNCA dispara (pior que o mock 0.85 atual, que ao menos dá Δ=0.15). Era o
        // "chip C3"; provado contraproducente 2026-07-17 (proibicoes.md §5). Exige override
        // consciente pra não acontecer por reflexo.
        if (! $this->option('mock') && ! env('DRIFT_BASELINE_TAUTOLOGIA_OK')) {
            $this->error(
                'BLOQUEADO: regravar o baseline captura gt-vs-gt (~1.0) — tautológico, deixa o '.
                'alarme cego pra sempre (US-COPI-143). O sinal de drift real é jana:ragas-real-eval. '.
                'Se você REALMENTE quer regravar mesmo assim (ex: só pro modo --mock de CI), use '.
                '--mock, ou defina DRIFT_BASELINE_TAUTOLOGIA_OK=1 cientemente.'
            );

            return self::FAILURE;
        }

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
