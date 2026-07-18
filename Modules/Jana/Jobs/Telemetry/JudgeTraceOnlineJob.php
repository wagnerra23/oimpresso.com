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
 * ⛔ LGPD Tier 0 (trace de cliente é biz≠1 — ADR 0093 + LGPD): DOIS gates OFF.
 *   1. config('jana.online_eval.enabled') — a listener nem dispatcha se false.
 *   2. config('jana.online_eval.judge') — 'local' roteia pro juiz self-hosted (CT 100,
 *      backend Ollama, ZERO egress pra terceiro · US-COPI-137 rota B); 'openai' manda
 *      a amostra pro juiz externo. Ambos SÓ depois do PiiRedactor. Qualquer outro valor
 *      SKIPa (fail-safe). Ligar de verdade = enabled=true E judge escolhido (decisões [W]);
 *      'local' exige ainda os pré-reqs de infra do config/ragas.php (modelo chat + expor Ollama).
 *
 * Por que async (fila): o juiz LLM é lento (~1-2s/chamada) e não pode atrasar a
 * resposta ao cliente. Prod tem worker (QUEUE_CONNECTION=database, verificado). O
 * dado (input/output) viaja no constructor — nunca re-busca do DB (SerializesModels
 * não carrega Eloquent aqui; são strings puras).
 *
 * Por que só faithfulness (não relevancy/recall): sem ground_truth no tráfego real,
 * recall não é computável (é métrica de gold-set). Faithfulness (resposta ancorada no
 * contexto que o modelo recebeu) é o proxy de alucinação e NÃO precisa de gt — é o
 * sinal online honesto. Relevancy precisaria isolar a pergunta do prompt (follow-up).
 *
 * @see Modules/Jana/Listeners/Telemetry/LangfuseAgentTelemetryListener.php
 * @see Modules/Jana/Services/Privacy/PiiRedactor.php
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
        // Gate 2 (LGPD): dois destinos válidos, o resto SKIPa (fail-safe — nunca cair
        // pro OpenAI por reflexo).
        //   'local'  → juiz self-hosted no CT 100 (rota B, US-COPI-137) — ZERO egress
        //              pra terceiro; o dado (PII-redigido) fica na nossa infra.
        //   'openai' → juiz externo (api.openai.com); a amostra PII-redigida SAI.
        $judgeTarget = (string) config('jana.online_eval.judge', 'local');
        if (! in_array($judgeTarget, ['local', 'openai'], true)) {
            Log::channel('copiloto-ai')->info(
                '[online-eval] SKIP: judge desconhecido — dado do cliente NÃO sai',
                ['judge' => $judgeTarget, 'business_id' => $this->businessId, 'trace' => $this->traceId]
            );

            return;
        }

        // 'local' força o backend Ollama (CT 100); 'openai' usa o default de config.
        if ($judgeTarget === 'local') {
            $judge->useBackend('ollama');
        }

        // Proteção dura: juiz em mock tornaria o score teatro. Não pontua em mock.
        if ($judge->isMockMode()) {
            return;
        }

        // ⛔ LGPD Tier 0: redige PII ANTES de qualquer coisa sair pro juiz externo.
        // input/output do cliente podem ter CPF/CNPJ/nome/email — nunca cru pra OpenAI.
        $context = $redactor->redact($this->rawContext);
        $answer = $redactor->redact($this->rawAnswer);

        // faithfulness(question, answer, context): a resposta está ancorada no que o
        // modelo recebeu? question=context (o prompt já embute a pergunta); sem gt.
        $faithfulness = $judge->scoreFaithfulness($context, $answer, $context);

        $client->recordScore(
            $this->traceId,
            'ragas_faithfulness_online',
            $faithfulness,
            'US-COPI-137 online eval (5% sample, PII-redacted)'
        );
    }
}
