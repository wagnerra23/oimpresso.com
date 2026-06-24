<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Recebe webhooks do C6.
 *
 * Rota: POST /paymentgateway/webhooks/c6/{businessId}
 * Sem auth middleware (chamado pelo C6 externamente).
 *
 * Onda 3 — ADR 0170. US-PG-002 (audit-senior 2026-05-25 VULN SEC P0-#2):
 * valida HMAC-SHA256 GitHub-style (`X-Hub-Signature-256: sha256=<hex>`)
 * ANTES de qualquer parse/DB-write.
 *
 * Identificadores C6: "transactionId" e "eventType".
 */
class C6WebhookController extends Controller
{
    public function __construct(private WebhookProcessor $processor)
    {
    }

    public function handle(Request $request, int $businessId): JsonResponse
    {
        // SUPERADMIN: webhook externo (C6) sem sessão autenticada; resolve a credencial pelo businessId da rota e valida HMAC antes de qualquer write.
        $credential = PaymentGatewayCredential::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('gateway_key', 'c6')
            ->where('ativo', true)
            ->orderByDesc('id')
            ->first();

        if (! $credential) {
            Log::warning('paymentgateway.c6.webhook.credential_not_found', [
                'business_id' => $businessId,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'credential_not_found',
            ], 404);
        }

        if (! $this->processor->validateSignature('c6', $request, $credential)) {
            Log::warning('paymentgateway.c6.webhook.signature_invalid', [
                'business_id'   => $businessId,
                'credential_id' => $credential->id,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'signature_invalid',
            ], 401);
        }

        $eventName = (string) $request->input('eventType', $request->input('event', 'unknown'));
        $eventId = (string) ($request->input('transactionId')
            ?? $request->input('id')
            ?? md5($eventName . json_encode($request->all())));

        return $this->processor->handle(
            gatewayKey: 'c6',
            request: $request,
            businessId: $businessId,
            eventName: $eventName,
            eventId: $eventId,
            signatureValid: true,
            credential: $credential,
        );
    }
}
