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
use Modules\PaymentGateway\Services\HttpClientFactory;

/**
 * Driver Banco Inter — API Cobrança v3 (OAuth2 + mTLS em prod).
 *
 * Onda 4a — ADR 0170. Implementa subset funcional do PaymentDriverContract:
 *   ✓ key, supports
 *   ✓ emitirBoleto (POST /cobranca/v3/cobrancas)
 *   ✓ cancelar (POST /cobranca/v3/cobrancas/{nossoNumero}/cancelar)
 *   ✓ consultar (GET /cobranca/v3/cobrancas/{nossoNumero})
 *   ✓ healthCheck (POST /oauth/v2/token)
 *   ✓ processWebhook (parse payload + map pra Cobranca)
 *
 * NÃO suportado nesta onda:
 *   ✗ emitirPix / emitirPixAutomatico (Onda 4b — Inter PIX cob)
 *   ✗ cobrarCartao (Inter não suporta — DriverNotSupportedException sempre)
 *   ✗ refund (Onda 4c — Inter parcial)
 *
 * Em prod, mTLS é configurado via cert path em $cred->config_json
 * ('certificado_crt', 'certificado_key'). Em test (Http::fake) bypass.
 */
class InterDriver implements PaymentDriverContract
{
    private const API_BASE_PRODUCTION = 'https://cdpj.partners.bancointer.com.br';
    private const API_BASE_SANDBOX = 'https://cdpj-sandbox.partners.uatinter.co';

    public function key(): string
    {
        return 'inter';
    }

    public function supports(string $tipo): bool
    {
        return in_array($tipo, ['boleto', 'pix_cob', 'pix_cobv'], true);
    }

    public function emitirBoleto(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        $this->assertCredential($cred);
        $config = $this->config($cred);

        $payload = $this->buildEmitirBoletoPayload($input, $config);

        $response = HttpClientFactory::send(fn () => $this->client($cred)
            ->post('/cobranca/v3/cobrancas', $payload));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Inter API falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $nossoNumero = (string) ($data['nossoNumero'] ?? $data['codigoSolicitacao'] ?? '');

        if ($nossoNumero === '') {
            throw new InvalidPayerException('Inter API retornou sem nossoNumero — payload incompleto');
        }

        return new CobrancaEmitidaResult(
            cobrancaId: 0, // será setado pelo PaymentGatewayService após persistência
            gatewayExternalId: $nossoNumero,
            tipo: 'boleto',
            emitidaEm: new \DateTimeImmutable(),
            linhaDigitavel: (string) ($data['linhaDigitavel'] ?? '') ?: null,
            codigoBarras: (string) ($data['codigoBarras'] ?? '') ?: null,
            nossoNumero: $nossoNumero,
            payloadGateway: $data,
        );
    }

    /**
     * Inter PIX cobrança via API Pix v2.
     *
     * Onda 4b: tipo 'cob' (imediata).
     * Onda 4c: tipo 'cobv' (com vencimento) via PUT /pix/v2/cobv/{txid}.
     *
     * Diferenças:
     *   - cob:  calendario.expiracao em segundos (TTL curto, ex 1h)
     *   - cobv: calendario.dataDeVencimento + validadeAposVencimento (dias)
     */
    public function emitirPix(EmitirCobrancaInput $input, object $cred, string $tipo): CobrancaEmitidaResult
    {
        if (! in_array($tipo, ['cob', 'cobv'], true)) {
            throw new DriverNotSupportedException(
                "Inter PIX suporta 'cob' ou 'cobv', recebido '{$tipo}'"
            );
        }
        $this->assertCredential($cred);

        $pixKey = $input->meta['pix_key'] ?? null;
        if ($pixKey === null) {
            throw new InvalidPayerException("Inter PIX {$tipo} exige meta.pix_key (chave PIX do beneficiário)");
        }

        $payload = [
            'devedor' => array_filter([
                'cpf'  => preg_replace('/\D/', '', $input->meta['payer_cpf_cnpj'] ?? '') ?: null,
                'nome' => $input->meta['payer_name'] ?? null,
            ]),
            'valor' => [
                'original' => number_format($input->valorCentavos / 100, 2, '.', ''),
            ],
            'chave'             => $pixKey,
            'solicitacaoPagador' => substr($input->descricao, 0, 140),
        ];

        if ($tipo === 'cob') {
            $payload['calendario'] = ['expiracao' => 3600]; // 1h padrão
        } else { // cobv
            $payload['calendario'] = [
                'dataDeVencimento'         => Carbon::instance($input->vencimento)->toDateString(),
                'validadeAposVencimento'   => $input->meta['validade_apos_vencimento'] ?? 30,
            ];
        }

        $endpoint = "/pix/v2/{$tipo}/{$input->idempotencyKey}";
        $response = HttpClientFactory::send(fn () => $this->client($cred)->put($endpoint, $payload));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Inter PIX {$tipo} falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $txid = (string) ($data['txid'] ?? $input->idempotencyKey);
        $emv = (string) ($data['pixCopiaECola'] ?? '');

        if ($emv === '') {
            throw new InvalidPayerException("Inter PIX {$tipo} retornou sem pixCopiaECola");
        }

        return new CobrancaEmitidaResult(
            cobrancaId: 0,
            gatewayExternalId: $txid,
            tipo: $tipo === 'cob' ? 'pix_cob' : 'pix_cobv',
            emitidaEm: new \DateTimeImmutable(),
            pixEmv: $emv,
            payloadGateway: $data,
        );
    }

    public function emitirPixAutomatico(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException(
            'Inter PIX Automático chega em Onda 4b. Use bcb_pix driver dedicado (Onda 4d).'
        );
    }

    public function cobrarCartao(EmitirCobrancaInput $input, object $cred, CardToken $token): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException('Inter não emite cartão. Use Asaas.');
    }

    public function cancelar(object $cobranca, object $cred, string $motivo): void
    {
        $this->assertCredential($cred);
        $nossoNumero = (string) ($cobranca->gateway_external_id ?? '');
        if ($nossoNumero === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra cancelar no Inter');
        }

        $response = HttpClientFactory::send(fn () => $this->client($cred)
            ->post("/cobranca/v3/cobrancas/{$nossoNumero}/cancelar", [
                'motivoCancelamento' => $this->mapMotivoCancelar($motivo),
            ]));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Inter cancelar falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }
    }

    /**
     * Inter refund — Onda 4c.
     *
     * **PIX cob/cobv:** suportado via POST /pix/v2/cob/{txid}/devolucao/{idDev}
     * (BCB padrão Pix devolução). Valor opcional pra refund parcial.
     *
     * **Boleto:** NÃO suportado via API Inter — banco exige estorno via PIX
     * recebimento (TED reverso operado pelo operador da conta). Throw
     * DriverNotSupportedException com mensagem clara.
     *
     * O tipo é inferido pelo cobranca->tipo (preenchido pelo Service quando
     * criou a Cobranca). Se ausente, throw.
     */
    public function refund(object $cobranca, object $cred, ?int $valorCentavos, string $motivo): void
    {
        $this->assertCredential($cred);
        $tipo = (string) ($cobranca->tipo ?? '');
        $extId = (string) ($cobranca->gateway_external_id ?? '');

        if ($extId === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra refund no Inter');
        }

        if (! in_array($tipo, ['pix_cob', 'pix_cobv'], true)) {
            throw new DriverNotSupportedException(
                "Inter refund só funciona pra PIX (cob/cobv). Tipo recebido '{$tipo}'. " .
                'Refund de boleto Inter precisa ser feito manualmente via PIX recebimento ' .
                '(TED reverso operado pelo titular da conta).'
            );
        }

        $idDevolucao = (string) ($cobranca->refund_idempotency_key ?? 'devolucao-' . substr(md5($extId . microtime()), 0, 20));
        $valorReais = $valorCentavos !== null
            ? number_format($valorCentavos / 100, 2, '.', '')
            : null;

        $payload = ['valor' => $valorReais ?? '0.00'];
        if ($motivo !== '') {
            $payload['descricao'] = substr($motivo, 0, 140);
        }

        $response = HttpClientFactory::send(fn () => $this->client($cred)
            ->put("/pix/v2/cob/{$extId}/devolucao/{$idDevolucao}", $payload));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Inter PIX devolução falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }
    }

    public function consultar(object $cobranca, object $cred): CobrancaStatus
    {
        $this->assertCredential($cred);
        $nossoNumero = (string) ($cobranca->gateway_external_id ?? '');
        if ($nossoNumero === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra consultar no Inter');
        }

        $response = HttpClientFactory::send(fn () => $this->client($cred)
            ->get("/cobranca/v3/cobrancas/{$nossoNumero}"));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "Inter consultar falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $situacao = (string) ($data['cobranca']['situacao'] ?? $data['situacao'] ?? 'PENDING');
        $valorPago = isset($data['cobranca']['valorTotalRecebimento'])
            ? (int) round((float) $data['cobranca']['valorTotalRecebimento'] * 100)
            : null;
        $pagaEm = ! empty($data['cobranca']['dataHoraSituacao'])
            ? new \DateTimeImmutable((string) $data['cobranca']['dataHoraSituacao'])
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
        $config = $this->config($cred);
        $start = microtime(true);

        try {
            $response = Http::asForm()
                ->withOptions($this->mtlsOptions($config))
                ->timeout(10)
                ->post($this->baseUrl($cred) . '/oauth/v2/token', [
                    'client_id'     => $config['client_id'] ?? '',
                    'client_secret' => $config['client_secret'] ?? '',
                    'scope'         => 'boleto-cobranca.read boleto-cobranca.write',
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
                errorMessage: "OAuth failed ({$response->status()}): " . substr($response->body(), 0, 120),
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
        // Onda 3 já persistiu o webhook em gateway_webhook_events.
        // Onda 4a apenas mapeia payload pro shape de Cobranca esperado
        // pelo dispatcher de eventos (Onda 4d-final).
        $nossoNumero = (string) ($payload['nossoNumero'] ?? $payload['cobranca']['nossoNumero'] ?? '');
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
        if ($cred->gateway_key !== 'inter') {
            throw new CredentialMisconfiguredException(
                "Credential gateway_key='{$cred->gateway_key}' não bate com driver Inter"
            );
        }
        if (empty($cred->config_json['client_id']) || empty($cred->config_json['client_secret'])) {
            throw new CredentialMisconfiguredException(
                'Inter credential precisa client_id + client_secret em config_json'
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

    /**
     * Cliente principal — com retry + 429 handler via HttpClientFactory
     * (Auditoria 2026-05-23 Onda 4e gap #1+#2). mTLS preservado via withOptions.
     */
    private function client(PaymentGatewayCredential $cred): PendingRequest
    {
        $config = $this->config($cred);

        return HttpClientFactory::make(
            baseUrl: $this->baseUrl($cred),
            timeoutSec: 30,
        )
            ->withToken($this->getAccessToken($cred))
            ->withOptions($this->mtlsOptions($config));
    }

    /**
     * mTLS — em prod usa cert files; em test (Http::fake) mTLS é bypass.
     */
    private function mtlsOptions(array $config): array
    {
        $opts = [];
        if (! empty($config['certificado_crt']) && is_file($config['certificado_crt'])) {
            $opts['cert'] = $config['certificado_crt'];
        }
        if (! empty($config['certificado_key']) && is_file($config['certificado_key'])) {
            $opts['ssl_key'] = $config['certificado_key'];
        }

        return $opts;
    }

    /**
     * Token cache simples in-memory per credential (Onda 4 — não persiste em DB).
     * Em prod, usar Redis cache com TTL = expires_in.
     */
    private array $tokenCache = [];

    private function getAccessToken(PaymentGatewayCredential $cred): string
    {
        $key = $cred->id . ':' . $cred->ambiente;

        if (isset($this->tokenCache[$key])) {
            return $this->tokenCache[$key];
        }

        $config = $this->config($cred);
        $response = Http::asForm()
            ->withOptions($this->mtlsOptions($config))
            ->timeout(10)
            ->post($this->baseUrl($cred) . '/oauth/v2/token', [
                'client_id'     => $config['client_id'] ?? '',
                'client_secret' => $config['client_secret'] ?? '',
                'scope'         => 'boleto-cobranca.read boleto-cobranca.write',
                'grant_type'    => 'client_credentials',
            ]);

        if (! $response->successful() || empty($response->json('access_token'))) {
            throw new CredentialMisconfiguredException(
                "Inter OAuth falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        return $this->tokenCache[$key] = (string) $response->json('access_token');
    }

    private function buildEmitirBoletoPayload(EmitirCobrancaInput $input, array $config): array
    {
        $valorReais = number_format($input->valorCentavos / 100, 2, '.', '');

        return [
            'seuNumero'           => substr($input->idempotencyKey, 0, 15),
            'valorNominal'        => (float) $valorReais,
            'dataVencimento'      => Carbon::instance($input->vencimento)->toDateString(),
            'numDiasAgenda'       => $input->meta['dias_baixa'] ?? 30,
            'pagador' => [
                'cpfCnpj'     => preg_replace('/\D/', '', $input->meta['payer_cpf_cnpj'] ?? ''),
                'tipoPessoa'  => $this->detectTipoPessoa($input->meta['payer_cpf_cnpj'] ?? ''),
                'nome'        => $input->meta['payer_name'] ?? 'Pagador',
                'endereco'    => $input->meta['payer_address'] ?? 'Não informado',
                'cidade'      => $input->meta['payer_city'] ?? 'São Paulo',
                'uf'          => $input->meta['payer_uf'] ?? 'SP',
                'cep'         => preg_replace('/\D/', '', $input->meta['payer_cep'] ?? '00000000'),
                'email'       => $input->meta['payer_email'] ?? null,
            ],
            'mensagem' => [
                'linha1' => substr($input->descricao, 0, 80),
                'linha2' => $input->instrucoesPagador ?? 'Não receber após o vencimento',
            ],
        ];
    }

    private function detectTipoPessoa(?string $cpfCnpj): string
    {
        $digits = preg_replace('/\D/', '', (string) $cpfCnpj);

        return strlen($digits ?? '') === 14 ? 'JURIDICA' : 'FISICA';
    }

    private function mapMotivoCancelar(string $motivo): string
    {
        // Inter aceita: ACERTOS, APEDIDODOCLIENTE, PAGODIRETOAOCLIENTE, SUBSTITUICAO
        return match (strtolower($motivo)) {
            'cliente_pediu', 'apedido', 'pedido_cliente' => 'APEDIDODOCLIENTE',
            'pago_direto', 'pago_fora'                    => 'PAGODIRETOAOCLIENTE',
            'substituicao', 'substituido'                 => 'SUBSTITUICAO',
            default                                       => 'ACERTOS',
        };
    }

    private function mapSituacao(string $interStatus): string
    {
        return match (strtoupper($interStatus)) {
            'RECEBIDO', 'MARCADO_RECEBIDO' => 'paga',
            'A_RECEBER', 'EM_PROCESSAMENTO' => 'emitida',
            'ATRASADO', 'VENCIDO'           => 'vencida',
            'CANCELADO', 'EXPIRADO'         => 'cancelada',
            default                         => 'pending',
        };
    }
}
