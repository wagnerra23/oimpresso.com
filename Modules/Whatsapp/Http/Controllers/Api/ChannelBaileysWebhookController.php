<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * Webhook receiver pro schema omnichannel novo (ADR 0135).
 *
 * Recebe eventos do daemon Baileys CT 100 endereçados por `channel_uuid`
 * (em vez de `business_uuid` legacy). Persiste Conversation/Message no
 * schema polimórfico em vez de WhatsappConversation/WhatsappMessage.
 *
 * Url canônica:
 *   POST /api/atendimento/channels/baileys/{channel_uuid}
 *
 * Daemon config (env CT 100):
 *   WEBHOOK_BASE_URL=https://oimpresso.com/api/atendimento/channels/baileys
 *
 * Coexiste com `BaileysWebhookController` legacy (esse recebe via
 * business_uuid). Pra cutover completo, remover legacy após canary 7d
 * sem regressão.
 *
 * Auth: HMAC opcional via Bearer (mesmo API_KEY do daemon). Sem auth
 * estrita aqui pq channel_uuid é UUID v4 (~122 bits entropia) — atacante
 * precisaria adivinhar OU vazar via outro canal.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
class ChannelBaileysWebhookController extends Controller
{
    public function handle(Request $request, string $channelUuid): JsonResponse
    {
        // Escapar global scope pra resolver Channel sem session() user
        $channel = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('channel_uuid', $channelUuid)
            ->first();

        if (! $channel) {
            Log::warning('[channel.baileys.webhook] channel not found', [
                'channel_uuid' => $channelUuid,
            ]);
            return response()->json(['ok' => false, 'error' => 'channel_not_found'], 404);
        }

        $event = (string) $request->input('event', '');
        $data = (array) $request->input('data', []);

        Log::info('[channel.baileys.webhook]', [
            'channel_id' => $channel->id,
            'business_id' => $channel->business_id,
            'event' => $event,
        ]);

        switch ($event) {
            case 'message':
                return $this->handleMessage($channel, $data);

            case 'message_status':
                return $this->handleMessageStatus($channel, $data);

            case 'connected':
                $channel->forceFill([
                    'status' => 'active',
                    'channel_health' => 'healthy',
                    'channel_health_consecutive_failures' => 0,
                    'last_health_check_at' => now(),
                    'last_health_message' => 'connected',
                    'display_identifier' => $data['display_phone'] ?? $channel->display_identifier,
                ])->save();
                return response()->json(['ok' => true, 'note' => 'connected_recorded'], 200);

            case 'qr_updated':
                // QR é exposto via /status endpoint do daemon — não persiste em DB
                return response()->json(['ok' => true, 'note' => 'qr_logged'], 200);

            case 'session_lost':
                $channel->forceFill([
                    'channel_health' => 'degraded',
                    'last_health_check_at' => now(),
                    'last_health_message' => 'session_lost: ' . ($data['reason'] ?? 'unknown'),
                ])->save();
                return response()->json(['ok' => true, 'note' => 'session_lost_logged'], 200);

            case 'ban_detected':
                $channel->forceFill([
                    'status' => 'banned',
                    'channel_health' => 'banned',
                    'last_health_check_at' => now(),
                    'last_health_message' => 'banned: ' . ($data['reason'] ?? 'unknown'),
                ])->save();
                return response()->json(['ok' => true, 'note' => 'ban_recorded'], 200);

            case 'disconnected':
                $channel->forceFill([
                    'status' => 'disconnected',
                    'channel_health' => 'disconnected',
                    'last_health_check_at' => now(),
                    'last_health_message' => 'disconnected: ' . ($data['reason'] ?? 'manual'),
                ])->save();
                return response()->json(['ok' => true, 'note' => 'disconnected_recorded'], 200);

            default:
                return response()->json(['ok' => true, 'note' => 'unknown_event_ignored'], 200);
        }
    }

    /**
     * Persiste inbound message no schema novo.
     *
     * Daemon Baileys envia `data.key` (JID + msg id) + `data.message` (proto).
     * Extrai customer_external_id (phone E.164 do remetente) + body (texto).
     */
    protected function handleMessage(Channel $channel, array $data): JsonResponse
    {
        $msgKey = $data['key'] ?? [];
        $remoteJid = $msgKey['remoteJid'] ?? null;
        $providerMessageId = $msgKey['id'] ?? null;
        $fromMe = (bool) ($msgKey['fromMe'] ?? false);

        if (! $remoteJid) {
            return response()->json(['ok' => false, 'error' => 'no_remote_jid'], 200);
        }

        // Extrai phone E.164 do JID: "5548999872822@s.whatsapp.net" → "+5548999872822"
        $rawNumber = preg_replace('/@.+$/', '', $remoteJid);
        $customerExternalId = '+' . $rawNumber;

        // Extrai body (depende do tipo de mensagem)
        $messageProto = $data['message'] ?? [];
        $body = $messageProto['conversation']
            ?? $messageProto['extendedTextMessage']['text']
            ?? null;

        // Tipo derivado da estrutura da mensagem
        $type = match (true) {
            isset($messageProto['imageMessage']) => 'image',
            isset($messageProto['documentMessage']) => 'document',
            isset($messageProto['audioMessage']) => 'audio',
            isset($messageProto['videoMessage']) => 'video',
            isset($messageProto['locationMessage']) => 'location',
            isset($messageProto['contactMessage']) => 'contacts',
            default => 'text',
        };

        // Pula global scope nas writes pq webhook não tem session user
        $conversation = Conversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->firstOrCreate(
                [
                    'business_id' => $channel->business_id,
                    'channel_id' => $channel->id,
                    'customer_external_id' => $customerExternalId,
                ],
                [
                    'contact_name' => $customerExternalId,
                    'status' => 'open',
                    'bot_handling' => false,
                    'last_inbound_at' => now(),
                    'last_message_at' => now(),
                    'unread_count' => 0,
                ]
            );

        Message::query()->create([
            'business_id' => $channel->business_id,
            'conversation_id' => $conversation->id,
            'direction' => $fromMe ? 'outbound' : 'inbound',
            'provider' => $channel->type,
            'provider_message_id' => $providerMessageId,
            'type' => $type,
            'body' => $body,
            'payload' => $data,
            'status' => $fromMe ? 'sent' : 'received',
            'sender_kind' => $fromMe ? 'human' : null,
        ]);

        // Atualiza last_*_at + unread count
        $conversation->forceFill([
            'last_message_at' => now(),
            'last_inbound_at' => $fromMe ? $conversation->last_inbound_at : now(),
            'last_outbound_at' => $fromMe ? now() : $conversation->last_outbound_at,
            'unread_count' => $fromMe ? $conversation->unread_count : $conversation->unread_count + 1,
        ])->save();

        return response()->json([
            'ok' => true,
            'note' => 'message_persisted',
            'conversation_id' => $conversation->id,
        ], 200);
    }

    /**
     * Update status de Message existente (delivered/read).
     */
    protected function handleMessageStatus(Channel $channel, array $data): JsonResponse
    {
        $key = $data['key'] ?? [];
        $providerMessageId = $key['id'] ?? null;
        $update = $data['update'] ?? [];
        $statusCode = $update['status'] ?? null;

        if (! $providerMessageId || $statusCode === null) {
            return response()->json(['ok' => false, 'error' => 'incomplete_status_update'], 200);
        }

        // Baileys status codes (proto.WebMessageInfo.StatusType):
        // 0=ERROR, 1=PENDING, 2=SERVER_ACK, 3=DELIVERY_ACK, 4=READ, 5=PLAYED
        $newStatus = match ((int) $statusCode) {
            0 => 'failed',
            1 => 'queued',
            2 => 'sent',
            3 => 'delivered',
            4, 5 => 'read',
            default => null,
        };

        if (! $newStatus) {
            return response()->json(['ok' => true, 'note' => 'unknown_status_code'], 200);
        }

        Message::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $channel->business_id)
            ->where('provider_message_id', $providerMessageId)
            ->update(['status' => $newStatus]);

        return response()->json(['ok' => true, 'note' => 'status_updated'], 200);
    }
}
