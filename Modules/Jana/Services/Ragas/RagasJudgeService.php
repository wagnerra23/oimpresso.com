<?php

declare(strict_types=1);

namespace Modules\Jana\Services\Ragas;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Telemetry\LangfuseClient;

/**
 * RagasJudgeService — LLM-as-a-judge pra métricas RAGAS canônicas.
 *
 * Implementa as 4 métricas core RAGAS (todas 0..1):
 *  - faithfulness       — claims da resposta suportados pelo contexto
 *  - answer_relevancy   — resposta direta à query
 *  - context_precision  — contexto ranqueado corretamente (info útil no topo)
 *  - context_recall     — contexto cobre tudo necessário pra ground truth
 *
 * Padrão "Single-Aspect Focus" (RAGAS docs): cada prompt avalia 1 dimensão.
 * Decompõe resposta em claims; LLM-as-judge vota suportado/não-suportado por claim;
 * score = supported / total.
 *
 * Custo aprox (gpt-4o-mini, 2026-05):
 *  - ~1200 tokens input + 300 output por avaliação = ~$0.0004/judge call
 *  - 4 métricas × 5 perguntas = ~$0.008/suite run
 *  - 2 suites (Brief + KbAnswer) × 1 run/semana = ~$0.016/semana
 *
 * @see ADR 0037 §GAP-2 — RAGAS gate em CI
 * @see config/ragas.php — thresholds e modelo judge
 */
class RagasJudgeService
{
    /**
     * Modo mock pra testes locais (sem chamar OpenAI real).
     */
    protected bool $mockMode = false;

    /**
     * Scores fixos retornados em mockMode (controlados por teste).
     *
     * @var array<string,float>
     */
    protected array $mockScores = [
        'faithfulness' => 0.85,
        'answer_relevancy' => 0.78,
        'context_precision' => 0.82,
        'context_recall' => 0.71,
    ];

    public function enableMock(array $scores = []): void
    {
        $this->mockMode = true;
        if (! empty($scores)) {
            $this->mockScores = array_merge($this->mockScores, $scores);
        }
    }

    /**
     * Indica se o service está em modo mock (testes).
     */
    public function isMockMode(): bool
    {
        return $this->mockMode;
    }

    /**
     * Faithfulness — claims da `answer` suportados pelo `context`?
     *
     * Prompt RAGAS-style: decompõe answer em claims; pra cada claim, contexto suporta?
     * Score = claims_supported / total_claims.
     */
    public function scoreFaithfulness(string $question, string $answer, string $context): float
    {
        if ($this->mockMode) {
            return $this->mockScores['faithfulness'];
        }

        $prompt = <<<PROMPT
Você é um avaliador rigoroso de RAG (RAGAS-style). Avalie FAITHFULNESS:
quantos claims da RESPOSTA são suportados pelo CONTEXTO recuperado?

PERGUNTA: {$question}

CONTEXTO RECUPERADO:
{$context}

RESPOSTA GERADA:
{$answer}

Tarefa:
1. Decomponha a RESPOSTA em claims atômicos (afirmações factuais).
2. Pra cada claim, marque "supported" se o CONTEXTO o suporta diretamente,
   ou "unsupported" se a RESPOSTA inventa info não presente no CONTEXTO.
3. Score = supported / total_claims (float 0..1).

Retorne SOMENTE JSON válido neste formato exato:
{"total_claims": N, "supported": M, "score": 0.XX, "reasoning": "..."}
PROMPT;

        return $this->callJudge($prompt, 'faithfulness');
    }

    /**
     * Answer Relevancy — resposta é direta à pergunta?
     *
     * Não avalia veracidade (isso é faithfulness). Avalia se a resposta
     * endereça a query OU divaga / dá info colateral.
     */
    public function scoreAnswerRelevancy(string $question, string $answer): float
    {
        if ($this->mockMode) {
            return $this->mockScores['answer_relevancy'];
        }

        $prompt = <<<PROMPT
Você é um avaliador RAGAS. Avalie ANSWER RELEVANCY:
a RESPOSTA endereça diretamente a PERGUNTA?

PERGUNTA: {$question}

RESPOSTA: {$answer}

Critérios:
- 1.0 = resposta direta, completa, sem digressão
- 0.7 = resposta cobre o essencial mas adiciona info não solicitada
- 0.4 = resposta tangencial, cobre só parcialmente
- 0.0 = resposta não responde à pergunta

IGNORE veracidade dos fatos. Avalie SÓ relevância pragmática.

Retorne SOMENTE JSON:
{"score": 0.XX, "reasoning": "..."}
PROMPT;

        return $this->callJudge($prompt, 'answer_relevancy');
    }

    /**
     * Context Precision — contexto recuperado tem info útil ranqueada no topo?
     *
     * Avalia "signal-to-noise" do retrieval: chunks relevantes vêm antes
     * de chunks ruidosos?
     */
    public function scoreContextPrecision(string $question, string $context, string $groundTruth = ''): float
    {
        if ($this->mockMode) {
            return $this->mockScores['context_precision'];
        }

        $gtSection = $groundTruth !== ''
            ? "GROUND TRUTH (resposta ideal):\n{$groundTruth}\n"
            : '';

        $prompt = <<<PROMPT
Você é um avaliador RAGAS. Avalie CONTEXT PRECISION:
o CONTEXTO recuperado tem info ÚTIL ranqueada no topo (signal > noise)?

PERGUNTA: {$question}

{$gtSection}
CONTEXTO RECUPERADO (ordem como veio do retriever):
{$context}

Critérios:
- 1.0 = chunks relevantes pra responder a pergunta aparecem no início
- 0.7 = relevantes presentes mas misturados com ruído
- 0.4 = ruído dominante, relevantes no fim
- 0.0 = contexto irrelevante / não suporta a pergunta

Retorne SOMENTE JSON:
{"score": 0.XX, "reasoning": "..."}
PROMPT;

        return $this->callJudge($prompt, 'context_precision');
    }

    /**
     * Context Recall — contexto cobre tudo necessário pra ground truth?
     *
     * Avalia "completeness" do retrieval: tudo que a resposta ideal precisa
     * está no contexto? Requer ground_truth.
     */
    public function scoreContextRecall(string $question, string $context, string $groundTruth): float
    {
        if ($this->mockMode) {
            return $this->mockScores['context_recall'];
        }

        $prompt = <<<PROMPT
Você é um avaliador RAGAS. Avalie CONTEXT RECALL:
o CONTEXTO recuperado cobre tudo que a GROUND TRUTH precisa?

PERGUNTA: {$question}

GROUND TRUTH (resposta ideal):
{$groundTruth}

CONTEXTO RECUPERADO:
{$context}

Tarefa:
1. Decomponha a GROUND TRUTH em claims atômicos.
2. Pra cada claim, marque "covered" se o CONTEXTO o suporta, ou "missing".
3. Score = covered / total_claims.

Retorne SOMENTE JSON:
{"total_claims": N, "covered": M, "score": 0.XX, "reasoning": "..."}
PROMPT;

        return $this->callJudge($prompt, 'context_recall');
    }

    /**
     * Roda todas as 4 métricas em sequência.
     *
     * @return array{faithfulness:float, answer_relevancy:float, context_precision:float, context_recall:float}
     */
    public function scoreAll(string $question, string $answer, string $context, string $groundTruth = ''): array
    {
        return [
            'faithfulness'      => $this->scoreFaithfulness($question, $answer, $context),
            'answer_relevancy'  => $this->scoreAnswerRelevancy($question, $answer),
            'context_precision' => $this->scoreContextPrecision($question, $context, $groundTruth),
            'context_recall'    => $groundTruth !== ''
                ? $this->scoreContextRecall($question, $context, $groundTruth)
                : 0.0,
        ];
    }

    /**
     * Chama gpt-4o-mini (ou modelo configurado) com prompt judge.
     *
     * Retorna score 0..1; em erro/timeout retorna 0.0 e loga warning
     * (gate falha → CI gera alerta, não bloqueia merge default).
     */
    protected function callJudge(string $prompt, string $metric): float
    {
        $apiKey = config('openai.api_key') ?: env('OPENAI_API_KEY');

        if (empty($apiKey)) {
            Log::warning("[RAGAS] OPENAI_API_KEY ausente — pulando metric={$metric}");

            return 0.0;
        }

        $model   = config('ragas.judge_model', 'gpt-4o-mini');
        $timeout = (int) config('ragas.judge_timeout', 60);

        try {
            $t0 = microtime(true);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])
                ->timeout($timeout)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'           => $model,
                    'temperature'     => 0.0,
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        ['role' => 'system', 'content' => 'Você é avaliador RAGAS imparcial. Retorna sempre JSON válido.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning("[RAGAS] Judge HTTP {$response->status()} metric={$metric}");

                return 0.0;
            }

            $raw  = $response->json('choices.0.message.content') ?? '{}';
            $data = json_decode($raw, true);

            $score = (float) ($data['score'] ?? 0.0);

            // Sanitiza pra range 0..1
            $score = max(0.0, min(1.0, $score));

            // US-COPI-108 (reconciliação 2026-07-12): este call-site usa Http::
            // direto — fora do listener global Langfuse (que só cobre events do
            // laravel/ai). Instrumentação inline: 1 trace + 1 generation + 1 score
            // por métrica. LangfuseClient é fail-open + no-op se langfuse.enabled=false
            // (caso do CI GH Actions, que nem alcança o host CT 100).
            $langfuse = app(LangfuseClient::class);
            $traceId  = $langfuse->traceComGeneration([
                'name'        => 'ragas-judge',
                'business_id' => null, // eval de governança repo-wide, sem tenant
                'tool'        => 'ragas-gate',
                'metadata'    => ['metric' => $metric],
            ], [
                'name'        => "ragas-{$metric}",
                'model'       => $model,
                'input'       => $prompt,
                'output'      => $raw,
                'usage'       => [
                    'input'  => $response->json('usage.prompt_tokens'),
                    'output' => $response->json('usage.completion_tokens'),
                ],
                'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
            ]);
            $langfuse->recordScore($traceId, "ragas_{$metric}", $score);

            return $score;
        } catch (\Throwable $e) {
            Log::warning("[RAGAS] Exception metric={$metric}: " . $e->getMessage());

            return 0.0;
        }
    }
}
