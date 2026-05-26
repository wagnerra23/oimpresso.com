<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Webhooks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Service compartilhado entre Webhook Controllers do PaymentGateway.
 *
 * Onda 3 — ADR 0170 / US-PG-002 (audit-senior 2026-05-25 VULN SEC P0-#2).
 *
 * Responsabilidades:
 *   1. Validar HMAC/token/mTLS signature via strategy por driver_key
 *      (asaas/inter/c6/bcb_pix) — método público validateSignature()
 *   2. Persistir o evento em gateway_webhook_events (idempotência via
 *      UNIQUE(business_id, gateway_key, gateway_event_id))
 *   3. Resposta 200 quando signature OK (gateway re-envia se demorar)
 *   4. Logar com PII redacted (LGPD)
 *
 * Strategy signature por driver_key:
 *   - asaas   → header `asaas-access-token` ≡ config_json.webhook_token
 *               (Asaas changelog fev/2026 — token estático, não HMAC)
 *   - inter   → HMAC-SHA256 raw body via header `x-inter-signature`
 *               (espelha InterPixWebhookController canon US-FIN-032)
 *   - c6      → HMAC-SHA256 GitHub-style via header `X-Hub-Signature-256`
 *               formato "sha256=<hex>"
 *   - bcb_pix → mTLS cert fingerprint via SSL_CLIENT_CERT server var
 *               ≡ config_json.psp_cert_fingerprint (Open Finance v2.1.0+)
 *
 * Fail-secure: credencial sem secret/token cadastrado → false (controller 401).
 * Toda comparação binária via `hash_equals` (constant-time, anti timing-attack).
 *
 * Defense-in-depth: controller chama validateSignature() ANTES de
 * qualquer parse/DB-write.
 */
class WebhookProcessor
{
    public function __construct(private PiiRedactor $redactor)
    {
    }

    public function handle(
        string $gatewayKey,
        Request $request,
        int $businessId,
        string $eventName,
        string $eventId,
        bool $signatureValid,
    ): JsonResponse {
        $payload = $request->all();

        // Idempotência at-DB-level: tenta inserir; se UNIQUE viola, ignora.
        try {
            $event = GatewayWebhookEvent::query()->create([
                'business_id'      => $businessId,
                'gateway_key'      => $gatewayKey,
                'evento'           => $eventName,
                'gateway_event_id' => $eventId,
                'payload'          => $payload,
                'signature_valid'  => $signatureValid,
                'processed_at'     => null,
            ]);

            Log::info('paymentgateway.webhook.received', [
                'business_id'      => $businessId,
                'gateway_key'      => $gatewayKey,
                'evento'           => $eventName,
                'event_id'         => $eventId,
                'webhook_row_id'   => $event->id,
                'signature_valid'  => $signatureValid,
                // LGPD — payload bruto contém PII; redact pra log.
                'payload_redacted' => $this->redactor->redact((string) json_encode($payload)),
            ]);

            return response()->json([
                'ok'             => true,
                'webhook_row_id' => $event->id,
                'duplicate'      => false,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // UNIQUE violation = duplicate webhook (banco já entregou antes).
            if ($this->isUniqueViolation($e)) {
                Log::info('paymentgateway.webhook.duplicate', [
                    'business_id' => $businessId,
                    'gateway_key' => $gatewayKey,
                    'event_id'    => $eventId,
                ]);

                return response()->json([
                    'ok'        => true,
                    'duplicate' => true,
                ]);
            }
            throw $e;
        }
    }

    /**
     * Strategy de validação de signature por gateway driver_key.
     *
     * Retorna true SOMENTE se header/cert provided bate exato com
     * secret/token cadastrado em config_json. Fail-secure por default.
     * Comparação binária via hash_equals (constant-time, anti timing-attack).
     *
     * US-PG-002 (VULN SEC P0-#2 — audit 2026-05-25).
     */
    public function validateSignature(
        string $gatewayKey,
        Request $request,
        PaymentGatewayCredential $credential,
    ): bool {
        $config = (array) ($credential->config_json ?? []);

        return match ($gatewayKey) {
            'asaas'   => $this->validateAsaas($request, $config),
            'inter'   => $this->validateInterHmac($request, $config),
            'c6'      => $this->validateC6Hmac($request, $config),
            'bcb_pix' => $this->validateBcbPixMtls($request, $config),
            default   => false, // gateway desconhecido → fail-secure
        };
    }

    /**
     * Asaas — token estático em header `asaas-access-token`
     * (changelog fev/2026 — obrigatório e auto-gerado).
     */
    private function validateAsaas(Request $request, array $config): bool
    {
        $expected = (string) ($config['webhook_token'] ?? '');
        if ($expected === '') {
            return false;
        }
        $provided = (string) $request->header('asaas-access-token', '');
        if ($provided === '') {
            return false;
        }
        return hash_equals($expected, $provided);
    }

    /**
     * Inter legacy — HMAC-SHA256 raw body via header `x-inter-signature`
     * (hex lowercase). Espelha pattern InterPixWebhookController canon
     * (Onda 26 US-FIN-032).
     */
    private function validateInterHmac(Request $request, array $config): bool
    {
        $secret = (string) ($config['webhook_secret'] ?? '');
        if ($secret === '') {
            return false;
        }
        $provided = (string) $request->header('x-inter-signature', '');
        if ($provided === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        return hash_equals($expected, $provided);
    }

    /**
     * C6 — GitHub-style `X-Hub-Signature-256: sha256=<hex>` HMAC-SHA256
     * raw body (mesmo formato Pagar.me canon Onda 4e).
     */
    private function validateC6Hmac(Request $request, array $config): bool
    {
        $secret = (string) ($config['webhook_secret'] ?? '');
        if ($secret === '') {
            return false;
        }
        $providedHeader = (string) $request->header('X-Hub-Signature-256', '');
        if ($providedHeader === '' || ! str_starts_with($providedHeader, 'sha256=')) {
            return false;
        }
        $providedHex = substr($providedHeader, 7);
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        return hash_equals($expected, $providedHex);
    }

    /**
     * BCB PIX (Open Finance v2.1.0+) — mTLS proof-of-possession.
     * Nginx/reverse-proxy popula `SSL_CLIENT_CERT` com PEM do PSP no handshake.
     * Calcula fingerprint SHA-256 e compara com config_json.psp_cert_fingerprint.
     */
    private function validateBcbPixMtls(Request $request, array $config): bool
    {
        $expectedFp = (string) ($config['psp_cert_fingerprint'] ?? '');
        if ($expectedFp === '') {
            return false;
        }
        $certPem = (string) ($request->server('SSL_CLIENT_CERT', '') ?? '');
        if ($certPem === '') {
            return false;
        }
        $fp = @openssl_x509_fingerprint($certPem, 'sha256');
        if ($fp === false || $fp === '') {
            return false;
        }
        $normalize = fn (string $v): string => strtolower(str_replace([':', ' '], '', $v));
        return hash_equals($normalize($expectedFp), $normalize($fp));
    }

    private function isUniqueViolation(\Illuminate\Database\QueryException $e): bool
    {
        return $e->getCode() === '23000'
            || str_contains($e->getMessage(), 'Duplicate entry')
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
