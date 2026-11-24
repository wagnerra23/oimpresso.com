<?php

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\ContaBancaria;
use App\Support\Privacy\PiiRedactor;
use Illuminate\Contracts\Encryption\DecryptException;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\RecurringBilling\Services\CobrancaNoGateway;

/**
 * Recebe webhooks do Asaas.
 * Resposta 200 imediata — processamento async via job.
 *
 * Rota: POST /webhooks/asaas/{businessId}
 * Sem auth middleware (é chamado pelo Asaas externamente).
 *
 * **Tier 0 multi-tenant**: validamos o token de autenticação no header
 * `asaas-access-token` contra o `webhook_secret` da credencial `asaas` ativa no PaymentGateway
 * (`payment_gateway_credentials`, desde a onda 2 de 2026-11-24; antes vinha de
 * `rb_boleto_credentials`)
 * do business antes de processar. Wagner configura
 * esse token no painel Asaas (Account → Integrações → Webhooks) durante o
 * onboarding da credencial. Mesmo pattern do `InterWebhookController`.
 */
class AsaasWebhookController extends Controller
{
    /**
     * Header que carrega o segredo do webhook. Fonte única: a tela de
     * Configurações exibe este mesmo valor (UC-RBCFG-09).
     */
    public const AUTH_HEADER = 'asaas-access-token';

    public function handle(Request $request, int $businessId): JsonResponse
    {
        // Tier 0 multi-tenant: autenticidade ANTES de qualquer processamento/dispatch.
        // Credencial escopada por business_id (where explícito em CobrancaNoGateway::credencial)
        // — token do business A nunca casa com o secret do business B.
        try {
            $credential = app(CobrancaNoGateway::class)->credencial($businessId, 'asaas');
        } catch (CredentialMisconfiguredException) {
            return $this->reject('credential_not_found', $businessId, 404);
        }

        // O PG cifra o config_json inteiro e guarda o segredo em claro dentro dele. Config que
        // não decifra (APP_KEY trocada) vale como sem segredo: rejeita, nunca 500.
        try {
            $expectedSecret = $credential->config_json['webhook_secret'] ?? null;
        } catch (DecryptException) {
            $expectedSecret = null;
        }
        $providedSecret = (string) $request->header(self::AUTH_HEADER, '');

        if (! is_string($expectedSecret) || $expectedSecret === '' || ! hash_equals($expectedSecret, $providedSecret)) {
            return $this->reject('secret_mismatch', $businessId, 401);
        }

        $event = $request->input('event');
        $payment = $request->input('payment', []);
        $externalRef = $payment['externalReference'] ?? null;

        // Idempotência — ignora evento já processado
        $eventId = $request->input('id') ?? md5($event . ($payment['id'] ?? ''));
        // Idempotência por empresa (unique business_id+provider+event_id desde 2026-11-19).
        $alreadyProcessed = DB::table('pg_webhook_events')
            ->where('business_id', $businessId)
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
