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
use Modules\Whatsapp\Services\Contacts\LidPhoneResolver;
use Modules\Whatsapp\Services\Csat\CsatResponseParser;
use Modules\Whatsapp\Services\Macros\MacroVariantResponseTracker;
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

            case 'history.sync':
                // Batch de mensagens históricas vindo do messaging-history.set
                // do Baileys daemon. Pode ser do pairing inicial (syncType 1/2)
                // ou on-demand (syncType 6 via fetchMessageHistory).
                // Chunked em batches de 100 msgs (anti-overflow webhook body).
                // MessagePersister idempotente — re-run safe.
                return $this->handleHistorySync($channel, $data);

            default:
                return response()->json(['ok' => true, 'note' => 'unknown_event_ignored'], 200);
        }
    }

    /**
     * Processa batch histórico messaging-history.set do daemon — ASYNC.
     *
     * V2 fix 2026-05-13 madrugada (incident burst webhook 404/429):
     * antes processava 50-100 msgs SÍNCRONO inline, com
     * `QUEUE_CONNECTION=sync` saturava PHP-FPM worker pool em ~30s →
     * próximos webhooks recebiam 404 do Apache → daemon retry esgotava
     * → msgs históricas perdidas (10k confirmado em prod).
     *
     * Agora: dispatcha `PersistHistorySyncBatchJob` na queue (workers
     * separados) e RESPONDE 202 IMEDIATO ao daemon. Daemon não vê 404
     * em burst, mensagens persistem em background async.
     *
     * Idempotência: MessagePersister via `provider_message_id` UNIQUE —
     * mesma msg vinda 2x = no-op (importante porque WhatsApp re-pareamento
     * manda histórico FULL de novo, não incremental).
     *
     * @see Modules/Whatsapp/Jobs/PersistHistorySyncBatchJob.php
     * @see Modules/Whatsapp/Services/Webhook/MessagePersister.php
     */
    protected function handleHistorySync(Channel $channel, array $data): JsonResponse
    {
        $syncType = (int) ($data['sync_type'] ?? 0);
        $chunkIndex = (int) ($data['chunk_index'] ?? 0);
        $chunkTotal = (int) ($data['chunk_total'] ?? 1);
        $messages = (array) ($data['messages'] ?? []);
        // Baileys daemon envia `contacts` array SÓ no chunk_index=0 (Instance.ts:279-281).
        // Shape: [{id: '5511X@s.whatsapp.net', name, notify, verifiedName}, ...].
        // Pode vir null/undefined nos chunks subsequentes — defensive cast pra array.
        $contacts = (array) ($data['contacts'] ?? []);

        Log::info('[channel.baileys.history-sync]', [
            'channel_id' => $channel->id,
            'business_id' => $channel->business_id,
            'sync_type' => $syncType,
            'chunk_index' => $chunkIndex,
            'chunk_total' => $chunkTotal,
            'messages_count' => count($messages),
            'contacts_count' => count($contacts),
        ]);

        // ─── Contacts persistence (fix 2026-05-15 — Wagner reportou sincronia
        // dos contatos não trouxe contatos pós re-pareamento Baileys 7.x).
        //
        // Daemon ENVIA contacts no payload mas backend ANTES ignorava — só lia
        // messages. Resultado: Conversation só ganhava nome quando msg com
        // pushName chegava; antes disso, exibia E.164 cru.
        //
        // Agora: dispatcha Job assíncrono que hidrata Conversation.contact_name
        // via UPDATE idempotente (preserva nome existente — só preenche quando
        // vazio ou igual ao E.164 fallback).
        //
        // Mesmo Job pattern do PersistHistorySyncBatchJob: queue=database,
        // 3 tries + backoff [10,30,90], businessId no constructor.
        //
        // @see Modules/Whatsapp/Jobs/PersistContactsFromHistorySyncJob.php
        if (! empty($contacts)) {
            \Modules\Whatsapp\Jobs\PersistContactsFromHistorySyncJob::dispatch(
                businessId: $channel->business_id,
                channelId: $channel->id,
                syncType: $syncType,
                contacts: $contacts,
            );

            // Métrica OTel lightweight bridge — contacts chunk enfileirado
            Log::channel('single')->info('[whatsapp.history-sync-contacts] chunk enfileirado', [
                'metric_name' => 'whatsapp_history_contacts_queued',
                'business_id' => $channel->business_id,
                'channel_id' => $channel->id,
                'sync_type' => $syncType,
                'contacts_count' => count($contacts),
                'attempt' => 1,
            ]);
        }

        if (empty($messages)) {
            // Edge case: chunk_index=0 só com contacts (sem messages) — ainda OK,
            // contacts foi dispatchado acima. Retorna 200.
            if (! empty($contacts)) {
                return response()->json([
                    'ok' => true,
                    'note' => 'history_chunk_contacts_only_queued',
                    'contacts_count' => count($contacts),
                ], 202);
            }
            return response()->json(['ok' => true, 'note' => 'history_chunk_empty'], 200);
        }

        // Arquitetura Wagner 2026-05-14 02h: "recebe tudo de maneira rapida
        // no redis ou onde, depois sincroniza com o banco, sempre guarda
        // para não perder".
        //
        // Job vai pra tabela `jobs` (persistente, atômico INSERT) — daemon
        // recebe 202 IMEDIATO. Webhook handler termina em ~5ms (só 1 INSERT
        // na queue, sem processar nenhuma msg). PHP-FPM worker liberado pro
        // próximo webhook.
        //
        // Worker separado processa fila em background:
        //   cron * * * * * php artisan queue:work database --queue=whatsapp-history
        //   --max-time=55 --stop-when-empty
        //
        // Garantia "não perder":
        // - INSERT na tabela `jobs` é atômico (MySQL ACID)
        // - Se Job falhar processando, 3 tries + backoff exponencial 10s/30s/90s
        // - Após 3 falhas, vai pra failed_jobs (não perde, só fica visível)
        // - Idempotente via provider_message_id UNIQUE no MessagePersister —
        //   re-run safe se WhatsApp mandar mesma msg 2x (re-pareamento)
        \Modules\Whatsapp\Jobs\PersistHistorySyncBatchJob::dispatch(
            businessId: $channel->business_id,
            channelId: $channel->id,
            syncType: $syncType,
            chunkIndex: $chunkIndex,
            chunkTotal: $chunkTotal,
            messages: $messages,
        );

        // ─── Métrica OTel lightweight bridge: chunk_queued ─────────────────
        // US-WA-085. Loki agrega via logQL `metric_name="whatsapp_history_chunk_queued"`
        // → contador Grafana. Pareia 1:1 com chunk_processed/chunk_failed —
        // se rate(queued) > rate(processed+failed) por >5min = backlog crescendo.
        // Tier 0 multi-tenant: business_id SEMPRE presente.
        Log::channel('single')->info('[whatsapp.history-sync-job] chunk enfileirado', [
            'metric_name' => 'whatsapp_history_chunk_queued',
            'business_id' => $channel->business_id,
            'channel_id' => $channel->id,
            'sync_type' => $syncType,
            'chunk_index' => $chunkIndex,
            'chunk_total' => $chunkTotal,
            'messages_count' => count($messages),
            'attempt' => 1, // sempre 1 — caller enfileira uma vez; retries são internas ao Job
        ]);

        return response()->json([
            'ok' => true,
            'note' => 'history_chunk_queued',
            'messages_count' => count($messages),
            'chunk_index' => $chunkIndex,
            'chunk_total' => $chunkTotal,
        ], 202);
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
        $senderPn = $msgKey['senderPn'] ?? null; // Baileys 6.7.x legacy — phone real quando remoteJid é @lid
        // Baileys 7.x — remoteJidAlt espelha PN/LID do remoteJid (alt JID).
        // Quando remoteJid é @lid, alt vem @s.whatsapp.net e vice-versa.
        // Não existe em payload 6.7.x legacy (opcional, back-compat preservado).
        $remoteJidAlt = $msgKey['remoteJidAlt'] ?? null;
        $providerMessageId = $msgKey['id'] ?? null;
        $fromMe = (bool) ($msgKey['fromMe'] ?? false);
        $pushName = $data['push_name'] ?? null;

        if (! $remoteJid && ! $senderPn && ! $remoteJidAlt) {
            return response()->json(['ok' => false, 'error' => 'no_remote_jid'], 200);
        }

        // Filter Tier 0 — drop NON-conversação:
        //   - `status@broadcast` → feed de WhatsApp Status (Stories) que outros contatos postam.
        //     Bug 2026-05-12: 54 status agruparam fake numa única conv #7 "+status" com push_name
        //     da última pessoa que postou. NÃO É CONVERSA — é stream de stories.
        //   - `@g.us` → grupos WhatsApp. Inbox é 1:1 atendimento por enquanto;
        //     grupos exigem schema diferente (US futura). Drop silencioso.
        //   - `@newsletter` / `@broadcast` → canais/listas de transmissão. Drop.
        $isStatusBroadcast = $remoteJid === 'status@broadcast'
            || str_starts_with((string) $remoteJid, 'status@');
        $isGroupOrBroadcast = str_contains((string) $remoteJid, '@g.us')
            || str_contains((string) $remoteJid, '@broadcast')
            || str_contains((string) $remoteJid, '@newsletter');
        if ($isStatusBroadcast || $isGroupOrBroadcast) {
            return response()->json([
                'ok' => true,
                'note' => $isStatusBroadcast ? 'status_broadcast_dropped' : 'group_or_broadcast_dropped',
                'remote_jid_sample' => substr((string) $remoteJid, 0, 50),
            ], 200);
        }

        // Resolve phone E.164 — prioridade:
        // 1. senderPn (Baileys 6.7.x legacy, formato "5548999872822@s.whatsapp.net") quando remoteJid é @lid → preferir
        // 2. remoteJidAlt se for @s.whatsapp.net (Baileys 7.x novo — espelha PN quando remoteJid é @lid)
        // 3. remoteJid se for @s.whatsapp.net normal
        // 4. fallback @lid se nada mais (workaround LidPhoneResolver entra em ação abaixo)
        if ($senderPn && str_contains((string) $senderPn, '@s.whatsapp.net')) {
            $resolvedJid = $senderPn;
        } elseif ($remoteJidAlt && str_contains((string) $remoteJidAlt, '@s.whatsapp.net')) {
            $resolvedJid = $remoteJidAlt;
        } else {
            $resolvedJid = $remoteJid;
        }
        $rawNumber = preg_replace('/@.+$/', '', (string) $resolvedJid);
        $customerExternalId = '+' . $rawNumber;

        // US-WA-093 — LID Resolution Custom (workaround pré-Baileys 7.x).
        //
        // Bug 2026-05-12 prod biz=1: customer_phone "+519691546333945" (15
        // dígitos sem DDI BR) na inbox em vez do número real. É um LID
        // (Linked ID Multi-Device) que mascara o phone quando cliente fala
        // via Click-to-Chat / Status / Ads. Baileys 6.7.9 não expõe Alt JID
        // — workaround tabela ponte custom.
        //
        // Lógica:
        //  (a) senderPn veio E remoteJid é @lid → REGISTRA mapping pra
        //      próximas msgs do mesmo LID resolverem cache (customer_phone
        //      já está correto via senderPn neste caso).
        //  (b) senderPn NÃO veio E remoteJid é @lid → tenta resolver via
        //      cache. Se acertar, troca customer_external_id pelo phone
        //      real cacheado. Caso contrário, REGISTRA o LID com phone=null
        //      pra rastrear (e UI badge "número oculto" entra em ação).
        $remoteJidIsLid = is_string($remoteJid) && str_contains($remoteJid, '@lid');
        if ($remoteJidIsLid) {
            /** @var LidPhoneResolver $lidResolver */
            $lidResolver = app(LidPhoneResolver::class);
            $senderPnHasPhone = is_string($senderPn) && str_contains($senderPn, '@s.whatsapp.net');
            // Baileys 7.x — remoteJidAlt entrega @s.whatsapp.net quando remoteJid é @lid.
            // Funcionalmente equivalente ao senderPn 6.7.x — usar como fonte
            // alternativa pra alimentar o cache LID→PN.
            $altHasPhone = is_string($remoteJidAlt) && str_contains($remoteJidAlt, '@s.whatsapp.net');

            if ($senderPnHasPhone) {
                // Caso (a) 6.7.x: WhatsApp deu o phone real ao lado do LID — grava o par.
                $lidResolver->record(
                    $channel->business_id,
                    $remoteJid,
                    $senderPn,
                );
            } elseif ($altHasPhone) {
                // Caso (a') Baileys 7.x: phone real vem em remoteJidAlt — grava o par.
                $lidResolver->record(
                    $channel->business_id,
                    $remoteJid,
                    $remoteJidAlt,
                );
            } else {
                // Caso (b): só temos LID — tenta cache.
                $cachedPhone = $lidResolver->resolve($channel->business_id, $remoteJid);
                if ($cachedPhone !== null) {
                    $customerExternalId = $cachedPhone;
                } else {
                    // Registra LID com phone=null pra futura descoberta.
                    $lidResolver->record($channel->business_id, $remoteJid, null);
                }
            }
        }

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
        //
        // US-WA-043 (PR-8 CYCLE-07) — dispatch PROATIVO mesmo sem `media_url`
        // direto. Pra Baileys, URL vem aninhada (.enc + mediaKey) e
        // `DownloadMediaJob::fetchViaDaemonDecrypt()` resolve a chave via
        // payload `imageMessage`/`audioMessage`/etc. SEM esse dispatch,
        // mídia inbound ficava só com placeholder "aguardando download" até
        // `BackfillMediaDownloadCommand` rodar (cron horário). Agora a UI
        // mostra a mídia segundos após webhook chegar.
        //
        // Guard `media_download_status IS NULL` evita re-dispatch em race
        // condition (Observer pode disparar em paralelo). `wasRecentlyCreated`
        // garante que reentregas idempotentes não dispatcham de novo.
        $mediaTypes = ['image', 'audio', 'video', 'document'];
        $downloadAlreadyDone = in_array(
            $message->media_download_status,
            [Message::DOWNLOAD_STATUS_SUCCESS, Message::DOWNLOAD_STATUS_DOWNLOADING],
            true,
        );
        if ($message->wasRecentlyCreated
            && in_array($type, $mediaTypes, true)
            && $message->media_url === null
            && ! $downloadAlreadyDone
        ) {
            DownloadMediaJob::dispatch(
                $channel->business_id,
                $message->id,
                (string) $mediaUrl,
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

        // PR-6 CYCLE-07 — CSAT response parsing.
        //
        // Quando atendente marca conv como `resolved`, CsatDispatcher envia
        // mensagem CSAT outbound + cria row pending em whatsapp_csat_responses.
        // Próximo inbound do mesmo cliente (`!$fromMe`) pode ser a nota — tenta
        // extrair score 1-5 + comment opcional via parser tolerante.
        //
        // Critérios pra tentar:
        //   - Message foi criada agora (não-duplicada)
        //   - É inbound (!$fromMe)
        //   - Type text com body (parser não roda em mídia)
        //   - CSAT feature toggle on (default true)
        //
        // Parser internamente busca CsatResponse pending dentro de 24h da
        // asked_at. Se não há pending → no-op silencioso. Se inbound não
        // parser como 1-5 → no-op também (cliente respondeu texto livre
        // "obrigado" sem nota, atendente vê normalmente na thread).
        if (
            $message->wasRecentlyCreated
            && ! $fromMe
            && $type === 'text'
            && $body !== null
            && (bool) config('whatsapp.csat.enabled', true)
        ) {
            $score = app(CsatResponseParser::class)->tryParse((string) $body);
            if ($score !== null) {
                $comment = app(CsatResponseParser::class)->tryParseComment((string) $body);
                app(CsatResponseParser::class)->recordResponse(
                    $channel->business_id,
                    $conversation->id,
                    $score,
                    $comment,
                    (int) $message->id,
                );
            }
        }

        // US-WA-049 — A/B testing response tracking (gap P2 #18).
        //
        // Quando inbound chega numa conv onde a última outbound tem
        // `macro_variant_id != null` E foi enviada nas últimas 24h,
        // incrementa `response_count` da variante. Idempotente — grava
        // flag em messages.payload pra nunca duplicar em reentregas
        // do daemon.
        //
        // Por que aqui: bloco isolado dos outros agents (CSAT/LID/auto-link)
        // pra evitar conflito de merge. Service `MacroVariantResponseTracker`
        // encapsula query + idempotência (ver Modules/Whatsapp/Services/
        // Macros/MacroVariantResponseTracker.php).
        if ($message->wasRecentlyCreated && ! $fromMe) {
            try {
                app(MacroVariantResponseTracker::class)
                    ->trackResponseFromInbound($channel->business_id, $message);
            } catch (\Throwable $e) {
                // Tracking é best-effort — falha NÃO derruba webhook.
                Log::warning('[macro_variant.response_track_failed]', [
                    'business_id' => $channel->business_id,
                    'inbound_message_id' => $message->id,
                    'exception' => mb_substr($e->getMessage(), 0, 200),
                ]);
            }
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
