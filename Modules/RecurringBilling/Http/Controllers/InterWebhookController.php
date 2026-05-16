<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\RecurringBilling\Jobs\ProcessInterWebhookJob;
use Modules\RecurringBilling\Models\BoletoCredential;

/**
 * Recebe webhooks PIX do Banco Inter (`pix.recebido`).
 *
 * Resposta 200 imediata — processamento async via job.
 *
 * Rota: POST /webhooks/inter/pix/{businessId}
 * Sem auth middleware (chamado pelo Inter externamente).
 *
 * **Tier 0 multi-tenant**: validamos shared secret no header
 * `X-Inter-Webhook-Secret` contra `BoletoCredential.config_json.webhook_secret`
 * do business antes de processar. Wagner configura o header custom no
 * Inter via `PUT /webhooks/pix-recebidos` durante onboarding da credencial.
 *
 * Idempotência via `pg_webhook_events.(provider='inter', event_id=endToEndId)`.
 *
 * LGPD (Wave 10 D7 — 2026-05-16):
 *   - `pg_webhook_events.payload` é redactado via `PiiRedactor::redactArray()`
 *     antes de persistir. Payload Inter PIX inclui `pagador.cpf|cnpj`,
 *     `pagador.nome`, `infoPagador`, valores (LGPD Art. 5º, I).
 *   - Job downstream `ProcessInterWebhookJob` recebe payload raw em memória.
 *
 * @see US-RB-047
 */
class InterWebhookController extends Controller
{
    public function handle(Request $request, int $businessId, PiiRedactor $redactor): JsonResponse
    {
        $credential = BoletoCredential::where('business_id', $businessId)
            ->where('banco', 'inter')
            ->where('ativo', true)
            ->first();

        if (! $credential) {
            return $this->reject('credential_not_found', $businessId, 404);
        }

        $expectedSecret = $credential->config_json['webhook_secret'] ?? null;
        $providedSecret = (string) $request->header('X-Inter-Webhook-Secret', '');

        if (! $expectedSecret || ! hash_equals((string) $expectedSecret, $providedSecret)) {
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

            $alreadyProcessed = DB::table('pg_webhook_events')
                ->where('provider', 'inter')
                ->where('event_id', $endToEndId)
                ->exists();

            if ($alreadyProcessed) {
                $skipped++;
                continue;
            }

            // LGPD: redact PII (CPF/CNPJ/nome pagador) antes de persistir.
            // Job recebe $pix raw em memória — regra de negócio preservada.
            $pixRedactado = $redactor->redactArray($pix);

            DB::table('pg_webhook_events')->insert([
                'provider'    => 'inter',
                'event_id'    => $endToEndId,
                'event_type'  => 'pix.recebido',
                'payload'     => json_encode($pixRedactado),
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
        Log::warning('InterWebhookController.reject', [
            'business_id' => $businessId,
            'reason'      => $reason,
            'body'        => '[REDACTED]',
        ]);

        return response()->json(['ok' => false, 'reason' => $reason], $status);
    }
}
