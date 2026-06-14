<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\PaymentGateway\Jobs\ProcessarWebhookPixInterJob;
use Modules\PaymentGateway\Models\InterWebhookLog;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * US-FIN-032 (Onda 26) — Webhook PIX recebido do Inter por credential.
 *
 * Rota: POST /webhooks/inter/{credentialId}
 *
 * Diferenças do controller legacy (`InterWebhookController` Onda 3):
 *   - Identifica tenant via {credentialId} (não {businessId} solto no path)
 *   - HMAC signature obrigatória via header `x-inter-signature`
 *     (secret armazenado em payment_gateway_credentials.config_json.webhook_secret)
 *   - Idempotência por (credential_id, txid) UNIQUE em `inter_webhook_log`
 *   - Enfileira `ProcessarWebhookPixInterJob` pra resolver cobranca → titulo
 *     (NÃO processa no controller — webhook precisa devolver 200 rápido)
 *
 * Payload esperado (Inter API Pix v2 webhook):
 *   {
 *     "pix": [{
 *       "endToEndId": "E12345678202605200001",
 *       "txid": "abc123",
 *       "valor": "100.50",
 *       "horario": "2026-05-20T12:34:56Z",
 *       "infoPagador": "...",
 *       "pagador": { "cpf": "...", "nome": "..." }
 *     }]
 *   }
 *
 * Resposta:
 *   - 200 { ok: true, processed: int, duplicated: int } — sucesso (sempre, exceto signature inválida)
 *   - 401 { ok: false, error: "signature_invalid" } — HMAC inválido
 *   - 404 { ok: false, error: "credential_not_found" } — credentialId inválido
 *
 * SEM auth middleware (chamado externamente pelo Inter).
 */
class InterPixWebhookController extends Controller
{
    public function __construct(private PiiRedactor $redactor)
    {
    }

    public function handle(Request $request, int $credentialId): JsonResponse
    {
        // 1. Resolve credencial (withoutGlobalScopes — webhook não tem sessão)
        // SUPERADMIN: webhook externo (Inter PIX) sem sessão autenticada; resolve a credencial pelo credentialId da rota e valida HMAC antes de qualquer write.
        $credential = PaymentGatewayCredential::withoutGlobalScopes()
            ->where('id', $credentialId)
            ->where('gateway_key', 'inter')
            ->first();

        if (! $credential) {
            Log::warning('paymentgateway.inter.webhook.credential_not_found', [
                'credential_id' => $credentialId,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'credential_not_found',
            ], 404);
        }

        // 2. Valida HMAC signature
        $signatureValid = $this->validateSignature($request, $credential);
        if (! $signatureValid) {
            Log::warning('paymentgateway.inter.webhook.signature_invalid', [
                'credential_id' => $credentialId,
                'business_id'   => $credential->business_id,
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'signature_invalid',
            ], 401);
        }

        // 3. Persiste log + enfileira worker pra cada PIX no payload
        $payload = $request->all();
        $pixEvents = $payload['pix'] ?? [];
        if (! is_array($pixEvents) || $pixEvents === []) {
            // Inter pode enviar payload de teste sem `pix` — devolve 200 vazio
            Log::info('paymentgateway.inter.webhook.empty_pix', [
                'credential_id' => $credentialId,
                'business_id'   => $credential->business_id,
            ]);
            return response()->json([
                'ok'         => true,
                'processed'  => 0,
                'duplicated' => 0,
            ]);
        }

        $processed = 0;
        $duplicated = 0;

        foreach ($pixEvents as $pix) {
            $txid = (string) ($pix['txid'] ?? '');
            if ($txid === '') {
                continue;
            }

            $valorReais = (float) ($pix['valor'] ?? 0);
            $valorCentavos = (int) round($valorReais * 100);
            $pagador = $pix['pagador'] ?? [];
            $cpfCnpjRaw = (string) ($pagador['cpf'] ?? $pagador['cnpj'] ?? '');
            // LGPD: redact CPF/CNPJ antes de gravar log
            $cpfCnpjRedacted = $cpfCnpjRaw !== ''
                ? $this->redactor->redact($cpfCnpjRaw)
                : null;

            $dataPagamento = ! empty($pix['horario'])
                ? \Carbon\Carbon::parse((string) $pix['horario'])->toDateTimeString()
                : now()->toDateTimeString();

            try {
                $log = InterWebhookLog::create([
                    'business_id'                   => $credential->business_id,
                    'payment_gateway_credential_id' => $credential->id,
                    'txid'                          => $txid,
                    'endToEndId'                    => (string) ($pix['endToEndId'] ?? '') ?: null,
                    'valor_centavos'                => $valorCentavos,
                    'payer_cpf_cnpj_redacted'       => $cpfCnpjRedacted,
                    'data_pagamento'                => $dataPagamento,
                    'signature_valid'               => true,
                    'status'                        => 'received',
                    'payload'                       => $pix,
                ]);

                ProcessarWebhookPixInterJob::dispatch($log->id, (int) $credential->business_id);
                $processed++;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($this->isUniqueViolation($e)) {
                    // Webhook duplicado — Inter retransmitiu. NÃO erro.
                    Log::info('paymentgateway.inter.webhook.duplicate', [
                        'credential_id' => $credentialId,
                        'business_id'   => $credential->business_id,
                        'txid'          => $txid,
                    ]);
                    $duplicated++;
                    continue;
                }
                throw $e;
            }
        }

        return response()->json([
            'ok'         => true,
            'processed'  => $processed,
            'duplicated' => $duplicated,
        ]);
    }

    /**
     * HMAC-SHA256 do raw body com secret em config_json.webhook_secret.
     *
     * Formato header `x-inter-signature`: hex lowercase (Inter API Pix v2).
     * Comparação timing-safe via hash_equals.
     *
     * Se credencial NÃO tem webhook_secret configurado: rejeita (fail-secure).
     * Wagner configura via wizard `/settings/payment-gateways` step 4.
     */
    private function validateSignature(Request $request, PaymentGatewayCredential $credential): bool
    {
        $secret = (string) ($credential->config_json['webhook_secret'] ?? '');
        if ($secret === '') {
            // Sem secret cadastrado: fail-secure (não aceita webhook).
            return false;
        }

        $providedSignature = (string) $request->header('x-inter-signature', '');
        if ($providedSignature === '') {
            return false;
        }

        $rawBody = $request->getContent();
        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $providedSignature);
    }

    private function isUniqueViolation(\Illuminate\Database\QueryException $e): bool
    {
        return $e->getCode() === '23000'
            || str_contains($e->getMessage(), 'Duplicate entry')
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
