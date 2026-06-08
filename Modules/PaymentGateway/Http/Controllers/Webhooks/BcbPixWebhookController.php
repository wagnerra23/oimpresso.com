<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Recebe webhooks BCB (PIX Automático — recv).
 *
 * Rota: POST /paymentgateway/webhooks/bcb-pix/{businessId}
 * Sem auth middleware (chamado pelo PSP via BCB).
 *
 * Onda 3 — ADR 0170. US-PG-002 (audit-senior 2026-05-25 VULN SEC P0-#2):
 * valida mTLS cert fingerprint (SHA-256) ANTES de qualquer parse/DB-write
 * — espelhando Open Finance Brasil v2.1.0+ (proof-of-possession via
 * `SSL_CLIENT_CERT` server var populada pelo nginx/reverse-proxy).
 *
 * Cadastrar fingerprint cert do PSP em
 * `payment_gateway_credentials.config_json.psp_cert_fingerprint`
 * (hex lowercase, sem dois-pontos).
 *
 * Identificadores BCB:
 *   - "txid" / "txId" — único da transação PIX
 *   - "evento" — "PIX_RECEBIDO" | "MANDATO_AUTORIZADO" | "MANDATO_REVOGADO"
 *
 * Regulamentação: Resolução BCB 380/2024 PIX Automático.
 */
class BcbPixWebhookController extends Controller
{
    public function __construct(private WebhookProcessor $processor)
    {
    }

    public function handle(Request $request, int $businessId): JsonResponse
    {
        $credential = PaymentGatewayCredential::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('gateway_key', 'bcb_pix')
            ->where('ativo', true)
            ->orderByDesc('id')
            ->first();

        if (! $credential) {
            Log::warning('paymentgateway.bcb_pix.webhook.credential_not_found', [
                'business_id' => $businessId,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'credential_not_found',
            ], 404);
        }

        if (! $this->processor->validateSignature('bcb_pix', $request, $credential)) {
            Log::warning('paymentgateway.bcb_pix.webhook.signature_invalid', [
                'business_id'   => $businessId,
                'credential_id' => $credential->id,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'signature_invalid',
            ], 401);
        }

        $eventName = (string) $request->input('evento', $request->input('event', 'unknown'));
        $eventId = (string) ($request->input('txid')
            ?? $request->input('txId')
            ?? $request->input('id')
            ?? md5($eventName . json_encode($request->all())));

        return $this->processor->handle(
            gatewayKey: 'bcb_pix',
            request: $request,
            businessId: $businessId,
            eventName: $eventName,
            eventId: $eventId,
            signatureValid: true,
        );
    }
}
