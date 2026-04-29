<?php

namespace Modules\Copiloto\Services\Ai;

use Illuminate\Support\Facades\Log;
use Modules\Copiloto\Ai\Agents\BriefingAgent;
use Modules\Copiloto\Ai\Agents\ChatCopilotoAgent;
use Modules\Copiloto\Ai\Agents\SugestoesMetasAgent;
use Modules\Copiloto\Contracts\AiAdapter;
use Modules\Copiloto\Contracts\MemoriaContrato;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Entities\Mensagem;
use Modules\Copiloto\Jobs\ExtrairFatosDaConversaJob;
use Modules\Copiloto\Services\ContextSnapshotService;
use Modules\Copiloto\Support\ContextoNegocio;

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

            return (string) $response;
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('gerarBriefing error: ' . $e->getMessage());

            return $this->fixtureBriefing($ctx);
        }
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
            Log::channel('copiloto-ai')->error('sugerirMetas error: ' . $e->getMessage());

            return $this->fixtureSugestoes($ctx);
        }
    }

    /**
     * Streaming nativo bypassando Vizra ADK (que ainda não tem stream API).
     *
     * Estratégia: replica o setup do system prompt do ChatCopilotoAgent (memoria
     * recall + ContextoNegocio) e chama OpenAI direto com stream. Preserva o
     * mesmo enriquecimento contextual da versão blocking.
     *
     * Vizra foi rejeitada via ADR 0048 — esta migração começa pelo streaming.
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

        // Mesma pipeline de enriquecimento do responderChat() blocking
        $memoriaContexto = $this->recallMemoria($conv, $mensagem);
        $ctx = $this->snapshotContexto($conv);

        // Re-usa o sistema de instructions do ChatCopilotoAgent — fonte de
        // verdade pra ContextoNegocio + memoria recall format.
        $agent = new ChatCopilotoAgent($conv, $memoriaContexto, $ctx);
        $systemPrompt = (string) $agent->instructions();

        // Histórico (até 20 últimas msgs user/assistant)
        $historico = $conv->mensagens()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($historico as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }
        // Garante a msg atual no fim
        $last = end($messages);
        if ($last['role'] !== 'user' || $last['content'] !== $mensagem) {
            $messages[] = ['role' => 'user', 'content' => $mensagem];
        }

        $startedAt = microtime(true);
        $tokensIn = 0;
        $tokensOut = 0;
        $textoCompleto = '';

        try {
            $stream = \OpenAI\Laravel\Facades\OpenAI::chat()->createStreamed([
                'model'         => config('copiloto.openai.model_chat', 'gpt-4o-mini'),
                'max_tokens'    => config('copiloto.openai.max_tokens_chat', 2000),
                'temperature'   => (float) config('copiloto.openai.temperature', 0.7),
                'messages'      => $messages,
                'stream_options' => ['include_usage' => true],
            ]);

            foreach ($stream as $response) {
                $delta = $response->choices[0]->delta->content ?? null;
                if (! empty($delta)) {
                    $textoCompleto .= $delta;
                    yield $delta;
                }

                if (isset($response->usage)) {
                    $tokensIn = $response->usage->promptTokens ?? 0;
                    $tokensOut = $response->usage->completionTokens ?? 0;
                }
            }

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            // Persiste tokens na latest assistant message (controller cria após stream)
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

            // Sprint 5 — extrair fatos novos em background (Horizon)
            if (config('copiloto.memoria.write_enabled', true)) {
                ExtrairFatosDaConversaJob::dispatch(
                    conversaId: $conv->id,
                    businessId: (int) $conv->business_id,
                    userId: (int) $conv->user_id,
                );
            }
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->error('responderChatStream error: ' . $e->getMessage());
            yield "\n\n_(Erro de IA: " . substr($e->getMessage(), 0, 100) . ")_";
        }
    }

    public function responderChat(Conversa $conv, string $mensagem): string
    {
        if (config('copiloto.dry_run')) {
            return "(dry-run) Recebi: \"{$mensagem}\". Quando a IA estiver plugada, eu respondo de verdade.";
        }

        // Sprint 5 (ADR 0036) — recall de memória semântica antes de chamar LLM.
        $memoriaContexto = $this->recallMemoria($conv, $mensagem);

        // MEM-HOT-2 (ADR 0047, Caminho A do ADR 0046) — snapshot de contexto de
        // negócio (faturamento/clientes/metas reais) injetado no system prompt.
        // Cache 10min em ContextSnapshotService; degradação silenciosa em erro.
        $ctx = $this->snapshotContexto($conv);

        $agent = new ChatCopilotoAgent($conv, $memoriaContexto, $ctx);

        // MEM-OTEL-1 (ADR 0051) — clock pra medir gen_ai.response.duration_ms
        $startedAt = microtime(true);

        try {
            $response = $agent->prompt($mensagem);
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

            // MEM-OTEL-1 (ADR 0051) — emite atributos gen_ai.* OpenTelemetry
            // semantic conventions. Plugável em Datadog/Langfuse/Arize sem rename.
            $this->emitirOtelGenAi($conv, $mensagem, $response, $durationMs, ok: true);

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

            Log::channel('copiloto-ai')->error('responderChat error: ' . $e->getMessage());

            // OTel emite mesmo em erro — observability precisa do span de falha
            $this->emitirOtelGenAi($conv, $mensagem, null, $durationMs, ok: false, error: $e);

            return 'Estou sem conexão com IA no momento. Você quer criar a meta manualmente?';
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
     */
    protected function recallMemoria(Conversa $conv, string $query): string
    {
        if (! config('copiloto.memoria.recall_enabled', true)) {
            return '';
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
                return '';
            }

            $linhas = collect($resultados)
                ->map(fn ($m) => '- ' . $m->fato)
                ->implode("\n");

            return "Você lembra dos seguintes fatos sobre este usuário/business:\n{$linhas}\n";
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('recallMemoria falhou (degradação silenciosa)', [
                'conversa_id' => $conv->id,
                'error' => $e->getMessage(),
            ]);
            return '';
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

    public function mascararDocumentos(string $texto): string
    {
        $texto = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', 'XXX.XXX.XXX-NN', $texto);
        $texto = preg_replace('/\b\d{2}\.?\d{3}\.?\d{3}\/?0001-?\d{2}\b/', 'XX.XXX.XXX/0001-NN', $texto);

        return $texto;
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
