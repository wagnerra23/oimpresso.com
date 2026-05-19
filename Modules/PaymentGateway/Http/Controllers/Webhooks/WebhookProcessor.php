<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Webhooks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;

/**
 * Service compartilhado entre Webhook Controllers do PaymentGateway.
 *
 * Onda 3 — ADR 0170.
 *
 * Responsabilidades:
 *   1. Persistir o evento em gateway_webhook_events (idempotência via
 *      UNIQUE(business_id, gateway_key, gateway_event_id))
 *   2. Resposta 200 imediata (gateway re-envia se demorar)
 *   3. Logar com PII redacted (LGPD)
 *
 * NÃO ainda nesta onda (Onda 4):
 *   - Validação HMAC real per banco (drivers ainda não existem)
 *   - Dispatch real do event CobrancaPaga (sem listeners)
 *   - Marcar processed_at após processamento async (vai virar Job)
 *
 * Por enquanto: signature_valid sempre false (driver real Onda 4 valida).
 * Event chega no DB pra Wagner inspecionar e validar shape antes de cutover.
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
                'signature_valid'  => false, // Onda 4 driver valida
                'processed_at'     => null,
            ]);

            Log::info('paymentgateway.webhook.received', [
                'business_id'    => $businessId,
                'gateway_key'    => $gatewayKey,
                'evento'         => $eventName,
                'event_id'       => $eventId,
                'webhook_row_id' => $event->id,
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

    private function isUniqueViolation(\Illuminate\Database\QueryException $e): bool
    {
        // MySQL: SQLSTATE 23000 / errno 1062
        return $e->getCode() === '23000'
            || str_contains($e->getMessage(), 'Duplicate entry')
            || str_contains($e->getMessage(), 'UNIQUE constraint failed'); // SQLite
    }
}
