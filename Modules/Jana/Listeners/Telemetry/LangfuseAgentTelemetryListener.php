<?php

declare(strict_types=1);

namespace Modules\Jana\Listeners\Telemetry;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use Modules\Jana\Ai\Agents\ChatCopilotoAgent;
use Modules\Jana\Services\Telemetry\LangfuseClient;

/**
 * LangfuseAgentTelemetryListener — observabilidade LLM global via events
 * nativos do laravel/ai (ADR 0132 + ADR 0035 Camada B).
 *
 * PONTO ÚNICO de emissão: TODA chamada LLM que passa pelo laravel/ai SDK
 * (Agents Promptable ADR 0141 — BriefDiarioAgent, KbAnswerAgent, etc — E o
 * chat via ChatCopilotoAgent, blocking OU streaming) dispara AgentPrompted/
 * AgentStreamed. Este listener converte cada uma em trace + generation no
 * Langfuse self-host CT 100 (custo/latência/tokens incl. prompt cache).
 *
 * Por que listener e não instrumentação per-call-site: o gap catalogado
 * 2026-07-02 foi exatamente esse — a emissão vivia só em
 * LaravelAiSdkDriver::responderChat (blocking), o tráfego real migrou pros
 * Agents + streaming e a telemetria morreu em 2026-05-11 sem ninguém notar.
 *
 * Duração: PromptingAgent/StreamingAgent (start) e AgentPrompted/AgentStreamed
 * (end) compartilham invocationId — clock guardado em array estático.
 *
 * MULTI-TENANT Tier 0: business_id extraído do próprio Agent (propriedade
 * pública `businessId` por convenção ADR 0141, ou `conversa->business_id`
 * no ChatCopilotoAgent) e vai em trace metadata. PII: input/output passam
 * pelo PiiRedactor dentro do LangfuseClient (config langfuse.redact_pii).
 *
 * Fail-open (princípio 8 ADR 0094): qualquer erro vira debug log, NUNCA
 * quebra a chamada LLM.
 */
class LangfuseAgentTelemetryListener
{
    /** @var array<string,float> microtime(true) do start, por invocationId */
    private static array $startedAt = [];

    /** Teto do buffer de starts — evita crescer sem fim em runtime long-running (FrankenPHP). */
    private const MAX_PENDING_STARTS = 100;

    public function __construct(private readonly LangfuseClient $client)
    {
    }

    /**
     * @return array<class-string, string> mapa evento => método
     */
    public function subscribe(Dispatcher $events): array
    {
        // AgentStreamed extends AgentPrompted, mas o dispatcher do Laravel só
        // propaga por interface (não por herança) — registrar ambos explícito.
        return [
            PromptingAgent::class => 'onStart',
            StreamingAgent::class => 'onStart',
            AgentPrompted::class => 'onEnd',
            AgentStreamed::class => 'onEnd',
        ];
    }

    public function onStart(PromptingAgent $event): void
    {
        if (count(self::$startedAt) >= self::MAX_PENDING_STARTS) {
            self::$startedAt = [];
        }

        self::$startedAt[$event->invocationId] = microtime(true);
    }

    public function onEnd(AgentPrompted $event): void
    {
        $startedAt = self::$startedAt[$event->invocationId] ?? null;
        unset(self::$startedAt[$event->invocationId]);

        if (! (bool) config('langfuse.enabled', false)) {
            return;
        }

        try {
            $agent = $event->prompt->agent;
            $nome = Str::kebab(class_basename($agent));
            $contexto = $this->extrairContexto($agent);
            $usage = $event->response->usage ?? null;
            $model = $event->response->meta->model ?? $event->prompt->model;
            $durationMs = $startedAt !== null
                ? (int) round((microtime(true) - $startedAt) * 1000)
                : null;

            $this->client->traceComGeneration(
                trace: [
                    'name' => $nome,
                    'business_id' => $contexto['business_id'],
                    'user_id' => $contexto['user_id'],
                    'conversation_id' => $contexto['conversation_id'],
                    'tool' => $nome,
                    'input' => $event->prompt->prompt,
                    'metadata' => [
                        'stream' => $event instanceof AgentStreamed,
                        'invocation_id' => $event->invocationId,
                    ],
                ],
                generation: [
                    'name' => "{$nome}:llm",
                    'model' => (string) $model,
                    'input' => $event->prompt->prompt,
                    'output' => (string) $event->response,
                    'usage' => $usage !== null ? [
                        'input' => (int) $usage->promptTokens,
                        'output' => (int) $usage->completionTokens,
                    ] : null,
                    'duration_ms' => $durationMs,
                    'metadata' => array_filter([
                        'provider' => $event->response->meta->provider ?? null,
                        'business_id' => $contexto['business_id'],
                        'cache_read_input_tokens' => $usage?->cacheReadInputTokens,
                        'cache_write_input_tokens' => $usage?->cacheWriteInputTokens,
                        'reasoning_tokens' => $usage?->reasoningTokens,
                    ], fn ($v) => $v !== null && $v !== 0),
                ],
            );
        } catch (\Throwable $e) {
            // Fail-open: telemetria NUNCA quebra a chamada LLM.
            Log::channel('copiloto-ai')->debug(
                'LangfuseAgentTelemetryListener falhou: ' . $e->getMessage()
            );
        }
    }

    /**
     * Extrai business_id/user_id/conversation_id do Agent sem acoplar em cada
     * classe: ChatCopilotoAgent expõe a Conversa; os demais Agents (ADR 0141)
     * expõem `businessId` público no constructor (Tier 0 mecânico).
     *
     * @return array{business_id: ?int, user_id: ?int, conversation_id: ?int}
     */
    protected function extrairContexto(object $agent): array
    {
        if ($agent instanceof ChatCopilotoAgent) {
            return [
                'business_id' => $agent->conversa->business_id !== null ? (int) $agent->conversa->business_id : null,
                'user_id' => $agent->conversa->user_id !== null ? (int) $agent->conversa->user_id : null,
                'conversation_id' => (int) $agent->conversa->id,
            ];
        }

        $businessId = null;
        foreach (['businessId', 'business_id'] as $prop) {
            if (property_exists($agent, $prop) && isset($agent->{$prop}) && is_numeric($agent->{$prop})) {
                $businessId = (int) $agent->{$prop};
                break;
            }
        }

        return ['business_id' => $businessId, 'user_id' => null, 'conversation_id' => null];
    }
}
