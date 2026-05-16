<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Drivers;

use App\Util\OtelHelper;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Entities\WhatsappTemplate;

/**
 * BaileysDriver — driver custom oimpresso (Sprint 3).
 *
 * Fala via REST com o daemon Node `whatsapp-baileys` rodando em CT 100
 * (ver Modules/Whatsapp/daemon-node/). O daemon mantém 1 socket Whatsapp Web
 * por instance usando `@whiskeysockets/baileys`.
 *
 * Decisão mãe: ADR 0096 emenda 4 — autorizado como estrutura customizada
 * de atendimento que resolve as 3 dores do Evolution (bans, schema, observabilidade).
 *
 * Riscos aceitos conscientemente:
 * - Não-oficial (Whatsapp Web reverse-engineered) — Meta pode banir
 * - oimpresso é mantenedor do daemon Node (Wagner reconhece "código extra")
 *
 * Mitigações:
 * - Fallback Meta Cloud OBRIGATÓRIO (gating FormRequest)
 * - Termo LGPD assinado em lgpd_acknowledged_at
 * - Versão Baileys pinned (não `latest` — Meta TOS muda)
 * - WhatsappDriverHealthCheck (6h em 6h) chama ping()
 * - Fallback automático Baileys → Meta Cloud quando driver_health ≥ degraded
 * - OTel + Prometheus dashboards CT 100 (a "dor de observabilidade" justifica)
 *
 * @see Modules/Whatsapp/daemon-node/README.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002d
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §16
 */
class BaileysDriver implements DriverInterface
{
    public function sendTemplate(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $templateName,
        array $params,
        string $locale = 'pt_BR',
    ): WhatsappSendResult {
        // Baileys não usa HSM — busca template local provider=baileys (ou zapi),
        // expande placeholders e envia freeform. Sem janela 24h restritiva.
        $template = WhatsappTemplate::query()
            ->where('business_id', $config->business_id)
            ->whereIn('provider', ['baileys', 'zapi'])
            ->where('name', $templateName)
            ->where('language', $locale)
            ->first();

        if ($template === null) {
            return WhatsappSendResult::failed(
                errorCode: 'baileys_template_not_found',
                errorMessage: "Template '{$templateName}' (lang={$locale}) não cadastrado pra Baileys.",
            );
        }

        return $this->sendFreeform($config, $to, $template->expandBody($params));
    }

    public function sendFreeform(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $body,
    ): WhatsappSendResult {
        return OtelHelper::spanBiz('whatsapp.baileys.send_freeform', function () use ($config, $to, $body) {
            $response = $this->client($config)
                ->post("/instances/{$config->baileys_instance_id}/text", [
                    'to' => $this->normalizePhone($to),
                    'text' => $body,
                ]);

            return $this->mapSendResponse($response);
        }, [
            'instance_id' => $config->baileys_instance_id ?? null,
            'body_length' => strlen($body),
        ]);
    }

    public function sendMedia(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $mediaUrl,
        string $type,
        ?string $caption = null,
    ): WhatsappSendResult {
        return OtelHelper::span('whatsapp.baileys.send_media', [
            'business_id' => $config->business_id,
            'instance_id' => $config->baileys_instance_id ?? null,
            'media_type' => $type,
        ], function () use ($config, $to, $mediaUrl, $type, $caption) {
            $daemonType = match ($type) {
                'image' => 'image',
                'document', 'pdf' => 'document',
                'audio' => 'audio',
                'video' => 'video',
                default => null,
            };

            if ($daemonType === null) {
                return WhatsappSendResult::failed(
                    errorCode: 'baileys_unsupported_media_type',
                    errorMessage: "Tipo de mídia '{$type}' não suportado pelo BaileysDriver.",
                );
            }

            $payload = [
                'to' => $this->normalizePhone($to),
                'media_url' => $mediaUrl,
                'type' => $daemonType,
            ];

            if ($caption !== null && in_array($daemonType, ['image', 'video', 'document'], true)) {
                $payload['caption'] = $caption;
            }

            $response = $this->client($config)
                ->post("/instances/{$config->baileys_instance_id}/media", $payload);

            return $this->mapSendResponse($response);
        });
    }

    public function sendInteractive(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $body,
        array $interactive,
    ): WhatsappSendResult {
        $type = (string) ($interactive['type'] ?? '');

        return OtelHelper::span('whatsapp.baileys.send_interactive', [
            'business_id' => $config->business_id,
            'instance_id' => $config->baileys_instance_id ?? null,
            'interactive_type' => $type,
        ], function () use ($config, $to, $body, $interactive, $type) {
            // Baileys 6.7+ suporta buttons + list nativos. CTA URL não tem
            // equivalente direto no Whatsapp Web protocol — daemon mapeia pra
            // texto com URL embedded, mas preferimos rejeitar explícito pro
            // caller saber que precisa cair pro Meta Cloud.
            if ($type === 'cta_url') {
                throw DriverDoesNotSupport::for('baileys', 'interactive.cta_url');
            }

            if (! in_array($type, ['buttons', 'list'], true)) {
                throw DriverDoesNotSupport::for('baileys', "interactive.{$type}");
            }

            $payload = [
                'to' => $this->normalizePhone($to),
                'body' => $body,
                'interactive' => $interactive,
            ];

            $response = $this->client($config)
                ->post("/instances/{$config->baileys_instance_id}/interactive", $payload);

            return $this->mapSendResponse($response);
        });
    }

    public function fetchMessageStatus(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $providerMessageId,
    ): MessageStatus {
        // O daemon reporta status updates via webhook outbound (event=message_status),
        // que vira UPDATE em whatsapp_messages.status (Observer). Não há endpoint
        // pull no daemon — retornamos best-effort baseado no que sabemos.
        return new MessageStatus(status: 'queued');
    }

    public function ping(WhatsappBusinessConfig|WhatsappBusinessPhone $config): DriverHealthStatus
    {
        if (empty($config->baileys_instance_id)) {
            return DriverHealthStatus::unhealthy(
                errorMessage: 'Baileys não configurado: baileys_instance_id ausente (rodar BaileysConnectJob)',
                sessionState: 'disconnected',
            );
        }
        if (empty(config('whatsapp.baileys.api_key'))) {
            return DriverHealthStatus::unhealthy(
                errorMessage: 'Baileys não configurado: WHATSAPP_BAILEYS_API_KEY ausente no .env (server-side)',
                sessionState: 'disconnected',
            );
        }

        $response = $this->client($config)
            ->get("/instances/{$config->baileys_instance_id}/status");

        if ($response->status() === 404) {
            return DriverHealthStatus::unhealthy(
                errorMessage: 'Instance não registrada no daemon — chamar POST /connect',
                sessionState: 'disconnected',
            );
        }

        if (! $response->successful()) {
            $banDetected = $response->status() === 401 || $response->status() === 403;

            return DriverHealthStatus::unhealthy(
                errorMessage: "Baileys daemon ping falhou: HTTP {$response->status()} — " . Str::limit($response->body(), 200),
                sessionState: 'disconnected',
                banDetected: $banDetected,
            );
        }

        $data = $response->json();
        $state = (string) ($data['state'] ?? 'unknown');

        return match ($state) {
            'connected' => DriverHealthStatus::healthy(
                displayPhone: $data['display_phone'] ?? null,
                sessionState: 'connected',
            ),
            'qr_required' => DriverHealthStatus::unhealthy(
                errorMessage: 'Aguardando scan QR Code (sessão Whatsapp Web não pareada)',
                sessionState: 'qr_required',
            ),
            'banned' => DriverHealthStatus::unhealthy(
                errorMessage: 'Daemon detectou ban Meta: ' . ($data['ban_reason'] ?? 'unknown'),
                sessionState: 'banned',
                banDetected: true,
            ),
            default => DriverHealthStatus::unhealthy(
                errorMessage: "Estado daemon: {$state}",
                sessionState: $state,
            ),
        };
    }

    /**
     * Cliente HTTP pra falar com o daemon Node CT 100.
     *
     * US-WA-022: daemon_url + api_key são server secrets globais
     * (env vars `.env` Hostinger), nunca per-tenant. Multi-tenancy é
     * via `baileys_instance_id` auto-gerado per-business + path param.
     *
     * Bearer = `WHATSAPP_BAILEYS_API_KEY` (mesma chave do Docker secret CT 100).
     * Em prod, IP whitelist Traefik garante que só Hostinger fala com daemon.
     *
     * @param  WhatsappBusinessConfig  $config  apenas pra contexto OTel; URL/token são globais
     */
    private function client(WhatsappBusinessConfig|WhatsappBusinessPhone $config): PendingRequest
    {
        unset($config); // explicit: tenant não dita URL/token (US-WA-022 invariante)

        $baseUrl = (string) config('whatsapp.baileys.daemon_url', 'https://whatsapp-baileys.oimpresso.local');
        $apiKey = (string) config('whatsapp.baileys.api_key', '');

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->timeout((int) config('whatsapp.baileys.request_timeout', 15))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson();
    }

    /**
     * Mapeia response do daemon Node pra WhatsappSendResult padronizado.
     *
     * Convenções do daemon (ver Modules/Whatsapp/daemon-node/src/http/routes/messages.ts):
     * - 200/202 com {message_id, status}      → ok
     * - 401 (Bearer inválido)                 → sessionLost (api_key rotacionado)
     * - 404 instance_not_found                → sessionLost (precisa reconectar)
     * - 409 instance not connected            → sessionLost
     * - 422 validation                        → erro permanente, sem retry
     * - 5xx                                   → retry transitório
     */
    private function mapSendResponse(Response $response): WhatsappSendResult
    {
        if ($response->successful()) {
            $messageId = (string) ($response->json('message_id') ?? Str::uuid()->toString());

            return WhatsappSendResult::ok($messageId);
        }

        $status = $response->status();
        $body = $response->body();
        $json = $response->json();
        $errorMessage = is_array($json)
            ? (string) ($json['message'] ?? $json['error'] ?? Str::limit($body, 500))
            : Str::limit($body, 500);
        $errorKey = is_array($json) ? (string) ($json['error'] ?? '') : '';

        $sessionLost = in_array($status, [401, 404, 409], true)
            || str_contains(strtolower($errorKey), 'not_connected')
            || str_contains(strtolower($errorKey), 'instance_not_found');

        $banDetected = str_contains(strtolower($body), 'banned')
            || str_contains(strtolower($body), 'logged_out')
            || str_contains(strtolower($body), 'forbidden');

        return WhatsappSendResult::failed(
            errorCode: "baileys_{$status}",
            errorMessage: $errorMessage,
            sessionLost: $sessionLost,
            banDetected: $banDetected,
        );
    }

    /**
     * Normaliza telefone pro daemon: dígitos puros com DDI.
     *
     * Daemon aceita JID completo (...@s.whatsapp.net) ou número puro.
     * Aqui retornamos número puro com DDI BR adicionado se faltar.
     */
    private function normalizePhone(string $to): string
    {
        if (str_contains($to, '@')) {
            return $to;
        }

        $digits = preg_replace('/\D/', '', $to);
        if ($digits === null || $digits === '') {
            return $to;
        }

        if (strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }

        return $digits;
    }
}
