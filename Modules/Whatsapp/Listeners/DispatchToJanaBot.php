<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Entities\WhatsappContactBotOverride;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Events\WhatsappMessageReceived;

/**
 * Bot Jana — encaminha mensagens inbound pro PolicyEngine ADS quando o
 * número Whatsapp tem `handles_jana_bot=true` (US-WA-020 + US-WA-040).
 *
 * **Sprint 3 prep — placeholder funcional Sprint 2:**
 *
 * Hoje: detecta phone configurado + intent básico, marca
 * `whatsapp_conversations.bot_handling=true`, loga; NÃO dispara resposta
 * automática ainda.
 *
 * Sprint 3 (quando ADS Universal ativar): substituir bloco `// SPRINT 3:`
 * abaixo por chamada real `decide('whatsapp', 'reply', $payload)` (skill
 * `ads-route` Tier A dormente). PolicyEngine retorna 1 dos 4 outcomes:
 *   - ALLOW_BRAIN_A → Jana responde direto (gpt-4o-mini)
 *   - REQUIRE_BRAIN_B → Jana responde via Sonnet (custo maior)
 *   - REQUIRE_HUMAN_REVIEW → marca `status=awaiting_human` + Centrifugo notify
 *   - BLOCK_ALWAYS → log + no-op
 *
 * **Multi-números (ADR 0117 — US-WA-040):**
 * Recupera phone da conversa via `whatsapp_business_phone_id` (preenchido
 * pelo ProcessIncomingWebhookJob ao processar inbound). Só processa se
 * `phone->handles_jana_bot=true` — admin pode desativar bot por número
 * (ex: deixar Comercial com bot, Financeiro só com humano).
 *
 * **Multi-tenant Tier 0:** business_id resolvido pelo evento (não session).
 * **PII redacted** em logs via PiiRedactor (skill commit-discipline).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-020, US-WA-040
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §3.2 (fluxo bot HITL)
 * @see memory/decisions/0117-multiplos-numeros-whatsapp-por-business.md
 */
class DispatchToJanaBot
{
    public function handle(WhatsappMessageReceived $event): void
    {
        if (! (bool) config('whatsapp.bot.enabled', false)) {
            return; // global feature flag desligado (default Sprint 2)
        }

        $message = $event->message;

        // SUPERADMIN: listener fora de session HTTP — business_id deduzido do webhook payload via WhatsappMessageReceived event
        $conversation = WhatsappConversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->find($message->conversation_id);

        if ($conversation === null) {
            return;
        }

        // Resolve phone via conversa OU fallback resolveForEvent('jana_bot')
        // (conversation.whatsapp_business_phone_id pode ser NULL em conversas
        // legacy migradas via PR 1 sem phone vinculado preciso)
        $phone = null;
        if ($conversation->whatsapp_business_phone_id !== null) {
            // SUPERADMIN: listener sem session — filtro defensivo where('business_id') garante Tier 0
            $phone = WhatsappBusinessPhone::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $message->business_id)
                ->where('id', $conversation->whatsapp_business_phone_id)
                ->first();
        }

        $phone ??= WhatsappBusinessPhone::resolveForEvent($message->business_id, 'jana_bot');

        if ($phone === null) {
            return; // business sem phone configurado — silencioso
        }

        if (! $phone->handles_jana_bot || ! $phone->bot_enabled) {
            return; // admin desativou bot pra este phone — silencioso
        }

        // US-WA-077 (ADR 0142 §3c) — override per-contato via /config bot=off.
        // Override vence o flag global do phone/business. Só consultamos
        // quando a conversa tem contact_id vinculado (conversas provisionais
        // sem contact_id seguem flag global — não há override pra applicar).
        if ($conversation->contact_id !== null) {
            $effectiveBotEnabled = WhatsappContactBotOverride::resolvedFor(
                (int) $message->business_id,
                (int) $conversation->contact_id,
                fallback: (bool) $phone->bot_enabled,
            );
            if (! $effectiveBotEnabled) {
                // Atendente desligou bot pra este contato — silencioso, igual gate global.
                return;
            }
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
        //           'phone_id' => $phone->id,
        //           'conversation_id' => $conversation->id,
        //           'inbound_text' => $message->body,
        //           'history_summary' => /* últimas N msgs da thread */,
        //       ],
        //   );
        //
        //   match ($outcome->action) {
        //       'ALLOW_BRAIN_A', 'REQUIRE_BRAIN_B' => $this->dispatchBotReply($conversation, $phone, $outcome),
        //       'REQUIRE_HUMAN_REVIEW' => $conversation->update(['status' => 'awaiting_human']),
        //       'BLOCK_ALWAYS' => /* log + no-op */,
        //   };
        // D7 LGPD — PII redaction antes de logar body do cliente (Wave 9 governance).
        // `inbound_preview` é o ÚNICO log-call do módulo Whatsapp que copia conteúdo
        // raw da mensagem do cliente pra storage/logs/laravel.log (vetor de vazamento
        // catalogado: msg pode conter CPF/CNPJ/email/phone). PiiRedactor substitui
        // por placeholders [REDACTED:CPF] etc preservando contexto pra debug.
        // Centrifugo previews (PublishMessageReceivedToCentrifugo + PublishOmnichannel)
        // NÃO precisam de redact — channel é tenant-scoped (only authorized UI subscribes).
        $previewRaw = mb_substr((string) $message->body, 0, 80);
        $previewRedacted = app(PiiRedactor::class)->redact($previewRaw);

        \Log::info('[whatsapp.dispatch_to_jana_bot] mensagem recebida (Sprint 3 prep — sem resposta automática ainda)', [
            'business_id' => $message->business_id,
            'phone_id' => $phone->id,
            'phone_label' => $phone->label,
            'conversation_id' => $conversation->id,
            'inbound_preview' => $previewRedacted,
        ]);
    }
}
