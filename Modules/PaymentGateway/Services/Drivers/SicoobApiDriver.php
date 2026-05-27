<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Drivers;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\NfeBrasil\Services\CertificadoService;
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
use RuntimeException;

/**
 * Driver Sicoob — API Cobrança Bancária v3 (REST + OAuth2 + mTLS).
 *
 * US-FIN-044 — Onda 4f.sicoob_api. Sinal qualificado: Martinho Caçambas
 * (biz=164) — Kamila (Admin#164) pediu 2026-05-27 [ADR 0105]. NÃO confundir
 * com ROTA LIVRE (biz=4 Larissa vestuário) — ROTA LIVRE não usa Sicoob.
 *
 * US-FIN-046 (refactor 2026-05-27) — mTLS REUSA o NfeCertificado do business
 * em vez de upload duplicado. Sicoob API exige cert ICP-Brasil A1 do CNPJ
 * da empresa, EXATAMENTE o mesmo cert que NfeBrasil já gerencia pra SEFAZ
 * (encrypt-at-rest via Crypt, validação CNPJ Subject CN, audit OTel).
 *
 * Single source of truth: cliente upload UMA vez em /fiscal/configuracao/certificado.
 *
 * Acoplamento cross-module Sicoob → NfeBrasil é débito aceitável até 2º
 * banco API entrar (Bradesco/Inter/BB). Aí extrai NfeCertificado pra
 * módulo neutro (US-FIN-047 backlog).
 *
 * URLs (Sicoob 2026):
 *   - Token (Keycloak): https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token
 *   - API prod:         https://api.sicoob.com.br/cobranca-bancaria/v3
 *   - API sandbox:      https://sandbox.sicoob.com.br/sicoob/sandbox/cobranca-bancaria/v3
 *
 * Refs:
 *   - https://developers.sicoob.com.br/portal/apis
 *   - ADR 0170-bancos-nativos-top5-drivers-separados §4f.sicoob_api
 *   - Modules/NfeBrasil/Services/CertificadoService (canon a reusar)
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

    public function __construct(
        private readonly CertificadoService $certificadoService,
    ) {}

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
            ->withOptions($this->mtlsOptions($cred));
    }

    /**
     * mTLS options pra Guzzle — REUSA NfeCertificado do business (US-FIN-046).
     *
     * Sicoob exige cert ICP-Brasil A1 do CNPJ da empresa — mesmo cert que
     * já vive em `nfe_certificados` encrypted-at-rest. Reusamos via
     * `CertificadoService::carregarParaSefaz()` (canon).
     *
     * Implementação:
     *   1. Carrega .pfx binary + senha via CertificadoService (decrypt em memória)
     *   2. Escreve .pfx temp em sys_get_temp_dir() pra Guzzle/curl (mTLS exige file)
     *   3. register_shutdown_function pra unlink ao final do request
     *   4. Retorna ['cert' => [$tempPath, $senha]] — Guzzle 7 propaga pra curl
     *
     * Segurança:
     *   - Temp file fica em sys_get_temp_dir() com prefix `sicoob-pfx-` e
     *     basename random via tempnam — atacante local não consegue prever path
     *   - Conteúdo no temp é o .pfx (PKCS12 cifrado pela própria senha) — atacante
     *     que pegue o arquivo ainda precisa da senha pra abrir
     *   - unlink no shutdown garante cleanup mesmo em exception
     *
     * Em test (Http::fake) mTLS é bypass — caller NÃO precisa NfeCertificado ativo
     * se o driver não fizer chamada real. Mas chamadas reais (emitirBoleto via
     * Http::fake response) ainda invocam mtlsOptions → carregarParaSefaz.
     */
    private function mtlsOptions(PaymentGatewayCredential $cred): array
    {
        try {
            $cert = $this->certificadoService->carregarParaSefaz($cred->business_id);
        } catch (RuntimeException $e) {
            throw new CredentialMisconfiguredException(
                'Sicoob API exige certificado A1 ICP-Brasil cadastrado em ' .
                '/fiscal/configuracao/certificado — mesmo cert usado pra NFe SEFAZ. ' .
                "Erro original: {$e->getMessage()}"
            );
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'sicoob-pfx-');
        if ($tempPath === false) {
            throw new CredentialMisconfiguredException(
                'Falha ao criar arquivo temporário pro .pfx Sicoob (sys_get_temp_dir não writable)'
            );
        }

        file_put_contents($tempPath, $cert['pfx_binary']);
        @chmod($tempPath, 0600);

        // Cleanup garantido — unlink quando request termina (sucesso OU exception).
        register_shutdown_function(static function () use ($tempPath): void {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        });

        return [
            'cert' => [$tempPath, $cert['senha']],
        ];
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
                ->withOptions($this->mtlsOptions($cred))
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
            'identificacaoEmissaoBoleto'      => 2,
            'identificacaoDistribuicaoBoleto' => 2,
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

    private function mapMotivoCancelar(string $motivo): int
    {
        return 1;
    }

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
