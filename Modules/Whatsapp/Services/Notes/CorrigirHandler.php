<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Notes;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\JanaCorrecao;
use Modules\Whatsapp\Entities\Message;

/**
 * Observabilidade D9.a (ADR 0155): handler inline com webhook; Tracer pai
 * via `OtelHelper::span(` herda o span do SlashCommandHandler dispatcher.
 *
 * CorrigirHandler — US-WA-075 (ADR 0142 §3a).
 *
 * Atendente vê resposta errada da Jana, escreve em nota interna:
 *
 *   /corrigir Deveria ter dito que entrega é em 3 dias, não 7
 *
 * → Grava row em `whatsapp_jana_correcoes` (training signal pra fine-tune
 *   ou retrieval few-shot futuro). Tabela conserva o par
 *   (mensagem-errada → correção-humana).
 *
 * ## Resolução de `message_id_errada`
 *
 * MVP (sem botão UI dedicado): pega a **última mensagem do bot na conversa**
 * (`sender_kind='bot'` mais recente). Fase 2 ([ADR 0142 §3a]) adiciona
 * `replied_to_message_id` no metadata da nota — frontend marca explícito
 * a qual msg o atendente está respondendo.
 *
 * Se nenhuma msg do bot existir na conversa → retorna error gracioso
 * ("Nenhuma mensagem do bot pra corrigir nesta conversa"). Atendente
 * provavelmente disparou `/corrigir` sem contexto adequado.
 *
 * ## Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093)
 *
 * - `business_id` resolvido da Message — já gateado pelo controller.
 * - `atendente_user_id` = sender_user_id da nota (humano que digitou).
 * - `contact_id` denormalizado da conversa (filtra dashboard rápido).
 * - Lookup da última msg do bot usa `withoutGlobalScope(ScopeByBusiness)`
 *   + `where('business_id', ...)` explícito porque o handler pode rodar
 *   em contexto sem session (Job futuro) — defense-in-depth.
 *
 * ## Convenção metadata
 *
 * - source           = 'human_note'   (training signal taxonomy)
 * - source_message_id = ID da própria nota (audit trail)
 * - resolution      = 'latest_bot_message_fallback' (MVP) | 'replied_to' (fase 2)
 *
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md §3a
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-075
 */
final class CorrigirHandler implements SlashCommandHandler
{
    public function handle(Message $note, string $arguments): SlashCommandResult
    {
        $arguments = trim($arguments);

        // Graceful no-op: `/corrigir` sem texto vira nota normal sem warning
        if ($arguments === '') {
            return SlashCommandResult::unrecognized();
        }

        // Defense-in-depth — handler NUNCA roda fora de nota interna. Controller
        // já gateia, mas check redundante alinha com Tier 0 (ADR 0142 §1).
        if (! $note->is_internal_note) {
            Log::warning('[whatsapp.slash.corrigir] handler invocado em mensagem NÃO nota interna — bloqueado', [
                'message_id' => $note->id,
                'business_id' => $note->business_id,
            ]);
            return SlashCommandResult::error('Comando /corrigir só funciona em nota interna.');
        }

        $businessId = (int) $note->business_id;
        $conversationId = (int) $note->conversation_id;
        $atendenteUserId = $note->sender_user_id !== null ? (int) $note->sender_user_id : 0;

        // Resolve contact_id da conversa (pode ser null — conv sem contato vinculado).
        $conversation = $note->conversation;
        $contactId = $conversation?->contact_id !== null ? (int) $conversation->contact_id : null;

        // Resolve message_id_errada via fallback (MVP — sem botão UI dedicado).
        // Tier 0 — filtra explicit por business_id sem depender de session().
        // Critério: última msg do bot na MESMA conversa, ANTES da nota.
        $msgErrada = Message::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('conversation_id', $conversationId)
            ->where('sender_kind', 'bot')
            ->where('id', '<', $note->id)
            ->orderByDesc('id')
            ->first();

        if ($msgErrada === null) {
            Log::info('[whatsapp.slash.corrigir] nenhuma msg do bot encontrada na conversa — graceful error', [
                'message_id' => $note->id,
                'business_id' => $businessId,
                'conversation_id' => $conversationId,
            ]);
            return SlashCommandResult::error('Nenhuma mensagem do bot pra corrigir nesta conversa.');
        }

        try {
            $correcao = JanaCorrecao::create([
                'business_id' => $businessId,
                'conversation_id' => $conversationId,
                'message_id_errada' => (int) $msgErrada->id,
                'correcao_texto' => $arguments,
                'contact_id' => $contactId,
                'atendente_user_id' => $atendenteUserId,
                'training_status' => JanaCorrecao::STATUS_PENDING_REVIEW,
                'metadata' => [
                    'source' => 'human_note',
                    'source_message_id' => (int) $note->id,
                    'resolution' => 'latest_bot_message_fallback',
                ],
            ]);

            Log::info('[whatsapp.slash.corrigir] correção persistida', [
                'correcao_id' => $correcao->id,
                'business_id' => $businessId,
                'conversation_id' => $conversationId,
                'message_id_errada' => $msgErrada->id,
                'note_message_id' => $note->id,
                'atendente_user_id' => $atendenteUserId,
                'contact_id' => $contactId,
            ]);

            return SlashCommandResult::success(
                '⚠ corrigida',
                '/copiloto/admin/correcoes-jana?correcao_id=' . $correcao->id,
            );
        } catch (\Throwable $e) {
            Log::error('[whatsapp.slash.corrigir] falha ao gravar correção', [
                'message_id' => $note->id,
                'business_id' => $businessId,
                'exception' => mb_substr($e->getMessage(), 0, 240),
            ]);

            return SlashCommandResult::error('Erro ao gravar correção — nota salva mas treino não registrado.');
        }
    }
}
