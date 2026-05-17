<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Services\Webhook;

use App\Util\OtelHelper;

/**
 * WebhookSignatureChecker — verificação canônica HMAC-SHA256 pra webhooks
 * Meta Cloud (header `X-Hub-Signature-256`) + Baileys daemon (header
 * `X-Baileys-Signature`) + Z-API (header `X-Webhook-Signature`).
 *
 * Stateless puro. Multi-tenant Tier 0 (ADR 0093): o secret é resolvido per-driver
 * por config; caller é responsável por pegar o secret correto (per-channel
 * quando aplicável).
 *
 * Spans canon `whatsapp.webhook.signature.*` (D9.a — zero-cost se otel.enabled=false).
 *
 * Pattern uniformiza checks dispersos em controllers (ChannelBaileysWebhookController,
 * MetaCloudWebhookController) sem mudar comportamento — Wave 18 D4 saturation.
 *
 * @see Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php
 * @see Modules/Whatsapp/Http/Controllers/Api/MetaCloudWebhookController.php
 * @see Modules/Whatsapp/Tests/Feature/WebhookSignatureCheckerTest.php
 */
final class WebhookSignatureChecker
{
    /** Header canon Meta Cloud (com prefixo "sha256="). */
    public const HEADER_META = 'X-Hub-Signature-256';

    /** Header canon Baileys daemon CT 100. */
    public const HEADER_BAILEYS = 'X-Baileys-Signature';

    /** Header canon Z-API. */
    public const HEADER_ZAPI = 'X-Webhook-Signature';

    /**
     * Verifica assinatura Meta Cloud (formato `sha256=<hex>`).
     *
     * Constante-time compare via `hash_equals`. Retorna false se header ausente,
     * formato inválido OU não bate com payload assinado pelo secret.
     */
    public function verifyMeta(string $rawBody, ?string $signatureHeader, string $secret): bool
    {
        return OtelHelper::span('whatsapp.webhook.signature.verify_meta', [
            'module' => 'Whatsapp',
            'driver' => 'meta_cloud',
            'tem_header' => $signatureHeader !== null && $signatureHeader !== '',
        ], function () use ($rawBody, $signatureHeader, $secret) {
            if ($signatureHeader === null || $signatureHeader === '') {
                return false;
            }

            // Meta envia "sha256=<hex>" — extrai parte hex
            if (! str_starts_with($signatureHeader, 'sha256=')) {
                return false;
            }

            $received = substr($signatureHeader, 7);
            if ($received === '' || ! ctype_xdigit($received)) {
                return false;
            }

            $expected = hash_hmac('sha256', $rawBody, $secret);

            return hash_equals($expected, $received);
        });
    }

    /**
     * Verifica assinatura Baileys daemon (formato hex puro, sem prefixo).
     */
    public function verifyBaileys(string $rawBody, ?string $signatureHeader, string $secret): bool
    {
        return OtelHelper::span('whatsapp.webhook.signature.verify_baileys', [
            'module' => 'Whatsapp',
            'driver' => 'baileys',
            'tem_header' => $signatureHeader !== null && $signatureHeader !== '',
        ], function () use ($rawBody, $signatureHeader, $secret) {
            if ($signatureHeader === null || $signatureHeader === '' || ! ctype_xdigit($signatureHeader)) {
                return false;
            }

            $expected = hash_hmac('sha256', $rawBody, $secret);

            return hash_equals($expected, $signatureHeader);
        });
    }

    /**
     * Verifica assinatura Z-API (formato hex puro).
     *
     * Z-API documenta `X-Webhook-Signature` como HMAC-SHA256 do raw body
     * com o secret do token configurado per-instance.
     */
    public function verifyZapi(string $rawBody, ?string $signatureHeader, string $secret): bool
    {
        return OtelHelper::span('whatsapp.webhook.signature.verify_zapi', [
            'module' => 'Whatsapp',
            'driver' => 'zapi',
            'tem_header' => $signatureHeader !== null && $signatureHeader !== '',
        ], function () use ($rawBody, $signatureHeader, $secret) {
            if ($signatureHeader === null || $signatureHeader === '' || ! ctype_xdigit($signatureHeader)) {
                return false;
            }

            $expected = hash_hmac('sha256', $rawBody, $secret);

            return hash_equals($expected, $signatureHeader);
        });
    }

    /**
     * Helper dispatch por driver (uniformiza chamada nos controllers).
     */
    public function verify(string $driver, string $rawBody, ?string $signatureHeader, string $secret): bool
    {
        return match ($driver) {
            'meta_cloud', 'meta'    => $this->verifyMeta($rawBody, $signatureHeader, $secret),
            'baileys'               => $this->verifyBaileys($rawBody, $signatureHeader, $secret),
            'zapi', 'z-api', 'z_api' => $this->verifyZapi($rawBody, $signatureHeader, $secret),
            default                  => false,
        };
    }
}
