<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Notes;

use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\WhatsappContactBotOverride;

/**
 * Observabilidade D9.a (ADR 0155): handler inline com webhook; Tracer pai
 * via `OtelHelper::span(` herda o span do SlashCommandHandler dispatcher.
 *
 * ConfigHandler — US-WA-077 (ADR 0142 §3c).
 *
 * Atendente escreve em nota interna:
 *
 *   /config bot=off    → desliga Jana SÓ pra esse contato
 *   /config bot=on     → reativa
 *   /config bot=true   → equivalente a on
 *   /config bot=false  → equivalente a off
 *
 * → updateOrCreate row em `whatsapp_contact_bot_overrides` por
 *   (business_id, contact_id). Engine de bot (DispatchToJanaBot) consulta
 *   {@see WhatsappContactBotOverride::resolvedFor()} ANTES do flag global
 *   do canal — override vence.
 *
 * Sintaxe v1 restrita a `bot={on|off|true|false}` (case-insensitive). Outras
 * chaves vão sair em US-WA-* posterior (ADR 0142 Alternativa E).
 *
 * Pré-condição: conversa precisa ter `contact_id` (vínculo CRM). Conversas
 * "provisionais" (cliente desconhecido sem contact vinculado) → error
 * pedindo pra usar "Vincular contato" no painel direito.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - `business_id` resolvido da Message (já gateado pelo controller)
 *   - UNIQUE composto (business_id, contact_id) defense-in-depth
 *
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md §3c
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-077
 */
final class ConfigHandler implements SlashCommandHandler
{
    /**
     * Regex sintaxe v1: SOMENTE `bot=on|off|true|false` (case-insensitive).
     * Reservado pra extensão futura: substituir alternativa literal por
     * `(\w+)=(...)` quando US autorizar mais chaves.
     */
    private const SYNTAX_PATTERN = '/^bot=(on|off|true|false)$/i';

    public function handle(Message $note, string $arguments): SlashCommandResult
    {
        $arguments = trim($arguments);

        // Graceful no-op: `/config` sem argumentos vira nota normal sem warning.
        if ($arguments === '') {
            return SlashCommandResult::unrecognized();
        }

        // Defense-in-depth — handler NUNCA roda fora de nota interna.
        // Controller já gateia, mas check redundante alinha com Tier 0
        // (ADR 0142 §1 — mesmo padrão LembrarHandler).
        if (! $note->is_internal_note) {
            Log::warning('[whatsapp.slash.config] handler invocado em mensagem NÃO nota interna — bloqueado', [
                'message_id' => $note->id,
                'business_id' => $note->business_id,
            ]);
            return SlashCommandResult::error('Comando /config só funciona em nota interna.');
        }

        if (! preg_match(self::SYNTAX_PATTERN, $arguments, $matches)) {
            return SlashCommandResult::error('Sintaxe inválida. Use /config bot=on|off');
        }

        // Mapa: on/true → bot_enabled=true; off/false → false. Case-insensitive
        // pelo flag /i da regex; lowercase pra comparação simples.
        $valueRaw = strtolower($matches[1]);
        $botEnabled = in_array($valueRaw, ['on', 'true'], true);
        $statusLabel = $botEnabled ? 'ligado' : 'desligado';

        $businessId = (int) $note->business_id;
        $atendenteUserId = $note->sender_user_id !== null ? (int) $note->sender_user_id : 0;

        // Resolve contact_id da conversa. Multi-tenant Tier 0 — relação
        // carregada usa global scope (ScopeByBusiness garante mesmo tenant).
        $conversation = $note->conversation;
        $contactId = $conversation?->contact_id;

        if ($contactId === null) {
            return SlashCommandResult::error(
                'Contato precisa estar vinculado ao CRM antes (use Vincular contato no painel direito).'
            );
        }

        try {
            $override = WhatsappContactBotOverride::updateOrCreate(
                [
                    'business_id' => $businessId,
                    'contact_id' => (int) $contactId,
                ],
                [
                    'bot_enabled' => $botEnabled,
                    'set_by_user_id' => $atendenteUserId,
                    'set_at' => now(),
                ],
            );

            Log::info('[whatsapp.slash.config] override bot persistido', [
                'override_id' => $override->id,
                'business_id' => $businessId,
                'contact_id' => (int) $contactId,
                'bot_enabled' => $botEnabled,
                'atendente_user_id' => $atendenteUserId,
                'conversation_id' => $note->conversation_id,
                'message_id' => $note->id,
            ]);

            return SlashCommandResult::success("🤖 bot {$statusLabel}", null);
        } catch (\Throwable $e) {
            Log::error('[whatsapp.slash.config] falha ao gravar override', [
                'message_id' => $note->id,
                'business_id' => $businessId,
                'contact_id' => (int) $contactId,
                'exception' => mb_substr($e->getMessage(), 0, 240),
            ]);

            return SlashCommandResult::error('Erro ao gravar override — nota salva mas config não aplicada.');
        }
    }
}
