<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Util\OtelHelper;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;

/**
 * US-WA-072 — Envio outbound de mídia via daemon Baileys.
 *
 * Acionado pelo `InboxController::sendMedia()` após persistir Message
 * com `status='queued'` + `media_url` apontando pro arquivo já no disco
 * public. Aqui só re-publica via daemon POST /instances/{id}/media,
 * passando a URL absoluta (daemon faz fetch internamente).
 *
 * Suporta `whatsapp_baileys` nesta fase. Z-API/Meta vão em PR futuro
 * (drivers ainda não aceitam Channel polimórfico).
 *
 * Multi-tenant Tier 0 (ADR 0093): `$businessId` no constructor.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-072
 */
class SendMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(
        public int $businessId,
        public int $messageId,
    ) {}

    public function handle(): void
    {
        OtelHelper::span('whatsapp.message.send_media', [
            'business_id' => $this->businessId,
            'message_id' => $this->messageId,
        ], fn () => $this->doHandle());
    }

    private function doHandle(): void
    {
        Log::info('whatsapp.message.send_media.started', [
            'business_id' => $this->businessId,
            'message_id' => $this->messageId,
        ]);

        $message = Message::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->where('id', $this->messageId)
            ->first();

        if (! $message) {
            Log::warning('[send_media] message not found', [
                'business_id' => $this->businessId,
                'message_id' => $this->messageId,
            ]);
            return;
        }

        $conversation = Conversation::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->where('id', $message->conversation_id)
            ->with('channel')
            ->first();

        if (! $conversation || ! $conversation->channel) {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => 'Conversation/Channel não encontrado.',
            ])->save();
            return;
        }

        $channel = $conversation->channel;

        // M3 fix 2026-05-28: aceita Baileys OU Whatsmeow. Dispatch por type:
        //   - Baileys (legacy):  POST /instances/{ch-uuid}/media  Bearer {API_KEY}
        //                        body {to, media_url, mimetype, filename, caption, type}
        //   - Whatsmeow (ADR 0204): POST /chat/send/{image|video|audio|document}
        //                        Header Token {user_token}
        //                        body {Phone, Image|Video|Audio|Document: <url>, Caption, FileName}
        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS
            && $channel->type !== Channel::TYPE_WHATSAPP_WHATSMEOW) {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => "Envio de mídia só implementado pra Baileys/Whatsmeow nesta fase. Tipo: {$channel->type}",
            ])->save();
            return;
        }

        $toPhone = preg_replace('/^\+/', '', $conversation->customer_external_id);
        $mediaPublicUrl = Storage::disk('public')->url($message->media_url);
        $isWhatsmeow = $channel->type === Channel::TYPE_WHATSAPP_WHATSMEOW;

        if ($isWhatsmeow) {
            $daemonUrl = rtrim((string) config('whatsapp.whatsmeow.daemon_url'), '/');
            $userToken = $channel->config_json['whatsmeow_user_token'] ?? null;
            if (! $userToken) {
                $message->forceFill([
                    'status' => 'failed',
                    'failed_reason' => 'whatsmeow_user_token ausente em channel.config_json — reconecte via QR.',
                ])->save();
                return;
            }
            $endpoint = match ($message->type) {
                'image' => '/chat/send/image',
                'video' => '/chat/send/video',
                'audio' => '/chat/send/audio',
                'document', 'pdf' => '/chat/send/document',
                default => null,
            };
            if ($endpoint === null) {
                $message->forceFill([
                    'status' => 'failed',
                    'failed_reason' => "Tipo de mídia não suportado pelo whatsmeow daemon: {$message->type}",
                ])->save();
                return;
            }
            // WuzAPI accepts URL no body field específico por tipo
            $bodyField = match ($message->type) {
                'image' => 'Image',
                'video' => 'Video',
                'audio' => 'Audio',
                default => 'Document',
            };
            $payload = array_filter([
                'Phone' => $toPhone,
                $bodyField => $mediaPublicUrl,
                'Caption' => $message->body ?? null,
                'FileName' => $message->media_filename ?? null,
                'Id' => strtoupper(str_replace('-', '', (string) \Illuminate\Support\Str::uuid())),
            ], fn ($v) => $v !== null && $v !== '');
            $authHeaders = ['Token' => $userToken, 'Content-Type' => 'application/json'];
        } else {
            $daemonUrl = rtrim((string) config('whatsapp.baileys.daemon_url'), '/');
            $apiKey = (string) config('whatsapp.baileys.api_key');
            $instanceId = 'ch-' . str_replace('-', '', $channel->channel_uuid);
            $endpoint = "/instances/{$instanceId}/media";
            $payload = [
                'to' => $toPhone,
                'media_url' => $mediaPublicUrl,
                'mimetype' => $message->media_mime,
                'filename' => $message->media_filename,
                'caption' => $message->body,
                'type' => $message->type,
            ];
            $authHeaders = ['Authorization' => "Bearer {$apiKey}", 'Content-Type' => 'application/json'];
        }

        try {
            $response = Http::withHeaders($authHeaders)
                ->withoutVerifying() // FIXME(US-WA-058): cert LE pendente CT 100
                ->timeout(30)
                ->post("{$daemonUrl}{$endpoint}", $payload);
        } catch (\Throwable $e) {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => mb_substr($e->getMessage(), 0, 240),
            ])->save();
            Log::error('[send_media] daemon exception', [
                'message_id' => $message->id,
                'channel_type' => $channel->type,
                'error' => $e->getMessage(),
            ]);
            throw $e; // permite retry
        }

        if (! $response->successful()) {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => 'Daemon ' . $response->status() . ': ' . mb_substr($response->body(), 0, 200),
            ])->save();
            Log::warning('[send_media] daemon non-2xx', [
                'message_id' => $message->id,
                'channel_type' => $channel->type,
                'status' => $response->status(),
            ]);
            return;
        }

        $respJson = $response->json();
        // Normaliza por tipo. Baileys {message_id, status} vs WuzAPI {Id, Details}.
        $providerMsgId = $isWhatsmeow
            ? ($respJson['Id'] ?? $respJson['Data']['Id'] ?? null)
            : ($respJson['message_id'] ?? null);
        $newStatus = $isWhatsmeow
            ? (($respJson['Details'] ?? '') === 'Sent' ? 'sent' : ($respJson['Details'] ?? 'sent'))
            : ($respJson['status'] ?? 'sent');

        $message->forceFill([
            'status' => $newStatus,
            'provider_message_id' => $providerMsgId,
        ])->save();

        Log::info('[send_media] dispatched', [
            'message_id' => $message->id,
            'business_id' => $this->businessId,
            'type' => $message->type,
        ]);
    }
}
