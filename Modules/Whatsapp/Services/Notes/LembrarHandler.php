<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Notes;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\MemoriaFato;
use Modules\Whatsapp\Entities\Message;

/**
 * Observabilidade D9.a (ADR 0155): handler inline com webhook; Tracer pai
 * via `OtelHelper::span(` herda o span do SlashCommandHandler dispatcher.
 *
 * LembrarHandler — US-WA-074.
 *
 * Atendente escreve em nota interna:
 *
 *   /lembrar prefere boleto, recusa cartão
 *
 * → Grava row em `jana_memoria_facts` (renomeada de `copiloto_memoria_facts`
 *   por ADR 0092). Embedding gerado async pelo Scout/Meilisearch via
 *   observer `Searchable` no MemoriaFato model.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - `business_id` resolvido da Message (já gateado pelo controller).
 *   - `user_id` = sender_user_id (atendente que criou a nota).
 *   - `metadata.contact_id` aponta pro contato da conversa.
 *
 * Convenção metadata (ADR 0142 §3d):
 *   - source           = 'human_note' (training signal taxonomy)
 *   - source_user_id   = atendente (audit)
 *   - source_*         = conversation_id / message_id pra retraçar origem
 *   - contact_id       = FK contacts UltimatePOS (filtra recall per-contact)
 *   - confidence       = 1.0 (humano corrige IA = confiança máxima)
 *   - category         = 'preference' | 'history' | 'constraint'
 *
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-074
 */
final class LembrarHandler implements SlashCommandHandler
{
    public function handle(Message $note, string $arguments): SlashCommandResult
    {
        $arguments = trim($arguments);

        // Graceful no-op: `/lembrar` sem texto vira nota normal sem warning
        if ($arguments === '') {
            return SlashCommandResult::unrecognized();
        }

        // Defense-in-depth — handler NUNCA roda fora de nota interna. Controller
        // já gateia, mas check redundante alinha com Tier 0 (ADR 0142 §1).
        if (! $note->is_internal_note) {
            Log::warning('[whatsapp.slash.lembrar] handler invocado em mensagem NÃO nota interna — bloqueado', [
                'message_id' => $note->id,
                'business_id' => $note->business_id,
            ]);
            return SlashCommandResult::error('Comando /lembrar só funciona em nota interna.');
        }

        $businessId = (int) $note->business_id;
        $atendenteUserId = $note->sender_user_id !== null ? (int) $note->sender_user_id : 0;

        // Resolve contact_id da conversa (pode ser null — conv sem contato vinculado).
        // Usa relação carregada se já vier preenchida; senão lazy-load. Multi-tenant
        // Tier 0 — global scope da Conversation só permite mesmo business_id.
        $conversation = $note->conversation;
        $contactId = $conversation?->contact_id !== null ? (int) $conversation->contact_id : null;

        try {
            $fato = MemoriaFato::create([
                'business_id' => $businessId,
                // Convenção (ADR 0052 + ADR 0142): user_id = atendente que criou
                // a memória. Quando contact for User UltimatePOS no futuro,
                // metadata.contact_id resolve o sujeito da preferência.
                'user_id' => $atendenteUserId,
                'fato' => $arguments,
                'metadata' => [
                    'source' => 'human_note',
                    'source_user_id' => $atendenteUserId,
                    'source_conversation_id' => (int) $note->conversation_id,
                    'source_message_id' => (int) $note->id,
                    'contact_id' => $contactId,
                    'confidence' => 1.0,
                    'category' => 'preference',
                ],
                'valid_from' => now(),
                // valid_until null = ativo até `esquecer()` ou supersede temporal
            ]);

            Log::info('[whatsapp.slash.lembrar] fato persistido', [
                'fato_id' => $fato->id,
                'business_id' => $businessId,
                'conversation_id' => $note->conversation_id,
                'message_id' => $note->id,
                'contact_id' => $contactId,
                'atendente_user_id' => $atendenteUserId,
            ]);

            return SlashCommandResult::success(
                '✓ memorizado',
                '/copiloto/admin/memoria?fact_id=' . $fato->id,
            );
        } catch (\Throwable $e) {
            Log::error('[whatsapp.slash.lembrar] falha ao gravar fato', [
                'message_id' => $note->id,
                'business_id' => $businessId,
                'exception' => mb_substr($e->getMessage(), 0, 240),
            ]);

            return SlashCommandResult::error('Erro ao memorizar — nota salva mas fato não gravado.');
        }
    }
}
