<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Jobs\ProcessIncomingWebhookJob;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;

/**
 * BaileysWebhookController — recebe eventos do daemon Node Baileys (CT 100).
 *
 * Middleware `whatsapp.baileys.signature` (VerifyBaileysSignature) valida
 * Bearer token timing-safe antes de chegar aqui.
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
 * **US-WA-022 — Estado reativo:** todos os events de saúde (connected,
 * qr_updated, session_lost, ban_detected, disconnected) são publicados
 * em Centrifugo channel `whatsapp:business:{id}` pra UI Settings reagir
 * em tempo real (ADR 0058).
 *
 * Respostas:
 *   - sempre 200 quando assinatura válida (daemon precisa do ack pra parar de retentar)
 *   - 401 se Bearer inválido (middleware)
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002d, US-WA-022
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

        $event = (string) $request->input('event', '');
        $data = (array) $request->input('data', []);

        switch ($event) {
            case 'message':
            case 'message_status':
                ProcessIncomingWebhookJob::dispatch(
                    $config->business_id,
                    'baileys',
                    array_merge($request->all(), ['provider' => 'baileys']),
                );

                return response()->json(['ok' => true], 200);

            case 'connected':
                $config->forceFill([
                    'display_phone' => $data['display_phone'] ?? $config->display_phone,
                    'baileys_verified_name' => $data['verified_name'] ?? $config->baileys_verified_name,
                    'baileys_profile_pic_url' => $data['profile_pic_url'] ?? $config->baileys_profile_pic_url,
                    'driver_health' => 'healthy',
                    'driver_health_consecutive_failures' => 0,
                    'last_health_check_at' => now(),
                    'last_health_message' => 'Baileys connected',
                ])->save();

                $this->publish($config, 'connected', [
                    'state' => 'connected',
                    'display_phone' => $config->display_phone,
                    'verified_name' => $config->baileys_verified_name,
                    'profile_pic_url' => $config->baileys_profile_pic_url,
                ]);

                return response()->json(['ok' => true, 'note' => 'connected_recorded'], 200);

            case 'qr_updated':
                \Log::info('[whatsapp.webhook.baileys.qr_updated]', [
                    'business_id' => $config->business_id,
                    'instance_id' => $request->input('instance_id'),
                ]);

                $this->publish($config, 'qr_updated', [
                    'state' => 'qr_required',
                    'qr' => $data['qr'] ?? null, // PNG base64 (data:image/png;base64,...)
                    'expires_in_seconds' => $data['expires_in_seconds'] ?? 60,
                ]);

                return response()->json(['ok' => true, 'note' => 'qr_published'], 200);

            case 'session_lost':
                \Log::warning('[whatsapp.webhook.baileys.session_lost] sessão caiu', [
                    'business_id' => $config->business_id,
                    'reason' => $data['reason'] ?? 'unknown',
                    'will_reconnect' => $data['will_reconnect'] ?? false,
                ]);

                $config->forceFill([
                    'driver_health' => 'degraded',
                    'last_health_check_at' => now(),
                    'last_health_message' => 'session_lost: ' . ($data['reason'] ?? 'unknown'),
                ])->save();

                $this->publish($config, 'session_lost', [
                    'state' => 'degraded',
                    'reason' => $data['reason'] ?? 'unknown',
                    'will_reconnect' => $data['will_reconnect'] ?? false,
                ]);

                return response()->json(['ok' => true, 'note' => 'session_lost_logged'], 200);

            case 'ban_detected':
                \Log::error('[whatsapp.webhook.baileys.ban_detected] BAN META', [
                    'business_id' => $config->business_id,
                    'reason' => $data['reason'] ?? 'unknown',
                ]);

                $config->forceFill([
                    'driver_health' => 'banned',
                    'last_health_check_at' => now(),
                    'last_health_message' => 'banned: ' . ($data['reason'] ?? 'unknown'),
                ])->save();

                $this->publish($config, 'ban_detected', [
                    'state' => 'banned',
                    'reason' => $data['reason'] ?? 'unknown',
                ]);

                // Cross-tenant alarm (≥3 bans em 24h → notifica Wagner) é
                // responsabilidade do WhatsappDriverHealthCheckJob agregando.
                return response()->json(['ok' => true, 'note' => 'ban_recorded'], 200);

            case 'disconnected':
                $config->forceFill([
                    'driver_health' => 'disconnected',
                    'last_health_check_at' => now(),
                    'last_health_message' => 'disconnected: ' . ($data['reason'] ?? 'manual'),
                ])->save();

                $this->publish($config, 'disconnected', [
                    'state' => 'disconnected',
                    'reason' => $data['reason'] ?? 'manual',
                ]);

                return response()->json(['ok' => true, 'note' => 'disconnected_recorded'], 200);

            default:
                \Log::info('[whatsapp.webhook.baileys] evento desconhecido ignorado', [
                    'business_id' => $config->business_id,
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
