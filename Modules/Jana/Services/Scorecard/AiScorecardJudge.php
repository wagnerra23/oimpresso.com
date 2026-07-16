<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Scorecard;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\Jana\Services\Telemetry\LangfuseClient;
use Throwable;

/**
 * AiScorecardJudge — Wave 24 (Agent B Governance v4 LLM baseline READ-ONLY 30d).
 *
 * Auditoria LLM-as-judge SOBRE rubricas Scoped Scorecards YAML (ADR 0160) +
 * resultado determinístico. Sugere `delta` (-/+ pontos) com justificativa,
 * mas NÃO altera score oficial.
 *
 * BASELINE 30 DIAS — anti-Goodhart Law (Jellyfish 2025 + estado-da-arte):
 *  - Score oficial permanece DETERMINÍSTICO via ScopedScorecardEvaluator (W21/W24-A)
 *  - AI roda paralelo, persiste sugestões em `mcp_scorecard_ai_suggestions`
 *  - Após 30d de baseline, Wagner decide se AI vira critério (sub-rules específicas)
 *    ou permanece só observacional
 *
 * Modelo: Anthropic claude-haiku-4-5 (Brain A barato, custo previsível).
 *  - Input: ~1.5k tokens (system 400 + YAML+breakdown 1100)
 *  - Output: ~250 tokens (delta + justificativa + confidence)
 *  - claude-haiku-4-5: ~$0.001/call ≈ R$ [redacted Tier 0]
 *  - 20 módulos × 1 run/semana = ~R$ [redacted Tier 0]/mês desprezível
 *
 * Stack canônica (ADR 0035): laravel/ai (Camada A). PII redactor pré-LLM
 * (ADR 0094 §Compliance LGPD Art. 7º) — embora rubrica YAML não DEVA conter
 * PII, defense in depth.
 *
 * @see memory/decisions/0160-governance-v4-scoped-scorecards-bucket-meta.md
 * @see memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md (§4 LGPD)
 * @see Wave 24 Agent B mega
 */
class AiScorecardJudge
{
    /**
     * Modo mock — testes locais sem chamar LLM real (CI safe).
     */
    protected bool $mockMode = false;

    /** @var array<string,mixed> Resposta fixa em mockMode (override por teste). */
    protected array $mockResponse = [
        'delta' => 0,
        'justificativa' => 'Mock baseline — heuristica deterministica alinhada com rubrica.',
        'confidence' => 0.80,
    ];

    public function __construct(
        protected PiiRedactor $redactor,
    ) {}

    public function enableMock(array $override = []): void
    {
        $this->mockMode = true;
        if (! empty($override)) {
            $this->mockResponse = array_merge($this->mockResponse, $override);
        }
    }

    /**
     * Sugere ajuste de score — READ-ONLY V2.
     *
     * @param  string  $module  slug-lowercase do módulo (ex: 'jana', 'admin')
     * @param  array  $scorecard  YAML decodificado (memory/governance/scorecards/<module>.yaml)
     * @param  array  $deterministicResult  Output do ScopedScorecardEvaluator:
     *                                       ['score_total' => int, 'meta_bucket' => int,
     *                                        'core' => [...], 'bucket_dimensions' => [...],
     *                                        'paired_violations' => [...]]
     * @return array{module:string, deterministic_score:int, ai_suggested_delta:int,
     *               ai_justificativa:string, ai_model:string, confidence:float, created_at:string}
     */
    public function suggestScoreAdjustment(
        string $module,
        array $scorecard,
        array $deterministicResult,
    ): array {
        return OtelHelper::span('jana.scorecard.ai_judge', [
            'module' => $module,
            'mode' => $this->mockMode ? 'mock' : 'real',
        ], function () use ($module, $scorecard, $deterministicResult) {
            try {
                $prompt = $this->buildPrompt($module, $scorecard, $deterministicResult);
                // Defense in depth: redacta PII antes de qualquer chamada LLM
                $prompt = $this->redactor->redact($prompt);

                $response = $this->callLlm($prompt);
                $parsed = $this->parseResponse($response);

                $out = [
                    'module' => $module,
                    'deterministic_score' => (int) ($deterministicResult['score_total'] ?? 0),
                    'ai_suggested_delta' => (int) $parsed['delta'],
                    'ai_justificativa' => (string) $parsed['justificativa'],
                    'ai_model' => $this->modelName(),
                    'confidence' => (float) $parsed['confidence'],
                    'created_at' => now()->toIso8601String(),
                ];

                $this->persist($out);

                return $out;
            } catch (Throwable $e) {
                Log::warning('ai_scorecard_judge.failure', [
                    'module' => $module,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'module' => $module,
                    'deterministic_score' => (int) ($deterministicResult['score_total'] ?? 0),
                    'ai_suggested_delta' => 0,
                    'ai_justificativa' => 'Falha LLM — baseline preserva score deterministico.',
                    'ai_model' => $this->modelName(),
                    'confidence' => 0.0,
                    'created_at' => now()->toIso8601String(),
                ];
            }
        });
    }

    /**
     * Monta prompt PT-BR — single-shot, sem tools, output JSON estrito.
     */
    public function buildPrompt(string $module, array $scorecard, array $det): string
    {
        $bucket = $scorecard['bucket'] ?? ($scorecard['metadata']['bucket'] ?? 'unknown');
        $score = (int) ($det['score_total'] ?? 0);
        $meta = (int) ($det['meta_bucket'] ?? 0);
        $core = $this->summarizeDim($det['core'] ?? []);
        $dims = $this->summarizeDim($det['bucket_dimensions'] ?? []);
        $violations = $this->summarizeViolations($det['paired_violations'] ?? []);

        return <<<PROMPT
        Modulo: {$module}
        Bucket: {$bucket}
        Score deterministico: {$score}/100
        Meta bucket: {$meta}

        Breakdown:
        Core: {$core}
        Bucket dims: {$dims}
        Paired violations: {$violations}

        Analise se algum sinal foi sub/superestimado pela heuristica deterministica.
        Output JSON ESTRITO (sem markdown wrap, apenas chaves):
        { "delta": -2, "justificativa": "F1.b cobra ratio mas tests vazios", "confidence": 0.8 }

        REGRAS DURAS:
        - delta inteiro entre -10 e +10
        - justificativa <= 160 chars PT-BR
        - confidence float [0..1]
        - SE rubrica e breakdown alinhados: delta=0, confidence>=0.8
        - SE detectar gap claro (test ratio inflado, cobertura LGPD ausente): delta negativo
        - NUNCA invente sinais que nao estao no breakdown
        PROMPT;
    }

    /**
     * Summariza dimensão (core OU bucket-specific) em string curta.
     */
    public function summarizeDim(array $dims): string
    {
        if (empty($dims)) {
            return '(vazio)';
        }
        $parts = [];
        foreach ($dims as $key => $data) {
            $current = is_array($data) ? ($data['current'] ?? null) : $data;
            $target = is_array($data) ? ($data['target'] ?? null) : null;
            $parts[] = $target !== null
                ? "{$key}={$current}/{$target}"
                : "{$key}={$current}";
        }

        return implode(', ', $parts);
    }

    /**
     * Summariza paired violations (anti-Goodhart) — Jellyfish 2025 §pattern.
     */
    public function summarizeViolations(array $violations): string
    {
        if (empty($violations)) {
            return 'nenhuma';
        }
        $parts = [];
        foreach ($violations as $v) {
            $rule = is_array($v) ? ($v['rule'] ?? 'unknown') : (string) $v;
            $reason = is_array($v) ? ($v['reason'] ?? '') : '';
            $parts[] = $reason ? "{$rule} ({$reason})" : $rule;
        }

        return implode('; ', $parts);
    }

    /**
     * Parseia resposta JSON do LLM — tolerante a markdown wrap acidental.
     *
     * @return array{delta:int, justificativa:string, confidence:float}
     */
    public function parseResponse(string $raw): array
    {
        $clean = trim($raw);
        // Tolerar markdown wrap ```json ... ```
        $clean = preg_replace('/^```(?:json)?\s*/m', '', (string) $clean);
        $clean = preg_replace('/\s*```$/m', '', (string) $clean);
        $clean = trim((string) $clean);

        $decoded = json_decode($clean, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException("Resposta LLM nao parseavel: {$raw}");
        }

        $delta = (int) ($decoded['delta'] ?? 0);
        $delta = max(-10, min(10, $delta));

        $just = (string) ($decoded['justificativa'] ?? '');
        if (strlen($just) > 160) {
            $just = substr($just, 0, 160);
        }

        $conf = (float) ($decoded['confidence'] ?? 0.0);
        $conf = max(0.0, min(1.0, $conf));

        return [
            'delta' => $delta,
            'justificativa' => $just,
            'confidence' => $conf,
        ];
    }

    /**
     * Modelo canônico — Brain A barato pra baseline 30d.
     */
    public function modelName(): string
    {
        return (string) config('jana.scorecard_ai.model', 'claude-haiku-4-5');
    }

    /**
     * Chama LLM real (Camada A laravel/ai) OR mock.
     *
     * IMPORTANTE: encapsulação aqui permite que W21/W24 sibling agents
     * substituam o transport via DI sem mudar AiScorecardJudge.
     */
    protected function callLlm(string $prompt): string
    {
        if ($this->mockMode) {
            return (string) json_encode($this->mockResponse);
        }

        // Stack canônica ADR 0035 — laravel/ai Brain A.
        // NOTE: a integração concreta é simples HTTP via Laravel\Ai client
        // quando configurado. Wave 24 baseline NÃO requer agent class (single-shot).
        // Wagner pode plugar provider config('ai.provider') em runtime.
        $apiKey = config('services.anthropic.api_key') ?? env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            // Sem credencial = fallback baseline neutro
            return (string) json_encode([
                'delta' => 0,
                'justificativa' => 'Sem ANTHROPIC_API_KEY — baseline neutro.',
                'confidence' => 0.0,
            ]);
        }

        // HTTP wrapper canônico via Illuminate\Support\Facades\Http permite mock em tests.
        $t0 = microtime(true);

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->modelName(),
            'max_tokens' => 400,
            'temperature' => 0.2,
            'system' => 'Voce e auditor SENIOR avaliando rubrica Scoped Scorecard. '.
                'Analise YAML + breakdown deterministico. Sugira ajustes (+/- pts) com justificativa. '.
                'NUNCA invente, so observe gaps.',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $body = $response->json();
        $text = (string) ($body['content'][0]['text'] ?? '');
        if ($text === '') {
            throw new \RuntimeException('LLM retornou content vazio');
        }

        // US-COPI-108 (reconciliação 2026-07-12): call-site Http:: direto, fora do
        // listener global Langfuse — instrumentação inline. No-op se disabled.
        app(LangfuseClient::class)->traceComGeneration([
            'name'        => 'scorecard-ai-judge',
            'business_id' => null, // governance meta repo-wide (ADR 0093 §metadata)
            'tool'        => 'scorecard-ai-judge',
            'metadata'    => ['prompt_chars' => strlen($prompt)],
        ], [
            'name'        => 'scorecard-suggest-delta',
            'model'       => $this->modelName(),
            'output'      => $text,
            'usage'       => [
                'input'  => $body['usage']['input_tokens'] ?? null,
                'output' => $body['usage']['output_tokens'] ?? null,
            ],
            'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
        ]);

        return $text;
    }

    /**
     * Persiste sugestão em `mcp_scorecard_ai_suggestions` (baseline 30d).
     * Tabela é repo-wide (governance meta) — sem business_id scope (ADR 0093 §metadata).
     */
    protected function persist(array $out): void
    {
        try {
            DB::table('mcp_scorecard_ai_suggestions')->insert([
                'module' => $out['module'],
                'deterministic_score' => $out['deterministic_score'],
                'ai_suggested_delta' => $out['ai_suggested_delta'],
                'ai_justificativa' => $out['ai_justificativa'],
                'ai_model' => $out['ai_model'],
                'confidence' => $out['confidence'],
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Best-effort — baseline não bloqueia score oficial
            Log::info('ai_scorecard_judge.persist_skip', [
                'module' => $out['module'],
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
