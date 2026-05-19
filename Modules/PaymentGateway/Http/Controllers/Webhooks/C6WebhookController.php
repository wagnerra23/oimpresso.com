<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe webhooks do C6.
 *
 * Rota: POST /paymentgateway/webhooks/c6/{businessId}
 * Sem auth middleware (chamado pelo C6 externamente).
 *
 * Onda 3 — ADR 0170.
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
        );
    }
}
