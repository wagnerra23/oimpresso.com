<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Drivers;

use App\Util\OtelHelper;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;

/**
 * MetaCloudDriver — fallback obrigatório Sprint 1 (driver oficial Meta).
 *
 * Fala direto com `graph.facebook.com/v21.0/{phone_number_id}/messages`.
 * Sem markup BSP. Free tier Meta cobre 1k conversas/mês BR.
 *
 * Risco ban: NENHUM (provedor oficial Meta).
 *
 * Onboarding: Meta Business Manager + verificação número (1-3 dias) +
 * HSM templates pendentes aprovação Meta (1-3 dias cada).
 *
 * Uso: cadastrado obrigatoriamente como fallback quando driver=zapi/baileys
 * (gating duro FormRequest). Pode também ser default pra businesses
 * enterprise compliance (flipa driver=meta_cloud na UI Settings).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §3.1 (outbound flow)
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class MetaCloudDriver implements DriverInterface
{
    public function sendTemplate(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $templateName,
        array $params,
        string $locale = 'pt_BR',
    ): WhatsappSendResult {
        return OtelHelper::span('whatsapp.meta_cloud.send_template', [
            'business_id' => $config->business_id,
            'template' => $templateName,
            'locale' => $locale,
            'phone_id' => $config->id,
        ], function () use ($config, $to, $templateName, $params, $locale) {
            $components = empty($params) ? [] : [
                [
                    'type' => 'body',
                    'parameters' => array_values(array_map(
                        fn ($value) => ['type' => 'text', 'text' => (string) $value],
                        $params,
                    )),
                ],
            ];

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $this->normalizePhone($to),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $locale],
                    'components' => $components,
                ],
            ];

            $response = $this->client($config)
                ->post("/{$config->meta_phone_number_id}/messages", $payload);

            return $this->mapSendResponse($response);
        });
    }

    public function sendFreeform(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $body,
    ): WhatsappSendResult {
        return OtelHelper::spanBiz('whatsapp.meta_cloud.send_freeform', function () use ($config, $to, $body) {
            // Meta Cloud só permite freeform dentro janela 24h. Validação real
            // (consultar WhatsappConversation->isWithinMeta24hWindow) acontece
            // no SendWhatsappMessageJob — aqui o driver tenta e Meta rejeita
            // se fora da janela.
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $this->normalizePhone($to),
                'type' => 'text',
                'text' => ['body' => $body],
            ];

            $response = $this->client($config)
                ->post("/{$config->meta_phone_number_id}/messages", $payload);

            return $this->mapSendResponse($response);
        }, [
            'phone_number_id' => $config->meta_phone_number_id ?? null,
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
        return OtelHelper::span('whatsapp.meta_cloud.send_media', [
            'business_id' => $config->business_id,
            'phone_number_id' => $config->meta_phone_number_id ?? null,
            'media_type' => $type,
        ], function () use ($config, $to, $mediaUrl, $type, $caption) {
            $mediaType = match ($type) {
                'image' => 'image',
                'document', 'pdf' => 'document',
                'audio' => 'audio',
                'video' => 'video',
                default => null,
            };

            if ($mediaType === null) {
                return WhatsappSendResult::failed(
                    errorCode: 'meta_unsupported_media_type',
                    errorMessage: "Tipo '{$type}' não suportado pelo MetaCloudDriver.",
                );
            }

            $mediaPayload = ['link' => $mediaUrl];
            if ($caption !== null && in_array($mediaType, ['image', 'document', 'video'], true)) {
                $mediaPayload['caption'] = $caption;
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $this->normalizePhone($to),
                'type' => $mediaType,
                $mediaType => $mediaPayload,
            ];

            $response = $this->client($config)
                ->post("/{$config->meta_phone_number_id}/messages", $payload);

            return $this->mapSendResponse($response);
        });
    }

    public function sendInteractive(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $body,
        array $interactive,
    ): WhatsappSendResult {
        return OtelHelper::span('whatsapp.meta_cloud.send_interactive', [
            'business_id' => $config->business_id,
            'phone_number_id' => $config->meta_phone_number_id ?? null,
            'interactive_type' => (string) ($interactive['type'] ?? ''),
        ], fn () => $this->sendInteractiveInterno($config, $to, $body, $interactive));
    }

    private function sendInteractiveInterno(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $body,
        array $interactive,
    ): WhatsappSendResult {
        // Meta Cloud interactive type — `type=interactive` no payload Whatsapp Cloud
        // https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages#interactive-object
        $type = (string) ($interactive['type'] ?? '');

        $action = match ($type) {
            'buttons' => [
                'buttons' => array_map(
                    fn (array $btn) => [
                        'type' => 'reply',
                        'reply' => [
                            'id' => (string) $btn['id'],
                            'title' => mb_substr((string) $btn['label'], 0, 20),
                        ],
                    ],
                    array_slice($interactive['buttons'] ?? [], 0, 3),
                ),
            ],
            'list' => [
                'button' => mb_substr((string) ($interactive['button_label'] ?? 'Escolha'), 0, 20),
                'sections' => array_map(
                    fn (array $section) => [
                        'title' => mb_substr((string) $section['title'], 0, 24),
                        'rows' => array_map(
                            fn (array $item) => array_filter([
                                'id' => (string) $item['id'],
                                'title' => mb_substr((string) $item['title'], 0, 24),
                                'description' => isset($item['description'])
                                    ? mb_substr((string) $item['description'], 0, 72)
                                    : null,
                            ], fn ($v) => $v !== null),
                            $section['items'] ?? [],
                        ),
                    ],
                    $interactive['sections'] ?? [],
                ),
            ],
            'cta_url' => [
                'name' => 'cta_url',
                'parameters' => [
                    'display_text' => mb_substr((string) ($interactive['button_label'] ?? 'Abrir'), 0, 20),
                    'url' => (string) ($interactive['url'] ?? ''),
                ],
            ],
            default => throw DriverDoesNotSupport::for('meta_cloud', "interactive.{$type}"),
        };

        $metaInteractiveType = match ($type) {
            'buttons' => 'button',
            'list' => 'list',
            'cta_url' => 'cta_url',
        };

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($to),
            'type' => 'interactive',
            'interactive' => [
                'type' => $metaInteractiveType,
                'body' => ['text' => $body],
                'action' => $action,
            ],
        ];

        $response = $this->client($config)
            ->post("/{$config->meta_phone_number_id}/messages", $payload);

        return $this->mapSendResponse($response);
    }

    public function fetchMessageStatus(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $providerMessageId,
    ): MessageStatus {
        // Meta Cloud não tem endpoint REST pra consultar status de mensagem
        // específica — status chega via webhook (statuses event).
        // Aqui retornamos 'queued' como placeholder; status real é
        // atualizado pelo ProcessIncomingWebhookJob (Lote 2c).
        return new MessageStatus(status: 'queued');
    }

    public function ping(WhatsappBusinessConfig|WhatsappBusinessPhone $config): DriverHealthStatus
    {
        return OtelHelper::span('whatsapp.meta_cloud.ping', [
            'business_id' => $config->business_id,
            'phone_number_id' => $config->meta_phone_number_id ?? null,
        ], function () use ($config) {
            // Meta Cloud não tem endpoint /ping; usamos GET no phone_number_id
            // pra validar token + número.
            $response = $this->client($config)
                ->get("/{$config->meta_phone_number_id}");

            if (! $response->successful()) {
                return DriverHealthStatus::unhealthy(
                    errorMessage: "Meta Cloud ping falhou: HTTP {$response->status()} — {$response->body()}",
                    sessionState: 'disconnected',
                    banDetected: false, // Meta oficial não bane
                );
            }

            $data = $response->json();

            return DriverHealthStatus::healthy(
                displayPhone: $data['display_phone_number'] ?? null,
                sessionState: 'connected',
            );
        });
    }

    /**
     * Busca templates HSM aprovados na Meta Business Manager.
     *
     * Endpoint: GET /{whatsapp_business_account_id}/message_templates
     *
     * Como `meta_phone_number_id` aponta pro PHONE NUMBER (não pro WABA),
     * primeiro buscamos `whatsapp_business_account` via GET phone_number_id;
     * depois buscamos templates do WABA.
     *
     * @return array<int, array<string, mixed>> Lista de templates normalizada:
     *   [
     *     ['name' => 'repair_status_ready', 'language' => 'pt_BR',
     *      'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => [...]],
     *     ...
     *   ]
     */
    public function fetchTemplates(WhatsappBusinessConfig|WhatsappBusinessPhone $config): array
    {
        // Step 1: pega WABA id a partir do phone_number_id
        $wabaResponse = $this->client($config)
            ->get("/{$config->meta_phone_number_id}", [
                'fields' => 'id,whatsapp_business_account',
            ]);

        if (! $wabaResponse->successful()) {
            return [];
        }

        $wabaId = $wabaResponse->json('whatsapp_business_account.id');
        if (empty($wabaId)) {
            return [];
        }

        // Step 2: lista templates do WABA (paginação simples — limit 100)
        $templatesResponse = $this->client($config)
            ->get("/{$wabaId}/message_templates", [
                'limit' => 100,
                'fields' => 'name,language,category,status,components,rejected_reason,id',
            ]);

        if (! $templatesResponse->successful()) {
            return [];
        }

        $items = $templatesResponse->json('data') ?? [];
        if (! is_array($items)) {
            return [];
        }

        return array_map(function ($t) {
            return [
                'meta_template_id' => $t['id'] ?? null,
                'name' => (string) ($t['name'] ?? ''),
                'language' => (string) ($t['language'] ?? 'pt_BR'),
                'category' => strtoupper((string) ($t['category'] ?? 'UTILITY')),
                'status' => strtoupper((string) ($t['status'] ?? 'PENDING')),
                'components' => $t['components'] ?? [],
                'rejection_reason' => $t['rejected_reason'] ?? null,
            ];
        }, $items);
    }

    /**
     * Parse webhook inbound payload Meta Cloud — extrai 3 identifiers canônicos.
     *
     * PR4 PoC (canary biz sandbox, NÃO toca prod biz=1):
     * Cloud API oficial Meta expõe `user_id` (BSUID Meta-oficial) em
     * `entry[].changes[].value.contacts[].user_id` desde 31-mar-2026.
     * Quando users adotarem username (jun/2026 GA), `wa_id` (phone) some —
     * só sobra BSUID como identificador estável business-scoped.
     *
     * Schema 3-identifiers (PR1, migration `add_identity_columns_to_conversations`):
     *  - `lid` — não exposto via Cloud API (resolvido internamente Meta)
     *  - `phone_e164` — sempre `+wa_id`
     *  - `bsuid` — `contacts[].user_id` quando disponível (NULL pré mar/2026
     *    e pra users que não optaram via Cloud API moderna)
     *
     * Cloud API webhook shape mar/2026+:
     * {
     *   "entry": [{
     *     "changes": [{
     *       "value": {
     *         "messages": [{
     *           "from": "5548999000000",
     *           "id": "wamid.HBgL...",
     *           "type": "text",
     *           "text": {"body": "..."}
     *         }],
     *         "contacts": [{
     *           "wa_id": "5548999000000",
     *           "user_id": "abc123-bsuid-xyz",
     *           "profile": {"name": "Cliente"}
     *         }]
     *       }
     *     }]
     *   }]
     * }
     *
     * Tolerante a payload pré mar/2026 (sem `user_id` em contacts) — campo
     * `bsuid` retorna null e migração lógica MessagePersister mantém compat.
     *
     * NÃO faz I/O, NÃO persiste — apenas parsing puro. Persistência
     * canônica fica no `ProcessIncomingWebhookJob` + `MessagePersister`
     * (não tocados neste PR).
     *
     * @param  array<string, mixed>  $payload  Body cru do webhook Meta
     * @return array<int, array{
     *     wa_id: string,
     *     phone_e164: string,
     *     bsuid: ?string,
     *     profile_name: ?string,
     *     message_id: ?string,
     *     type: string,
     *     body: string
     * }>
     *
     * @see Modules/Whatsapp/Database/Migrations/2026_05_15_010000_add_identity_columns_to_conversations.php
     * @see memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md §7 Opção A
     */
    public function parseInboundWebhook(array $payload): array
    {
        $out = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $value = $change['value'] ?? [];
                if (! is_array($value)) {
                    continue;
                }

                // Indexa contacts por wa_id pra lookup O(1) ao iterar messages.
                $contactsByWaId = [];
                foreach ($value['contacts'] ?? [] as $contact) {
                    $waId = $contact['wa_id'] ?? null;
                    if (is_string($waId) && $waId !== '') {
                        $contactsByWaId[$waId] = $contact;
                    }
                }

                foreach ($value['messages'] ?? [] as $msg) {
                    if (! is_array($msg)) {
                        continue;
                    }

                    $waId = $msg['from'] ?? null;
                    if (! is_string($waId) || $waId === '') {
                        continue;
                    }

                    $contact = $contactsByWaId[$waId] ?? [];
                    $type = (string) ($msg['type'] ?? 'text');

                    // Extrai body conforme tipo — text/button/interactive todos têm
                    // forma diferente. PoC cobre text; outros tipos retornam ''
                    // (persister real é responsável por tipos especiais).
                    $body = match ($type) {
                        'text' => (string) ($msg['text']['body'] ?? ''),
                        'button' => (string) ($msg['button']['text'] ?? ''),
                        'interactive' => (string) (
                            $msg['interactive']['button_reply']['title']
                            ?? $msg['interactive']['list_reply']['title']
                            ?? ''
                        ),
                        default => '',
                    };

                    $out[] = [
                        'wa_id' => $waId,
                        'phone_e164' => '+'.$waId,
                        'bsuid' => isset($contact['user_id']) && is_string($contact['user_id']) && $contact['user_id'] !== ''
                            ? $contact['user_id']
                            : null,
                        'profile_name' => isset($contact['profile']['name']) && is_string($contact['profile']['name'])
                            ? $contact['profile']['name']
                            : null,
                        'message_id' => isset($msg['id']) && is_string($msg['id']) ? $msg['id'] : null,
                        'type' => $type,
                        'body' => $body,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Cliente HTTP configurado pra Meta Cloud API.
     *
     * Bearer token = meta_access_token (cifrado em DB, decifrado no Model getter).
     */
    private function client(WhatsappBusinessConfig|WhatsappBusinessPhone $config): PendingRequest
    {
        $apiVersion = config('whatsapp.meta.api_version', 'v21.0');
        $baseUrl = config('whatsapp.meta.base_url', 'https://graph.facebook.com');

        return Http::baseUrl("{$baseUrl}/{$apiVersion}")
            ->timeout(config('whatsapp.meta.request_timeout', 10))
            ->withToken($config->meta_access_token)
            ->acceptJson()
            ->asJson();
    }

    /**
     * Mapeia response Meta Cloud pra WhatsappSendResult padronizado.
     *
     * Estrutura sucesso Meta:
     *   { "messaging_product": "whatsapp",
     *     "messages": [{ "id": "wamid.HBgL..." }] }
     *
     * Estrutura erro Meta:
     *   { "error": { "code": 131056, "message": "...", ... } }
     */
    private function mapSendResponse(Response $response): WhatsappSendResult
    {
        if ($response->successful()) {
            $messageId = $response->json('messages.0.id');

            if ($messageId === null) {
                return WhatsappSendResult::failed(
                    errorCode: 'meta_unexpected_response',
                    errorMessage: 'Meta retornou 2xx mas sem messages[0].id: ' . $response->body(),
                );
            }

            return WhatsappSendResult::ok((string) $messageId);
        }

        $errorCode = $response->json('error.code') ?? 'unknown';
        $errorMessage = $response->json('error.message') ?? $response->body();

        return WhatsappSendResult::failed(
            errorCode: "meta_{$errorCode}",
            errorMessage: is_string($errorMessage) ? $errorMessage : json_encode($errorMessage),
            sessionLost: false, // Meta oficial não tem sessão Whatsapp Web
            banDetected: false, // Meta oficial não bane
        );
    }

    /**
     * Normaliza telefone pra formato Meta: dígitos puros sem '+', com DDI.
     */
    private function normalizePhone(string $to): string
    {
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
