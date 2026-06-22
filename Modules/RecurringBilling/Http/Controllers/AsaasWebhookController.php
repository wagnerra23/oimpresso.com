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
 * **Tier 0 multi-tenant**: validamos o token de autenticação no header
 * `asaas-access-token` contra `BoletoCredential.config_json.webhook_secret`
 * da credencial Asaas ativa do business antes de processar. Wagner configura
 * esse token no painel Asaas (Account → Integrações → Webhooks) durante o
 * onboarding da credencial. Mesmo pattern do `InterWebhookController`.
 */
class AsaasWebhookController extends Controller
{
    public function handle(Request $request, int $businessId): JsonResponse
    {
        // Tier 0 multi-tenant: autenticidade ANTES de qualquer processamento/dispatch.
        // Credencial escopada por business_id (global scope HasBusinessScope + where explícito)
        // — token do business A nunca casa com o secret do business B.
        $credential = BoletoCredential::where('business_id', $businessId)
            ->where('banco', 'asaas')
            ->where('ativo', true)
            ->first();

        if (! $credential) {
            return $this->reject('credential_not_found', $businessId, 404);
        }

        $expectedSecret = $credential->config_json['webhook_secret'] ?? null;
        $providedSecret = (string) $request->header('asaas-access-token', '');

        if (! $expectedSecret || ! hash_equals((string) $expectedSecret, $providedSecret)) {
            return $this->reject('secret_mismatch', $businessId, 401);
        }

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

    /**
     * Rejeita o webhook sem nunca logar o secret recebido nem o esperado.
     * Mesmo pattern de `InterWebhookController::reject`.
     */
    private function reject(string $reason, int $businessId, int $status): JsonResponse
    {
        // D7 LGPD: defense-in-depth — redact placeholder (secret/PII NUNCA vai pro log).
        $bodySanitized = app(PiiRedactor::class)->redact('[REDACTED]');

        Log::warning('AsaasWebhookController.reject', [
            'business_id' => $businessId,
            'reason'      => $reason,
            'body'        => $bodySanitized,
        ]);

        return response()->json(['ok' => false, 'reason' => $reason], $status);
    }
}
