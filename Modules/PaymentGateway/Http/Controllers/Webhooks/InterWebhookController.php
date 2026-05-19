<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe webhooks do Inter (boleto + Pix Cob).
 *
 * Rota: POST /paymentgateway/webhooks/inter/{businessId}
 * Sem auth middleware (chamado pelo Inter externamente).
 *
 * Onda 3 — ADR 0170. Cutover via DNS/proxy fica pra Onda 3.5.
 * RB continua recebendo em /webhooks/inter (sem conflito).
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
        $eventName = (string) $request->input('evento', $request->input('event', 'unknown'));
        $eventId = (string) ($request->input('id')
            ?? $request->input('txid')
            ?? $request->input('nossoNumero')
            ?? md5($eventName . json_encode($request->all())));

        return $this->processor->handle(
            gatewayKey: 'inter',
            request: $request,
            businessId: $businessId,
            eventName: $eventName,
            eventId: $eventId,
        );
    }
}
