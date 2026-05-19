<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe webhooks BCB (PIX Automático — recv).
 *
 * Rota: POST /paymentgateway/webhooks/bcb-pix/{businessId}
 * Sem auth middleware (chamado pelo PSP via BCB).
 *
 * Onda 3 — ADR 0170. Novo na arquitetura (não existe em RB).
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
        );
    }
}
