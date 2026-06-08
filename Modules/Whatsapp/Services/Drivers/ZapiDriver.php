<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Entities\WhatsappTemplate;

/**
 * Observabilidade D9.a (ADR 0155): chamadas HTTP Z-API envolvidas em
 * `OtelHelper::span(` (Tracer whatsapp.driver.zapi.<method>) — mede
 * latência por business + provider response time.
 *
 * ZapiDriver — driver default Sprint 1.
 *
 * Z-API é SaaS BR (`api.z-api.io`) baseado em Whatsapp Web/Baileys.
 * Onboarding 5 min (scan QR Code). Mensagens freeform sem janela 24h.
 *
 * Risco aceito conscientemente (ADR 0096 emenda 4):
 * - Não-oficial (Whatsapp Web reverse-engineered) — Meta pode banir
 * - Z-API tem chat suporte BR; risco terceirizado
 *
 * Mitigações:
 * - Fallback Meta Cloud OBRIGATÓRIO (gating duro FormRequest Lote 2c)
 * - Termo LGPD assinado em lgpd_acknowledged_at
 * - WhatsappDriverHealthCheck (6h em 6h) — Sprint 2
 * - Fallback automático Z-API → Meta Cloud quando driver_health ≥ degraded
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002b
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §3.1 (outbound flow)
 * @see https://developer.z-api.io
 */
class ZapiDriver implements DriverInterface
{
    public function sendTemplate(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $templateName,
        array $params,
        string $locale = 'pt_BR',
    ): WhatsappSendResult {
        // Z-API não usa HSM — busca template local, expande placeholders e envia freeform
        $template = WhatsappTemplate::where('business_id', $config->business_id)
            ->where('provider', 'zapi')
            ->where('name', $templateName)
            ->where('language', $locale)
            ->first();

        if ($template === null) {
            return WhatsappSendResult::failed(
                errorCode: 'zapi_template_not_found',
                errorMessage: "Template '{$templateName}' (lang={$locale}, provider=zapi) não cadastrado.",
            );
        }

        $body = $template->expandBody($params);

        return $this->sendFreeform($config, $to, $body);
    }

    public function sendFreeform(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $body,
    ): WhatsappSendResult {
        $response = $this->client($config)
            ->post("/instances/{$config->zapi_instance_id}/token/{$config->zapi_instance_token}/send-text", [
                'phone' => $this->normalizePhone($to),
                'message' => $body,
            ]);

        return $this->mapSendResponse($response);
    }

    public function sendMedia(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $mediaUrl,
        string $type,
        ?string $caption = null,
    ): WhatsappSendResult {
        $endpoint = match ($type) {
            'image' => 'send-image',
            'document', 'pdf' => 'send-document',
            'audio' => 'send-audio',
            default => null,
        };

        if ($endpoint === null) {
            return WhatsappSendResult::failed(
                errorCode: 'zapi_unsupported_media_type',
                errorMessage: "Tipo de mídia '{$type}' não suportado pelo ZapiDriver.",
            );
        }

        $payload = [
            'phone' => $this->normalizePhone($to),
            $type === 'image' ? 'image' : ($type === 'audio' ? 'audio' : 'document') => $mediaUrl,
        ];

        if ($caption !== null && in_array($type, ['image', 'document', 'pdf'], true)) {
            $payload['caption'] = $caption;
        }

        $response = $this->client($config)
            ->post("/instances/{$config->zapi_instance_id}/token/{$config->zapi_instance_token}/{$endpoint}", $payload);

        return $this->mapSendResponse($response);
    }

    public function sendInteractive(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $body,
        array $interactive,
    ): WhatsappSendResult {
        $type = (string) ($interactive['type'] ?? '');

        // Z-API não tem endpoint nativo pra CTA URL (Whatsapp Cloud-only).
        if ($type === 'cta_url') {
            throw DriverDoesNotSupport::for('zapi', 'interactive.cta_url');
        }

        if ($type === 'buttons') {
            // POST /send-button-actions — payload Z-API:
            // { phone, message, buttonActions: [{ id, label }, ...] }
            $payload = [
                'phone' => $this->normalizePhone($to),
                'message' => $body,
                'buttonActions' => array_map(
                    fn (array $btn) => [
                        'id' => (string) $btn['id'],
                        'label' => (string) $btn['label'],
                    ],
                    array_slice($interactive['buttons'] ?? [], 0, 3),
                ),
            ];
            $endpoint = 'send-button-actions';
        } elseif ($type === 'list') {
            // POST /send-option-list — Z-API formata via {title, buttonLabel, options}
            $items = [];
            foreach (($interactive['sections'] ?? []) as $section) {
                foreach (($section['items'] ?? []) as $item) {
                    $items[] = array_filter([
                        'id' => (string) $item['id'],
                        'title' => (string) $item['title'],
                        'description' => $item['description'] ?? null,
                    ], fn ($v) => $v !== null);
                }
            }
            $payload = [
                'phone' => $this->normalizePhone($to),
                'message' => $body,
                'optionList' => [
                    'title' => mb_substr((string) ($interactive['button_label'] ?? 'Escolha'), 0, 24),
                    'buttonLabel' => mb_substr((string) ($interactive['button_label'] ?? 'Opções'), 0, 20),
                    'options' => $items,
                ],
            ];
            $endpoint = 'send-option-list';
        } else {
            throw DriverDoesNotSupport::for('zapi', "interactive.{$type}");
        }

        $response = $this->client($config)
            ->post("/instances/{$config->zapi_instance_id}/token/{$config->zapi_instance_token}/{$endpoint}", $payload);

        return $this->mapSendResponse($response);
    }

    public function fetchMessageStatus(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $providerMessageId,
    ): MessageStatus {
        $response = $this->client($config)
            ->get("/instances/{$config->zapi_instance_id}/token/{$config->zapi_instance_token}/message-status/{$providerMessageId}");

        if (! $response->successful()) {
            return new MessageStatus(status: 'failed', failedReason: 'zapi_status_unavailable');
        }

        $data = $response->json();
        $status = strtolower($data['status'] ?? 'unknown');

        return new MessageStatus(
            status: match ($status) {
                'sent' => 'sent',
                'delivered', 'received' => 'delivered',
                'read', 'viewed' => 'read',
                'failed', 'error' => 'failed',
                default => 'queued',
            },
            failedReason: $data['error'] ?? null,
            deliveredAt: isset($data['delivered_at']) ? new \DateTimeImmutable($data['delivered_at']) : null,
            readAt: isset($data['read_at']) ? new \DateTimeImmutable($data['read_at']) : null,
        );
    }

    public function ping(WhatsappBusinessConfig|WhatsappBusinessPhone $config): DriverHealthStatus
    {
        $response = $this->client($config)
            ->get("/instances/{$config->zapi_instance_id}/token/{$config->zapi_instance_token}/status");

        if (! $response->successful()) {
            $banDetected = $response->status() === 401 || $response->status() === 403;

            return DriverHealthStatus::unhealthy(
                errorMessage: "Z-API ping falhou: HTTP {$response->status()} — {$response->body()}",
                sessionState: 'disconnected',
                banDetected: $banDetected,
            );
        }

        $data = $response->json();
        $connected = (bool) ($data['connected'] ?? false);
        $smartphoneConnected = (bool) ($data['smartphoneConnected'] ?? false);

        if (! $connected || ! $smartphoneConnected) {
            return DriverHealthStatus::unhealthy(
                errorMessage: 'Z-API conectado mas smartphone desconectado — pode precisar re-scan QR',
                sessionState: 'qr_required',
            );
        }

        return DriverHealthStatus::healthy(
            displayPhone: $data['phone'] ?? null,
            sessionState: 'connected',
        );
    }

    /**
     * Cliente HTTP configurado pra esta instance Z-API.
     *
     * Header `Client-Token` é segurança Z-API — bloqueia chamadas
     * de pessoas que descobrem instance_id+token mas não têm o client_token
     * (rotacionável independente).
     */
    private function client(WhatsappBusinessConfig|WhatsappBusinessPhone $config): PendingRequest
    {
        return Http::baseUrl(config('whatsapp.zapi.base_url', 'https://api.z-api.io'))
            ->timeout(config('whatsapp.zapi.request_timeout', 15))
            ->withHeaders([
                'Client-Token' => $config->zapi_client_token,
                'Content-Type' => 'application/json',
            ])
            ->acceptJson();
    }

    /**
     * Mapeia response HTTP do Z-API pra WhatsappSendResult padronizado.
     *
     * Detecção de ban: 401/403 com mensagens específicas Z-API.
     */
    private function mapSendResponse(Response $response): WhatsappSendResult
    {
        if ($response->successful()) {
            $messageId = $response->json('messageId') ?? $response->json('id') ?? Str::uuid()->toString();

            return WhatsappSendResult::ok((string) $messageId);
        }

        $body = $response->body();
        $status = $response->status();
        $errorJson = $response->json();
        $errorMessage = $errorJson['error'] ?? $errorJson['message'] ?? $body;

        $sessionLost = $status === 401 || str_contains(strtolower($body), 'session');
        $banDetected = $status === 403 || str_contains(strtolower($body), 'banned')
            || str_contains(strtolower($body), 'blocked');

        return WhatsappSendResult::failed(
            errorCode: "zapi_{$status}",
            errorMessage: is_string($errorMessage) ? $errorMessage : json_encode($errorMessage),
            sessionLost: $sessionLost,
            banDetected: $banDetected,
        );
    }

    /**
     * Normaliza telefone pra formato Z-API: dígitos puros sem '+', com DDI.
     *
     * "+5511987654321" → "5511987654321"
     * "11987654321" (sem DDI BR) → "5511987654321"
     */
    private function normalizePhone(string $to): string
    {
        $digits = preg_replace('/\D/', '', $to);

        if ($digits === null || $digits === '') {
            return $to;
        }

        // Sem DDI Brasil (10 ou 11 dígitos) → adiciona 55
        if (strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }

        return $digits;
    }
}
