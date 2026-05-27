<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Drivers;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
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
use Modules\PaymentGateway\Services\HttpClientFactory;

/**
 * Driver Sicoob — API Cobrança Bancária v3 (REST + OAuth2 + mTLS).
 *
 * US-FIN-044 — Onda 4f.sicoob_api. Sinal qualificado: biz=4 ROTA LIVRE
 * (Larissa via Kamila) pediu 2026-05-27 [ADR 0105].
 *
 * PR2 (este) — OAuth2 client_credentials + emitirBoleto + cancelar + consultar
 * + healthCheck. mTLS handshake real fica pra PR3 (mTLS bypass em test
 * via Http::fake — funciona). Webhook em PR4.
 *
 * URLs (Sicoob 2026):
 *   - Token (Keycloak): https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token
 *   - API prod:         https://api.sicoob.com.br/cobranca-bancaria/v3
 *   - API sandbox:      https://sandbox.sicoob.com.br/sicoob/sandbox/cobranca-bancaria/v3
 *
 * Particularidades vs InterDriver:
 *   - Sicoob exige header `client_id` em TODAS as chamadas (não só no token)
 *   - Token cache em Laravel Cache (Redis-safe, multi-process) — Inter usa
 *     in-memory que perde token entre requests
 *   - Scopes Sicoob são granulares: boletos_inclusao + boletos_consulta +
 *     boletos_alteracao + webhooks_inclusao + webhooks_consulta
 *   - consultar usa query string composta (numeroCliente + codigoModalidade +
 *     nossoNumero), não path /boletos/{nn}
 *
 * Em prod, mTLS configurado via $cred->mtls_pfx_path + senha cifrada em
 * config_json['mtls_pfx_password']. Em test (Http::fake) bypass automático.
 *
 * Refs:
 *   - https://developers.sicoob.com.br/portal/apis (docs oficiais)
 *   - https://api.sicoob.com.br/cobranca-bancaria/v3 (base prod)
 *   - ADR 0170-bancos-nativos-top5-drivers-separados §4f.sicoob_api
 *   - memory/sessions/2026-05-27-sicoob-api-credenciais-pedido.md
 */
class SicoobApiDriver implements PaymentDriverContract
{
    private const API_BASE_PRODUCTION = 'https://api.sicoob.com.br/cobranca-bancaria/v3';

    private const API_BASE_SANDBOX = 'https://sandbox.sicoob.com.br/sicoob/sandbox/cobranca-bancaria/v3';

    private const OAUTH_URL_PRODUCTION = 'https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token';

    private const OAUTH_URL_SANDBOX = 'https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token';

    /**
     * Scopes mínimos pra cobrança REST v3. Webhook scopes chegam no PR4.
     */
    private const SCOPES = 'boletos_inclusao boletos_consulta boletos_alteracao';

    public function key(): string
    {
        return 'sicoob_api';
    }

    public function supports(string $tipo): bool
    {
        return in_array($tipo, ['boleto', 'pix_cob'], true);
    }

    public function emitirBoleto(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        $this->assertCredential($cred);
        $config = $this->config($cred);

        $payload = $this->buildEmitirBoletoPayload($input, $config);

        $response = HttpClientFactory::send(fn () => $this->client($cred)
            ->post('/boletos', [$payload]));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Sicoob API falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];

        // Sicoob retorna `resultado: [{ status: 200, boleto: {...} }]` quando
        // sucesso em batch; ou objeto direto quando 1 boleto só.
        $boleto = $data['resultado'][0]['boleto'] ?? $data['boleto'] ?? $data;
        $nossoNumero = (string) ($boleto['nossoNumero'] ?? '');
        $linhaDigitavel = (string) ($boleto['linhaDigitavel'] ?? '');
        $codigoBarras = (string) ($boleto['codigoBarras'] ?? '');

        if ($nossoNumero === '') {
            throw new InvalidPayerException('Sicoob retornou sem nossoNumero — payload provavelmente incompleto');
        }

        return new CobrancaEmitidaResult(
            cobrancaId: 0,
            gatewayExternalId: $nossoNumero,
            tipo: 'boleto',
            emitidaEm: new \DateTimeImmutable(),
            linhaDigitavel: $linhaDigitavel ?: null,
            codigoBarras: $codigoBarras ?: null,
            nossoNumero: $nossoNumero,
            payloadGateway: $data,
        );
    }

    public function emitirPix(EmitirCobrancaInput $input, object $cred, string $tipo): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException(
            'Sicoob PIX cob chega em onda futura. Use sicoob_cnab OU bcb_pix driver.'
        );
    }

    public function emitirPixAutomatico(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException(
            'Sicoob PIX Automático não suportado nesta API. Use bcb_pix driver (regulado BCB).'
        );
    }

    public function cobrarCartao(EmitirCobrancaInput $input, object $cred, CardToken $token): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException('Sicoob não emite cartão via API Cobrança. Use Asaas/Pagar.me.');
    }

    public function cancelar(object $cobranca, object $cred, string $motivo): void
    {
        $this->assertCredential($cred);
        $config = $this->config($cred);
        $nossoNumero = (string) ($cobranca->gateway_external_id ?? '');

        if ($nossoNumero === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra cancelar no Sicoob');
        }

        $payload = [
            'numeroCliente'    => (int) ($config['numero_cliente'] ?? $config['convenio'] ?? 0),
            'codigoModalidade' => (int) ($config['codigo_modalidade'] ?? $config['carteira'] ?? 1),
            'nossoNumero'      => (int) $nossoNumero,
            'codigoBaixa'      => $this->mapMotivoCancelar($motivo),
        ];

        $response = HttpClientFactory::send(fn () => $this->client($cred)
            ->patch('/boletos/baixa', $payload));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Sicoob cancelar falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }
    }

    public function refund(object $cobranca, object $cred, ?int $valorCentavos, string $motivo): void
    {
        throw new DriverNotSupportedException(
            'Sicoob refund de boleto não suportado via API. TED reverso operado manualmente ' .
            'pelo titular da conta cooperativada.'
        );
    }

    public function consultar(object $cobranca, object $cred): CobrancaStatus
    {
        $this->assertCredential($cred);
        $config = $this->config($cred);
        $nossoNumero = (string) ($cobranca->gateway_external_id ?? '');

        if ($nossoNumero === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra consultar no Sicoob');
        }

        $query = [
            'numeroCliente'    => (int) ($config['numero_cliente'] ?? $config['convenio'] ?? 0),
            'codigoModalidade' => (int) ($config['codigo_modalidade'] ?? $config['carteira'] ?? 1),
            'nossoNumero'      => (int) $nossoNumero,
        ];

        $response = HttpClientFactory::send(fn () => $this->client($cred)->get('/boletos', $query));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Sicoob consultar falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $boleto = $data['resultado'][0]['boleto'] ?? $data['boleto'] ?? $data;
        $situacao = (string) ($boleto['situacaoBoleto'] ?? $boleto['situacao'] ?? 'PENDING');
        $valorPago = isset($boleto['valorRecebido'])
            ? (int) round((float) $boleto['valorRecebido'] * 100)
            : null;
        $pagaEm = ! empty($boleto['dataLiquidacao'])
            ? new \DateTimeImmutable((string) $boleto['dataLiquidacao'])
            : null;

        return new CobrancaStatus(
            status: $this->mapSituacao($situacao),
            pagaEm: $pagaEm,
            valorPagoCentavos: $valorPago,
            formaPagamento: $pagaEm ? 'boleto' : null,
            payloadGateway: $data,
        );
    }

    public function healthCheck(object $cred): DriverHealth
    {
        $this->assertCredential($cred);
        $start = microtime(true);

        try {
            // OAuth handshake = boa prova de saúde (token + mTLS + DNS + TLS).
            $this->getAccessToken($cred, forceRefresh: true);
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            return new DriverHealth(
                ok: true,
                status: $latencyMs > 3000 ? 'degraded' : 'ok',
                latencyMs: $latencyMs,
                checkedAt: new \DateTimeImmutable(),
            );
        } catch (\Throwable $e) {
            return new DriverHealth(
                ok: false,
                status: 'down',
                latencyMs: (int) round((microtime(true) - $start) * 1000),
                checkedAt: new \DateTimeImmutable(),
                errorMessage: substr($e->getMessage(), 0, 200),
            );
        }
    }

    public function processWebhook(array $payload, object $cred): ?object
    {
        // PR4 implementa HMAC + dispatch real. PR2 deixa só mapping superficial
        // pra Onda 3 (gateway_webhook_events) não quebrar se chegar payload
        // inesperado.
        $nossoNumero = (string) ($payload['nossoNumero'] ?? $payload['boleto']['nossoNumero'] ?? '');
        if ($nossoNumero === '') {
            return null;
        }

        return (object) [
            'gateway_external_id' => $nossoNumero,
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
        if ($cred->gateway_key !== 'sicoob_api') {
            throw new CredentialMisconfiguredException(
                "Credential gateway_key='{$cred->gateway_key}' não bate com driver Sicoob API"
            );
        }
        $config = $cred->config_json ?? [];
        if (empty($config['client_id']) || empty($config['client_secret'])) {
            throw new CredentialMisconfiguredException(
                'Sicoob API credential precisa client_id + client_secret em config_json'
            );
        }
        // Convênio (numero_cliente) é o código cedente Sicoob — sem ele não
        // emite boleto. Carteira (codigo_modalidade) tem default 1 (Simples).
        if (empty($config['numero_cliente']) && empty($config['convenio'])) {
            throw new CredentialMisconfiguredException(
                'Sicoob API credential precisa numero_cliente (convênio/código cedente)'
            );
        }
    }

    private function config(PaymentGatewayCredential $cred): array
    {
        return $cred->config_json ?? [];
    }

    private function baseUrl(PaymentGatewayCredential $cred): string
    {
        return $cred->ambiente === 'sandbox'
            ? self::API_BASE_SANDBOX
            : self::API_BASE_PRODUCTION;
    }

    private function oauthUrl(PaymentGatewayCredential $cred): string
    {
        return $cred->ambiente === 'sandbox'
            ? self::OAUTH_URL_SANDBOX
            : self::OAUTH_URL_PRODUCTION;
    }

    /**
     * Cliente HTTP com Bearer + client_id header + mTLS options.
     * mTLS handshake real chega no PR3.
     */
    private function client(PaymentGatewayCredential $cred): PendingRequest
    {
        $config = $this->config($cred);

        return HttpClientFactory::make(
            baseUrl: $this->baseUrl($cred),
            timeoutSec: 30,
        )
            ->withToken($this->getAccessToken($cred))
            ->withHeaders([
                'client_id'    => (string) ($config['client_id'] ?? ''),
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->withOptions($this->mtlsOptions($config));
    }

    /**
     * mTLS — placeholder PR2. Handshake real (.pfx + senha cifrada) chega
     * no PR3. Em test (Http::fake) mTLS é bypass automático.
     */
    private function mtlsOptions(array $config): array
    {
        // PR3 vai popular ['cert' => $pfxPath, 'ssl_key' => [$pfxPath, $password]]
        // ou via curl options. Mantém vazio aqui — Http::fake nos tests não
        // precisa de cert real.
        return [];
    }

    /**
     * OAuth2 client_credentials com cache Laravel (Redis-safe, multi-process).
     *
     * Cache key por business_id + ambiente + hash do client_id pra evitar
     * vazamento entre tenants (multi-tenant Tier 0). TTL 3500s (margem antes
     * do Sicoob expirar em 3600s).
     */
    private function getAccessToken(PaymentGatewayCredential $cred, bool $forceRefresh = false): string
    {
        $config = $this->config($cred);
        $clientIdHash = substr(hash('sha256', (string) ($config['client_id'] ?? '')), 0, 12);
        $cacheKey = "sicoob_api:token:{$cred->business_id}:{$cred->ambiente}:{$clientIdHash}";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addSeconds(3500), function () use ($cred, $config) {
            $response = Http::asForm()
                ->withOptions($this->mtlsOptions($config))
                ->withHeaders(['Accept' => 'application/json'])
                ->timeout(10)
                ->post($this->oauthUrl($cred), [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $config['client_id'] ?? '',
                    'client_secret' => $config['client_secret'] ?? '',
                    'scope'         => self::SCOPES,
                ]);

            if (! $response->successful() || empty($response->json('access_token'))) {
                throw new CredentialMisconfiguredException(
                    "Sicoob OAuth falhou ({$response->status()}): " . substr($response->body(), 0, 200)
                );
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * Monta payload boleto v3 (Sicoob exige campos cooperativa-específicos
     * vs Inter: numeroCliente=convênio, codigoModalidade=carteira).
     */
    private function buildEmitirBoletoPayload(EmitirCobrancaInput $input, array $config): array
    {
        $valorReais = round($input->valorCentavos / 100, 2);

        return [
            'numeroCliente'                   => (int) ($config['numero_cliente'] ?? $config['convenio'] ?? 0),
            'codigoModalidade'                => (int) ($config['codigo_modalidade'] ?? $config['carteira'] ?? 1),
            'numeroContaCorrente'             => (int) ($config['numero_conta'] ?? $config['conta'] ?? 0),
            'codigoEspecieDocumento'          => (string) ($config['especie_documento'] ?? 'DM'),
            'dataEmissao'                     => Carbon::now()->toIso8601String(),
            'seuNumero'                       => substr($input->idempotencyKey, 0, 15),
            'identificacaoBoletoEmpresa'      => substr($input->idempotencyKey, 0, 25),
            'identificacaoEmissaoBoleto'      => 2, // 2 = banco emite
            'identificacaoDistribuicaoBoleto' => 2, // 2 = banco distribui
            'valor'                           => $valorReais,
            'dataVencimento'                  => Carbon::instance($input->vencimento)->toIso8601String(),
            'numeroDiasLimiteRecebimento'     => $input->meta['dias_baixa'] ?? 30,
            'pagador' => [
                'numeroCpfCnpj' => preg_replace('/\D/', '', $input->meta['payer_cpf_cnpj'] ?? ''),
                'nome'          => $input->meta['payer_name'] ?? 'Pagador',
                'endereco'      => $input->meta['payer_address'] ?? 'Não informado',
                'bairro'        => $input->meta['payer_district'] ?? 'Centro',
                'cidade'        => $input->meta['payer_city'] ?? 'São Paulo',
                'cep'           => preg_replace('/\D/', '', $input->meta['payer_cep'] ?? '01000000'),
                'uf'            => $input->meta['payer_uf'] ?? 'SP',
                'email'         => array_filter([$input->meta['payer_email'] ?? null]),
            ],
            'mensagensInstrucao' => array_values(array_filter([
                substr($input->descricao, 0, 80) ?: null,
                $input->instrucoesPagador ?? null,
            ])),
        ];
    }

    /**
     * Sicoob codigoBaixa (PATCH /boletos/baixa):
     *   1 = Comandar baixa (motivo livre). Outros códigos chegam futuro.
     */
    private function mapMotivoCancelar(string $motivo): int
    {
        return 1;
    }

    /**
     * Sicoob situacaoBoleto → status canon oimpresso.
     *
     * Valores Sicoob v3: EM_ABERTO, BAIXADO, LIQUIDADO, PROTESTADO,
     * EM_PROTESTO, NEGATIVADO, EM_NEGATIVACAO, REGISTRADO.
     */
    private function mapSituacao(string $sicoobStatus): string
    {
        return match (strtoupper($sicoobStatus)) {
            'LIQUIDADO'                    => 'paga',
            'EM_ABERTO', 'REGISTRADO'      => 'emitida',
            'PROTESTADO', 'EM_PROTESTO'    => 'vencida',
            'NEGATIVADO', 'EM_NEGATIVACAO' => 'vencida',
            'BAIXADO'                      => 'cancelada',
            default                        => 'pending',
        };
    }
}
