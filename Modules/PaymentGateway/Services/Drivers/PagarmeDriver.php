<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Drivers;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Modules\PaymentGateway\Contracts\PaymentDriverContract;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\CobrancaStatus;
use Modules\PaymentGateway\Dto\DriverHealth;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CardDeclinedException;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\HttpClientFactory;

/**
 * Driver Pagar.me — API v5 (Stone group).
 *
 * Onda 4e — ADR 0170. Driver Pagar.me espelha estrutura do AsaasDriver
 * pra boleto+pix_cob+card; também suporta cancelar/refund/consultar/health/webhook.
 *
 * Suporta:
 *   ✓ boleto, pix_cob, card
 *   ✓ cancelar, refund (parcial OK via amount), consultar, healthCheck, processWebhook
 * NÃO suporta (use outros drivers):
 *   ✗ pix_cobv (PIX com vencimento regulado — Asaas/Inter)
 *   ✗ pix_recv (PIX Automático mandato — BcbPix driver dedicado)
 *
 * ── DECISÕES DE PESQUISA (WebSearch 2026-05-22) ───────────────────────────
 *
 * 1. Endpoint base: `https://api.pagar.me/core/v5` (mesma URL pra sandbox + prod;
 *    sandbox é determinado pelo prefixo da secret_key `sk_test_...`).
 *    Fonte: https://docs.pagar.me/reference/autenticação-2
 *
 * 2. Auth: HTTP Basic Auth, username = secret_key, password vazio.
 *    Header: `Authorization: Basic base64(secret_key:)`.
 *    Laravel: `Http::withBasicAuth($secret, '')`.
 *
 * 3. Modelo:
 *    - **Order** (POST /orders) contém items + customer + payments[]
 *    - **Charge** é gerada dentro do order (1 charge por payment_method)
 *    - Boleto/PIX/CC todos via Order. Resposta retorna `charges[0].id` (ch_xxx)
 *      que é o id pra cancelar/refund/consultar.
 *    Fonte: https://docs.pagar.me/reference/criar-pedido-2
 *
 * 4. Cancelar / Refund: `DELETE /charges/{charge_id}` body opcional {"amount":xxx}
 *    pra refund parcial (cartão; boleto cancelado é só mudança de status).
 *    Fonte: https://docs.pagar.me/reference/cancelar-cobrança
 *
 * 5. Status enum Pagar.me v5 charges:
 *    `pending` (boleto não pago / pix não pago / card autorizado não capturado)
 *    `paid` (pago/capturado)
 *    `failed` (recusado / não confirmado)
 *    `canceled` (estornado / cancelado)
 *    `processing`, `overpaid`, `underpaid`, `chargedback`
 *    Mapping → enum interno (paga/emitida/vencida/cancelada/pending).
 *    Fonte: https://docs.pagar.me/page/chargeback-novo-status-na-cobrança
 *
 * 6. Webhook signature: header `X-Hub-Signature-256` formato `sha256=<hex>`
 *    HMAC-SHA256(raw_body, webhook_secret) — secret configurado por endpoint
 *    no dashboard Pagar.me. Comparação timing-safe via hash_equals.
 *    Fonte: https://docs.pagar.me/reference/visão-geral-sobre-webhooks
 *
 * 7. Webhook events relevantes: `charge.paid`, `charge.payment_failed`,
 *    `charge.refunded`, `charge.partial_canceled`, `charge.pending`,
 *    `charge.chargedback`. Payload tem `data.id` (charge id), `type` (evento).
 *
 * 8. Health check: `GET /balance` (saldo da conta) — endpoint barato, exige
 *    auth válida; retorna 200 ok ou 401 unauthorized. Boa pra ping.
 *
 * Credenciais (config_json):
 *   secret_key:     "sk_test_..." (sandbox) | "sk_live_..." (prod)
 *   ambiente:       'sandbox' | 'production' (informativo — endpoint igual)
 *   webhook_secret: "..." (pra validar HMAC X-Hub-Signature-256)
 *
 * Custo IA: Driver NÃO chama LLM. Apenas HTTP REST Pagar.me. Latência média ~300ms
 * em produção (Stone CDN), pode chegar a 2-3s em horário de pico de boletos.
 */
class PagarmeDriver implements PaymentDriverContract
{
    /**
     * Pagar.me v5 — sandbox e prod usam a mesma URL.
     * O que muda é o prefixo da secret_key ('sk_test_' vs 'sk_live_').
     */
    private const API_BASE = 'https://api.pagar.me/core/v5';

    public function key(): string
    {
        return 'pagarme';
    }

    public function supports(string $tipo): bool
    {
        return in_array($tipo, ['boleto', 'pix_cob', 'card'], true);
    }

    public function emitirBoleto(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        return $this->emitirOrder($input, $cred, 'boleto');
    }

    public function emitirPix(EmitirCobrancaInput $input, object $cred, string $tipo): CobrancaEmitidaResult
    {
        if ($tipo !== 'cob') {
            throw new DriverNotSupportedException("Pagar.me só suporta PIX cob; recebido '{$tipo}'");
        }

        return $this->emitirOrder($input, $cred, 'pix');
    }

    public function emitirPixAutomatico(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException(
            'Pagar.me não suporta PIX Automático (recv) via Open Finance Pix Automático regulado. ' .
            'Use bcb_pix driver dedicado (Onda 4d).'
        );
    }

    public function cobrarCartao(EmitirCobrancaInput $input, object $cred, CardToken $token): CobrancaEmitidaResult
    {
        $this->assertCredential($cred);

        $payload = $this->buildOrderPayload($input, [
            [
                'payment_method' => 'credit_card',
                'credit_card'    => [
                    'card_token'   => $token->token, // tokenizado client-side (PCI)
                    'installments' => (int) ($input->meta['installments'] ?? 1),
                ],
            ],
        ]);

        $response = HttpClientFactory::send(fn () => $this->client($cred)->post('/orders', $payload));

        // Pagar.me v5: 400 = invalid_request (cartão recusado vem como
        // status='failed' na charge dentro de 200 OK também). Tratamos ambos.
        if ($response->status() === 400 || $response->status() === 422) {
            throw new CardDeclinedException(
                'Pagar.me recusou cartão: ' . substr($response->body(), 0, 200)
            );
        }
        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Pagar.me cartão falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $charge = $data['charges'][0] ?? [];
        $chargeId = (string) ($charge['id'] ?? '');

        if ($chargeId === '') {
            throw new InvalidPayerException('Pagar.me cartão retornou sem charge.id');
        }

        // Status 'failed' dentro do 200 OK = recusa do emissor.
        if (($charge['status'] ?? '') === 'failed') {
            throw new CardDeclinedException(
                'Pagar.me cartão recusado pelo emissor: ' .
                ($charge['last_transaction']['acquirer_message'] ?? 'sem mensagem')
            );
        }

        return new CobrancaEmitidaResult(
            cobrancaId: 0,
            gatewayExternalId: $chargeId,
            tipo: 'card',
            emitidaEm: new \DateTimeImmutable(),
            payloadGateway: $data,
        );
    }

    public function cancelar(object $cobranca, object $cred, string $motivo): void
    {
        $this->assertCredential($cred);
        $extId = (string) ($cobranca->gateway_external_id ?? '');
        if ($extId === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra cancelar no Pagar.me');
        }

        // DELETE /charges/{id} sem amount = cancela total
        $response = HttpClientFactory::send(fn () => $this->client($cred)->delete("/charges/{$extId}"));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Pagar.me cancelar falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }
    }

    public function refund(object $cobranca, object $cred, ?int $valorCentavos, string $motivo): void
    {
        $this->assertCredential($cred);
        $extId = (string) ($cobranca->gateway_external_id ?? '');
        if ($extId === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra estornar no Pagar.me');
        }

        // Pagar.me v5: refund = DELETE /charges/{id} com body {amount} pra parcial
        $payload = [];
        if ($valorCentavos !== null) {
            $payload['amount'] = $valorCentavos; // Pagar.me usa centavos como int
        }

        $response = HttpClientFactory::send(fn () => $this->client($cred)
            ->withBody(json_encode($payload, JSON_UNESCAPED_UNICODE), 'application/json')
            ->delete("/charges/{$extId}"));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Pagar.me refund falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }
    }

    public function consultar(object $cobranca, object $cred): CobrancaStatus
    {
        $this->assertCredential($cred);
        $extId = (string) ($cobranca->gateway_external_id ?? '');
        if ($extId === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra consultar no Pagar.me');
        }

        $response = HttpClientFactory::send(fn () => $this->client($cred)->get("/charges/{$extId}"));
        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Pagar.me consultar falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];

        return new CobrancaStatus(
            status: $this->mapStatus((string) ($data['status'] ?? '')),
            pagaEm: ! empty($data['paid_at']) ? new \DateTimeImmutable($data['paid_at']) : null,
            valorPagoCentavos: isset($data['paid_amount']) ? (int) $data['paid_amount'] : null,
            formaPagamento: $this->mapPaymentMethod((string) ($data['payment_method'] ?? '')),
            payloadGateway: $data,
        );
    }

    public function healthCheck(object $cred): DriverHealth
    {
        $this->assertCredential($cred);
        $start = microtime(true);

        try {
            // GET /balance é o ping canônico (endpoint barato, exige auth válida)
            // Usa clientHealth (sem retry) — 1 fail = down do dashboard
            $response = $this->clientHealth($cred)->get('/balance');
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                return new DriverHealth(
                    ok: true,
                    status: $latencyMs > 3000 ? 'degraded' : 'ok',
                    latencyMs: $latencyMs,
                    checkedAt: new \DateTimeImmutable(),
                );
            }

            return new DriverHealth(
                ok: false,
                status: 'down',
                latencyMs: $latencyMs,
                checkedAt: new \DateTimeImmutable(),
                errorMessage: "Pagar.me {$response->status()}: " . substr($response->body(), 0, 120),
            );
        } catch (\Throwable $e) {
            return new DriverHealth(
                ok: false,
                status: 'down',
                latencyMs: (int) round((microtime(true) - $start) * 1000),
                checkedAt: new \DateTimeImmutable(),
                errorMessage: $e->getMessage(),
            );
        }
    }

    /**
     * Processa payload de webhook Pagar.me — chamado pelo PagarmeWebhookController.
     *
     * Pagar.me v5 envia eventos no shape:
     *   {
     *     "id": "hook_xxx",
     *     "type": "charge.paid" | "charge.payment_failed" | "charge.refunded" | ...,
     *     "data": { "id": "ch_xxx", "status": "paid", ... }   // a charge afetada
     *   }
     *
     * Validação HMAC é responsabilidade do controller (precisa raw body).
     * Aqui só extraímos charge_id pra resolver Cobranca.
     */
    public function processWebhook(array $payload, object $cred): ?object
    {
        $extId = (string) (
            $payload['data']['id']
            ?? $payload['data']['charge']['id']
            ?? ''
        );

        if ($extId === '') {
            return null;
        }

        return (object) [
            'gateway_external_id' => $extId,
            'gateway_key'         => $this->key(),
            'event_type'          => (string) ($payload['type'] ?? ''),
            'raw_payload'         => $payload,
        ];
    }

    // ─── helpers ─────────────────────────────────────────────────────────

    /**
     * Cria order com payment_method especificado (boleto|pix).
     * Para 'card', use cobrarCartao (precisa CardToken).
     */
    private function emitirOrder(EmitirCobrancaInput $input, object $cred, string $paymentMethod): CobrancaEmitidaResult
    {
        $this->assertCredential($cred);

        $paymentBlock = match ($paymentMethod) {
            'boleto' => [
                'payment_method' => 'boleto',
                'boleto'         => [
                    'instructions' => $input->instrucoesPagador ?: 'Pagar até a data de vencimento',
                    'due_at'       => Carbon::instance($input->vencimento)->toIso8601String(),
                ],
            ],
            'pix' => [
                'payment_method' => 'pix',
                'pix'            => [
                    'expires_in' => $this->expiresInSecondsFrom($input->vencimento),
                ],
            ],
            default => throw new DriverNotSupportedException("emitirOrder não suporta '{$paymentMethod}'")
        };

        $payload = $this->buildOrderPayload($input, [$paymentBlock]);
        $response = HttpClientFactory::send(fn () => $this->client($cred)->post('/orders', $payload));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Pagar.me {$paymentMethod} falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $charge = $data['charges'][0] ?? [];
        $chargeId = (string) ($charge['id'] ?? '');
        if ($chargeId === '') {
            throw new InvalidPayerException('Pagar.me retornou sem charge.id — payload incompleto');
        }

        $lastTx = $charge['last_transaction'] ?? [];

        $linhaDigitavel = null;
        $codigoBarras = null;
        $boletoPdfUrl = null;
        $pixEmv = null;
        $pixQrCodePath = null;

        if ($paymentMethod === 'boleto') {
            $linhaDigitavel = (string) ($lastTx['line'] ?? '') ?: null;
            $codigoBarras   = (string) ($lastTx['barcode'] ?? '') ?: null;
            $boletoPdfUrl   = (string) ($lastTx['pdf'] ?? $lastTx['url'] ?? '') ?: null;
        }

        if ($paymentMethod === 'pix') {
            $pixEmv        = (string) ($lastTx['qr_code'] ?? '') ?: null;
            $pixQrCodePath = (string) ($lastTx['qr_code_url'] ?? '') ?: null;
        }

        return new CobrancaEmitidaResult(
            cobrancaId: 0,
            gatewayExternalId: $chargeId,
            tipo: $paymentMethod === 'boleto' ? 'boleto' : 'pix_cob',
            emitidaEm: new \DateTimeImmutable(),
            linhaDigitavel: $linhaDigitavel,
            codigoBarras: $codigoBarras,
            pixEmv: $pixEmv,
            pixQrCodePath: $pixQrCodePath,
            boletoPdfUrl: $boletoPdfUrl,
            payloadGateway: $data,
        );
    }

    /**
     * Monta payload base do POST /orders compartilhado por boleto/pix/card.
     */
    private function buildOrderPayload(EmitirCobrancaInput $input, array $payments): array
    {
        $cpfCnpj = preg_replace('/\D/', '', $input->meta['payer_cpf_cnpj'] ?? '');
        $name = (string) ($input->meta['payer_name'] ?? "Pagador {$input->contactId}");
        $email = (string) ($input->meta['payer_email'] ?? '');

        if ($cpfCnpj === '') {
            throw new InvalidPayerException('Pagar.me exige CPF/CNPJ do pagador em meta.payer_cpf_cnpj');
        }

        $customer = array_filter([
            'name'           => $name,
            'email'          => $email !== '' ? $email : null,
            'document'       => $cpfCnpj,
            'document_type'  => strlen($cpfCnpj) === 11 ? 'CPF' : 'CNPJ',
            'type'           => strlen($cpfCnpj) === 11 ? 'individual' : 'company',
            'code'           => 'contact:' . $input->contactId,
        ]);

        return [
            'code'      => $input->idempotencyKey,
            'customer'  => $customer,
            'items'     => [[
                'amount'      => $input->valorCentavos,
                'description' => substr($input->descricao, 0, 254),
                'quantity'    => 1,
                'code'        => 'item:' . $input->idempotencyKey,
            ]],
            'payments'  => $payments,
        ];
    }

    private function expiresInSecondsFrom(\DateTimeInterface $vencimento): int
    {
        $diff = $vencimento->getTimestamp() - time();
        // Pagar.me PIX exige expires_in > 0. Default 1h se já passou.
        return $diff > 0 ? $diff : 3600;
    }

    private function assertCredential(object $cred): void
    {
        if (! $cred instanceof PaymentGatewayCredential) {
            throw new CredentialMisconfiguredException(
                'Credential precisa ser PaymentGatewayCredential, recebeu: ' . get_class($cred)
            );
        }
        if ($cred->gateway_key !== 'pagarme') {
            throw new CredentialMisconfiguredException(
                "Credential gateway_key='{$cred->gateway_key}' não bate com driver Pagar.me"
            );
        }
        if (empty($cred->config_json['secret_key'])) {
            throw new CredentialMisconfiguredException(
                'Pagar.me credential precisa secret_key em config_json (sk_test_* ou sk_live_*)'
            );
        }
    }

    /**
     * Cliente principal — com retry + 429 handler via HttpClientFactory
     * (Auditoria 2026-05-23 Onda 4e gap #1+#2).
     */
    private function client(PaymentGatewayCredential $cred): PendingRequest
    {
        $secret = (string) ($cred->config_json['secret_key'] ?? '');

        return HttpClientFactory::make(
            baseUrl: self::API_BASE,
            timeoutSec: 30,
        )->withBasicAuth($secret, '');
    }

    /**
     * Cliente healthcheck — SEM retry (1 fail = down).
     */
    private function clientHealth(PaymentGatewayCredential $cred): PendingRequest
    {
        $secret = (string) ($cred->config_json['secret_key'] ?? '');

        return HttpClientFactory::make(
            baseUrl: self::API_BASE,
            timeoutSec: 30,
            withRetry: false,
        )->withBasicAuth($secret, '');
    }

    /**
     * Mapeia status Pagar.me v5 → enum canon interno
     * (paga | emitida | vencida | cancelada | pending | erro).
     */
    private function mapStatus(string $pagarmeStatus): string
    {
        return match (strtolower($pagarmeStatus)) {
            'paid', 'overpaid'                    => 'paga',
            'pending', 'processing', 'underpaid'  => 'emitida',
            'canceled', 'refunded', 'partial_canceled' => 'cancelada',
            'failed', 'chargedback'               => 'erro',
            default                               => 'pending',
        };
    }

    private function mapPaymentMethod(string $method): ?string
    {
        return match (strtolower($method)) {
            'boleto'                    => 'boleto',
            'pix'                       => 'pix',
            'credit_card', 'debit_card' => 'cartao',
            default                     => null,
        };
    }
}
