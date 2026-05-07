<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Events\WhatsappMessageReceived;

/**
 * Bot Jana — encaminha mensagens inbound pro PolicyEngine ADS quando
 * `bot_enabled=true` no business config (US-WA-020 Sprint 3).
 *
 * **Sprint 3 prep — placeholder funcional Sprint 2:**
 *
 * Hoje: detecta config + intent básico, marca `whatsapp_conversations.bot_handling=true`,
 * loga; NÃO dispara resposta automática ainda.
 *
 * Sprint 3 (quando ADS Universal ativar): substituir bloco `// SPRINT 3:`
 * abaixo por chamada real `decide('whatsapp', 'reply', $payload)` (skill
 * `ads-route` Tier A dormente). PolicyEngine retorna 1 dos 4 outcomes:
 *   - ALLOW_BRAIN_A → Jana responde direto (gpt-4o-mini)
 *   - REQUIRE_BRAIN_B → Jana responde via Sonnet (custo maior)
 *   - REQUIRE_HUMAN_REVIEW → marca `status=awaiting_human` + Centrifugo notify
 *   - BLOCK_ALWAYS → log + no-op
 *
 * **Multi-tenant Tier 0:** business_id resolvido pelo evento (não session).
 * **PII redacted** em logs via PiiRedactor (skill commit-discipline).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-020
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §3.2 (fluxo bot HITL)
 */
class DispatchToJanaBot
{
    public function handle(WhatsappMessageReceived $event): void
    {
        if (! (bool) config('whatsapp.bot.enabled', false)) {
            return; // global feature flag desligado (default Sprint 2)
        }

        $message = $event->message;

        $config = WhatsappBusinessConfig::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $message->business_id)
            ->first();

        if ($config === null || ! (bool) $config->bot_enabled) {
            return; // business desativou bot
        }

        $conversation = WhatsappConversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->find($message->conversation_id);

        if ($conversation === null) {
            return;
        }

        // Marca conversa como bot_handling — UI mostra badge "🤖 bot"
        if (! $conversation->bot_handling) {
            $conversation->update(['bot_handling' => true]);
        }

        // SPRINT 3: substituir o bloco abaixo por:
        //
        //   $outcome = app(\Modules\Ads\Services\DecisionRouter::class)->decide(
        //       domain: 'whatsapp',
        //       intent: 'reply',
        //       payload: [
        //           'business_id' => $message->business_id,
        //           'conversation_id' => $conversation->id,
        //           'inbound_text' => $message->body,
        //           'history_summary' => /* últimas N msgs da thread */,
        //       ],
        //   );
        //
        //   match ($outcome->action) {
        //       'ALLOW_BRAIN_A', 'REQUIRE_BRAIN_B' => $this->dispatchBotReply($conversation, $outcome),
        //       'REQUIRE_HUMAN_REVIEW' => $conversation->update(['status' => 'awaiting_human']),
        //       'BLOCK_ALWAYS' => /* log + no-op */,
        //   };
        \Log::info('[whatsapp.dispatch_to_jana_bot] mensagem recebida (Sprint 3 prep — sem resposta automática ainda)', [
            'business_id' => $message->business_id,
            'conversation_id' => $conversation->id,
            'inbound_preview' => mb_substr((string) $message->body, 0, 80),
        ]);
    }
}
