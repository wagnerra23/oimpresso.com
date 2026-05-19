<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe webhooks do Asaas.
 *
 * Rota: POST /paymentgateway/webhooks/asaas/{businessId}
 * Sem auth middleware (chamado pelo Asaas externamente).
 *
 * Onda 3 — ADR 0170. Pattern espelha RB AsaasWebhookController existente
 * (que continua recebendo em /webhooks/asaas/{businessId} no RB).
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
        );
    }
}
