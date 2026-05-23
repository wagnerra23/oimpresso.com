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
 * Driver Asaas — API REST v3.
 *
 * Onda 4b — ADR 0170. Asaas é o driver MAIS completo:
 *   ✓ boleto, pix_cob, card
 *   ✓ cancelar, refund (parcial OK), consultar, healthCheck, processWebhook
 *   ✗ pix_cobv (PIX com vencimento — Asaas suporta via tipo "PIX" + dueDate, mapped boleto+pix; pode chegar 4c)
 *   ✗ pix_recv (PIX Automático Asaas não suporta — usa BCB dedicado driver 4d)
 *
 * Credenciais (config_json):
 *   api_key:  "$aact_..." (token)
 *   ambiente: 'sandbox' | 'production'
 */
class AsaasDriver implements PaymentDriverContract
{
    private const API_BASE_PRODUCTION = 'https://api.asaas.com/v3';
    private const API_BASE_SANDBOX = 'https://sandbox.asaas.com/api/v3';

    public function key(): string
    {
        return 'asaas';
    }

    public function supports(string $tipo): bool
    {
        return in_array($tipo, ['boleto', 'pix_cob', 'card'], true);
    }

    public function emitirBoleto(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        return $this->emitirPayment($input, $cred, 'BOLETO');
    }

    public function emitirPix(EmitirCobrancaInput $input, object $cred, string $tipo): CobrancaEmitidaResult
    {
        if ($tipo !== 'cob') {
            throw new DriverNotSupportedException("Asaas só suporta PIX cob; recebido '{$tipo}'");
        }

        return $this->emitirPayment($input, $cred, 'PIX');
    }

    public function emitirPixAutomatico(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException(
            'Asaas não suporta PIX Automático (recv). Use bcb_pix driver dedicado (Onda 4d).'
        );
    }

    public function cobrarCartao(EmitirCobrancaInput $input, object $cred, CardToken $token): CobrancaEmitidaResult
    {
        $this->assertCredential($cred);

        $customerId = $this->resolveCustomer($input, $cred);

        $response = HttpClientFactory::send(fn () => $this->client($cred)
            ->post('/payments', [
                'customer'           => $customerId,
                'billingType'        => 'CREDIT_CARD',
                'value'              => round($input->valorCentavos / 100, 2),
                'dueDate'            => Carbon::instance($input->vencimento)->toDateString(),
                'description'        => $input->descricao,
                'externalReference'  => $input->idempotencyKey,
                'creditCard'         => [
                    'holderName'  => $token->holderName,
                    'number'      => '', // PCI: tokenizado, número não trafega
                    'expiryMonth' => $token->expMonth,
                    'expiryYear'  => $token->expYear,
                ],
                'creditCardToken' => $token->token,
            ]));

        if ($response->status() === 400) {
            throw new CardDeclinedException(
                'Asaas recusou cartão: ' . substr($response->body(), 0, 200)
            );
        }
        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Asaas cartão falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];

        return new CobrancaEmitidaResult(
            cobrancaId: 0,
            gatewayExternalId: (string) ($data['id'] ?? ''),
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
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra cancelar no Asaas');
        }

        $response = HttpClientFactory::send(fn () => $this->client($cred)->delete("/payments/{$extId}"));
        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Asaas cancelar falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }
    }

    public function refund(object $cobranca, object $cred, ?int $valorCentavos, string $motivo): void
    {
        $this->assertCredential($cred);
        $extId = (string) ($cobranca->gateway_external_id ?? '');
        if ($extId === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra estornar no Asaas');
        }

        $payload = ['description' => $motivo];
        if ($valorCentavos !== null) {
            $payload['value'] = round($valorCentavos / 100, 2);
        }

        $response = HttpClientFactory::send(fn () => $this->client($cred)->post("/payments/{$extId}/refund", $payload));
        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Asaas refund falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }
    }

    public function consultar(object $cobranca, object $cred): CobrancaStatus
    {
        $this->assertCredential($cred);
        $extId = (string) ($cobranca->gateway_external_id ?? '');
        if ($extId === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra consultar no Asaas');
        }

        $response = HttpClientFactory::send(fn () => $this->client($cred)->get("/payments/{$extId}"));
        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Asaas consultar falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];

        return new CobrancaStatus(
            status: $this->mapStatus((string) ($data['status'] ?? '')),
            pagaEm: ! empty($data['paymentDate']) ? new \DateTimeImmutable($data['paymentDate']) : null,
            valorPagoCentavos: isset($data['netValue']) ? (int) round((float) $data['netValue'] * 100) : null,
            formaPagamento: $this->mapBillingType((string) ($data['billingType'] ?? '')),
            payloadGateway: $data,
        );
    }

    public function healthCheck(object $cred): DriverHealth
    {
        $this->assertCredential($cred);
        $start = microtime(true);

        try {
            $response = $this->clientHealth($cred)->get('/finance/balance');
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
                errorMessage: "Asaas {$response->status()}: " . substr($response->body(), 0, 120),
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

    public function processWebhook(array $payload, object $cred): ?object
    {
        $extId = (string) ($payload['payment']['id'] ?? $payload['id'] ?? '');
        if ($extId === '') {
            return null;
        }

        return (object) [
            'gateway_external_id' => $extId,
            'gateway_key'         => $this->key(),
            'raw_payload'         => $payload,
        ];
    }

    // ─── helpers ─────────────────────────────────────────────────────────

    private function emitirPayment(EmitirCobrancaInput $input, object $cred, string $billingType): CobrancaEmitidaResult
    {
        $this->assertCredential($cred);
        $customerId = $this->resolveCustomer($input, $cred);

        $response = HttpClientFactory::send(fn () => $this->client($cred)
            ->post('/payments', [
                'customer'           => $customerId,
                'billingType'        => $billingType, // BOLETO | PIX
                'value'              => round($input->valorCentavos / 100, 2),
                'dueDate'            => Carbon::instance($input->vencimento)->toDateString(),
                'description'        => $input->descricao,
                'externalReference'  => $input->idempotencyKey,
                'postalService'      => false,
            ]));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Asaas {$billingType} falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $id = (string) ($data['id'] ?? '');
        if ($id === '') {
            throw new InvalidPayerException('Asaas retornou sem id — payload incompleto');
        }

        // BOLETO → busca identificationField + bankSlipUrl
        // PIX → busca QR code
        $linhaDigitavel = null;
        $codigoBarras = null;
        $boletoPdfUrl = null;
        $pixEmv = null;

        if ($billingType === 'BOLETO') {
            $bankSlip = HttpClientFactory::send(fn () => $this->client($cred)->get("/payments/{$id}/identificationField"));
            if ($bankSlip->successful()) {
                $bs = $bankSlip->json() ?? [];
                $linhaDigitavel = (string) ($bs['identificationField'] ?? '');
                $codigoBarras = (string) ($bs['barCode'] ?? '');
            }
            $boletoPdfUrl = (string) ($data['bankSlipUrl'] ?? '');
        }

        if ($billingType === 'PIX') {
            $pixData = HttpClientFactory::send(fn () => $this->client($cred)->get("/payments/{$id}/pixQrCode"));
            if ($pixData->successful()) {
                $pd = $pixData->json() ?? [];
                $pixEmv = (string) ($pd['payload'] ?? '');
            }
        }

        return new CobrancaEmitidaResult(
            cobrancaId: 0,
            gatewayExternalId: $id,
            tipo: $billingType === 'BOLETO' ? 'boleto' : 'pix_cob',
            emitidaEm: new \DateTimeImmutable(),
            linhaDigitavel: $linhaDigitavel,
            codigoBarras: $codigoBarras,
            pixEmv: $pixEmv,
            boletoPdfUrl: $boletoPdfUrl,
            payloadGateway: $data,
        );
    }

    /**
     * Asaas exige customer pre-existente. Cria via externalReference (idempotente).
     */
    private function resolveCustomer(EmitirCobrancaInput $input, PaymentGatewayCredential $cred): string
    {
        $externalRef = 'contact:' . $input->contactId;
        $cpfCnpj = preg_replace('/\D/', '', $input->meta['payer_cpf_cnpj'] ?? '');
        $name = $input->meta['payer_name'] ?? "Pagador {$input->contactId}";
        $email = $input->meta['payer_email'] ?? null;

        // Procura por externalReference primeiro
        $existing = HttpClientFactory::send(fn () => $this->client($cred)->get('/customers', ['externalReference' => $externalRef]));
        if ($existing->successful()) {
            $data = $existing->json() ?? [];
            if (! empty($data['data'][0]['id'])) {
                return (string) $data['data'][0]['id'];
            }
        }

        // Cria novo
        $created = HttpClientFactory::send(fn () => $this->client($cred)->post('/customers', array_filter([
            'name'              => $name,
            'cpfCnpj'           => $cpfCnpj,
            'email'             => $email,
            'externalReference' => $externalRef,
        ])));

        if ($created->failed()) {
            throw new InvalidPayerException(
                "Asaas customer create falhou ({$created->status()}): " . substr($created->body(), 0, 200)
            );
        }

        $newId = (string) ($created->json('id') ?? '');
        if ($newId === '') {
            throw new InvalidPayerException('Asaas customer create retornou sem id');
        }

        return $newId;
    }

    private function assertCredential(object $cred): void
    {
        if (! $cred instanceof PaymentGatewayCredential) {
            throw new CredentialMisconfiguredException(
                'Credential precisa ser PaymentGatewayCredential, recebeu: ' . get_class($cred)
            );
        }
        if ($cred->gateway_key !== 'asaas') {
            throw new CredentialMisconfiguredException(
                "Credential gateway_key='{$cred->gateway_key}' não bate com driver Asaas"
            );
        }
        if (empty($cred->config_json['api_key'])) {
            throw new CredentialMisconfiguredException('Asaas credential precisa api_key em config_json');
        }
    }

    private function baseUrl(PaymentGatewayCredential $cred): string
    {
        return $cred->ambiente === 'sandbox'
            ? self::API_BASE_SANDBOX
            : self::API_BASE_PRODUCTION;
    }

    /**
     * Cliente principal — com retry + 429 handler via HttpClientFactory
     * (Auditoria 2026-05-23 Onda 4e gap #1+#2).
     */
    private function client(PaymentGatewayCredential $cred): PendingRequest
    {
        $config = $cred->config_json ?? [];

        return HttpClientFactory::make(
            baseUrl: $this->baseUrl($cred),
            headers: [
                'access_token' => (string) ($config['api_key'] ?? ''),
                'Accept'       => 'application/json',
            ],
            timeoutSec: 30,
        );
    }

    /**
     * Cliente healthcheck — SEM retry (1 fail = down).
     */
    private function clientHealth(PaymentGatewayCredential $cred): PendingRequest
    {
        $config = $cred->config_json ?? [];

        return HttpClientFactory::make(
            baseUrl: $this->baseUrl($cred),
            headers: [
                'access_token' => (string) ($config['api_key'] ?? ''),
                'Accept'       => 'application/json',
            ],
            timeoutSec: 30,
            withRetry: false,
        );
    }

    private function mapStatus(string $asaasStatus): string
    {
        return match ($asaasStatus) {
            'CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH' => 'paga',
            'PENDING', 'AWAITING_RISK_ANALYSIS'         => 'emitida',
            'OVERDUE'                                   => 'vencida',
            'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE', 'REFUNDED',
            'REFUND_REQUESTED', 'REFUND_IN_PROGRESS'    => 'cancelada',
            default                                     => 'pending',
        };
    }

    private function mapBillingType(string $billingType): ?string
    {
        return match ($billingType) {
            'BOLETO'       => 'boleto',
            'PIX'          => 'pix',
            'CREDIT_CARD'  => 'cartao',
            default        => null,
        };
    }
}
