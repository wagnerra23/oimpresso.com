<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Recebe webhooks Pagar.me v5.
 *
 * Rota: POST /paymentgateway/webhooks/pagarme/{businessId}
 * Sem auth middleware (chamado pelo Pagar.me externamente).
 *
 * Onda 4e — ADR 0170.
 *
 * Pagar.me v5 webhook signature:
 *   - Header: X-Hub-Signature-256
 *   - Formato: "sha256=<hex>" (igual GitHub)
 *   - Algoritmo: HMAC-SHA256(raw_body, webhook_secret)
 *   - Secret configurado por endpoint no dashboard Pagar.me
 *
 * Identificadores Pagar.me:
 *   - id: ID do evento ("hook_xxx") — usado pra idempotência
 *   - type: "charge.paid" | "charge.payment_failed" | "charge.refunded" |
 *           "charge.partial_canceled" | "charge.pending" | "charge.chargedback"
 *   - data.id: ID da charge afetada ("ch_xxx") — fallback pro event_id
 *
 * Fail-secure: se credencial não tem webhook_secret cadastrado OU
 * signature inválida → 401 e não persiste evento (anti-spoofing).
 */
class PagarmeWebhookController extends Controller
{
    public function __construct(private WebhookProcessor $processor)
    {
    }

    public function handle(Request $request, int $businessId): JsonResponse
    {
        // 1. Resolve credencial Pagar.me ATIVA do business (withoutGlobalScopes —
        //    webhook não tem sessão autenticada).
        $credential = PaymentGatewayCredential::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('gateway_key', 'pagarme')
            ->where('ativo', true)
            ->orderByDesc('id')
            ->first();

        if (! $credential) {
            Log::warning('paymentgateway.pagarme.webhook.credential_not_found', [
                'business_id' => $businessId,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'credential_not_found',
            ], 404);
        }

        // 2. Valida HMAC signature (X-Hub-Signature-256)
        if (! $this->validateSignature($request, $credential)) {
            Log::warning('paymentgateway.pagarme.webhook.signature_invalid', [
                'business_id'   => $businessId,
                'credential_id' => $credential->id,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'signature_invalid',
            ], 401);
        }

        // 3. Extrai metadata pro WebhookProcessor (idempotência + log)
        $eventName = (string) $request->input('type', 'unknown');
        $eventId = (string) (
            $request->input('id')
            ?? ($request->input('data.id') !== null ? $eventName . ':' . $request->input('data.id') : md5($eventName . json_encode($request->all())))
        );

        return $this->processor->handle(
            gatewayKey: 'pagarme',
            request: $request,
            businessId: $businessId,
            eventName: $eventName,
            eventId: $eventId,
        );
    }

    /**
     * HMAC-SHA256 do raw body com secret em config_json.webhook_secret.
     *
     * Pagar.me v5: header `X-Hub-Signature-256` formato "sha256=<hex>".
     * Comparação timing-safe via hash_equals.
     *
     * Fail-secure: se credencial NÃO tem webhook_secret cadastrado → false
     * (Wagner configura via wizard /settings/payment-gateways step 4).
     */
    private function validateSignature(Request $request, PaymentGatewayCredential $credential): bool
    {
        $secret = (string) ($credential->config_json['webhook_secret'] ?? '');
        if ($secret === '') {
            return false;
        }

        $providedHeader = (string) $request->header('X-Hub-Signature-256', '');
        if ($providedHeader === '' || ! str_starts_with($providedHeader, 'sha256=')) {
            return false;
        }

        $providedHex = substr($providedHeader, 7); // strip 'sha256='
        $rawBody = $request->getContent();
        $expectedHex = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expectedHex, $providedHex);
    }
}
