<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

/**
 * WhatsmeowWebhookController — recebe eventos do daemon Go WuzAPI (CT 100).
 *
 * Middleware `whatsapp.whatsmeow.signature` (VerifyWhatsmeowSignature) valida
 * HMAC global + resolve `whatsapp.config` + `whatsapp.channel` em request
 * attributes ANTES do controller rodar.
 *
 * Eventos do daemon (WuzAPI events subscription):
 *   - `Message`         — mensagem inbound (cliente → business)
 *   - `ReadReceipt`     — status update (sent/delivered/read)
 *   - `Connected`       — sessão WhatsApp Web pareada com sucesso
 *   - `Disconnected`    — sessão caiu (transitório ou manual)
 *   - `PairSuccess`     — variante de Connected (Beeper docs)
 *
 * **Multi-tenant Tier 0 (ADR 0093):** webhook URL inclui {business_uuid};
 * middleware resolveu config + channel via business_id global scope bypass
 * (pré-auth) com HMAC valid. Aqui usamos os attributes resolvidos.
 *
 * Respostas:
 *   - sempre 200 quando assinatura válida (daemon precisa do ack)
 *   - 401 se HMAC/Token inválido (middleware)
 *
 * @see memory/decisions/0204-whatsmeow-driver-substituto-baileys.md
 * @see Modules/Whatsapp/Http/Middleware/VerifyWhatsmeowSignature.php
 */
class WhatsmeowWebhookController extends Controller
{
    public function __construct(private readonly CentrifugoPublisher $centrifugo) {}

    public function handle(Request $request): JsonResponse
    {
        // ADR 0135 (Caixa Unificada v4): middleware resolve business_id via
        // business.uuid (NÃO whatsapp_business_configs legacy). Sessão 2026-05-27.
        $businessId = (int) $request->attributes->get('whatsapp.business_id', 0);
        /** @var Channel|null $channel */
        $channel = $request->attributes->get('whatsapp.channel');

        $payload = $request->all();

        // WuzAPI envelope: payload outer tem {instanceName, jsonData (string)}.
        // jsonData é JSON string aninhado com {event:{...}, type:"Message"}.
        // Sessão 2026-05-27 confirmou via daemon logs. Faz unwrap defensivo.
        $unwrapped = $payload;
        if (isset($payload['jsonData']) && is_string($payload['jsonData'])) {
            $decoded = json_decode($payload['jsonData'], true);
            if (is_array($decoded)) {
                $unwrapped = array_merge($payload, $decoded);
            }
        }

        $event = (string) ($unwrapped['type'] ?? $unwrapped['Event'] ?? '');
        $instanceName = (string) ($payload['instanceName'] ?? $unwrapped['instanceName'] ?? '');

        // Fallback resolveChannel via instanceName (WuzAPI envia nome do user
        // criado em POST /admin/users, que é nosso whatsmeowUserName()).
        if ($channel === null && $instanceName !== '') {
            $channel = Channel::query()
                ->withoutGlobalScope(\Modules\Jana\Scopes\ScopeByBusiness::class)
                ->where('business_id', $businessId)
                ->where('type', Channel::TYPE_WHATSAPP_WHATSMEOW)
                ->get()
                ->first(fn (Channel $ch) => $ch->whatsmeowUserName() === $instanceName);
        }

        Log::info('whatsapp.webhook.received', [
            'business_id' => $businessId,
            'provider' => 'whatsmeow',
            'channel_id' => $channel?->id,
            'event' => $event,
            'instance_name' => $instanceName,
        ]);

        // Substitui payload pelo unwrapped pra downstream
        $payload = $unwrapped;

        // Eventos de mensagem/status → enfileira processamento assíncrono
        if (in_array($event, ['Message', 'ReadReceipt'], true)) {
            ProcessIncomingWebhookJob::dispatch(
                $businessId,
                'whatsmeow',
                array_merge($payload, ['provider' => 'whatsmeow']),
                null, // phone_id legacy (não usado com Channel-based)
            );

            return response()->json(['ok' => true], 200);
        }

        // Eventos de estado da sessão → atualiza channel health + publica realtime
        if ($channel === null) {
            // Sem channel resolvido — só loga e retorna 200 (daemon vai retentar)
            Log::warning('[whatsapp.webhook.whatsmeow] evento de estado sem channel resolvido', [
                'business_id' => $businessId,
                'event' => $event,
            ]);
            return response()->json(['ok' => true, 'note' => 'no_channel'], 200);
        }

        return match ($event) {
            'Connected', 'PairSuccess' => $this->handleConnected($channel, $payload),
            'Disconnected', 'LoggedOut' => $this->handleDisconnected($channel, $payload),
            'QRCode', 'QR' => $this->handleQrUpdated($channel, $payload),
            default => $this->handleUnknown($channel, $event, $payload),
        };
    }

    /**
     * Sessão WhatsApp Web pareou com sucesso. Marca channel ativo + publica
     * estado realtime pra UI atualizar.
     */
    private function handleConnected(Channel $channel, array $payload): JsonResponse
    {
        $channel->forceFill([
            'status' => 'active',
            'channel_health' => 'healthy',
            'channel_health_consecutive_failures' => 0,
            'last_health_check_at' => now(),
            'last_health_message' => 'whatsmeow connected',
        ])->save();

        $this->publish($channel, 'connected', [
            'state' => 'connected',
            'channel_id' => $channel->id,
            'jid' => $payload['Data']['Jid'] ?? null,
        ]);

        return response()->json(['ok' => true, 'note' => 'connected_recorded'], 200);
    }

    private function handleDisconnected(Channel $channel, array $payload): JsonResponse
    {
        $reason = (string) ($payload['Data']['Reason'] ?? $payload['reason'] ?? 'unknown');
        $banKeywords = ['banned', 'forbidden', 'logged_out', 'multidevice_mismatch'];
        $banDetected = false;
        foreach ($banKeywords as $kw) {
            if (str_contains(strtolower($reason), $kw)) {
                $banDetected = true;
                break;
            }
        }

        $newHealth = $banDetected ? 'banned' : 'disconnected';

        $channel->forceFill([
            'channel_health' => $newHealth,
            'last_health_check_at' => now(),
            'last_health_message' => "whatsmeow disconnected: {$reason}",
        ])->save();

        if ($banDetected) {
            Log::error('[whatsapp.webhook.whatsmeow.ban_detected]', [
                'business_id' => $channel->business_id,
                'channel_id' => $channel->id,
                'reason' => $reason,
            ]);
        }

        $this->publish($channel, $banDetected ? 'ban_detected' : 'disconnected', [
            'state' => $newHealth,
            'channel_id' => $channel->id,
            'reason' => $reason,
        ]);

        return response()->json(['ok' => true, 'note' => "{$newHealth}_recorded"], 200);
    }

    private function handleQrUpdated(Channel $channel, array $payload): JsonResponse
    {
        $qrBase64 = $payload['Data']['QRCode'] ?? $payload['qr'] ?? null;

        $this->publish($channel, 'qr_updated', [
            'state' => 'qr_required',
            'channel_id' => $channel->id,
            'qr' => $qrBase64,
            'expires_in_seconds' => $payload['Data']['Expires'] ?? 60,
        ]);

        return response()->json(['ok' => true, 'note' => 'qr_published'], 200);
    }

    private function handleUnknown(Channel $channel, string $event, array $payload): JsonResponse
    {
        Log::info('[whatsapp.webhook.whatsmeow] evento desconhecido ignorado', [
            'business_id' => $channel->business_id,
            'channel_id' => $channel->id,
            'event' => $event,
        ]);

        return response()->json(['ok' => true, 'note' => 'unknown_event_ignored'], 200);
    }

    /**
     * Publica evento Whatsmeow no canal Centrifugo do business
     * (`whatsapp:business:{id}`). UI Settings/Inbox reage em tempo real.
     * Falha silenciosa é OK — Centrifugo é eventually consistent (ADR 0058).
     */
    private function publish(Channel $channel, string $event, array $payload): void
    {
        $this->centrifugo->publish(
            "whatsapp:business:{$channel->business_id}",
            ['event' => "whatsmeow.{$event}"] + $payload
        );
    }
}
