<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Recebe webhooks do Asaas.
 *
 * Rota: POST /paymentgateway/webhooks/asaas/{businessId}
 * Sem auth middleware (chamado pelo Asaas externamente).
 *
 * Onda 3 — ADR 0170. US-PG-002 (audit-senior 2026-05-25 VULN SEC P0-#2):
 * valida `asaas-access-token` ANTES de qualquer parse/DB-write.
 * Asaas mudança fev/2026: token webhook obrigatório e auto-gerado.
 * Cadastrar em payment_gateway_credentials.config_json.webhook_token.
 *
 * Identificadores Asaas:
 *   - id: ID do evento
 *   - event: "PAYMENT_RECEIVED" | "PAYMENT_CONFIRMED" | "PAYMENT_OVERDUE" | ...
 *   - payment.id: ID da cobrança (fallback)
 */
class AsaasWebhookController extends Controller
{
    public function __construct(private WebhookProcessor $processor)
    {
    }

    public function handle(Request $request, int $businessId): JsonResponse
    {
        $credential = PaymentGatewayCredential::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('gateway_key', 'asaas')
            ->where('ativo', true)
            ->orderByDesc('id')
            ->first();

        if (! $credential) {
            Log::warning('paymentgateway.asaas.webhook.credential_not_found', [
                'business_id' => $businessId,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'credential_not_found',
            ], 404);
        }

        if (! $this->processor->validateSignature('asaas', $request, $credential)) {
            Log::warning('paymentgateway.asaas.webhook.signature_invalid', [
                'business_id'   => $businessId,
                'credential_id' => $credential->id,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'signature_invalid',
            ], 401);
        }

        $eventName = (string) $request->input('event', 'unknown');
        $paymentId = $request->input('payment.id');
        $eventId = (string) ($request->input('id')
            ?? ($paymentId !== null ? $eventName . ':' . $paymentId : md5($eventName . json_encode($request->all()))));

        return $this->processor->handle(
            gatewayKey: 'asaas',
            request: $request,
            businessId: $businessId,
            eventName: $eventName,
            eventId: $eventId,
            signatureValid: true,
        );
    }
}
