<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

/**
 * BaileysWebhookController — recebe eventos do daemon Node Baileys (CT 100).
 *
 * Middleware `whatsapp.baileys.signature` (VerifyBaileysSignature) valida
 * Bearer token timing-safe + resolve `whatsapp.phone` via instance_id.
 *
 * Eventos do daemon (ver Modules/Whatsapp/daemon-node/src/webhook/WebhookDispatcher.ts):
 *   - `message`         — mensagem inbound (cliente → business)
 *   - `message_status`  — status update (sent/delivered/read)
 *   - `connected`       — sessão Whatsapp Web pareada com sucesso
 *   - `qr_updated`      — novo QR Code disponível pra scan
 *   - `session_lost`    — sessão caiu (transitório, daemon vai tentar reconectar)
 *   - `ban_detected`    — daemon detectou ban Meta (não-recuperável sem novo número)
 *   - `disconnected`    — disconnect manual
 *
 * **Multi-números (ADR 0117 — US-WA-040):**
 * State updates (connected/qr_updated/session_lost/ban_detected/disconnected)
 * preferem atualizar o `WhatsappBusinessPhone` específico se resolvido pelo
 * middleware. Fallback config legacy se phone não cadastrado (durante
 * coexistência PR 1 → PR 5).
 *
 * **US-WA-022 — Estado reativo:** todos events de saúde publicados em
 * Centrifugo channel `whatsapp:business:{id}` pra UI Settings reagir em
 * tempo real (ADR 0058). Channel granular `whatsapp:business:{id}:phone:{uuid}`
 * fica pra PR 3.
 *
 * Respostas:
 *   - sempre 200 quando assinatura válida (daemon precisa do ack pra parar de retentar)
 *   - 401 se Bearer inválido (middleware)
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002d, US-WA-022, US-WA-040
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §16.5
 * @see resources/js/Pages/Whatsapp/Settings.charter.md
 */
class BaileysWebhookController extends Controller
{
    public function __construct(private readonly CentrifugoPublisher $centrifugo) {}

    public function handle(Request $request): JsonResponse
    {
        /** @var WhatsappBusinessConfig $config */
        $config = $request->attributes->get('whatsapp.config');
        /** @var WhatsappBusinessPhone|null $phone */
        $phone = $request->attributes->get('whatsapp.phone');

        // Target = phone se resolvido, senão config (legacy fallback durante coexistência)
        $target = $phone ?? $config;

        $event = (string) $request->input('event', '');
        $data = (array) $request->input('data', []);

        Log::info('whatsapp.webhook.received', [
            'business_id' => $config->business_id,
            'provider' => 'baileys',
            'phone_id' => $phone?->id,
            'event' => $event,
        ]);

        switch ($event) {
            case 'message':
            case 'message_status':
                ProcessIncomingWebhookJob::dispatch(
                    $config->business_id,
                    'baileys',
                    array_merge($request->all(), ['provider' => 'baileys']),
                    $phone?->id,
                );

                return response()->json(['ok' => true], 200);

            case 'connected':
                $target->forceFill([
                    'display_phone' => $data['display_phone'] ?? $target->display_phone,
                    'baileys_verified_name' => $data['verified_name'] ?? $target->baileys_verified_name,
                    'baileys_profile_pic_url' => $data['profile_pic_url'] ?? $target->baileys_profile_pic_url,
                    'driver_health' => 'healthy',
                    'driver_health_consecutive_failures' => 0,
                    'last_health_check_at' => now(),
                    'last_health_message' => 'Baileys connected',
                ])->save();

                $this->publish($config, 'connected', [
                    'state' => 'connected',
                    'phone_id' => $phone?->id,
                    'display_phone' => $target->display_phone,
                    'verified_name' => $target->baileys_verified_name,
                    'profile_pic_url' => $target->baileys_profile_pic_url,
                ]);

                return response()->json(['ok' => true, 'note' => 'connected_recorded'], 200);

            case 'qr_updated':
                \Log::info('[whatsapp.webhook.baileys.qr_updated]', [
                    'business_id' => $config->business_id,
                    'phone_id' => $phone?->id,
                    'instance_id' => $request->input('instance_id'),
                ]);

                $this->publish($config, 'qr_updated', [
                    'state' => 'qr_required',
                    'phone_id' => $phone?->id,
                    'qr' => $data['qr'] ?? null,
                    'expires_in_seconds' => $data['expires_in_seconds'] ?? 60,
                ]);

                return response()->json(['ok' => true, 'note' => 'qr_published'], 200);

            case 'session_lost':
                \Log::warning('[whatsapp.webhook.baileys.session_lost] sessão caiu', [
                    'business_id' => $config->business_id,
                    'phone_id' => $phone?->id,
                    'reason' => $data['reason'] ?? 'unknown',
                    'will_reconnect' => $data['will_reconnect'] ?? false,
                ]);

                $target->forceFill([
                    'driver_health' => 'degraded',
                    'last_health_check_at' => now(),
                    'last_health_message' => 'session_lost: ' . ($data['reason'] ?? 'unknown'),
                ])->save();

                $this->publish($config, 'session_lost', [
                    'state' => 'degraded',
                    'phone_id' => $phone?->id,
                    'reason' => $data['reason'] ?? 'unknown',
                    'will_reconnect' => $data['will_reconnect'] ?? false,
                ]);

                return response()->json(['ok' => true, 'note' => 'session_lost_logged'], 200);

            case 'ban_detected':
                \Log::error('[whatsapp.webhook.baileys.ban_detected] BAN META', [
                    'business_id' => $config->business_id,
                    'phone_id' => $phone?->id,
                    'reason' => $data['reason'] ?? 'unknown',
                ]);

                $target->forceFill([
                    'driver_health' => 'banned',
                    'last_health_check_at' => now(),
                    'last_health_message' => 'banned: ' . ($data['reason'] ?? 'unknown'),
                ])->save();

                $this->publish($config, 'ban_detected', [
                    'state' => 'banned',
                    'phone_id' => $phone?->id,
                    'reason' => $data['reason'] ?? 'unknown',
                ]);

                return response()->json(['ok' => true, 'note' => 'ban_recorded'], 200);

            case 'disconnected':
                $target->forceFill([
                    'driver_health' => 'disconnected',
                    'last_health_check_at' => now(),
                    'last_health_message' => 'disconnected: ' . ($data['reason'] ?? 'manual'),
                ])->save();

                $this->publish($config, 'disconnected', [
                    'state' => 'disconnected',
                    'phone_id' => $phone?->id,
                    'reason' => $data['reason'] ?? 'manual',
                ]);

                return response()->json(['ok' => true, 'note' => 'disconnected_recorded'], 200);

            default:
                \Log::info('[whatsapp.webhook.baileys] evento desconhecido ignorado', [
                    'business_id' => $config->business_id,
                    'phone_id' => $phone?->id,
                    'event' => $event,
                ]);

                return response()->json(['ok' => true, 'note' => 'unknown_event_ignored'], 200);
        }
    }

    /**
     * Publica evento Baileys no canal Centrifugo do business
     * (`whatsapp:business:{id}`). UI Settings/Inbox reage em tempo real.
     * Falha silenciosa é OK — Centrifugo é eventually consistent (ADR 0058).
     *
     * @param  array<string, mixed>  $payload
     */
    private function publish(WhatsappBusinessConfig $config, string $event, array $payload): void
    {
        $this->centrifugo->publish(
            "whatsapp:business:{$config->business_id}",
            ['event' => "baileys.{$event}"] + $payload
        );
    }
}
