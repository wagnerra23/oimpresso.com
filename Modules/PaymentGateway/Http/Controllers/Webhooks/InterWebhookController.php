<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Webhook\WebhookEventId;

/**
 * Recebe webhooks do Inter (boleto + Pix Cob — controller LEGACY).
 *
 * Rota: POST /paymentgateway/webhooks/inter/{businessId}
 * Sem auth middleware (chamado pelo Inter externamente).
 *
 * NÃO confundir com `InterPixWebhookController` (Onda 26 US-FIN-032) que
 * vive em /webhooks/inter/{credentialId} e processa PIX dedicado com job.
 *
 * Onda 3 — ADR 0170. US-PG-002 (audit-senior 2026-05-25 VULN SEC P0-#2):
 * valida HMAC-SHA256 (`x-inter-signature`) ANTES de qualquer parse/DB-write
 * — espelhando pattern InterPixWebhookController canon.
 *
 * Identificadores Inter:
 *   - boletoCobranca: "txid" no payload (PIX recv) ou "nossoNumero" (boleto)
 *   - evento: "cobranca.paga" | "cobranca.vencida" | "cobranca.cancelada"
 */
class InterWebhookController extends Controller
{
    public function __construct(private WebhookProcessor $processor)
    {
    }

    public function handle(Request $request, int $businessId): JsonResponse
    {
        // SUPERADMIN: webhook externo (Inter legacy) sem sessão autenticada; resolve a credencial pelo businessId da rota e valida HMAC antes de qualquer write.
        $credential = PaymentGatewayCredential::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('gateway_key', 'inter')
            ->where('ativo', true)
            ->orderByDesc('id')
            ->first();

        if (! $credential) {
            Log::warning('paymentgateway.inter.webhook.credential_not_found', [
                'business_id' => $businessId,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'credential_not_found',
            ], 404);
        }

        if (! $this->processor->validateSignature('inter', $request, $credential)) {
            Log::warning('paymentgateway.inter.webhook.signature_invalid', [
                'business_id'   => $businessId,
                'credential_id' => $credential->id,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'signature_invalid',
            ], 401);
        }

        $eventName = (string) $request->input('evento', $request->input('event', 'unknown'));
        // Auditoria PG 2.3: `txid`/`nossoNumero` identificam a cobrança, não o
        // evento — compostos com evento + situação para a liquidação não ser
        // descartada como duplicata de um evento anterior da mesma cobrança.
        $idCobranca = $request->input('txid') ?? $request->input('nossoNumero');
        $eventId = match (true) {
            $request->input('id') !== null => (string) $request->input('id'),
            $idCobranca !== null           => WebhookEventId::daCobranca((string) $idCobranca, $eventName, $request->all()),
            default                        => md5($eventName . json_encode($request->all())),
        };

        return $this->processor->handle(
            gatewayKey: 'inter',
            request: $request,
            businessId: $businessId,
            eventName: $eventName,
            eventId: $eventId,
            signatureValid: true,
            credential: $credential,
        );
    }
}
