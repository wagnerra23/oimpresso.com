<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;

/**
 * WhatsmeowDriver — driver não-oficial substituto Baileys (ADR 0204).
 *
 * Daemon Go via WuzAPI wrapper (asternic/wuzapi) sobre lib whatsmeow
 * (tulir/whatsmeow). Roda em CT 100 com Traefik IP whitelist Hostinger
 * (ADR 0058 + ADR 0062).
 *
 * Modelo de uso = Baileys (que foi descontinuado ADR 0202):
 *  - Scan QR no celular (30s onboarding)
 *  - Custo zero (só CT 100 que já paga)
 *  - Sem janela 24h restritiva
 *  - **Risco ban Meta igual Baileys** ([issue #810](https://github.com/tulir/whatsmeow/issues/810))
 *
 * Ganho técnico (vs Baileys):
 *  - Footprint -37% RAM por sessão (50MB vs 80MB)
 *  - Sessões long-running estáveis (Beeper mantenedor pago)
 *  - Multi-session nativo (vs Baileys mysqlAuthState custom)
 *
 * Mitigações reusadas integral do canon (ADR 0096 emenda 1-3 preservadas):
 *  - Fallback Meta Cloud obrigatório quando driver_health degrada
 *  - LGPD ack explícito no FormRequest (mesmo padrão Baileys)
 *  - Health check 6h em 6h (WhatsappDriverHealthCheckJob)
 *  - Cross-tenant ban alarm threshold (3/24h = alerta Wagner)
 *
 * Observabilidade D9.a (ADR 0155): chamadas HTTP WuzAPI envolvidas em
 * `OtelHelper::span(whatsapp.driver.whatsmeow.<method>)` — mede latência por
 * business + daemon response time.
 *
 * **Driver assina canais Channel (ADR 0135) não legacy WhatsappBusinessConfig.**
 * As assinaturas do contrato DriverInterface aceitam `WhatsappBusinessConfig|
 * WhatsappBusinessPhone` por compat — runtime resolve via business_id do
 * channel correspondente (lookup feito pelo job que dispara o driver).
 *
 * @see memory/decisions/0204-whatsmeow-driver-substituto-baileys.md
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-002 / US-WA-040
 * @see https://github.com/asternic/wuzapi/blob/main/API.md
 */
class WhatsmeowDriver implements DriverInterface
{
    public function sendTemplate(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $templateName,
        array $params,
        string $locale = 'pt_BR',
    ): WhatsappSendResult {
        // WhatsApp Web (não-oficial) não usa HSM — busca template local
        // pra expandir placeholders e envia como freeform. Mesma estratégia
        // ZapiDriver/BaileysDriver herdada.
        $template = \Modules\Whatsapp\Entities\WhatsappTemplate::where('business_id', $config->business_id)
            ->where('provider', 'whatsmeow')
            ->where('name', $templateName)
            ->where('language', $locale)
            ->first();

        if ($template === null) {
            return WhatsappSendResult::failed(
                errorCode: 'whatsmeow_template_not_found',
                errorMessage: "Template '{$templateName}' (lang={$locale}, provider=whatsmeow) não cadastrado.",
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
        $userToken = $this->resolveUserToken($config);
        if ($userToken === null) {
            return WhatsappSendResult::failed(
                errorCode: 'whatsmeow_no_user_token',
                errorMessage: 'whatsmeow_user_token ausente em config_json — channel não foi conectado.',
            );
        }

        $response = $this->client($userToken)
            ->post('/chat/send/text', [
                'Phone' => $this->normalizePhone($to),
                'Body' => $body,
                'Id' => strtoupper(str_replace('-', '', (string) Str::uuid())),
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
        $userToken = $this->resolveUserToken($config);
        if ($userToken === null) {
            return WhatsappSendResult::failed(
                errorCode: 'whatsmeow_no_user_token',
                errorMessage: 'whatsmeow_user_token ausente em config_json.',
            );
        }

        // WuzAPI tem endpoints separados por tipo (mesma estrutura Z-API).
        // Body field varia: Image, Document, Audio.
        $endpoint = match ($type) {
            'image' => '/chat/send/image',
            'document', 'pdf' => '/chat/send/document',
            'audio' => '/chat/send/audio',
            default => null,
        };

        if ($endpoint === null) {
            return WhatsappSendResult::failed(
                errorCode: 'whatsmeow_unsupported_media_type',
                errorMessage: "Tipo de mídia '{$type}' não suportado pelo WhatsmeowDriver.",
            );
        }

        $bodyField = match ($type) {
            'image' => 'Image',
            'document', 'pdf' => 'Document',
            'audio' => 'Audio',
        };

        $payload = [
            'Phone' => $this->normalizePhone($to),
            $bodyField => $mediaUrl,
        ];

        if ($caption !== null && in_array($type, ['image', 'document', 'pdf'], true)) {
            $payload['Caption'] = $caption;
        }

        $response = $this->client($userToken)->post($endpoint, $payload);

        return $this->mapSendResponse($response);
    }

    public function sendInteractive(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $to,
        string $body,
        array $interactive,
    ): WhatsappSendResult {
        $type = (string) ($interactive['type'] ?? '');

        // WuzAPI atual NÃO tem endpoint nativo pra interactive (Cloud-only).
        // Driver não-oficial fallback: envia texto plain + lista numerada.
        // Tipos buttons/list/cta_url documentados como não suportados.
        throw DriverDoesNotSupport::for('whatsmeow', "interactive.{$type}");
    }

    public function fetchMessageStatus(
        WhatsappBusinessConfig|WhatsappBusinessPhone $config,
        string $providerMessageId,
    ): MessageStatus {
        // WuzAPI não expõe endpoint message-status — recebido via webhook
        // ReadReceipt (Message → ReadReceipt event). Aqui sempre retorna
        // queued (status definitivo vem do webhook handler).
        return new MessageStatus(status: 'queued', failedReason: null);
    }

    public function ping(WhatsappBusinessConfig|WhatsappBusinessPhone $config): DriverHealthStatus
    {
        $userToken = $this->resolveUserToken($config);
        if ($userToken === null) {
            return DriverHealthStatus::unhealthy(
                errorMessage: 'whatsmeow_user_token ausente — channel não foi conectado.',
                sessionState: 'never_connected',
            );
        }

        $response = $this->client($userToken)->get('/session/status');

        if (! $response->successful()) {
            $banDetected = $response->status() === 403 || $response->status() === 401;

            return DriverHealthStatus::unhealthy(
                errorMessage: "Whatsmeow ping falhou: HTTP {$response->status()} — {$response->body()}",
                sessionState: 'disconnected',
                banDetected: $banDetected,
            );
        }

        $data = $response->json();
        $connected = (bool) ($data['Connected'] ?? false);
        $loggedIn = (bool) ($data['LoggedIn'] ?? false);

        if (! $connected || ! $loggedIn) {
            return DriverHealthStatus::unhealthy(
                errorMessage: 'Whatsmeow conectado mas sessão WhatsApp não logada — precisa re-scan QR',
                sessionState: 'qr_required',
            );
        }

        return DriverHealthStatus::healthy(
            displayPhone: $data['Jid'] ?? null,
            sessionState: 'connected',
        );
    }

    /**
     * Provisiona uma nova sessão WhatsApp no daemon (POST /admin/users).
     *
     * Usado pelo Controller `ChannelsController::connect` quando Wagner
     * adiciona um channel novo via UI. Retorna o user_token gerado, que
     * o controller cifra e salva em `channels.config_json` (cast encrypted).
     *
     * @param  string  $businessUuid  Pra montar webhook_url específico business
     * @return array{name: string, token: string, webhook: string}
     *
     * @throws \RuntimeException quando daemon retorna erro
     */
    public function provisionSession(Channel $channel, string $businessUuid): array
    {
        $adminToken = config('whatsapp.whatsmeow.api_key');
        $daemonUrl = config('whatsapp.whatsmeow.daemon_url');
        $timeout = (int) config('whatsapp.whatsmeow.request_timeout', 15);

        if (empty($adminToken) || empty($daemonUrl)) {
            throw new \RuntimeException(
                'WHATSMEOW_DAEMON_URL ou WHATSMEOW_API_KEY não configurados no .env. '
                . 'Veja runbook memory/requisitos/Whatsapp/runbooks/whatsmeow-daemon-deploy-ct100.md'
            );
        }

        $userName = $channel->whatsmeowUserName();
        if ($userName === null) {
            throw new \RuntimeException(
                'channel.channel_uuid ausente ou type != whatsapp_whatsmeow.'
            );
        }

        // Token user-específico criptograficamente forte
        $userToken = bin2hex(random_bytes(16));

        // Webhook URL com business_uuid pra resolver multi-tenant no controller
        $webhookUrl = rtrim((string) config('app.url'), '/')
            . "/api/whatsapp/webhook/whatsmeow/{$businessUuid}";

        $response = Http::withHeaders(['Authorization' => $adminToken])
            ->withoutVerifying() // Daemon CT 100 self-signed dev; cert LE pendente
            ->timeout($timeout)
            ->baseUrl($daemonUrl)
            ->asJson()
            ->post('/admin/users', [
                'name' => $userName,
                'token' => $userToken,
                'webhook' => $webhookUrl,
                // LoggedOut assinado (Fase B · incidente 2026-06-18 / POC WAHA-GOWS Phase 2):
                // o WuzAPI RECEBE "logged out from another device" mas só repassa o webhook
                // se o tipo estiver assinado. Sem ele, logout remoto some → channel_health
                // fica healthy eternamente (raiz do falso "fora do ar", ADR 0286). O app já
                // roteia LoggedOut → handleDisconnected (WhatsmeowWebhookController).
                'events' => 'Message,ReadReceipt,Connected,Disconnected,LoggedOut',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Daemon /admin/users retornou {$response->status()}: {$response->body()}"
            );
        }

        return [
            'name' => $userName,
            'token' => $userToken,
            'webhook' => $webhookUrl,
        ];
    }

    /**
     * Inicia conexão de sessão WhatsApp Web — retorna QR base64 pro frontend.
     *
     * Chamado pelo controller após `provisionSession()` ou quando sessão
     * caiu (reconnect).
     *
     * @return array{qr_base64: ?string, state: string}
     */
    public function connect(Channel $channel): array
    {
        $userToken = $this->resolveUserTokenFromChannel($channel);
        if ($userToken === null) {
            throw new \RuntimeException('channel.config_json.whatsmeow_user_token ausente.');
        }

        // Reconciliação: se daemon já tem sessão conectada (loggedIn=true OU
        // connected=true sem QR pending), pula /session/connect que retorna
        // 500 "already connected". Tratado como sucesso — UI vai mostrar QR
        // do GET /session/qr se ainda pendente, ou mensagem "já pareado" se
        // loggedIn=true. (Débito #4 sessão 2026-05-27.)
        $statusResp = $this->client($userToken)->get('/session/status');
        $statusData = $statusResp->json('data') ?? [];
        $alreadyConnected = ($statusData['connected'] ?? false) === true;
        $alreadyLoggedIn = ($statusData['loggedIn'] ?? false) === true;

        if (! $alreadyConnected) {
            // POST /session/connect (gera/refresh QR)
            $connectResponse = $this->client($userToken)
                ->post('/session/connect', [
                    'Subscribe' => ['Message', 'ReadReceipt', 'Connected', 'Disconnected', 'LoggedOut'],
                    'Immediate' => false,
                ]);

            if (! $connectResponse->successful()) {
                throw new \RuntimeException(
                    "Daemon /session/connect retornou {$connectResponse->status()}: {$connectResponse->body()}"
                );
            }
        }

        // Se já pareado (loggedIn=true), retornar state direto sem QR
        if ($alreadyLoggedIn) {
            return ['qr_base64' => null, 'state' => 'paired'];
        }

        // GET /session/qr (retorna QR base64 ou status connected)
        $qrResponse = $this->client($userToken)->get('/session/qr');

        if (! $qrResponse->successful()) {
            return ['qr_base64' => null, 'state' => 'error'];
        }

        $data = $qrResponse->json();
        // WuzAPI envelope: {"code":200,"data":{"QRCode":"data:image/png;base64,..."},"success":true}
        // QR fica em data.QRCode (nested), não no root. Sessão 2026-05-27 confirmou via curl.
        $qrDataUrl = $data['data']['QRCode'] ?? null;
        // Strip "data:image/png;base64," prefix se presente — UI já adiciona o prefix
        $qrBase64 = $qrDataUrl && str_contains($qrDataUrl, ',')
            ? explode(',', $qrDataUrl, 2)[1]
            : $qrDataUrl;

        return [
            'qr_base64' => $qrBase64,
            'state' => $qrBase64 ? 'qr_required' : 'unknown',
        ];
    }

    /**
     * Logout sessão WhatsApp Web (mas mantém user no daemon — admin delete
     * via /admin/users/{name} se quiser remover completo).
     */
    public function disconnect(Channel $channel): void
    {
        $userToken = $this->resolveUserTokenFromChannel($channel);
        if ($userToken === null) {
            return; // sessão nunca conectada — no-op
        }

        $this->client($userToken)->post('/session/logout');
    }

    /**
     * Resolve user_token do channel (cifrado em config_json).
     *
     * Channel-based driver (ADR 0135). Recebe config como WhatsappBusinessConfig
     * ou WhatsappBusinessPhone por compat assinatura DriverInterface, mas na
     * prática o caller passa o config equivalente ao channel.config_json — daí
     * lê via attribute `whatsmeow_user_token` (espera estar disponível).
     */
    private function resolveUserToken(WhatsappBusinessConfig|WhatsappBusinessPhone $config): ?string
    {
        // Compat — config pode ter sido enriquecida com chaves whatsmeow_*
        // pelo caller (ChannelDriverFactory hidrata config a partir de channel).
        return $config->whatsmeow_user_token ?? null;
    }

    private function resolveUserTokenFromChannel(Channel $channel): ?string
    {
        $cfg = $channel->config_json ?? [];
        return $cfg['whatsmeow_user_token'] ?? null;
    }

    /**
     * Cliente HTTP configurado com Token header pra user-scoped endpoints.
     */
    private function client(string $userToken): PendingRequest
    {
        return Http::baseUrl(config('whatsapp.whatsmeow.daemon_url'))
            ->timeout(config('whatsapp.whatsmeow.request_timeout', 15))
            ->withoutVerifying() // Daemon CT 100 cert self-signed dev
            ->withHeaders([
                'Token' => $userToken,
                'Content-Type' => 'application/json',
            ])
            ->acceptJson();
    }

    /**
     * Mapeia response HTTP WuzAPI pra WhatsappSendResult padronizado.
     *
     * WuzAPI responses success shape:
     *   { "Details": "Sent", "Id": "...", "Timestamp": ... }
     *
     * Erro shape:
     *   { "code": 401|403, "error": "Token not authorized" }
     */
    private function mapSendResponse(Response $response): WhatsappSendResult
    {
        if ($response->successful()) {
            $messageId = $response->json('Id')
                ?? $response->json('Data.Id')
                ?? Str::uuid()->toString();

            return WhatsappSendResult::ok((string) $messageId);
        }

        $body = $response->body();
        $status = $response->status();
        $errorJson = $response->json();
        $errorMessage = $errorJson['error']
            ?? $errorJson['message']
            ?? $errorJson['Details']
            ?? $body;

        $sessionLost = $status === 401 || str_contains(strtolower($body), 'session');
        $banDetected = $status === 403
            || str_contains(strtolower($body), 'banned')
            || str_contains(strtolower($body), 'blocked')
            || str_contains(strtolower($body), 'logged out');

        return WhatsappSendResult::failed(
            errorCode: "whatsmeow_{$status}",
            errorMessage: is_string($errorMessage) ? $errorMessage : json_encode($errorMessage),
            sessionLost: $sessionLost,
            banDetected: $banDetected,
        );
    }

    /**
     * Normaliza telefone pra formato WuzAPI: dígitos puros sem '+', com DDI.
     *
     * "+5511987654321" → "5511987654321"
     * "11987654321" → "5511987654321"
     *
     * Mesma normalização ZapiDriver pra consistência cross-driver.
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
