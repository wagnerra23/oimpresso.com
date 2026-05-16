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
 *
 * LGPD (Wave 10 D7 — 2026-05-16):
 *   - `pg_webhook_events.payload` é redactado via PiiRedactor::redactArray()
 *     antes de gravar (CPF/email/telefone/CEP do pagador Asaas).
 *   - Job downstream `ProcessAsaasWebhookJob` recebe `$payment` raw em memória
 *     (não relê de DB), preservando a regra de negócio.
 *   - Vetor #1 de vazamento PII em RecurringBilling: payload Asaas inclui
 *     `customer.cpfCnpj`, `customer.email`, `payment.creditCard.holderInfo`.
 */
class AsaasWebhookController extends Controller
{
    public function handle(Request $request, int $businessId, PiiRedactor $redactor): JsonResponse
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
            return response()->json(['ok' => true, 'skipped' => 'duplicate']);
        }

        // LGPD: redacta PII no payload antes de persistir. Strings sem PII
        // passam intactas (regex no-op) → testes existentes não quebram.
        $payloadRedactado = $redactor->redactArray($request->all());

        // Registra evento antes de processar (at-least-once)
        DB::table('pg_webhook_events')->insert([
            'provider'    => 'asaas',
            'event_id'    => $eventId,
            'event_type'  => $event,
            'payload'     => json_encode($payloadRedactado),
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
