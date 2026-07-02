<?php

namespace Modules\Jana\Services\Ai;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Ai\Agents\BriefingAgent;
use Modules\Jana\Ai\Agents\ChatCopilotoAgent;
use Modules\Jana\Ai\Agents\SugestoesMetasAgent;
use Modules\Jana\Contracts\AiAdapter;
use Modules\Jana\Contracts\MemoriaContrato;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\Mensagem;
use Modules\Jana\Jobs\ExtrairFatosDaConversaJob;
use Modules\Jana\Services\ContextSnapshotService;
use Modules\Jana\Support\ContextoNegocio;

/**
 * LaravelAiSdkDriver — driver canônico de IA do Copiloto.
 *
 * Stack-alvo (verdade canônica — ADR 0035):
 *  - Camada A: laravel/ai (Laravel AI SDK oficial fev/2026) — este driver
 *  - Camada B: vizra/vizra-adk (sprints 2-3, ADR 0032)
 *  - Camada C: MemoriaContrato + Mem0RestDriver/MeilisearchDriver (sprints 4-5/8-10, ADRs 0031+0033)
 *
 * Substitui OpenAiDirectDriver (que dependia de openai-php/laravel abandonado).
 * Mantém sanitização de CPF/CNPJ herdada do driver legado.
 */
class LaravelAiSdkDriver implements AiAdapter
{
    public function gerarBriefing(ContextoNegocio $ctx): string
    {
        // D9.a Observability — span zero-cost ao redor do call LLM.
        return OtelHelper::spanBiz('jana.ai.gerar_briefing', function () use ($ctx) {
            if (config('copiloto.dry_run')) {
                return $this->fixtureBriefing($ctx);
            }

            $ctxSanitizado = $this->sanitizarContexto($ctx);
            $agent = new BriefingAgent($ctxSanitizado);

            try {
                $response = $agent->prompt($agent->montarPromptBriefing());

                Log::channel('copiloto-ai')->info('gerarBriefing', [
                    'business_id' => $ctx->businessId,
                    'driver' => 'laravel_ai_sdk',
                ]);

                // GAP D4 #5 — Prompt cache observability (Anthropic).
                $this->logPromptCacheUsage(
                    agent: 'BriefingAgent',
                    businessId: $ctx->businessId,
                    usage: $response->usage ?? null,
                );

                return (string) $response;
            } catch (\Throwable $e) {
                // D7 LGPD (Wave 10) — provider exception pode echo system message
                // contendo dados do business. Redact ANTES de logar.
                Log::channel('copiloto-ai')->error(
                    'gerarBriefing error: ' . $this->redactErrorMessage($e),
                );

                return $this->fixtureBriefing($ctx);
            }
        }, ['business_id' => $ctx->businessId, 'driver' => 'laravel_ai_sdk']);
    }

    public function sugerirMetas(ContextoNegocio $ctx, string $prompt): array
    {
        if (config('copiloto.dry_run')) {
            return $this->fixtureSugestoes($ctx);
        }

        $ctxSanitizado = $this->sanitizarContexto($ctx);
        $agent = new SugestoesMetasAgent($ctxSanitizado, $prompt);

        try {
            $response = $agent->prompt($agent->montarPromptSugestoes());

            Log::channel('copiloto-ai')->info('sugerirMetas', [
                'business_id' => $ctx->businessId,
                'driver' => 'laravel_ai_sdk',
            ]);

            $propostas = $response['propostas'] ?? null;

            if (! is_array($propostas) || empty($propostas)) {
                Log::channel('copiloto-ai')->warning('sugerirMetas: shape inválido, usando fixture');

                return $this->fixtureSugestoes($ctx);
            }

            foreach ($propostas as $p) {
                foreach (['nome', 'metrica', 'valor_alvo', 'periodo', 'dificuldade', 'racional', 'dependencias'] as $campo) {
                    if (! array_key_exists($campo, $p)) {
                        Log::channel('copiloto-ai')->warning("sugerirMetas: campo {$campo} ausente, usando fixture");

                        return $this->fixtureSugestoes($ctx);
                    }
                }

                if (! in_array($p['dificuldade'], ['facil', 'realista', 'ambicioso'])) {
                    return $this->fixtureSugestoes($ctx);
                }
            }

            return $propostas;
        } catch (\Throwable $e) {
            // D7 LGPD (Wave 10) — redact provider exception antes de log.
            Log::channel('copiloto-ai')->error(
                'sugerirMetas error: ' . $this->redactErrorMessage($e),
            );

            return $this->fixtureSugestoes($ctx);
        }
    }

    /**
     * Streaming nativo via laravel/ai SDK — API canônica.
     *
     * `Agent::stream()` (trait Promptable) retorna StreamableAgentResponse iterável.
     * Cada evento é um StreamEvent (TextDelta, StreamEnd, etc.). Aproveita o
     * MESMO enriquecimento (memoria + ContextoNegocio) do responderChat blocking,
     * sem duplicação de código.
     */
    public function responderChatStream(Conversa $conv, string $mensagem): \Generator
    {
        if (config('copiloto.dry_run')) {
            $fake = "(dry-run) Recebi: \"{$mensagem}\". Quando a IA estiver plugada, eu respondo de verdade.";
            foreach (str_split($fake, 8) as $chunk) {
                usleep(50_000);
                yield $chunk;
            }
            return;
        }

        // MEM-CACHE-1 — tenta cache semântico ANTES de chamar LLM.
        // Hit → emite resposta cacheada como 1 chunk e termina (zero token cost).
        // Miss → segue pipeline normal e GRAVA ao final.
        if (config('copiloto.cache.enabled', true)) {
            $cacheService = app(\Modules\Jana\Services\Cache\SemanticCacheService::class);
            if ($cached = $cacheService->buscar($conv, $mensagem)) {
                Log::channel('copiloto-ai')->info('responderChatStream: CACHE HIT', [
                    'conversa_id' => $conv->id,
                    'cache_id' => $cached->id,
                    'hits_acumulados' => $cached->hits,
                ]);
                yield $cached->resposta;
                return;
            }
        }

        // COPI-43 (LGPD): redacta PII BR antes de enviar pro LLM externo (vide responderChat).
        $mensagemPraLlm = $this->mascararDocumentos($mensagem);

        // MEM-HOT-2 — snapshot de contexto (reusado pela cascata clarify E pelo chat).
        $ctx = $this->snapshotContexto($conv);

        // JANA ADVISOR (Metade A — clarify reativo, proposta §10.4). Cascata
        // Decidir→Clarificar→Responder ANTES do recall/LLM: se a entrada é AMBÍGUA de
        // intenção, a Jana devolve a pergunta de maior ganho em vez de chutar. Flag
        // default-OFF; fail-open → segue normal. A pergunta vai como 1 chunk e termina.
        if ($pergunta = $this->talvezClarificar($conv, $mensagem, $mensagemPraLlm, $ctx)) {
            yield $pergunta;
            return;
        }

        // Mesma pipeline do responderChat() blocking — DRY.
        $recall = $this->recallMemoria($conv, $mensagem);
        $memoriaContexto = $recall['texto'];
        $memoriaIds = $recall['ids'];
        $agent = new ChatCopilotoAgent($conv, $memoriaContexto, $ctx);

        $startedAt = microtime(true);
        $tokensIn = 0;
        $tokensOut = 0;
        $textoCompleto = '';

        try {
            // laravel/ai Agent::stream() — retorna StreamableAgentResponse iterável.
            // Cada $event é um StreamEvent: TextDelta (delta), StreamEnd (usage), etc.
            $stream = $agent->stream($mensagemPraLlm);

            foreach ($stream as $event) {
                if ($event instanceof \Laravel\Ai\Streaming\Events\TextDelta) {
                    $delta = $event->delta;
                    if ($delta !== '') {
                        $textoCompleto .= $delta;
                        yield $delta;
                    }
                }
                // Outros eventos (StreamEnd, ToolCalls) são ignorados aqui — usage
                // fica disponível em $stream->usage após iteração completa.
            }

            // Após iteração: $stream->text + $stream->usage estão populados
            $tokensIn = $stream->usage?->promptTokens ?? 0;
            $tokensOut = $stream->usage?->completionTokens ?? 0;

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            Mensagem::where('conversa_id', $conv->id)
                ->where('role', 'assistant')
                ->latest('created_at')
                ->first()
                ?->update(['tokens_in' => $tokensIn, 'tokens_out' => $tokensOut]);

            Log::channel('copiloto-ai')->info('responderChatStream', [
                'conversa_id'           => $conv->id,
                'driver'                => 'laravel_ai_sdk',
                'memoria_recall_chars'  => strlen($memoriaContexto),
                'contexto_negocio'      => $ctx !== null ? 'on' : 'off',
                'tokens_in'             => $tokensIn,
                'tokens_out'            => $tokensOut,
                'chars_out'             => mb_strlen($textoCompleto),
                'duration_ms'           => $durationMs,
            ]);

            // GAP D4 #5 — Prompt cache observability (Anthropic).
            $this->logPromptCacheUsage(
                agent: 'ChatCopilotoAgent',
                businessId: $conv->business_id !== null ? (int) $conv->business_id : null,
                usage: $stream->usage ?? null,
                conversaId: (int) $conv->id,
            );

            // MEM-CACHE-1 — grava resposta no cache pra futuras queries similares
            if (config('copiloto.cache.enabled', true) && $textoCompleto !== '') {
                try {
                    app(\Modules\Jana\Services\Cache\SemanticCacheService::class)
                        ->gravar($conv, $mensagem, $textoCompleto, $tokensIn, $tokensOut, [
                            'duration_ms' => $durationMs,
                            'memoria_recall_chars' => strlen($memoriaContexto),
                        ]);
                } catch (\Throwable $e) {
                    Log::channel('copiloto-ai')->warning('SemanticCache: gravar falhou: ' . $e->getMessage());
                }
            }

            // MEM-FASE6 — registra uso dos fatos que foram injetados no prompt
            if (! empty($memoriaIds)) {
                app(\Modules\Jana\Services\Memoria\HitTrackerService::class)
                    ->registrarUso((int) $conv->business_id, $memoriaIds);
            }

            // Sprint 5 — extrair fatos em background (Horizon)
            if (config('copiloto.memoria.write_enabled', true)) {
                ExtrairFatosDaConversaJob::dispatch(
                    conversaId: $conv->id,
                    businessId: (int) $conv->business_id,
                    userId: (int) $conv->user_id,
                );
            }

            // MEM-S8-2 — comprime histórico se passou threshold de turnos (>15)
            if (config('copiloto.summarizer.enabled', true)) {
                try {
                    app(\Modules\Jana\Services\Memoria\ConversationSummarizer::class)
                        ->comprimirSeNecessario($conv);
                } catch (\Throwable $e) {
                    Log::channel('copiloto-ai')->warning('Summarizer: falhou (não-crítico): ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            // D7 LGPD (Wave 10) — redact provider exception (Anthropic/OpenAI
            // pode echo system message + user content). Output user-facing
            // já é truncado a 100 chars, mas reservamos PiiRedactor canônico.
            $sanitizado = $this->redactErrorMessage($e);
            Log::channel('copiloto-ai')->error('responderChatStream error: ' . $sanitizado);
            yield "\n\n_(Erro de IA: " . mb_substr($sanitizado, 0, 100) . ")_";
        }
    }

    public function responderChat(Conversa $conv, string $mensagem): string
    {
        if (config('copiloto.dry_run')) {
            return "(dry-run) Recebi: \"{$mensagem}\". Quando a IA estiver plugada, eu respondo de verdade.";
        }

        // MEM-CACHE-1 — antes de TUDO, tenta cache semântico.
        // Hit → retorno imediato com 0 token de custo.
        if (config('copiloto.cache.enabled', true)) {
            $cacheService = app(\Modules\Jana\Services\Cache\SemanticCacheService::class);
            if ($cached = $cacheService->buscar($conv, $mensagem)) {
                Log::channel('copiloto-ai')->info('responderChat: CACHE HIT', [
                    'conversa_id' => $conv->id,
                    'cache_id' => $cached->id,
                    'hits_acumulados' => $cached->hits,
                    'r$_economizado_total' => $cached->totalEconomizado(),
                ]);
                return $cached->resposta;
            }
        }

        // MEM-HOT-2 (ADR 0047, Caminho A do ADR 0046) — snapshot de contexto de
        // negócio (faturamento/clientes/metas reais) injetado no system prompt.
        // Cache 10min em ContextSnapshotService; degradação silenciosa em erro.
        // Subido p/ ANTES do recall: reusado pela cascata clarify E pelo chat.
        $ctx = $this->snapshotContexto($conv);

        // COPI-43 (LGPD): redacta PII BR antes de enviar pro LLM externo.
        // Mensagem original já persistida em jana_mensagens pelo controller — NÃO afeta
        // visualização do user no chat (que vê dado real). Recall memória usa $mensagem
        // original (busca interna, não sai pra rede). Aqui apenas o que vai pra OpenAI.
        $mensagemPraLlm = $this->mascararDocumentos($mensagem);

        // JANA ADVISOR (Metade A — clarify reativo, proposta §10.4). Cascata
        // Decidir→Clarificar→Responder ANTES do recall/LLM: se a entrada é AMBÍGUA de
        // intenção, devolve a pergunta de maior ganho em vez de chutar. Flag default-OFF;
        // fail-open → segue normal. Sai sem gravar cache nem extrair fatos (não houve resposta).
        if ($pergunta = $this->talvezClarificar($conv, $mensagem, $mensagemPraLlm, $ctx)) {
            return $pergunta;
        }

        // Sprint 5 (ADR 0036) — recall de memória semântica antes de chamar LLM.
        $recall = $this->recallMemoria($conv, $mensagem);
        $memoriaContexto = $recall['texto'];
        $memoriaIds = $recall['ids'];

        $agent = new ChatCopilotoAgent($conv, $memoriaContexto, $ctx);

        // MEM-OTEL-1 (ADR 0051) — clock pra medir gen_ai.response.duration_ms
        $startedAt = microtime(true);

        try {
            $response = $agent->prompt($mensagemPraLlm);
            $texto = (string) $response;

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            Mensagem::where('conversa_id', $conv->id)
                ->where('role', 'assistant')
                ->latest('created_at')
                ->first()
                ?->update([
                    'tokens_in' => $response->usage->promptTokens ?? null,
                    'tokens_out' => $response->usage->completionTokens ?? null,
                ]);

            Log::channel('copiloto-ai')->info('responderChat', [
                'conversa_id' => $conv->id,
                'driver' => 'laravel_ai_sdk',
                'memoria_recall_chars' => strlen($memoriaContexto),
                'contexto_negocio' => $ctx !== null ? 'on' : 'off',
            ]);

            // GAP D4 #5 — Prompt cache observability (Anthropic).
            // Log estruturado quando há cache hit/write — análise via
            // `tail -f storage/logs/copiloto-ai.log | grep prompt_cache`.
            $this->logPromptCacheUsage(
                agent: 'ChatCopilotoAgent',
                businessId: $conv->business_id !== null ? (int) $conv->business_id : null,
                usage: $response->usage ?? null,
                conversaId: (int) $conv->id,
            );

            // MEM-OTEL-1 (ADR 0051) — emite atributos gen_ai.* OpenTelemetry
            // semantic conventions. Plugável em Datadog/Langfuse/Arize sem rename.
            $this->emitirOtelGenAi($conv, $mensagem, $response, $durationMs, ok: true);

            // ADR 0132 — trace Langfuse de SUCESSO agora emitido globalmente pelo
            // LangfuseAgentTelemetryListener (event AgentPrompted do laravel/ai).
            // Emissão local removida 2026-07-02 pra não duplicar trace.
            // Caminho de ERRO (catch abaixo) mantém emitirLangfuseTrace — exception
            // aborta o SDK antes do event, então o listener nunca vê a falha.

            // MEM-CACHE-1 — grava resposta no cache pra futuras queries similares.
            if (config('copiloto.cache.enabled', true) && $texto !== '') {
                try {
                    app(\Modules\Jana\Services\Cache\SemanticCacheService::class)
                        ->gravar($conv, $mensagem, $texto,
                            $response->usage->promptTokens ?? null,
                            $response->usage->completionTokens ?? null,
                            ['duration_ms' => $durationMs]);
                } catch (\Throwable $e) {
                    Log::channel('copiloto-ai')->warning('SemanticCache: gravar falhou: ' . $e->getMessage());
                }
            }

            // MEM-FASE6 — registra uso dos fatos injetados no prompt
            if (! empty($memoriaIds)) {
                app(\Modules\Jana\Services\Memoria\HitTrackerService::class)
                    ->registrarUso((int) $conv->business_id, $memoriaIds);
            }

            // Sprint 5 — após resposta, extrair fatos novos em background (Horizon).
            if (config('copiloto.memoria.write_enabled', true)) {
                ExtrairFatosDaConversaJob::dispatch(
                    conversaId: $conv->id,
                    businessId: (int) $conv->business_id,
                    userId: (int) $conv->user_id,
                );
            }

            return $texto;
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            // D7 LGPD (Wave 10) — redact provider exception ANTES de log.
            Log::channel('copiloto-ai')->error(
                'responderChat error: ' . $this->redactErrorMessage($e),
            );

            // OTel emite mesmo em erro — observability precisa do span de falha
            $this->emitirOtelGenAi($conv, $mensagem, null, $durationMs, ok: false, error: $e);

            // ADR 0132 — Langfuse trace de erro (level=ERROR).
            $this->emitirLangfuseTrace(
                tool: 'jana-chat',
                conv: $conv,
                input: $mensagem,
                output: null,
                tokensIn: null,
                tokensOut: null,
                durationMs: $durationMs,
                ok: false,
                error: $e,
            );

            return 'Estou sem conexão com IA no momento. Você quer criar a meta manualmente?';
        }
    }

    /**
     * ADR 0132 + ADR 0037 §GAP-1 — emite trace + generation pra Langfuse v3 self-host.
     *
     * Pattern "trace + generation":
     *  - startTrace marca operação raiz (chat/brief/sugerir) com business_id metadata
     *  - recordGeneration registra chamada LLM (model/tokens/latency) DENTRO do trace
     *  - endTrace fecha trace com output final + status (ERROR se falhou)
     *
     * Fail-silent — wrap try/catch garante NUNCA quebrar chat por causa de telemetria.
     * O LangfuseClient internamente já é fail-open (queue + Http warnings), mas
     * defense-in-depth aqui evita exception em path do user (linha 332-342 captura).
     */
    protected function emitirLangfuseTrace(
        string $tool,
        Conversa $conv,
        string $input,
        ?string $output,
        ?int $tokensIn,
        ?int $tokensOut,
        int $durationMs,
        bool $ok,
        ?\Throwable $error = null,
        int $memoriaRecallChars = 0,
    ): void {
        try {
            /** @var \Modules\Jana\Services\Telemetry\LangfuseClient $client */
            $client = app(\Modules\Jana\Services\Telemetry\LangfuseClient::class);

            $sistema = (string) config('ai.default', 'openai');
            $modelo  = (string) config(
                "ai.providers.{$sistema}.models.text.default",
                config('copiloto.openai.model_chat', 'gpt-4o-mini'),
            );

            $traceId = $client->startTrace([
                'name' => $tool,
                'business_id' => $conv->business_id !== null ? (int) $conv->business_id : null,
                'user_id' => (int) $conv->user_id,
                'conversation_id' => (int) $conv->id,
                'tool' => $tool,
                'input' => $input,
                'metadata' => [
                    'driver' => 'laravel_ai_sdk',
                    'memoria_recall_chars' => $memoriaRecallChars,
                ],
            ]);

            $client->recordGeneration($traceId, [
                'name' => "{$tool}-response",
                'model' => $modelo,
                'input' => $input,
                'output' => $output,
                'usage' => [
                    'input' => $tokensIn ?? 0,
                    'output' => $tokensOut ?? 0,
                ],
                'duration_ms' => $durationMs,
                'level' => $ok ? 'DEFAULT' : 'ERROR',
                'status_message' => $error !== null ? substr($error->getMessage(), 0, 200) : null,
            ]);

            $client->endTrace($traceId, [
                'output' => $output,
                'level' => $ok ? null : 'ERROR',
                'status_message' => $error !== null ? get_class($error) . ': ' . $error->getMessage() : null,
            ]);
        } catch (\Throwable $tracingErr) {
            // Telemetria nunca quebra chat — defense-in-depth.
            Log::channel('copiloto-ai')->debug('emitirLangfuseTrace falhou: ' . $tracingErr->getMessage());
        }
    }

    /**
     * MEM-OTEL-1 (ADR 0051) — emite 1 evento estruturado seguindo
     * OpenTelemetry GenAI semantic conventions (atributos `gen_ai.*`).
     *
     * Hoje grava no log channel `otel-gen-ai`; quando OTel SDK PHP entrar,
     * o mesmo dict vira `Span::setAttributes()`. Datadog/Langfuse/Arize
     * mapeiam direto sem rename.
     *
     * Atributos inclusos:
     *   - gen_ai.system / gen_ai.request.model / gen_ai.operation.name
     *   - gen_ai.usage.input_tokens / output_tokens
     *   - gen_ai.response.duration_ms / gen_ai.response.finish_reason
     *   - gen_ai.business_id (custom — multi-tenant audit LGPD)
     *   - gen_ai.conversation.id / gen_ai.user.id
     *   - gen_ai.copiloto.memoria_recall_chars / contexto_negocio (custom)
     *
     * Falha silente — telemetria nunca quebra chat.
     */
    protected function emitirOtelGenAi(
        Conversa $conv,
        string $prompt,
        mixed $response,
        int $durationMs,
        bool $ok,
        ?\Throwable $error = null,
    ): void {
        try {
            $sistema = (string) config('ai.default', 'openai');
            $modelo  = (string) config(
                "ai.providers.{$sistema}.models.text.default",
                config('copiloto.openai.model_chat', 'gpt-4o-mini'),
            );

            $attrs = [
                // Padrão OTel GenAI
                'gen_ai.system'                  => $sistema,
                'gen_ai.request.model'           => $modelo,
                'gen_ai.operation.name'          => 'chat',
                'gen_ai.response.duration_ms'    => $durationMs,
                // Identidade (custom, multi-tenant + LGPD audit)
                'gen_ai.business_id'             => $conv->business_id !== null ? (int) $conv->business_id : null,
                'gen_ai.user.id'                 => (int) $conv->user_id,
                'gen_ai.conversation.id'         => (int) $conv->id,
                // Contexto Copiloto (custom)
                'gen_ai.copiloto.prompt_chars'   => strlen($prompt),
                'gen_ai.copiloto.driver'         => 'laravel_ai_sdk',
            ];

            if ($ok && $response !== null) {
                $attrs['gen_ai.usage.input_tokens']  = $response->usage->promptTokens ?? null;
                $attrs['gen_ai.usage.output_tokens'] = $response->usage->completionTokens ?? null;
                $attrs['gen_ai.response.finish_reason'] = $response->finishReason ?? 'stop';
            } else {
                $attrs['gen_ai.error.type']    = $error !== null ? get_class($error) : 'unknown';
                $attrs['gen_ai.error.message'] = $error !== null ? $error->getMessage() : 'unknown';
                $attrs['gen_ai.response.finish_reason'] = 'error';
            }

            Log::channel('otel-gen-ai')->info('gen_ai.span', $attrs);
        } catch (\Throwable $logErr) {
            // Never crash chat over telemetry
            Log::channel('copiloto-ai')->debug('emitirOtelGenAi falhou: ' . $logErr->getMessage());
        }
    }

    /**
     * Busca top-K memórias relevantes via MemoriaContrato e retorna texto pronto pra
     * injetar como system additional message. Falha silente — recall não pode quebrar chat.
     *
     * @return array{texto:string, ids:int[]}
     */
    protected function recallMemoria(Conversa $conv, string $query): array
    {
        if (! config('copiloto.memoria.recall_enabled', true)) {
            return ['texto' => '', 'ids' => []];
        }

        try {
            /** @var MemoriaContrato $memoria */
            $memoria = app(MemoriaContrato::class);
            $topK = (int) config('copiloto.memoria.meilisearch.top_k_default', 5);

            $resultados = $memoria->buscar(
                businessId: (int) $conv->business_id,
                userId: (int) $conv->user_id,
                query: $query,
                topK: $topK,
            );

            if (empty($resultados)) {
                return ['texto' => '', 'ids' => []];
            }

            $ids = collect($resultados)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

            $linhas = collect($resultados)
                ->map(fn ($m) => '- ' . $m->fato)
                ->implode("\n");

            return [
                'texto' => "Você lembra dos seguintes fatos sobre este usuário/business:\n{$linhas}\n",
                'ids'   => $ids,
            ];
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('recallMemoria falhou (degradação silenciosa)', [
                'conversa_id' => $conv->id,
                'error' => $e->getMessage(),
            ]);
            return ['texto' => '', 'ids' => []];
        }
    }

    /**
     * MEM-HOT-2 (ADR 0047) — busca ContextoNegocio do business da conversa pra
     * injetar no system prompt do ChatCopilotoAgent. Falha silente: se o
     * snapshot quebrar (DB lento, query erro), retorna null e o chat funciona
     * sem contexto rico (degradação como `recallMemoria`).
     */
    protected function snapshotContexto(Conversa $conv): ?ContextoNegocio
    {
        if ($conv->business_id === null) {
            return null;
        }

        try {
            $service = app(ContextSnapshotService::class);
            $ctx = $service->paraBusiness((int) $conv->business_id);

            // Sanitiza CPF/CNPJ no nome/observações antes de mandar pra LLM.
            return $this->sanitizarContexto($ctx);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('snapshotContexto falhou (degradação silenciosa)', [
                'conversa_id' => $conv->id,
                'business_id' => $conv->business_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * JANA ADVISOR (Metade A — clarify reativo, proposta §10.4).
     *
     * Roda a cascata Decidir→Clarificar→Responder via ClarifyCascadeService. Retorna a
     * PERGUNTA de maior ganho de informação quando a entrada é ambígua de intenção, ou
     * `null` quando o chat deve seguir e responder normalmente.
     *
     * Contrato de segurança:
     *   - Flag `copiloto.clarify.enabled` default-OFF → retorna null sem custo (no-op).
     *   - FAIL-OPEN: o service captura tudo internamente; aqui ainda há try/catch de
     *     defense-in-depth pra NUNCA quebrar o chat por causa do advisor.
     *
     * `$ctx` é reusado (já snapshotado/sanitizado) pra não duplicar consulta.
     */
    protected function talvezClarificar(
        Conversa $conv,
        string $mensagemOriginal,
        string $mensagemRedigida,
        ?ContextoNegocio $ctx,
    ): ?string {
        if (! (bool) config('copiloto.clarify.enabled', false)) {
            return null;
        }

        try {
            $resultado = app(\Modules\Jana\Services\Ai\Clarify\ClarifyCascadeService::class)
                ->decidir($conv, $mensagemOriginal, $mensagemRedigida, $ctx);

            return $resultado->deveClarificar() ? $resultado->pergunta : null;
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('talvezClarificar fail-open (segue resposta)', [
                'conversa_id' => $conv->id,
                'error' => $this->redactErrorMessage($e),
            ]);

            return null;
        }
    }

    /**
     * Sanitiza PII BR antes de enviar pra LLM externo.
     *
     * COPI-43 (LGPD-blocker, 2026-05-06): substitui regex inline (cobria só CPF+CNPJ
     * formatados) por PiiRedactor canônico — agora cobre 5 tipos: CPF, CNPJ, EMAIL,
     * CEP, PHONE BR (com/sem máscara). 18 tests Pest cobrindo edge cases.
     *
     * Flag COPILOTO_PII_REDACT_DISABLE (default false): permite desativar emergencialmente
     * se causar regressão — mas o redactor é mais conservador (redacta MAIS, não menos).
     */
    public function mascararDocumentos(string $texto): string
    {
        if (env('COPILOTO_PII_REDACT_DISABLE', false)) {
            // Fallback ao regex inline antigo (CPF + CNPJ apenas).
            $texto = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', 'XXX.XXX.XXX-NN', $texto);
            $texto = preg_replace('/\b\d{2}\.?\d{3}\.?\d{3}\/?0001-?\d{2}\b/', 'XX.XXX.XXX/0001-NN', $texto);

            return $texto;
        }

        return app(\Modules\Jana\Services\Privacy\PiiRedactor::class)->redact($texto);
    }

    /**
     * D7 LGPD (Wave 10) — Redacta exception message ANTES de log.
     *
     * Provider externo (Anthropic/OpenAI) pode lançar exception cujo `getMessage()`
     * inclui partes do prompt original (system message com nome business + last
     * user message com CPF/CNPJ/email). Logar raw = vazamento Tier 0 LGPD ADR 0093.
     *
     * Trunca a 500 chars + aplica PiiRedactor canônico (5 tipos PII BR cobertos:
     * CPF, CNPJ, EMAIL, CEP, PHONE BR). ADR 0035 stack IA canônica preservada.
     *
     * Fail-open: se PiiRedactor falhar (não deveria — service simples regex), cai
     * pra truncate puro pra nunca quebrar o logger.
     */
    protected function redactErrorMessage(\Throwable $e): string
    {
        $raw = mb_substr($e->getMessage(), 0, 500);
        try {
            return app(\Modules\Jana\Services\Privacy\PiiRedactor::class)->redact($raw);
        } catch (\Throwable $redactErr) {
            // Logger nunca quebra por causa de PII redaction — fallback seguro.
            return $raw;
        }
    }

    protected function sanitizarContexto(ContextoNegocio $ctx): ContextoNegocio
    {
        return new ContextoNegocio(
            businessId:     $ctx->businessId,
            businessName:   $this->mascararDocumentos($ctx->businessName),
            faturamento90d: $ctx->faturamento90d,
            clientesAtivos: $ctx->clientesAtivos,
            modulosAtivos:  $ctx->modulosAtivos,
            metasAtivas:    $ctx->metasAtivas,
            observacoes:    $ctx->observacoes !== null ? $this->mascararDocumentos($ctx->observacoes) : null,
        );
    }

    protected function fixtureBriefing(ContextoNegocio $ctx): string
    {
        $nomeBiz = $ctx->businessName;
        $clientes = $ctx->clientesAtivos;

        return "Olá! Sou seu Copiloto. Estou olhando {$nomeBiz} — vejo {$clientes} clientes ativos "
            . 'e ' . count($ctx->faturamento90d) . ' meses de faturamento nos últimos 90 dias. '
            . 'Quer que eu sugira metas pro próximo período? É só pedir.';
    }

    /**
     * GAP D4 #5 — Loga métricas de Anthropic prompt caching.
     *
     * `Laravel\Ai\Responses\Data\Usage` expõe:
     *   - `cacheReadInputTokens` → tokens reaproveitados do cache (90% mais
     *     baratos que input regular)
     *   - `cacheWriteInputTokens` → tokens gravados pela primeira vez (~25%
     *     mais caros — investimento que paga no próximo hit)
     *
     * Log canônico `prompt_cache_event` permite agregação:
     *   tail -f storage/logs/copiloto-ai.log | grep prompt_cache_event
     *   → calcular cache_hit_rate = cache_read / (cache_read + regular_input)
     *
     * Meta: 74% atual (ADR 0053) → 85%+ pós-implementação (validação 2sem
     * pós-deploy via aggregation no log channel ou Langfuse trace).
     *
     * Fail-silent: telemetria nunca quebra chat.
     */
    protected function logPromptCacheUsage(
        string $agent,
        ?int $businessId,
        mixed $usage,
        ?int $conversaId = null,
    ): void {
        try {
            if ($usage === null) {
                return;
            }

            $cacheRead   = (int) ($usage->cacheReadInputTokens ?? 0);
            $cacheWrite  = (int) ($usage->cacheWriteInputTokens ?? 0);
            $promptToks  = (int) ($usage->promptTokens ?? 0);

            // Só loga se houve qualquer atividade de cache (evita ruído quando
            // provider não-Anthropic ou cache off).
            if ($cacheRead === 0 && $cacheWrite === 0) {
                return;
            }

            // Taxa de hit calculada local pra economizar pipeline de agregação
            // posterior (input regular = prompt - cache_read).
            $regularInput = max($promptToks - $cacheRead, 0);
            $totalInput   = $cacheRead + $regularInput;
            $hitRate      = $totalInput > 0 ? round($cacheRead / $totalInput, 4) : 0.0;

            Log::channel('copiloto-ai')->info('prompt_cache_event', [
                'agent'                  => $agent,
                'business_id'            => $businessId,
                'conversa_id'            => $conversaId,
                'cache_read_tokens'      => $cacheRead,
                'cache_creation_tokens'  => $cacheWrite,
                'regular_input_tokens'   => $regularInput,
                'cache_hit_rate'         => $hitRate,
                'event_type'             => $cacheRead > 0 ? 'hit' : 'write',
            ]);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->debug('logPromptCacheUsage falhou: ' . $e->getMessage());
        }
    }

    protected function fixtureSugestoes(ContextoNegocio $ctx): array
    {
        return [
            [
                'nome' => 'Faturamento — conservador',
                'metrica' => 'faturamento',
                'valor_alvo' => 120000,
                'periodo' => 'mensal',
                'dificuldade' => 'facil',
                'racional' => 'Manter base atual com +10% sobre média 90d.',
                'dependencias' => [],
            ],
            [
                'nome' => 'Faturamento — realista',
                'metrica' => 'faturamento',
                'valor_alvo' => 180000,
                'periodo' => 'mensal',
                'dificuldade' => 'realista',
                'racional' => '+50% requer campanha em clientes B + upsell de módulos.',
                'dependencias' => ['Grow', 'PontoWr2 em 2 clientes'],
            ],
            [
                'nome' => 'Faturamento — ambicioso',
                'metrica' => 'faturamento',
                'valor_alvo' => 300000,
                'periodo' => 'mensal',
                'dificuldade' => 'ambicioso',
                'racional' => 'Alavancagem total: ativar 49 businesses + captar 10 novos.',
                'dependencias' => ['Grow', 'Campanha reativação', 'Comercial dedicado'],
            ],
        ];
    }
}
