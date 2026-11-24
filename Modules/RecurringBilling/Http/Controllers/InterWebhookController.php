<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\Privacy\PiiRedactor;
use Modules\RecurringBilling\Jobs\ProcessInterWebhookJob;
use Illuminate\Contracts\Encryption\DecryptException;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\RecurringBilling\Services\CobrancaNoGateway;

/**
 * Recebe webhooks PIX do Banco Inter (`pix.recebido`).
 *
 * Resposta 200 imediata — processamento async via job.
 *
 * Rota: POST /webhooks/inter/pix/{businessId}
 * Sem auth middleware (chamado pelo Inter externamente).
 *
 * **Tier 0 multi-tenant**: validamos shared secret no header
 * `X-Inter-Webhook-Secret` contra o `webhook_secret` da credencial `inter` ativa no PaymentGateway
 * (`payment_gateway_credentials`, desde a onda 2 de 2026-11-24; antes vinha de
 * `rb_boleto_credentials`)
 * do business antes de processar. Wagner configura o header custom no
 * Inter via `PUT /webhooks/pix-recebidos` durante onboarding da credencial.
 *
 * Idempotência via `pg_webhook_events.(provider='inter', event_id=endToEndId)`.
 *
 * @see US-RB-047
 */
class InterWebhookController extends Controller
{
    /**
     * Header que carrega o segredo do webhook. Fonte única: a tela de
     * Configurações exibe este mesmo valor (UC-RBCFG-09).
     */
    public const AUTH_HEADER = 'X-Inter-Webhook-Secret';

    public function handle(Request $request, int $businessId): JsonResponse
    {
        try {
            $credential = app(CobrancaNoGateway::class)->credencial($businessId, 'inter');
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

        $pixArray = $request->input('pix', []);
        if (! is_array($pixArray) || empty($pixArray)) {
            return response()->json(['ok' => true, 'skipped' => 'no_pix']);
        }

        $accepted = 0;
        $skipped  = 0;

        foreach ($pixArray as $pix) {
            $endToEndId = $pix['endToEndId'] ?? null;
            if (! $endToEndId) {
                $skipped++;
                continue;
            }

            // Idempotência por empresa (unique business_id+provider+event_id desde 2026-11-19).
            $alreadyProcessed = DB::table('pg_webhook_events')
                ->where('business_id', $businessId)
                ->where('provider', 'inter')
                ->where('event_id', $endToEndId)
                ->exists();

            if ($alreadyProcessed) {
                $skipped++;
                continue;
            }

            DB::table('pg_webhook_events')->insert([
                'provider'    => 'inter',
                'event_id'    => $endToEndId,
                'event_type'  => 'pix.recebido',
                'payload'     => json_encode($pix),
                'business_id' => $businessId,
                'processed'   => false,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            ProcessInterWebhookJob::dispatch($businessId, $endToEndId, $pix)
                ->onQueue('rb_webhooks');

            $accepted++;
        }

        return response()->json(['ok' => true, 'accepted' => $accepted, 'skipped' => $skipped]);
    }

    private function reject(string $reason, int $businessId, int $status): JsonResponse
    {
        // D7 LGPD: garantia adicional via PiiRedactor mesmo o body já estando placeholder
        // (defense-in-depth — se futura iteração quiser logar trechos, redact é obrigatório)
        $bodySanitized = app(PiiRedactor::class)->redact('[REDACTED]');

        Log::warning('InterWebhookController.reject', [
            'business_id' => $businessId,
            'reason'      => $reason,
            'body'        => $bodySanitized,
        ]);

        return response()->json(['ok' => false, 'reason' => $reason], $status);
    }
}
