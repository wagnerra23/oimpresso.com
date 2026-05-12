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

        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS) {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => "Envio de mídia só implementado pra Baileys nesta fase. Tipo: {$channel->type}",
            ])->save();
            return;
        }

        $daemonUrl = config('whatsapp.baileys.daemon_url');
        $apiKey = config('whatsapp.baileys.api_key');
        $instanceId = 'ch-' . str_replace('-', '', $channel->channel_uuid);
        $toPhone = preg_replace('/^\+/', '', $conversation->customer_external_id);

        // Daemon faz fetch da URL absoluta — passamos URL pública (assinada ou
        // direto se disco público). Em prod precisaria URL externa acessível
        // pelo CT 100 (Storage::url() na Hostinger).
        $mediaPublicUrl = Storage::disk('public')->url($message->media_url);

        try {
            $response = Http::withToken($apiKey)
                ->withoutVerifying() // FIXME(US-WA-058): cert LE pendente
                ->timeout(30)
                ->post("{$daemonUrl}/instances/{$instanceId}/media", [
                    'to' => $toPhone,
                    'media_url' => $mediaPublicUrl,
                    'mime' => $message->media_mime,
                    'filename' => $message->media_filename,
                    'caption' => $message->body, // body é caption no envio
                    'type' => $message->type,    // image|audio|document|video
                ]);
        } catch (\Throwable $e) {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => mb_substr($e->getMessage(), 0, 240),
            ])->save();
            Log::error('[send_media] daemon exception', [
                'message_id' => $message->id,
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
                'status' => $response->status(),
            ]);
            return;
        }

        $payload = $response->json();
        $message->forceFill([
            'status' => $payload['status'] ?? 'sent',
            'provider_message_id' => $payload['message_id'] ?? null,
        ])->save();

        Log::info('[send_media] dispatched', [
            'message_id' => $message->id,
            'business_id' => $this->businessId,
            'type' => $message->type,
        ]);
    }
}
