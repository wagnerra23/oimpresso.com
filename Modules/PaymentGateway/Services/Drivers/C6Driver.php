<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Drivers;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Contracts\PaymentDriverContract;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\CobrancaStatus;
use Modules\PaymentGateway\Dto\DriverHealth;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\DriverNotSupportedException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Driver C6 Bank — Open Banking C6 API.
 *
 * Onda 4b — ADR 0170. C6 historicamente é CNAB-based (boleto via remessa),
 * mas C6 Bank tem API Open Banking PJ (https://developers.c6bank.com.br).
 * Esta impl segue o padrão REST/OAuth2 dela.
 *
 * NOTA: cliente real C6 pode precisar ativar Open Banking PJ antes
 * (suporte C6 conta gerente). Fallback CNAB legacy fica em RecurringBilling
 * via eduardokum/laravel-boleto até deprecação.
 *
 * Onda 4b suporta:
 *   ✓ boleto, pix_cob
 *   ✓ cancelar, consultar, healthCheck, processWebhook
 *   ✗ pix_cobv/recv (não na API atual)
 *   ✗ card (C6 PJ não emite cartão de cobrança)
 *   ✗ refund (CNAB-based via remessa async — Onda 4c se evoluir)
 *
 * Credenciais (config_json):
 *   client_id:     OAuth2 PJ
 *   client_secret: OAuth2 PJ
 *   ambiente:      'sandbox' | 'production'
 *   conta:         conta corrente C6 (FK negócio)
 */
class C6Driver implements PaymentDriverContract
{
    private const API_BASE_PRODUCTION = 'https://baas-api.c6bank.info/api/v1';
    private const API_BASE_SANDBOX = 'https://sandbox-baas-api.c6bank.info/api/v1';

    private array $tokenCache = [];

    public function key(): string
    {
        return 'c6';
    }

    public function supports(string $tipo): bool
    {
        return in_array($tipo, ['boleto', 'pix_cob'], true);
    }

    public function emitirBoleto(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        $this->assertCredential($cred);

        $response = $this->client($cred)
            ->post('/cobrancas', $this->buildBoletoPayload($input));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "C6 boleto falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $externalId = (string) ($data['id'] ?? $data['nossoNumero'] ?? '');
        if ($externalId === '') {
            throw new InvalidPayerException('C6 retornou sem id/nossoNumero');
        }

        return new CobrancaEmitidaResult(
            cobrancaId: 0,
            gatewayExternalId: $externalId,
            tipo: 'boleto',
            emitidaEm: new \DateTimeImmutable(),
            linhaDigitavel: (string) ($data['linhaDigitavel'] ?? '') ?: null,
            codigoBarras: (string) ($data['codigoBarras'] ?? '') ?: null,
            nossoNumero: (string) ($data['nossoNumero'] ?? $externalId) ?: null,
            payloadGateway: $data,
        );
    }

    public function emitirPix(EmitirCobrancaInput $input, object $cred, string $tipo): CobrancaEmitidaResult
    {
        if ($tipo !== 'cob') {
            throw new DriverNotSupportedException("C6 só suporta PIX cob; recebido '{$tipo}'");
        }
        $this->assertCredential($cred);

        $response = $this->client($cred)
            ->post('/pix/cobrancas', $this->buildPixPayload($input));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "C6 PIX falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $externalId = (string) ($data['txid'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            throw new InvalidPayerException('C6 PIX retornou sem txid');
        }

        return new CobrancaEmitidaResult(
            cobrancaId: 0,
            gatewayExternalId: $externalId,
            tipo: 'pix_cob',
            emitidaEm: new \DateTimeImmutable(),
            pixEmv: (string) ($data['emv'] ?? $data['copiaECola'] ?? '') ?: null,
            payloadGateway: $data,
        );
    }

    public function emitirPixAutomatico(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException(
            'C6 não suporta PIX Automático recv. Use bcb_pix driver dedicado (Onda 4d).'
        );
    }

    public function cobrarCartao(EmitirCobrancaInput $input, object $cred, CardToken $token): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException(
            'C6 PJ não emite cartão de cobrança. Use Asaas.'
        );
    }

    public function cancelar(object $cobranca, object $cred, string $motivo): void
    {
        $this->assertCredential($cred);
        $extId = (string) ($cobranca->gateway_external_id ?? '');
        if ($extId === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra cancelar no C6');
        }

        $response = $this->client($cred)
            ->delete("/cobrancas/{$extId}", ['motivo' => $motivo]);

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "C6 cancelar falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }
    }

    public function refund(object $cobranca, object $cred, ?int $valorCentavos, string $motivo): void
    {
        throw new DriverNotSupportedException(
            'C6 refund não suportado nesta onda. Evolução condicional Onda 4c se cliente real demandar.'
        );
    }

    public function consultar(object $cobranca, object $cred): CobrancaStatus
    {
        $this->assertCredential($cred);
        $extId = (string) ($cobranca->gateway_external_id ?? '');
        if ($extId === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra consultar no C6');
        }

        $response = $this->client($cred)->get("/cobrancas/{$extId}");
        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "C6 consultar falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $status = (string) ($data['status'] ?? $data['situacao'] ?? '');

        return new CobrancaStatus(
            status: $this->mapStatus($status),
            pagaEm: ! empty($data['dataPagamento']) ? new \DateTimeImmutable($data['dataPagamento']) : null,
            valorPagoCentavos: isset($data['valorPago']) ? (int) round((float) $data['valorPago'] * 100) : null,
            formaPagamento: $status === 'PAGO' ? 'boleto' : null,
            payloadGateway: $data,
        );
    }

    public function healthCheck(object $cred): DriverHealth
    {
        $this->assertCredential($cred);
        $start = microtime(true);

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post($this->baseUrl($cred) . '/oauth/token', [
                    'client_id'     => $cred->config_json['client_id'] ?? '',
                    'client_secret' => $cred->config_json['client_secret'] ?? '',
                    'grant_type'    => 'client_credentials',
                ]);

            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful() && ! empty($response->json('access_token'))) {
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
                errorMessage: "C6 OAuth failed ({$response->status()})",
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
        $extId = (string) ($payload['transactionId'] ?? $payload['id'] ?? '');
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

    private function assertCredential(object $cred): void
    {
        if (! $cred instanceof PaymentGatewayCredential) {
            throw new CredentialMisconfiguredException(
                'Credential precisa ser PaymentGatewayCredential, recebeu: ' . get_class($cred)
            );
        }
        if ($cred->gateway_key !== 'c6') {
            throw new CredentialMisconfiguredException(
                "Credential gateway_key='{$cred->gateway_key}' não bate com driver C6"
            );
        }
        if (empty($cred->config_json['client_id']) || empty($cred->config_json['client_secret'])) {
            throw new CredentialMisconfiguredException(
                'C6 credential precisa client_id + client_secret em config_json'
            );
        }
    }

    private function baseUrl(PaymentGatewayCredential $cred): string
    {
        return $cred->ambiente === 'sandbox'
            ? self::API_BASE_SANDBOX
            : self::API_BASE_PRODUCTION;
    }

    private function client(PaymentGatewayCredential $cred): PendingRequest
    {
        return Http::baseUrl($this->baseUrl($cred))
            ->withToken($this->getAccessToken($cred))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    private function getAccessToken(PaymentGatewayCredential $cred): string
    {
        $key = $cred->id . ':' . $cred->ambiente;
        if (isset($this->tokenCache[$key])) {
            return $this->tokenCache[$key];
        }

        $config = $cred->config_json ?? [];
        $response = Http::asForm()
            ->timeout(10)
            ->post($this->baseUrl($cred) . '/oauth/token', [
                'client_id'     => $config['client_id'] ?? '',
                'client_secret' => $config['client_secret'] ?? '',
                'grant_type'    => 'client_credentials',
            ]);

        if (! $response->successful() || empty($response->json('access_token'))) {
            throw new CredentialMisconfiguredException(
                "C6 OAuth falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        return $this->tokenCache[$key] = (string) $response->json('access_token');
    }

    private function buildBoletoPayload(EmitirCobrancaInput $input): array
    {
        return [
            'tipo'         => 'BOLETO',
            'seuNumero'    => substr($input->idempotencyKey, 0, 15),
            'valor'        => round($input->valorCentavos / 100, 2),
            'vencimento'   => Carbon::instance($input->vencimento)->toDateString(),
            'descricao'    => substr($input->descricao, 0, 80),
            'pagador'      => [
                'cpfCnpj' => preg_replace('/\D/', '', $input->meta['payer_cpf_cnpj'] ?? ''),
                'nome'    => $input->meta['payer_name'] ?? 'Pagador',
                'email'   => $input->meta['payer_email'] ?? null,
            ],
        ];
    }

    private function buildPixPayload(EmitirCobrancaInput $input): array
    {
        return [
            'calendario' => [
                'expiracao' => 3600, // 1h
            ],
            'devedor' => [
                'cpf'  => preg_replace('/\D/', '', $input->meta['payer_cpf_cnpj'] ?? ''),
                'nome' => $input->meta['payer_name'] ?? 'Pagador',
            ],
            'valor' => [
                'original' => number_format($input->valorCentavos / 100, 2, '.', ''),
            ],
            'chave'             => $input->meta['pix_key'] ?? '',
            'solicitacaoPagador' => substr($input->descricao, 0, 80),
            'externalReference' => $input->idempotencyKey,
        ];
    }

    private function mapStatus(string $c6Status): string
    {
        return match (strtoupper($c6Status)) {
            'PAGO', 'CONFIRMED', 'LIQUIDADO'         => 'paga',
            'EMITIDO', 'A_RECEBER', 'PENDING'        => 'emitida',
            'VENCIDO', 'OVERDUE'                     => 'vencida',
            'CANCELADO', 'CANCELED', 'EXPIRADO'      => 'cancelada',
            default                                  => 'pending',
        };
    }
}
