<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Recebe webhooks Sicoob API v3 (boleto liquidado/vencido/cancelado).
 *
 * US-FIN-044 PR4 — Onda 4f.sicoob_api.
 *
 * Rota: POST /paymentgateway/webhooks/sicoob-api/{businessId}
 * SEM auth middleware (chamado pelo Sicoob externamente).
 *
 * Pipeline:
 *   1. Resolve credential ativa pra business_id+gateway_key=sicoob_api
 *   2. Valida HMAC `x-sicoob-signature` ANTES de qualquer parse/DB-write
 *      (US-PG-002 / VULN SEC P0-#2 padrão canon)
 *   3. Delega pro WebhookProcessor (idempotência at-DB-level via UNIQUE
 *      (business_id, gateway_key, gateway_event_id) — mesma fundação dos
 *      Inter/C6/Asaas)
 *
 * Eventos esperados Sicoob v3:
 *   - cobranca.liquidada (= boleto pago)
 *   - cobranca.vencida
 *   - cobranca.cancelada
 *
 * Identificador idempotente:
 *   - Sicoob NÃO envia `id` único em todos eventos → fallback pro hash
 *     determinístico (evento + nossoNumero + dataLiquidacao).
 *
 * HMAC:
 *   - Header `x-sicoob-signature` = HMAC-SHA256 do raw body com
 *     config_json['webhook_secret'] (hex lowercase, sem prefixo).
 *   - Pattern espelha InterWebhookController (US-PG-002).
 *   - Doc Sicoob v3 pode usar formato variante (ex `sha256=<hex>` ou
 *     header `signature`); se Lea trouxer doc real do gerente divergente,
 *     ajuste pontual no WebhookProcessor::validateSicoobApiHmac().
 */
class SicoobApiWebhookController extends Controller
{
    public function __construct(private WebhookProcessor $processor)
    {
    }

    public function handle(Request $request, int $businessId): JsonResponse
    {
        // SUPERADMIN: webhook externo (Sicoob API) sem sessão autenticada; resolve a credencial pelo businessId da rota e valida HMAC antes de qualquer write.
        $credential = PaymentGatewayCredential::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('gateway_key', 'sicoob_api')
            ->where('ativo', true)
            ->orderByDesc('id')
            ->first();

        if (! $credential) {
            Log::warning('paymentgateway.sicoob_api.webhook.credential_not_found', [
                'business_id' => $businessId,
            ]);

            return response()->json([
                'ok'    => false,
                'error' => 'credential_not_found',
            ], 404);
        }

        if (! $this->processor->validateSignature('sicoob_api', $request, $credential)) {
            Log::warning('paymentgateway.sicoob_api.webhook.signature_invalid', [
                'business_id'   => $businessId,
                'credential_id' => $credential->id,
            ]);

            return response()->json([
                'ok'    => false,
                'error' => 'signature_invalid',
            ], 401);
        }

        $eventName = (string) $request->input('evento', $request->input('event', 'unknown'));

        // Sicoob: tenta id explícito; fallback hash determinístico do payload
        // (evento + nossoNumero + dataLiquidacao quando presentes).
        $eventId = (string) (
            $request->input('id')
            ?? $request->input('eventId')
            ?? $request->input('nossoNumero')
            ?? $request->input('boleto.nossoNumero')
            ?? md5($eventName . json_encode($request->all()))
        );

        return $this->processor->handle(
            gatewayKey: 'sicoob_api',
            request: $request,
            businessId: $businessId,
            eventName: $eventName,
            eventId: $eventId,
            signatureValid: true,
        );
    }
}
