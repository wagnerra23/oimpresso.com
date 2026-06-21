<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Ai\Agents\InboxAssistAgent;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;

/**
 * InboxAiController — IA na thread da Caixa Unificada V4 (PR-9 do brief [CC]).
 *
 * 3 endpoints finos sobre o InboxAssistAgent (laravel/ai, stack ADR 0035 —
 * mesma infra validada dos Agents da Jana):
 *   POST /atendimento/inbox/{id}/ai/summarize     → resumo 5 bullets
 *   POST /atendimento/inbox/{id}/ai/ask           → pergunta sobre a conversa
 *   POST /atendimento/inbox/{id}/ai/suggest-reply → sugestão de resposta
 *
 * Guardas (todas as rotas):
 *   - Tier 0 ADR 0093: conversa do business atual + ACL canal (US-WA-069)
 *   - LGPD: transcript passa pelo PiiRedactor da Jana ANTES do provider
 *   - Custo: `config('copiloto.dry_run')` devolve fixture SEM tocar LLM
 *     (mesma chave que gateia a Jana — dev/test nunca gastam token)
 *   - Falha de provider → 503 com mensagem legível (nunca 500 cru)
 */
class InboxAiController extends Controller
{
    /** Últimas N mensagens no transcript — janela suficiente sem estourar contexto. */
    protected const TRANSCRIPT_LIMIT = 60;

    public function summarize(Request $request, int $id): JsonResponse
    {
        $conversation = $this->resolveConversationOrAbort($id);
        $convText = $this->convToText($conversation);

        if (config('copiloto.dry_run')) {
            return response()->json(['text' => $this->fixtureSummarize($conversation)]);
        }

        return $this->runAgent(
            fn (InboxAssistAgent $agent) => $agent->prompt($agent->promptSummarize($convText)),
            'summarize',
            $conversation,
        );
    }

    public function ask(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:500']]);
        $conversation = $this->resolveConversationOrAbort($id);
        $convText = $this->convToText($conversation);

        if (config('copiloto.dry_run')) {
            return response()->json(['text' => "[dry-run] Pergunta recebida: \"{$data['question']}\" — em produção a IA responde com base no transcript ({$this->messageCount($conversation)} mensagens)."]);
        }

        return $this->runAgent(
            fn (InboxAssistAgent $agent) => $agent->prompt($agent->promptAsk($convText, $data['question'])),
            'ask',
            $conversation,
        );
    }

    public function suggestReply(Request $request, int $id): JsonResponse
    {
        $conversation = $this->resolveConversationOrAbort($id);
        $convText = $this->convToText($conversation);

        if (config('copiloto.dry_run')) {
            return response()->json(['text' => 'Olá! Recebemos sua mensagem e já estamos verificando — te retorno em instantes.']);
        }

        return $this->runAgent(
            fn (InboxAssistAgent $agent) => $agent->prompt($agent->promptSuggestReply($convText)),
            'suggest_reply',
            $conversation,
        );
    }

    /**
     * Conversa do business atual + ACL canal (fail-loud) — Tier 0.
     */
    protected function resolveConversationOrAbort(int $id): Conversation
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);

        $conversation = Conversation::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $canSeeAll = (bool) (auth()->user()?->can('whatsapp.view-all-phones') ?? false);
        $channelId = (int) ($conversation->channel_id ?? 0);
        if (! $canSeeAll && $channelId !== 0) {
            $hasAccess = ChannelUserAccess::query()
                ->where('business_id', $businessId)
                ->where('user_id', $userId)
                ->where('channel_id', $channelId)
                ->whereNull('revoked_at')
                ->exists();
            if (! $hasAccess) {
                abort(403, 'Sem acesso ao canal desta conversa.');
            }
        }

        return $conversation;
    }

    /**
     * Transcript das últimas N mensagens — REDIGIDO (PiiRedactor da Jana) antes
     * de qualquer provider. Notas internas entram marcadas (contexto da equipe;
     * o Agent é instruído a nunca expô-las em resposta cliente-facing).
     */
    protected function convToText(Conversation $conversation): string
    {
        $messages = Message::query()
            ->where('business_id', (int) $conversation->business_id)
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->limit(self::TRANSCRIPT_LIMIT)
            ->get()
            ->reverse();

        $lines = $messages->map(function (Message $m) use ($conversation) {
            $who = $m->is_internal_note
                ? '[NOTA INTERNA]'
                : ($m->direction === 'inbound' ? ($conversation->contact_name ?: 'Cliente') : 'Atendente');
            $body = $m->body ?: ($m->media_url ? '[mídia]' : '');

            return "{$who}: {$body}";
        })->implode("\n");

        // LGPD — CPF/CNPJ/PII nunca vão pro provider (reusa redactor canon da Jana)
        try {
            return app(\Modules\Jana\Services\Privacy\PiiRedactor::class)->redact($lines);
        } catch (\Throwable) {
            // Fallback regex CPF/CNPJ (mesmo do LaravelAiSdkDriver legacy path)
            $lines = (string) preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', 'XXX.XXX.XXX-NN', $lines);

            return (string) preg_replace('/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/', 'XX.XXX.XXX/XXXX-NN', $lines);
        }
    }

    /**
     * Executa o Agent com falha graciosa (provider fora → 503 legível).
     */
    protected function runAgent(\Closure $call, string $action, Conversation $conversation): JsonResponse
    {
        try {
            $response = $call(new InboxAssistAgent());

            Log::channel('copiloto-ai')->info("[inbox-ai] {$action}", [
                'business_id' => (int) $conversation->business_id,
                'conversation_id' => $conversation->id,
            ]);

            return response()->json(['text' => (string) $response]);
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning("[inbox-ai] {$action} falhou", [
                'business_id' => (int) $conversation->business_id,
                'conversation_id' => $conversation->id,
                'error_class' => get_class($e),
            ]);

            return response()->json([
                'error' => 'IA indisponível agora — tente de novo em instantes.',
            ], 503);
        }
    }

    protected function fixtureSummarize(Conversation $conversation): string
    {
        $n = $this->messageCount($conversation);

        return "[dry-run] Resumo da conversa com {$conversation->contact_name}: {$n} mensagens no transcript. Em produção a IA devolve 5 bullets (contexto · pedido · status · pendências · próximo passo).";
    }

    protected function messageCount(Conversation $conversation): int
    {
        return Message::query()
            ->where('business_id', (int) $conversation->business_id)
            ->where('conversation_id', $conversation->id)
            ->count();
    }
}
