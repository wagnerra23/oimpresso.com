<?php

declare(strict_types=1);

namespace Modules\Jana\Jobs\Telemetry;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\Jana\Services\Ragas\JudgeUnavailableException;
use Modules\Jana\Services\Ragas\OllamaRagasJudge;
use Modules\Jana\Services\Ragas\RagasJudgeService;
use Modules\Jana\Services\Telemetry\LangfuseClient;

/**
 * JudgeTraceOnlineJob — US-COPI-137 — eval RAGAS no tráfego REAL da Jana.
 *
 * Amostra ~5% dos traces reais (dispatch pela LangfuseAgentTelemetryListener) e
 * pontua faithfulness da resposta REAL do cliente, gravando o score de volta no
 * próprio trace (LangfuseClient::recordScore). Fecha o buraco: a única medição de
 * qualidade era offline (gold-set) — se a Jana degradar pro cliente, ninguém sabia.
 *
 * ⛔ LGPD Tier 0 (trace de cliente é biz≠1 — ADR 0093 + LGPD):
 *   1. config('copiloto.online_eval.enabled') — a listener nem dispatcha se false (gate [W]).
 *   2. config('copiloto.online_eval.judge'):
 *        - 'local'  (default) — OllamaRagasJudge, juiz self-host CT 100, ZERO egress
 *          (implementado 2026-07-18). O dado do cliente NÃO sai da infra.
 *        - 'openai' — manda a amostra pro juiz externo, exige aceite LGPD do [W].
 *   O PiiRedactor roda ANTES do juiz nos DOIS caminhos (defesa em profundidade).
 *
 * ⚠️ NAMESPACE (fix 2026-07-18): lê `copiloto.online_eval.*` (o bloco vive em
 * config.php, merged como `copiloto`). ANTES lia `jana.online_eval.*` — namespace
 * que só tem retention/memoria → enabled=true no config.php não ligava nada.
 *
 * Por que async (fila): o juiz LLM é lento (~1-2s/chamada, mais ainda no Ollama
 * CPU-only) e não pode atrasar a resposta ao cliente. Prod tem worker
 * (QUEUE_CONNECTION=database, verificado). O dado (input/output) viaja no
 * constructor — nunca re-busca do DB (SerializesModels não carrega Eloquent aqui;
 * são strings puras).
 *
 * Por que só faithfulness (não relevancy/recall): sem ground_truth no tráfego real,
 * recall não é computável (é métrica de gold-set). Faithfulness (resposta ancorada no
 * contexto que o modelo recebeu) é o proxy de alucinação e NÃO precisa de gt — é o
 * sinal online honesto. Relevancy precisaria isolar a pergunta do prompt (follow-up).
 *
 * @see Modules/Jana/Listeners/Telemetry/LangfuseAgentTelemetryListener.php
 * @see Modules/Jana/Services/Privacy/PiiRedactor.php
 * @see Modules/Jana/Services/Ragas/OllamaRagasJudge.php
 * @see memory/requisitos/Jana/SPEC.md#US-COPI-137
 */
class JudgeTraceOnlineJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  string   $traceId    id do trace no Langfuse (alvo do recordScore)
     * @param  int|null $businessId tenant do trace (Tier 0 — vai no metadata/log, nunca session())
     * @param  string   $rawContext prompt que o modelo recebeu (contexto) — PII bruto
     * @param  string   $rawAnswer  resposta do modelo — PII bruto
     */
    public function __construct(
        public readonly string $traceId,
        public readonly ?int $businessId,
        public readonly string $rawContext,
        public readonly string $rawAnswer,
    ) {}

    /**
     * Decisão de amostragem — PURA (testável sem fila/DB) e DETERMINÍSTICA por trace
     * (mesmo trace → mesma decisão, idempotente se re-dispatchado). Usa crc32 do
     * traceId mascarado pra 31 bits (positivo em qualquer plataforma).
     */
    public static function shouldSample(string $traceId, float $rate): bool
    {
        if ($rate <= 0.0 || $traceId === '') {
            return false;
        }
        if ($rate >= 1.0) {
            return true;
        }

        return (crc32($traceId) & 0x7fffffff) % 10000 < (int) round($rate * 10000);
    }

    public function handle(PiiRedactor $redactor, RagasJudgeService $judge, LangfuseClient $client): void
    {
        // Gate 2 (LGPD). 'local' = juiz Ollama self-host CT 100 (zero egress);
        // 'openai' = juiz externo (aceite LGPD [W]). Qualquer outro valor = SKIP
        // honesto (nada roda, nada sai) — nunca cai pro OpenAI por reflexo.
        $judgeTarget = (string) config('copiloto.online_eval.judge', 'local');
        if ($judgeTarget !== 'local' && $judgeTarget !== 'openai') {
            Log::channel('copiloto-ai')->info(
                '[online-eval] SKIP: judge desconhecido — nada roda, nada sai',
                ['judge' => $judgeTarget, 'business_id' => $this->businessId, 'trace' => $this->traceId]
            );

            return;
        }

        // 'local' → juiz self-host (zero egress); 'openai' → juiz externo.
        $judgeService = $judgeTarget === 'local' ? app(OllamaRagasJudge::class) : $judge;

        // Proteção dura: juiz em mock tornaria o score teatro. Não pontua em mock.
        if ($judgeService->isMockMode()) {
            return;
        }

        // ⛔ LGPD Tier 0: redige PII ANTES do juiz nos DOIS caminhos. input/output do
        // cliente podem ter CPF/CNPJ/nome/email — nunca cru (nem pro juiz local).
        $context = $redactor->redact($this->rawContext);
        $answer = $redactor->redact($this->rawAnswer);

        // faithfulness(question, answer, context): a resposta está ancorada no que o
        // modelo recebeu? question=context (o prompt já embute a pergunta); sem gt.
        try {
            $faithfulness = $judgeService->scoreFaithfulness($context, $answer, $context);
        } catch (JudgeUnavailableException $e) {
            // HONESTO: juiz indisponível (Ollama down / sem modelo de chat / JSON
            // inválido) → NÃO grava score. 0.0 seria falso alarme de "infiel".
            Log::channel('copiloto-ai')->info(
                '[online-eval] SKIP: juiz indisponível — score NÃO gravado (sem fabricação)',
                [
                    'judge' => $judgeTarget,
                    'reason' => $e->getMessage(),
                    'business_id' => $this->businessId,
                    'trace' => $this->traceId,
                ]
            );

            return;
        }

        $client->recordScore(
            $this->traceId,
            'ragas_faithfulness_online',
            $faithfulness,
            "US-COPI-137 online eval (judge={$judgeTarget}, ~5% sample, PII-redacted)"
        );
    }
}
