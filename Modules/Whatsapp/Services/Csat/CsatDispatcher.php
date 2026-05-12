<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Csat;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\CsatResponse;
use Modules\Whatsapp\Entities\Message;

/**
 * CsatDispatcher — envia pesquisa CSAT pós-resolução (PR-6 CYCLE-07).
 *
 * Quando atendente marca conversa como `resolved` (InboxController::updateStatus),
 * `DispatchCsatJob` chama `dispatchOnResolve()` que:
 *   1. Idempotência: se já há CsatResponse pending nas últimas 24h pra esta
 *      conversation → no-op (não duplica).
 *   2. Persiste Message outbound type='text' status='queued' com texto template
 *      "Como você avalia este atendimento? Responda com um número de 1 a 5..."
 *   3. Chama daemon Baileys CT 100 `/instances/{id}/text` (só whatsapp_baileys
 *      nesta fase — outros types retornam null + warning).
 *   4. Cria 1 row em `whatsapp_csat_responses` com `score=null`, `asked_at=now`.
 *
 * Inbound subsequente é parseado pelo webhook (ChannelBaileysWebhookController)
 * via `CsatResponseParser::recordResponse()`.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - Caller (Job) passa `businessId` explícito; usa `withoutGlobalScopes`
 *     porque executa em fila sem session().
 *   - Channel é validado contra business_id da conv.
 *
 * @see CsatResponseParser
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap #5 P1
 */
class CsatDispatcher
{
    /**
     * Default template CSAT — atendente per-business override em PR futuro.
     *
     * Texto curto + emoji estrelas pra encorajar tap rápido. Convenção
     * Chatwoot/Take Blip: explicar escala (1=ruim, 5=excelente) na 1ª msg.
     */
    public const DEFAULT_TEMPLATE = "Como voce avalia este atendimento? Responda com um numero de 1 a 5.\n\n1 = Ruim\n2 = Regular\n3 = Bom\n4 = Otimo\n5 = Excelente";

    /**
     * Dispara CSAT pós-resolve.
     *
     * @return ?CsatResponse Row pending criada, ou null se idempotente/canal incompatível
     */
    public function dispatchOnResolve(Conversation $conv, int $resolvedBy): ?CsatResponse
    {
        // Idempotência — se já há row pending nas últimas 24h pra esta conv,
        // não duplica. Window CSAT::DISPATCH_DEDUP_HOURS = 24.
        $existing = CsatResponse::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $conv->business_id)
            ->where('conversation_id', $conv->id)
            ->whereNull('score')
            ->where('asked_at', '>=', now()->subHours(CsatResponse::DISPATCH_DEDUP_HOURS))
            ->first();

        if ($existing) {
            Log::info('[csat.dispatch.skipped] já há pesquisa pending nas últimas 24h', [
                'business_id' => $conv->business_id,
                'conversation_id' => $conv->id,
                'existing_csat_id' => $existing->id,
            ]);
            return null;
        }

        // Carrega channel sem global scope (sem session) — webhook/job context.
        $channel = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $conv->business_id)
            ->where('id', $conv->channel_id)
            ->first();

        if (! $channel) {
            Log::warning('[csat.dispatch.skipped] canal não encontrado', [
                'business_id' => $conv->business_id,
                'conversation_id' => $conv->id,
                'channel_id' => $conv->channel_id,
            ]);
            return null;
        }

        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS) {
            // Outros types (whatsapp_meta, zapi, etc) suportados em PR futuro
            // quando drivers refatorados pra aceitar Channel direto.
            Log::info('[csat.dispatch.skipped] canal não-baileys nesta fase', [
                'channel_id' => $channel->id,
                'channel_type' => $channel->type,
            ]);
            return null;
        }

        $body = self::DEFAULT_TEMPLATE;

        // Persiste Message outbound status='queued' ANTES do dispatch (defesa
        // em profundidade — row já existe se daemon falhar). Mesmo pattern
        // de InboxController::send.
        $message = Message::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->create([
                'business_id' => $conv->business_id,
                'conversation_id' => $conv->id,
                'direction' => 'outbound',
                'provider' => $channel->type,
                'type' => 'text',
                'body' => $body,
                'status' => 'queued',
                'sender_user_id' => $resolvedBy ?: null,
                'sender_kind' => 'system', // CSAT dispatcher é automático, não humano direto
                'is_internal_note' => false,
            ]);

        $conv->forceFill([
            'last_outbound_at' => now(),
            'last_message_at' => now(),
        ])->save();

        // Cria row CsatResponse pending — webhook inbound vai popular o score
        // quando cliente responder via parser.
        $csat = CsatResponse::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->create([
                'business_id' => $conv->business_id,
                'conversation_id' => $conv->id,
                'resolved_message_id' => $message->id,
                'resolved_by_user_id' => $resolvedBy ?: null,
                'asked_at' => now(),
            ]);

        // Dispatch daemon — tolerante a erro (row já persistida). HTTP fake
        // friendly: nos testes, `Http::fake()` intercepta sem quebrar.
        $daemonUrl = config('whatsapp.baileys.daemon_url');
        $apiKey = config('whatsapp.baileys.api_key');
        if ($daemonUrl && $apiKey) {
            $instanceId = 'ch-' . str_replace('-', '', (string) $channel->channel_uuid);
            $toPhone = preg_replace('/^\+/', '', (string) $conv->customer_external_id);

            try {
                $response = Http::withToken($apiKey)
                    ->withoutVerifying() // FIXME(US-WA-058): cert LE pendente
                    ->timeout(15)
                    ->post("{$daemonUrl}/instances/{$instanceId}/text", [
                        'to' => $toPhone,
                        'text' => $body,
                    ]);

                if ($response->successful()) {
                    $payload = $response->json();
                    $message->forceFill([
                        'status' => $payload['status'] ?? 'sent',
                        'provider_message_id' => $payload['message_id'] ?? null,
                    ])->save();
                } else {
                    $message->forceFill([
                        'status' => 'failed',
                        'failed_reason' => 'Daemon ' . $response->status() . ': ' . mb_substr($response->body(), 0, 200),
                    ])->save();
                    Log::warning('[csat.dispatch.daemon_error]', [
                        'channel_id' => $channel->id,
                        'status' => $response->status(),
                    ]);
                }
            } catch (\Throwable $e) {
                $message->forceFill([
                    'status' => 'failed',
                    'failed_reason' => mb_substr($e->getMessage(), 0, 240),
                ])->save();
                Log::warning('[csat.dispatch.exception]', [
                    'channel_id' => $channel->id,
                    'exception' => mb_substr($e->getMessage(), 0, 200),
                ]);
            }
        }

        Log::info('[csat.dispatch.created]', [
            'business_id' => $conv->business_id,
            'conversation_id' => $conv->id,
            'csat_id' => $csat->id,
            'resolved_by_user_id' => $resolvedBy,
        ]);

        return $csat;
    }
}
