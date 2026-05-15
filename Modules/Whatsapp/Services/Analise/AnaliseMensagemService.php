<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Analise;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Ai\Agents\AnalisarMensagemAgent;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Throwable;

/**
 * US-WA-095 — Analisa 1 mensagem inbound usando Jana e persiste resultado.
 *
 * Stateless. Idempotente — se mensagem já tem `analise_at` preenchido,
 * pula silenciosamente (re-run safe).
 *
 * Fail-open: erro Jana NÃO rethrow. Loga `whatsapp_analise_falhou` métrica
 * e segue. Mensagem fica sem análise mas pipeline não trava.
 *
 * Trust L1 (Camada B Jana): só lê + grava colunas dedicadas. NÃO altera
 * status nem dispara side-effects. NÃO envia msg ao cliente.
 *
 * @see Modules/Jana/Ai/Agents/AnalisarMensagemAgent.php
 */
class AnaliseMensagemService
{
    public const SKIP_REASON_DISABLED = 'disabled';
    public const SKIP_REASON_ALREADY_DONE = 'already_done';
    public const SKIP_REASON_NOT_INBOUND = 'not_inbound';
    public const SKIP_REASON_EMPTY_BODY = 'empty_body';
    public const SKIP_REASON_INTERNAL_NOTE = 'internal_note';
    public const SKIP_REASON_TYPE_UNSUPPORTED = 'type_unsupported';

    /**
     * Analisa 1 mensagem. Retorna true se persistiu, false se pulou/falhou.
     *
     * Use `dispatchableForMessage()` no Listener pra rotear via Job; chamada
     * direta deste método rola síncrono (usado pelo Command de backlog).
     */
    public function analisar(Message $message): bool
    {
        // Gate 1 — feature flag global
        if (! (bool) config('whatsapp.analise.enabled', false)) {
            $this->logSkip($message, self::SKIP_REASON_DISABLED);
            return false;
        }

        // Gate 2 — só inbound (outbound do bot/atendente não interessa pra voz-cliente)
        if ($message->direction !== Message::DIRECTION_INBOUND) {
            $this->logSkip($message, self::SKIP_REASON_NOT_INBOUND);
            return false;
        }

        // Gate 3 — idempotência (já analisada)
        if ($message->analise_at !== null) {
            $this->logSkip($message, self::SKIP_REASON_ALREADY_DONE);
            return false;
        }

        // Gate 4 — nota interna do atendente não é msg do cliente
        if ((bool) $message->is_internal_note) {
            $this->logSkip($message, self::SKIP_REASON_INTERNAL_NOTE);
            return false;
        }

        // Gate 5 — só text/interactive/template (mídia entra após transcription Whisper)
        $allowedTypes = ['text', 'interactive', 'template'];
        if (! in_array($message->type, $allowedTypes, true)) {
            $this->logSkip($message, self::SKIP_REASON_TYPE_UNSUPPORTED);
            return false;
        }

        $body = trim((string) $message->body);
        if ($body === '') {
            $this->logSkip($message, self::SKIP_REASON_EMPTY_BODY);
            return false;
        }

        // SUPERADMIN: service roda em Job sem session HTTP — withoutGlobalScope
        // + filtro defensivo where('business_id') preserva Tier 0 (ADR 0093).
        $conversation = Conversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $message->business_id)
            ->where('id', $message->conversation_id)
            ->first();

        $businessName = $this->resolveBusinessName($message->business_id);
        $contactName = $conversation?->contact_name;
        $previousContext = $this->buildPreviousContext($message, $conversation);

        $modelo = (string) config('whatsapp.analise.model', 'gpt-4o-mini');
        $startedAtMs = (int) (microtime(true) * 1000);

        try {
            $agent = new AnalisarMensagemAgent(
                businessName: $businessName,
                messageBody: $body,
                contactName: $contactName,
                previousContext: $previousContext,
            );

            $response = $agent->prompt($agent->montarPrompt());

            $categoria = $this->ensureEnum($response['categoria'] ?? null, AnalisarMensagemAgent::CATEGORIAS, 'outro');
            $tema = $this->ensureEnum($response['tema'] ?? null, AnalisarMensagemAgent::TEMAS, 'outro');
            $urgencia = $this->ensureEnum($response['urgencia'] ?? null, AnalisarMensagemAgent::URGENCIAS, 'baixa');
            $resumo = trim((string) ($response['resumo'] ?? ''));
            // Hard cap 280 chars (alinhado coluna VARCHAR migration)
            if (mb_strlen($resumo) > 280) {
                $resumo = mb_substr($resumo, 0, 277) . '...';
            }

            // Token + cost estimate (proxy ~4 chars/token PT-BR; Service não tem
            // acesso ao usage real do laravel/ai 0.6 — quando SDK expor, troca).
            $tokensIn = $this->estimateTokens($agent->instructions() . "\n" . $agent->montarPrompt());
            $tokensOut = $this->estimateTokens(json_encode($response, JSON_UNESCAPED_UNICODE) ?: '');
            $costCentavos = $this->calcCostCentavos($tokensIn, $tokensOut, $modelo);

            // Persist defensive — withoutGlobalScope + Eloquent update direto
            // (não usa fillable pq Message é append-only nas colunas de domínio;
            // colunas analise_* são tracking metadata, write OK)
            Message::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('id', $message->id)
                ->update([
                    'analise_categoria' => $categoria,
                    'analise_tema' => $tema,
                    'analise_urgencia' => $urgencia,
                    'analise_resumo' => $resumo,
                    'analise_at' => now(),
                    'analise_model' => $modelo,
                    'analise_tokens_in' => $tokensIn,
                    'analise_tokens_out' => $tokensOut,
                    'analise_cost_centavos' => $costCentavos,
                ]);

            $durationMs = (int) (microtime(true) * 1000) - $startedAtMs;

            // Métrica OTel lightweight (pattern PersistHistorySyncBatchJob)
            Log::channel('single')->info('[whatsapp.analise] mensagem analisada', [
                'metric_name' => 'whatsapp_analise_ok',
                'business_id' => $message->business_id,
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'categoria' => $categoria,
                'tema' => $tema,
                'urgencia' => $urgencia,
                'modelo' => $modelo,
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'cost_centavos' => $costCentavos,
                'duration_ms' => $durationMs,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::channel('single')->warning('[whatsapp.analise] falhou (fail-open)', [
                'metric_name' => 'whatsapp_analise_falhou',
                'business_id' => $message->business_id,
                'message_id' => $message->id,
                'modelo' => $modelo,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Resolve business name pra prompt (não cache — chamada rara, msg-level).
     */
    protected function resolveBusinessName(int $businessId): string
    {
        try {
            $name = \DB::table('business')->where('id', $businessId)->value('name');
            return is_string($name) && $name !== '' ? $name : "Business #{$businessId}";
        } catch (Throwable) {
            return "Business #{$businessId}";
        }
    }

    /**
     * Constrói contexto (últimas 3 msgs da conversa, exceto a atual).
     * Limita a 800 chars total pra controlar custo.
     */
    protected function buildPreviousContext(Message $message, ?Conversation $conversation): ?string
    {
        if ($conversation === null) {
            return null;
        }

        try {
            $previous = Message::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $message->business_id)
                ->where('conversation_id', $message->conversation_id)
                ->where('id', '<', $message->id)
                ->where('is_internal_note', false)
                ->whereIn('type', ['text', 'interactive', 'template'])
                ->orderByDesc('id')
                ->limit(3)
                ->get(['direction', 'body']);

            if ($previous->isEmpty()) {
                return null;
            }

            $lines = $previous->reverse()->map(function (Message $m): string {
                $dir = $m->direction === Message::DIRECTION_INBOUND ? 'CLIENTE' : 'EMPRESA';
                $body = trim((string) $m->body);
                if (mb_strlen($body) > 200) {
                    $body = mb_substr($body, 0, 197) . '...';
                }
                return "{$dir}: {$body}";
            })->implode("\n");

            return mb_strlen($lines) > 800 ? mb_substr($lines, 0, 797) . '...' : $lines;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Garante enum válido. Se LLM retornou valor fora do schema, usa fallback.
     *
     * @param  list<string>  $whitelist
     */
    protected function ensureEnum(mixed $value, array $whitelist, string $fallback): string
    {
        if (! is_string($value)) {
            return $fallback;
        }
        return in_array($value, $whitelist, true) ? $value : $fallback;
    }

    /**
     * Estimativa proxy: 1 token ≈ 4 chars (PT-BR, gpt tokenizer).
     */
    protected function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Custo em centavos BRL. Pega pricing canônico de `copiloto.ai.pricing.{model}`.
     * Default fallback: gpt-4o-mini ($0.00015 input / $0.0006 output por 1k).
     */
    protected function calcCostCentavos(int $tokensIn, int $tokensOut, string $model): int
    {
        $pricing = config("copiloto.ai.pricing.{$model}");
        $cambio = (float) config('copiloto.ai.cambio_brl_usd', 5.50);

        if (! is_array($pricing) || ! isset($pricing['input'], $pricing['output'])) {
            $inputUsdPer1k = 0.00015;
            $outputUsdPer1k = 0.0006;
        } else {
            $inputUsdPer1k = (float) $pricing['input'];
            $outputUsdPer1k = (float) $pricing['output'];
        }

        $costUsd = ($tokensIn / 1000) * $inputUsdPer1k + ($tokensOut / 1000) * $outputUsdPer1k;
        $costBrl = $costUsd * $cambio;

        return max(0, (int) round($costBrl * 100));
    }

    protected function logSkip(Message $message, string $reason): void
    {
        Log::channel('single')->debug('[whatsapp.analise] skip', [
            'metric_name' => 'whatsapp_analise_skip',
            'business_id' => $message->business_id,
            'message_id' => $message->id,
            'reason' => $reason,
        ]);
    }
}
