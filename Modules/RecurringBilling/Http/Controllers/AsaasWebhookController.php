<?php

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\RecurringBilling\Models\BoletoCredential;

/**
 * Recebe webhooks do Asaas.
 * Resposta 200 imediata — processamento async via job.
 *
 * Rota: POST /webhooks/asaas/{businessId}
 * Sem auth middleware (é chamado pelo Asaas externamente).
 */
class AsaasWebhookController extends Controller
{
    public function handle(Request $request, int $businessId): JsonResponse
    {
        $event = $request->input('event');
        $payment = $request->input('payment', []);
        $externalRef = $payment['externalReference'] ?? null;

        // Idempotência — ignora evento já processado
        $eventId = $request->input('id') ?? md5($event . ($payment['id'] ?? ''));
        $alreadyProcessed = DB::table('pg_webhook_events')
            ->where('provider', 'asaas')
            ->where('event_id', $eventId)
            ->exists();

        if ($alreadyProcessed) {
            Log::info('AsaasWebhookController.duplicate', [
                'business_id' => $businessId,
                'event_id'    => $eventId,
                'event'       => $event,
                // D7 LGPD: payload bruto contém CPF/CNPJ/email do pagador — redact pra log seguro
                'payload_summary' => app(PiiRedactor::class)->redact((string) json_encode([
                    'event' => $event,
                    'payment_id' => $payment['id'] ?? null,
                ])),
            ]);

            return response()->json(['ok' => true, 'skipped' => 'duplicate']);
        }

        // Registra evento antes de processar (at-least-once)
        DB::table('pg_webhook_events')->insert([
            'provider'    => 'asaas',
            'event_id'    => $eventId,
            'event_type'  => $event,
            'payload'     => json_encode($request->all()),
            'business_id' => $businessId,
            'processed'   => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Dispatch async para não travar o Asaas
        \Modules\RecurringBilling\Jobs\ProcessAsaasWebhookJob::dispatch(
            $businessId,
            $eventId,
            $event,
            $payment,
        )->onQueue('rb_webhooks');

        return response()->json(['ok' => true]);
    }
}
