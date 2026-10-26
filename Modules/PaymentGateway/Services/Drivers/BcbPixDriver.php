<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Drivers;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Contracts\CobrancaRecorrenteDriver;
use Modules\PaymentGateway\Contracts\PaymentDriverContract;
use Modules\PaymentGateway\Services\HttpClientFactory;
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
 * Driver PIX Automático regulado BCB — Resolução 380/2024.
 *
 * Onda 4d.1 — ADR 0170. Driver especializado em **PIX Recorrência** (recv):
 * mandatos recorrentes onde o pagador autoriza cobranças automáticas no app
 * do banco dele (out-of-band). Cobranças subsequentes vêm via webhook.
 *
 * **PSP-agnóstico** — Wagner pode rotear via Inter/C6/Asaas/PSP terceiro
 * que implemente API Open Finance Pix Automático. URL base via config.
 *
 * Suporta:
 *   ✓ emitirPixAutomatico (cria a recorrência via POST /v2/rec — API Pix BCB v2.10.0)
 *   ✓ emitirCobrancaRecorrente (cobrança de UM ciclo via PUT /v2/cobr/{txid})
 *   ✓ cancelar (PATCH /v2/rec/{idRec} status=CANCELADA — a spec não tem DELETE)
 *   ✓ consultar (GET /v2/rec/{idRec})
 *   ✓ healthCheck (OAuth ping)
 *   ✓ processWebhook (cobranças recorrentes notificadas pelo PSP)
 *
 * NÃO suporta (use outros drivers):
 *   ✗ emitirBoleto, emitirPix(cob/cobv), cobrarCartao, refund
 *
 * Credenciais (config_json):
 *   client_id:     OAuth2 PSP
 *   client_secret: OAuth2 PSP
 *   base_url:      URL PSP (sandbox/prod do parceiro escolhido)
 *   ambiente:      'sandbox' | 'production'
 *   recebedor_agencia / recebedor_conta / recebedor_tipo_conta (CORRENTE|POUPANCA|PAGAMENTO):
 *                  conta do recebedor que o `/cobr` exige (a spec recusa conta de outro usuário)
 *
 * Fluxo PIX Automático (BCB):
 *   1. Beneficiário (Wagner) cria mandato PUT /v2/rec/{txid} com pagador info
 *   2. Pagador autoriza via app banco dele (out-of-band, manual)
 *   3. Banco do pagador notifica BCB que aceitou
 *   4. PSP do beneficiário notifica via webhook que mandato está ATIVO
 *   5. A CADA ciclo o recebedor cria a cobrança (`PUT /cobr/{txid}`), de 10 a 2 dias antes do
 *      vencimento — o PSP não gera sozinho (corrigido 2026-10-26 contra a spec oficial)
 *   6. Webhook chega a cada cobrança paga/falhada
 */
class BcbPixDriver implements PaymentDriverContract, CobrancaRecorrenteDriver
{
    /** Escopos OAuth da API Pix BCB (securitySchemes da v2.10.0). */
    private const ESCOPOS = 'rec.read rec.write cobr.read cobr.write solicrec.read solicrec.write';

    private array $tokenCache = [];

    public function key(): string
    {
        return 'bcb_pix';
    }

    public function supports(string $tipo): bool
    {
        return $tipo === 'pix_recv';
    }

    public function emitirBoleto(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException('BcbPix só faz pix_recv. Use inter/c6/asaas pra boleto.');
    }

    public function emitirPix(EmitirCobrancaInput $input, object $cred, string $tipo): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException(
            "BcbPix só faz pix_recv (PIX Automático). Pra '{$tipo}' use inter/c6/asaas."
        );
    }

    /**
     * Cria a recorrência (mandato) do Pix Automático — `POST /v2/rec` (API Pix BCB v2.10.0).
     *
     * A recorrência nasce `CRIADA`; o pagador ainda precisa aprová-la (jornada por QR ou por
     * solicitação — próxima entrega). Corpo conforme `recBody1` da spec: `vinculo` com o devedor,
     * `calendario`, `valor.valorRec` e `politicaRetentativa`. A recorrência não tem `recebedor.chave`
     * (o driver antigo mandava, num `PUT /rec/{id}` que a spec não tem).
     */
    public function emitirPixAutomatico(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult
    {
        $this->assertCredential($cred);

        $doc = preg_replace('/\D/', '', (string) ($input->meta['payer_cpf_cnpj'] ?? ''));
        if (! in_array(strlen($doc), [11, 14], true)) {
            throw new InvalidPayerException('BcbPix recorrência exige CPF ou CNPJ do pagador em meta.payer_cpf_cnpj');
        }

        $payload = [
            'vinculo' => [
                'contrato' => substr($input->idempotencyKey, 0, 35),
                'devedor'  => array_filter([
                    'cpf'  => strlen($doc) === 11 ? $doc : null,
                    'cnpj' => strlen($doc) === 14 ? $doc : null,
                    'nome' => $input->meta['payer_name'] ?? null,
                ]),
                'objeto'   => substr($input->descricao, 0, 35),
            ],
            'calendario' => array_filter([
                'dataInicial'   => Carbon::instance($input->vencimento)->toDateString(),
                'dataFinal'     => $input->meta['data_final'] ?? null,
                'periodicidade' => $input->meta['periodicidade'] ?? 'MENSAL', // SEMANAL/MENSAL/TRIMESTRAL/SEMESTRAL/ANUAL
            ]),
            'valor' => [
                'valorRec' => $this->reais($input->valorCentavos),
            ],
            'politicaRetentativa' => $input->meta['politica_retentativa'] ?? 'NAO_PERMITE',
        ];

        $response = HttpClientFactory::send(fn () => $this->client($cred)->post('/v2/rec', $payload));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "BcbPix recorrência falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $idRec = (string) ($data['idRec'] ?? '');
        if ($idRec === '') {
            throw new GatewayUnavailableException('BcbPix recorrência: resposta sem idRec');
        }

        return new CobrancaEmitidaResult(
            cobrancaId: 0,
            gatewayExternalId: $idRec,
            tipo: 'pix_recv',
            emitidaEm: new \DateTimeImmutable(),
            payloadGateway: $data,
        );
    }

    /**
     * Cobrança de UM ciclo da recorrência — `PUT /v2/cobr/{txid}` (API Pix BCB v2.10.0).
     *
     * O `txid` deriva do `idempotencyKey` (mesma chave → mesmo txid): a spec recusa txid repetido e
     * recusa segunda cobrança ativa do mesmo `idRec` no mesmo ciclo, então reenviar não duplica.
     * Valor em centavos inteiros convertido para texto "123.45", sem float.
     */
    public function emitirCobrancaRecorrente(EmitirCobrancaInput $input, object $cred, string $idRec): CobrancaEmitidaResult
    {
        $this->assertCredential($cred);
        if ($idRec === '') {
            throw new InvalidPayerException('BcbPix cobrança recorrente exige o idRec da recorrência aprovada');
        }
        if ($input->valorCentavos <= 0) {
            throw new InvalidPayerException('BcbPix cobrança recorrente exige valor positivo');
        }
        $config = $cred->config_json ?? [];
        if (empty($config['recebedor_agencia']) || empty($config['recebedor_conta'])) {
            throw new CredentialMisconfiguredException(
                'BcbPix cobrança recorrente precisa recebedor_agencia + recebedor_conta em config_json'
            );
        }

        $txid = self::txidDe($input->idempotencyKey);
        $payload = [
            'idRec'         => $idRec,
            'infoAdicional' => substr($input->descricao, 0, 140),
            'calendario'    => [
                'dataDeVencimento' => Carbon::instance($input->vencimento)->toDateString(),
            ],
            'valor'         => [
                'original' => $this->reais($input->valorCentavos),
            ],
            'ajusteDiaUtil' => true,
            'recebedor'     => [
                'agencia'   => (string) $config['recebedor_agencia'],
                'conta'     => (string) $config['recebedor_conta'],
                'tipoConta' => (string) ($config['recebedor_tipo_conta'] ?? 'CORRENTE'),
            ],
        ];

        $response = HttpClientFactory::send(fn () => $this->client($cred)->put("/v2/cobr/{$txid}", $payload));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "BcbPix cobrança recorrente falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];

        return new CobrancaEmitidaResult(
            cobrancaId: 0,
            gatewayExternalId: (string) ($data['txid'] ?? $txid),
            tipo: 'pix_recv',
            emitidaEm: new \DateTimeImmutable(),
            payloadGateway: $data,
        );
    }

    /** txid da spec: 26 a 35 caracteres alfanuméricos. Determinístico a partir da chave de idempotência. */
    public static function txidDe(string $idempotencyKey): string
    {
        return 'RB' . substr(hash('sha256', $idempotencyKey), 0, 33);
    }

    /** Centavos inteiros → "123.45" sem passar por float. */
    private function reais(int $centavos): string
    {
        return sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);
    }

    public function cobrarCartao(EmitirCobrancaInput $input, object $cred, CardToken $token): CobrancaEmitidaResult
    {
        throw new DriverNotSupportedException('BcbPix não emite cartão. Use Asaas.');
    }

    /**
     * Revoga mandato PIX Automático.
     * Cobranças futuras param. Cobranças já efetivadas mantêm-se.
     */
    public function cancelar(object $cobranca, object $cred, string $motivo): void
    {
        $this->assertCredential($cred);
        $idRec = (string) ($cobranca->gateway_external_id ?? '');
        if ($idRec === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra revogar mandato BcbPix');
        }

        // A spec v2.10.0 não tem DELETE em /rec/{idRec}: o cancelamento é PATCH com status CANCELADA,
        // e o PATCH não tem campo de motivo — `$motivo` fica no log de quem chama.
        $response = HttpClientFactory::send(fn () => $this->client($cred)
            ->patch("/v2/rec/{$idRec}", ['status' => 'CANCELADA']));

        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "BcbPix revogar mandato falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }
    }

    public function refund(object $cobranca, object $cred, ?int $valorCentavos, string $motivo): void
    {
        throw new DriverNotSupportedException(
            'BcbPix mandato não tem refund individual via API. ' .
            'Revogue o mandato (cancelar) + faça devolução PIX manual da cobrança específica.'
        );
    }

    public function consultar(object $cobranca, object $cred): CobrancaStatus
    {
        $this->assertCredential($cred);
        $idRec = (string) ($cobranca->gateway_external_id ?? '');
        if ($idRec === '') {
            throw new InvalidPayerException('Cobranca sem gateway_external_id pra consultar mandato BcbPix');
        }

        $response = HttpClientFactory::send(fn () => $this->client($cred)->get("/v2/rec/{$idRec}"));
        if ($response->failed()) {
            throw new GatewayUnavailableException(
                "BcbPix consultar falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        $data = $response->json() ?? [];
        $status = (string) ($data['status'] ?? '');

        return new CobrancaStatus(
            status: $this->mapStatus($status),
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
                    'scope'         => self::ESCOPOS,
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
                errorMessage: "BcbPix OAuth failed ({$response->status()})",
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
        // Webhook BCB padrão: pix[] (lista de cobranças confirmadas) OU
        // rec[] (atualizações de mandato).
        $idRec = (string) (
            $payload['idRec']
            ?? $payload['rec']['idRec']
            ?? $payload['pix'][0]['infoPagador']['idRec']
            ?? ''
        );

        if ($idRec === '') {
            return null;
        }

        return (object) [
            'gateway_external_id' => $idRec,
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
        if ($cred->gateway_key !== 'bcb_pix') {
            throw new CredentialMisconfiguredException(
                "Credential gateway_key='{$cred->gateway_key}' não bate com driver BcbPix"
            );
        }
        $config = $cred->config_json ?? [];
        if (empty($config['client_id']) || empty($config['client_secret'])) {
            throw new CredentialMisconfiguredException(
                'BcbPix credential precisa client_id + client_secret em config_json'
            );
        }
        if (empty($config['base_url'])) {
            throw new CredentialMisconfiguredException(
                'BcbPix credential precisa base_url em config_json (PSP-agnóstico)'
            );
        }
    }

    private function baseUrl(PaymentGatewayCredential $cred): string
    {
        return rtrim((string) ($cred->config_json['base_url'] ?? ''), '/');
    }

    /**
     * Cliente principal — com retry + 429 handler via HttpClientFactory
     * (Auditoria 2026-05-23 Onda 4e gap #1+#2).
     */
    private function client(PaymentGatewayCredential $cred): PendingRequest
    {
        return HttpClientFactory::make(
            baseUrl: $this->baseUrl($cred),
            timeoutSec: 30,
        )->withToken($this->getAccessToken($cred));
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
                'scope'         => self::ESCOPOS,
            ]);

        if (! $response->successful() || empty($response->json('access_token'))) {
            throw new CredentialMisconfiguredException(
                "BcbPix OAuth falhou ({$response->status()}): " . substr($response->body(), 0, 200)
            );
        }

        return $this->tokenCache[$key] = (string) $response->json('access_token');
    }

    private function mapStatus(string $bcbStatus): string
    {
        return match (strtoupper($bcbStatus)) {
            'ATIVA', 'ACTIVE'                          => 'emitida',
            'CRIADA', 'CREATED', 'AGUARDANDO_PAGADOR'  => 'pending',
            'REJEITADA', 'REJEITADA_PAGADOR'           => 'erro',
            'CANCELADA', 'EXPIRADA', 'CANCELED'        => 'cancelada',
            'CONCLUIDA', 'COMPLETED'                   => 'paga',
            default                                    => 'pending',
        };
    }
}
