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
use Modules\Whatsapp\Jobs\DownloadMediaJob;
use Modules\Whatsapp\Services\Contacts\ConversationContactLinker;
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
        $senderPn = $msgKey['senderPn'] ?? null; // Whatsapp Multi-Device entrega phone real aqui quando remoteJid é @lid
        $providerMessageId = $msgKey['id'] ?? null;
        $fromMe = (bool) ($msgKey['fromMe'] ?? false);
        $pushName = $data['push_name'] ?? null;

        if (! $remoteJid && ! $senderPn) {
            return response()->json(['ok' => false, 'error' => 'no_remote_jid'], 200);
        }

        // Resolve phone E.164:
        // 1. senderPn (formato "5548999872822@s.whatsapp.net") quando remoteJid é @lid → preferir
        // 2. remoteJid se for @s.whatsapp.net normal
        // 3. fallback @lid se nada mais (rato)
        $resolvedJid = ($senderPn && str_contains($senderPn, '@s.whatsapp.net'))
            ? $senderPn
            : $remoteJid;
        $rawNumber = preg_replace('/@.+$/', '', $resolvedJid);
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
            isset($messageProto['stickerMessage']) => 'image',
            isset($messageProto['locationMessage']) => 'location',
            isset($messageProto['contactMessage']) => 'contacts',
            default => 'text',
        };

        // US-WA-072 + Hotfix B1 (2026-05-12) — extrai meta de mídia.
        //
        // Daemon Baileys CT 100 (quando rodando) envia `data.media_url` (URL
        // direct-download decodificada), `data.mime`, `data.size_bytes`,
        // `data.duration_s`, `data.filename`. Caso ideal — meta no nível raiz.
        //
        // Bug em prod 2026-05-12: webhook estava recebendo payload aninhado
        // raw do Baileys (sem o flatten do daemon) — 89 messages biz=1 sem
        // body, 0 com `media_url`/`media_mime` populado. Áudios chegavam só
        // como type=audio sem nenhuma meta visível.
        //
        // Fallback defensivo: se daemon não normalizou, lê direto dos protos
        // aninhados `imageMessage`/`audioMessage`/`videoMessage`/`documentMessage`/
        // `stickerMessage`. URL Baileys é criptografada (.enc + mediaKey) —
        // Hostinger Laravel NÃO consegue decrypt (precisa SDK Baileys daemon
        // CT 100). Por isso `media_url` continua null nesse fallback; UI B2
        // renderiza placeholder "aguardando download". Daemon C futuro vai
        // popular media_url via endpoint decrypt+upload.
        $mediaProto = $messageProto['imageMessage']
            ?? $messageProto['audioMessage']
            ?? $messageProto['videoMessage']
            ?? $messageProto['documentMessage']
            ?? $messageProto['stickerMessage']
            ?? null;

        $mediaUrl = $data['media_url'] ?? null;
        $mediaMimeRaw = $data['mime']
            ?? ($mediaProto['mimetype'] ?? null);
        // Sanitize MIME: Baileys envia `"audio/ogg; codecs=opus"` — strip
        // codec pra match com Message::MEDIA_MIME_WHITELIST e Whisper API.
        $mediaMime = $mediaMimeRaw
            ? trim(explode(';', (string) $mediaMimeRaw, 2)[0])
            : null;
        $mediaSize = isset($data['size_bytes'])
            ? (int) $data['size_bytes']
            : (isset($mediaProto['fileLength']) ? (int) $mediaProto['fileLength'] : null);
        $mediaDuration = isset($data['duration_s'])
            ? (int) $data['duration_s']
            : (isset($mediaProto['seconds']) ? (int) $mediaProto['seconds'] : null);
        $mediaFilename = $data['filename']
            ?? ($mediaProto['fileName'] ?? null);

        // Caption (image/video) sobrescreve body=null quando vem
        if ($body === null) {
            $body = $messageProto['imageMessage']['caption']
                ?? $messageProto['videoMessage']['caption']
                ?? $messageProto['documentMessage']['caption']
                ?? null;
        }

        // US-WA-076: filtrar msgs "protocol" do Baileys (senderKeyDistributionMessage,
        // protocolMessage, messageContextInfo, app-state-sync events). Chegam com
        // body=null + sem media (cai no default 'text' do match). Sem valor pro
        // Inbox — só polui Conversation + zera o preview lastMsg.
        //
        // Bug observado em prod 2026-05-11: conv #3 do biz=1 acumulou ~10 msgs
        // outbound body=null porque chip Suporte emitia eventos internos quando
        // reconectava durante o pairing turbulento US-WA-067/068. Mensagens
        // vazias quebravam o preview na lista esquerda + criavam "conv fantasma"
        // com customer_external_id = phone do próprio chip (não tem como conversar
        // com si mesmo). Filtro aqui evita esse lixo entrar no schema.
        if ($body === null && $type === 'text') {
            return response()->json([
                'ok' => true,
                'note' => 'protocol_msg_ignored',
                'provider_message_id' => $providerMessageId,
            ], 200);
        }

        // US-WA-066: drop inbound de conv blocked ANTES do firstOrCreate de Message.
        // Verificação ANTES do firstOrCreate da Conversation pra NÃO criar conv
        // nova com is_blocked. Se a conv não existe, deixa criar normalmente
        // (não tem como bloquear o que nunca contataram).
        $existingConv = Conversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $channel->business_id)
            ->where('channel_id', $channel->id)
            ->where('customer_external_id', $customerExternalId)
            ->first();

        if ($existingConv && $existingConv->is_blocked && ! $fromMe) {
            // Inbound msg de contato bloqueado — drop silencioso (Wagner regra
            // anti-spam US-WA-066). Não persiste Message, não bump unread_count,
            // não emite evento Centrifugo. fromMe outbound ainda passa pq o
            // atendente pode ter enviado msg programática antes do bloqueio.
            return response()->json([
                'ok' => true,
                'note' => 'inbound_dropped_blocked',
                'conversation_id' => $existingConv->id,
            ], 200);
        }

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
                    'contact_name' => $pushName ?: $customerExternalId,
                    'status' => 'open',
                    'bot_handling' => false,
                    'last_inbound_at' => now(),
                    'last_message_at' => now(),
                    'unread_count' => 0,
                ]
            );

        // Atualiza contact_name se push_name veio E o atual era só o E.164
        // (não sobrescreve nome já curado pelo atendente)
        if ($pushName && $conversation->contact_name === $customerExternalId) {
            $conversation->contact_name = $pushName;
            $conversation->save();
        }

        // US-WA-078: auto-link Contact CRM por phone normalizado.
        //
        // Hoje em prod biz=1: 32 conversations com contact_id=null (NENHUMA
        // linkada manualmente porque US-WA-064 exige clique no atendente).
        // Esta lógica roda em duas situações:
        //   (a) conversation recém-criada (wasRecentlyCreated=true) — best
        //       moment, evita ficar orfã desde o nascimento.
        //   (b) conversation pré-existente com contact_id=null — orphan
        //       tracking pra casos onde o Contact CRM foi cadastrado DEPOIS
        //       da conv ser criada (atendente cadastrou via /contacts).
        //
        // Service `ConversationContactLinker` isola a heurística (LIKE phone
        // fuzzy match, min 8 dígitos, scope business_id, ambiguidade=log warn)
        // pra ser reusada pelo command de backfill (Parte B) com a MESMA
        // lógica — sem duplicar regex/LIKE/ambiguidade entre webhook e CLI.
        if ($conversation->wasRecentlyCreated || $conversation->contact_id === null) {
            /** @var ConversationContactLinker $linker */
            $linker = app(ConversationContactLinker::class);
            $linker->tryLink($conversation);
        }

        // Idempotência (US-WA-070): daemon Baileys reentrega o mesmo
        // `provider_message_id` várias vezes em reconnect/replay/restart.
        // `create()` puro quebrava na UNIQUE `msgs_provider_msg_uniq` →
        // 500 → daemon retentava infinito → todo pipeline real-time morria
        // junto (Observer/Event/Listener/Centrifugo nunca rodam após exception).
        //
        // `firstOrCreate` keyed em (business_id, provider_message_id):
        //  - 1ª chamada: cria row → Observer fires → Centrifugo publish OK
        //  - reentregas: no-op (row já existe) → 200 idempotente sem dup
        //
        // Pula global scope no SELECT do firstOrCreate (webhook não tem
        // session user — global scope HasBusinessScope filtraria por null).
        $message = Message::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->firstOrCreate(
                [
                    'business_id' => $channel->business_id,
                    'provider_message_id' => $providerMessageId,
                ],
                [
                    'conversation_id' => $conversation->id,
                    'direction' => $fromMe ? 'outbound' : 'inbound',
                    'provider' => $channel->type,
                    'type' => $type,
                    'body' => $body,
                    'payload' => $data,
                    'status' => $fromMe ? 'sent' : 'received',
                    'sender_kind' => $fromMe ? 'human' : null,
                    // US-WA-072 + Hotfix B1 (2026-05-12) — preserva meta vinda
                    // do daemon antes do download. Quando daemon C normaliza
                    // flat (`data.media_url` set), persiste o path direto pra
                    // UI já renderizar a mídia. Quando só vem proto Baileys
                    // raw aninhado, `$mediaUrl` permanece null (URL cripto
                    // `.enc` que Hostinger não decrypta) — UI B2 mostra
                    // placeholder "aguardando download".
                    'media_url' => $mediaUrl,
                    'media_mime' => $mediaMime,
                    'media_size_bytes' => $mediaSize,
                    'media_duration_s' => $mediaDuration,
                    'media_filename' => $mediaFilename,
                ]
            );

        // US-WA-072 — dispatcha DownloadMediaJob async pra baixar a mídia do
        // provider, salvar no disco public, gerar thumbnail (image) e
        // encadear TranscribeAudioJob (audio). Só se for mídia E só na 1ª
        // criação (reentregas não duplicam download).
        $mediaTypes = ['image', 'audio', 'video', 'document'];
        if ($message->wasRecentlyCreated && in_array($type, $mediaTypes, true) && $mediaUrl) {
            DownloadMediaJob::dispatch(
                $channel->business_id,
                $message->id,
                $mediaUrl,
                (string) $mediaMime,
            );
        }

        // Atualiza last_*_at + unread count APENAS se row foi criada agora
        // (reentregas duplicadas NÃO devem incrementar unread).
        if ($message->wasRecentlyCreated) {
            $conversation->forceFill([
                'last_message_at' => now(),
                'last_inbound_at' => $fromMe ? $conversation->last_inbound_at : now(),
                'last_outbound_at' => $fromMe ? now() : $conversation->last_outbound_at,
                'unread_count' => $fromMe ? $conversation->unread_count : $conversation->unread_count + 1,
            ])->save();
        }

        return response()->json([
            'ok' => true,
            'note' => $message->wasRecentlyCreated ? 'message_persisted' : 'message_duplicate_ignored',
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
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
